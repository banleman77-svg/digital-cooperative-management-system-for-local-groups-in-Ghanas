<?php
/**
 * Susu Cycle Engine — business logic for starting cycles and managing rounds.
 * Include this file wherever cycle operations are needed.
 */

function next_due_date(string $from, string $frequency): string {
    $date = new DateTime($from);
    match ($frequency) {
        'DAILY'    => $date->modify('+1 day'),
        'WEEKLY'   => $date->modify('+1 week'),
        'BIWEEKLY' => $date->modify('+2 weeks'),
        'MONTHLY'  => $date->modify('+1 month'),
        default    => throw new InvalidArgumentException("Unknown frequency: $frequency"),
    };
    return $date->format('Y-m-d');
}

function start_cycle(int $groupId): array {
    $db = db();

    $group = $db->prepare('SELECT * FROM groups_ WHERE id=?');
    $group->execute([$groupId]); $group = $group->fetch();
    if (!$group) throw new Exception('Group not found.');

    $existing = $db->prepare("SELECT id FROM cycles WHERE group_id=? AND status='ACTIVE'");
    $existing->execute([$groupId]);
    if ($existing->fetch()) throw new Exception('This group already has an active cycle.');

    $members = $db->prepare("SELECT m.*, u.id AS user_id FROM memberships m JOIN users u ON u.id=m.user_id WHERE m.group_id=? AND m.is_active=1 ORDER BY m.rotation_position");
    $members->execute([$groupId]); $members = $members->fetchAll();

    if (count($members) < 2) throw new Exception('A cycle needs at least 2 active members.');

    $totalRounds = count($members);
    $startDate   = date('Y-m-d');
    $endDate     = $startDate;
    for ($i = 1; $i < $totalRounds; $i++) {
        $endDate = next_due_date($endDate, $group['frequency']);
    }

    $lastCycle = $db->prepare('SELECT MAX(cycle_number) FROM cycles WHERE group_id=?');
    $lastCycle->execute([$groupId]);
    $cycleNumber = ($lastCycle->fetchColumn() ?: 0) + 1;

    $db->beginTransaction();
    try {
        $db->prepare('INSERT INTO cycles (group_id,cycle_number,start_date,expected_end_date,total_rounds,status) VALUES (?,?,?,?,?,?)')->execute([$groupId,$cycleNumber,$startDate,$endDate,$totalRounds,'ACTIVE']);
        $cycleId = $db->lastInsertId();

        $dueDate = $startDate;
        foreach ($members as $i => $m) {
            $status = $i === 0 ? 'OPEN' : 'PENDING';
            $db->prepare('INSERT INTO rounds (cycle_id,round_number,due_date,recipient_id,status) VALUES (?,?,?,?,?)')->execute([$cycleId,$i+1,$dueDate,$m['user_id'],$status]);
            $dueDate = next_due_date($dueDate, $group['frequency']);
        }

        $db->prepare("UPDATE groups_ SET status='ACTIVE' WHERE id=? AND status='PENDING'")->execute([$groupId]);
        $db->commit();
        return ['cycle_id' => $cycleId, 'cycle_number' => $cycleNumber, 'total_rounds' => $totalRounds];
    } catch (Exception $e) {
        $db->rollBack(); throw $e;
    }
}

function confirm_contribution(int $contributionId, string $financialTxnId = ''): void {
    $db = db();
    $db->prepare("UPDATE contributions SET status='CONFIRMED', confirmed_at=NOW(), momo_financial_txn_id=? WHERE id=?")->execute([$financialTxnId, $contributionId]);

    $contrib = $db->prepare('SELECT * FROM contributions WHERE id=?');
    $contrib->execute([$contributionId]); $contrib = $contrib->fetch();

    check_and_close_round($contrib['round_id']);
    if (function_exists('calculate_trust_score')) {
        calculate_trust_score((int)$contrib['member_id']);
    }
}

function check_and_close_round(int $roundId): void {
    $db = db();
    $round = $db->prepare('SELECT r.*, cy.group_id FROM rounds r JOIN cycles cy ON cy.id=r.cycle_id WHERE r.id=?');
    $round->execute([$roundId]); $round = $round->fetch();
    if (!$round || $round['status'] !== 'OPEN') return;

    $expected = $db->prepare("SELECT COUNT(*) FROM memberships WHERE group_id=? AND is_active=1");
    $expected->execute([$round['group_id']]); $expected = $expected->fetchColumn();

    $confirmed = $db->prepare("SELECT COUNT(*) FROM contributions WHERE round_id=? AND status='CONFIRMED'");
    $confirmed->execute([$roundId]); $confirmed = $confirmed->fetchColumn();

    if ($confirmed >= $expected) {
        $db->prepare("UPDATE rounds SET status='CLOSED' WHERE id=?")->execute([$roundId]);
    }
}

function complete_payout(int $payoutId, string $financialTxnId = ''): void {
    $db = db();
    $db->prepare("UPDATE payouts SET status='COMPLETED', processed_at=NOW(), momo_financial_txn_id=? WHERE id=?")->execute([$financialTxnId, $payoutId]);

    $payout = $db->prepare('SELECT * FROM payouts WHERE id=?');
    $payout->execute([$payoutId]); $payout = $payout->fetch();

    $db->prepare("UPDATE rounds SET status='PAID' WHERE id=?")->execute([$payout['round_id']]);

    $round = $db->prepare('SELECT * FROM rounds WHERE id=?');
    $round->execute([$payout['round_id']]); $round = $round->fetch();

    // Open next round
    $next = $db->prepare("SELECT * FROM rounds WHERE cycle_id=? AND round_number=? AND status='PENDING'");
    $next->execute([$round['cycle_id'], $round['round_number'] + 1]); $next = $next->fetch();
    if ($next) {
        $db->prepare("UPDATE rounds SET status='OPEN' WHERE id=?")->execute([$next['id']]);
    } else {
        // Last round — complete the cycle
        $db->prepare("UPDATE cycles SET status='COMPLETED', actual_end_date=CURDATE() WHERE id=?")->execute([$round['cycle_id']]);
    }
}
