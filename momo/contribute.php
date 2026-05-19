<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/../config/db.php';
// momo/contribute.php — initiate MoMo request-to-pay for a round contribution
// Any active member of the group can pay when the round is OPEN

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_login();
require_once __DIR__ . '/MomoService.php';
require_once __DIR__ . '/../includes/cycle_engine.php';

$user    = current_user();
$roundId = (int)($_POST['round_id'] ?? 0);
$payForMember = (int)($_POST['member_id'] ?? 0);  // Optional: treasurer paying for someone else

// Fetch round + group details
$stmt = db()->prepare("SELECT r.*, cy.group_id, g.contribution_amount, g.name AS group_name, g.treasurer_id, g.collector_id
    FROM rounds r
    JOIN cycles cy ON cy.id=r.cycle_id
    JOIN groups_ g ON g.id=cy.group_id
    WHERE r.id=?");
$stmt->execute([$roundId]);
$round = $stmt->fetch();

if (!$round) {
    flash('danger', 'Round not found.');
    header('Location:' . APP_URL . '/dashboard/');
    exit;
}

// Check round is OPEN — all members can pay when round is open
if ($round['status'] !== 'OPEN') {
    flash('danger', 'This round is not open for contributions. Status: ' . $round['status']);
    header('Location:' . APP_URL . '/rounds/detail.php?id=' . $roundId);
    exit;
}

$isManager = ($round['treasurer_id'] == $user['id'] || $round['collector_id'] == $user['id']);

// Decide who is paying: self by default, or another member if treasurer initiates
if ($payForMember && $isManager) {
    $uid = $payForMember;
} else {
    $uid = $user['id'];
}

// Verify the payer is an active member of this group
$check = db()->prepare("SELECT u.phone, u.full_name
    FROM memberships m
    JOIN users u ON u.id = m.user_id
    WHERE m.group_id=? AND m.user_id=? AND m.is_active=1");
$check->execute([$round['group_id'], $uid]);
$payer = $check->fetch();

if (!$payer) {
    flash('danger', 'You are not an active member of this group.');
    header('Location:' . APP_URL . '/rounds/detail.php?id=' . $roundId);
    exit;
}

// Check if this member has already paid
$existing = db()->prepare('SELECT id, status FROM contributions WHERE round_id=? AND member_id=?');
$existing->execute([$roundId, $uid]);
$existing = $existing->fetch();

if ($existing && $existing['status'] === 'CONFIRMED') {
    flash('warning', $payer['full_name'] . ' has already contributed for this round.');
    header('Location:' . APP_URL . '/rounds/detail.php?id=' . $roundId);
    exit;
}

$amount     = $round['contribution_amount'];
$externalId = "contrib-{$roundId}-{$uid}";

try {
    $momo   = new MomoCollectionService();
    $result = $momo->requestToPay($amount, $payer['phone'], $externalId, "Susu: {$round['group_name']}", "Round {$round['round_number']}");

    if ($existing) {
        db()->prepare("UPDATE contributions SET amount=?,method='MOMO',status='PENDING',momo_reference=? WHERE id=?")
            ->execute([$amount, $result['reference_id'], $existing['id']]);
    } else {
        db()->prepare("INSERT INTO contributions (round_id,member_id,amount,method,status,momo_reference) VALUES (?,?,?,'MOMO','PENDING',?)")
            ->execute([$roundId, $uid, $amount, $result['reference_id']]);
    }

    audit('MOMO_PAYMENT', "Initiated MoMo payment for {$payer['full_name']} (GHS $amount) for Round {$round['round_number']}");

    flash('success', "Payment prompt sent to {$payer['phone']}. Member must enter MoMo PIN to confirm.");
} catch (Exception $e) {
    flash('danger', 'Could not initiate payment: ' . $e->getMessage());
}

header('Location: ' . APP_URL . '/rounds/detail.php?id=' . $roundId);
exit;
