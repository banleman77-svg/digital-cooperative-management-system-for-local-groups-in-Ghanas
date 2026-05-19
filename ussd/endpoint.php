<?php
// ussd/endpoint.php — Africa's Talking compatible USSD handler
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';

$phone     = $_POST['phoneNumber'] ?? '';
$text      = $_POST['text'] ?? '';
$sessionId = $_POST['sessionId'] ?? '';

header('Content-Type: text/plain');
echo handle_ussd($phone, $text);
exit;

// ── USSD menu engine ───────────────────────────────────────

function ussd_end(string $msg): string { return "END $msg"; }
function ussd_con(string $msg): string { return "CON $msg"; }

function handle_ussd(string $phone, string $text): string {
    try { $phone = normalize_phone($phone); } catch (Exception) { return ussd_end('Invalid phone number.'); }

    $user = db()->prepare('SELECT * FROM users WHERE phone=? AND is_active=1');
    $user->execute([$phone]); $user = $user->fetch();
    if (!$user) return ussd_end("You are not registered.\nAsk your treasurer to add you.");

    $inputs = $text !== '' ? explode('*', $text) : [];
    if (!$inputs) return main_menu($user);

    return match($inputs[0]) {
        '1'     => balance_screen($user),
        '2'     => next_contribution($user),
        '3'     => next_payout($user),
        '4'     => pay_contribution($user, $inputs),
        '5'     => group_info($user, $inputs),
        '0'     => ussd_end('Thank you for using Susu Connect.'),
        default => ussd_end('Invalid choice. Please dial again.'),
    };
}

function main_menu(array $user): string {
    $name = explode(' ', $user['full_name'])[0];
    return ussd_con("Welcome, $name\n1. My balance\n2. Next contribution\n3. Next payout\n4. Pay contribution\n5. Group info\n0. Exit");
}

function balance_screen(array $user): string {
    $in  = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM contributions WHERE member_id=? AND status='CONFIRMED'");
    $in->execute([$user['id']]); $in = $in->fetchColumn();
    $out = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM payouts WHERE recipient_id=? AND status='COMPLETED'");
    $out->execute([$user['id']]); $out = $out->fetchColumn();
    return ussd_end("Balance Summary\nTotal paid in:\nGHS ".number_format($in,2)."\nTotal received:\nGHS ".number_format($out,2));
}

function next_contribution(array $user): string {
    $stmt = db()->prepare("SELECT g.name, g.contribution_amount, r.due_date, r.id AS round_id, (SELECT COUNT(*) FROM contributions c WHERE c.round_id=r.id AND c.member_id=? AND c.status='CONFIRMED') AS paid FROM memberships m JOIN groups_ g ON g.id=m.group_id JOIN cycles cy ON cy.group_id=g.id AND cy.status='ACTIVE' JOIN rounds r ON r.cycle_id=cy.id AND r.status='OPEN' WHERE m.user_id=? AND m.is_active=1 LIMIT 3");
    $stmt->execute([$user['id'],$user['id']]); $rows = $stmt->fetchAll();
    if (!$rows) return ussd_end("No open contributions\nright now.");
    $lines = ["Next Contributions:"];
    foreach ($rows as $r) {
        $status = $r['paid'] ? 'PAID' : 'DUE';
        $lines[] = substr($r['name'],0,18).":\nGHS ".$r['contribution_amount']." $status";
    }
    return ussd_end(implode("\n", $lines));
}

function next_payout(array $user): string {
    $stmt = db()->prepare("SELECT g.name, r.due_date, r.round_number FROM rounds r JOIN cycles cy ON cy.id=r.cycle_id JOIN groups_ g ON g.id=cy.group_id WHERE r.recipient_id=? AND r.status IN ('PENDING','OPEN','CLOSED') ORDER BY r.due_date LIMIT 3");
    $stmt->execute([$user['id']]); $rows = $stmt->fetchAll();
    if (!$rows) return ussd_end("No upcoming payouts\nscheduled.");
    $lines = ["Upcoming Payouts:"];
    foreach ($rows as $r) $lines[] = substr($r['name'],0,18).":\nRound {$r['round_number']} - {$r['due_date']}";
    return ussd_end(implode("\n", $lines));
}

function pay_contribution(array $user, array $inputs): string {
    $stmt = db()->prepare("SELECT g.id AS gid, g.name, g.contribution_amount, r.id AS round_id FROM memberships m JOIN groups_ g ON g.id=m.group_id JOIN cycles cy ON cy.group_id=g.id AND cy.status='ACTIVE' JOIN rounds r ON r.cycle_id=cy.id AND r.status='OPEN' WHERE m.user_id=? AND m.is_active=1 LIMIT 9");
    $stmt->execute([$user['id']]); $groups = $stmt->fetchAll();
    if (!$groups) return ussd_end("No groups with\nopen contributions.");

    if (count($inputs) === 1) {
        $menu = "Pay for which group?";
        foreach ($groups as $i => $g) $menu .= "\n".($i+1).". ".substr($g['name'],0,22);
        return ussd_con($menu);
    }

    $idx = (int)$inputs[1] - 1;
    if (!isset($groups[$idx])) return ussd_end("Invalid selection.");
    $g = $groups[$idx];

    if (count($inputs) === 2) {
        return ussd_con("Confirm payment:\nGHS {$g['contribution_amount']}\nto ".substr($g['name'],0,20)."\n1. Yes\n2. No");
    }

    if ($inputs[2] !== '1') return ussd_end("Payment cancelled.");

    require_once __DIR__ . '/../momo/MomoService.php';
    try {
        $momo   = new MomoCollectionService();
        $result = $momo->requestToPay($g['contribution_amount'], $user['phone'], "ussd-{$g['round_id']}-{$user['id']}", "Susu: {$g['name']}");
        $existing = db()->prepare('SELECT id FROM contributions WHERE round_id=? AND member_id=?');
        $existing->execute([$g['round_id'],$user['id']]); $existing = $existing->fetch();
        if ($existing) {
            db()->prepare("UPDATE contributions SET status='PENDING',momo_reference=? WHERE id=?")->execute([$result['reference_id'],$existing['id']]);
        } else {
            db()->prepare("INSERT INTO contributions (round_id,member_id,amount,method,status,momo_reference) VALUES (?,?,?,'MOMO','PENDING',?)")->execute([$g['round_id'],$user['id'],$g['contribution_amount'],$result['reference_id']]);
        }
        return ussd_end("Prompt sent to phone.\nEnter MoMo PIN\nto confirm payment.");
    } catch (Exception $e) {
        return ussd_end("Payment failed.\nTry again later.");
    }
}

function group_info(array $user, array $inputs): string {
    $stmt = db()->prepare("SELECT g.*, m.rotation_position, (SELECT COUNT(*) FROM memberships m2 WHERE m2.group_id=g.id AND m2.is_active=1) AS member_count FROM memberships m JOIN groups_ g ON g.id=m.group_id WHERE m.user_id=? AND m.is_active=1 LIMIT 9");
    $stmt->execute([$user['id']]); $groups = $stmt->fetchAll();
    if (!$groups) return ussd_end("No groups found.");

    if (count($inputs) === 1) {
        $menu = "Select group:";
        foreach ($groups as $i => $g) $menu .= "\n".($i+1).". ".substr($g['name'],0,22);
        return ussd_con($menu);
    }
    $idx = (int)$inputs[1] - 1;
    if (!isset($groups[$idx])) return ussd_end("Invalid selection.");
    $g = $groups[$idx];
    return ussd_end("{$g['name']}\nCode: {$g['code']}\nMembers: {$g['member_count']}\nAmount: GHS {$g['contribution_amount']}\nFreq: {$g['frequency']}\nYour position: #{$g['rotation_position']}");
}
