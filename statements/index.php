<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/../config/db.php';
$pageTitle = 'My Statement';
require '../includes/header.php';
$uid = $user['id'];

$profile = db()->prepare('SELECT * FROM users WHERE id=?');
$profile->execute([$uid]); $profile = $profile->fetch();

$stmt = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM contributions WHERE member_id=? AND status='CONFIRMED'");
$stmt->execute([$uid]); $totalIn = $stmt->fetchColumn();

$stmt = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM payouts WHERE recipient_id=? AND status='COMPLETED'");
$stmt->execute([$uid]); $totalOut = $stmt->fetchColumn();

$stmt = db()->prepare("SELECT COUNT(*) FROM contributions WHERE member_id=? AND status='CONFIRMED'");
$stmt->execute([$uid]); $totalContribs = $stmt->fetchColumn();

$stmt = db()->prepare("SELECT COUNT(*) FROM contributions WHERE member_id=? AND status='FAILED'");
$stmt->execute([$uid]); $failedCount = $stmt->fetchColumn();

// All transactions
$stmt = db()->prepare("
  (SELECT 'CONTRIBUTION' AS type, c.amount, c.status, c.method, c.confirmed_at AS txn_date,
    g.name AS gname, r.round_number, c.id AS ref_id, c.momo_reference
   FROM contributions c
   JOIN rounds r ON r.id=c.round_id
   JOIN cycles cy ON cy.id=r.cycle_id
   JOIN groups_ g ON g.id=cy.group_id
   WHERE c.member_id=?)
  UNION ALL
  (SELECT 'PAYOUT' AS type, p.amount, p.status, 'MOMO' AS method, p.processed_at AS txn_date,
    g.name AS gname, r.round_number, p.id AS ref_id, p.momo_reference
   FROM payouts p
   JOIN rounds r ON r.id=p.round_id
   JOIN cycles cy ON cy.id=r.cycle_id
   JOIN groups_ g ON g.id=cy.group_id
   WHERE p.recipient_id=?)
  ORDER BY txn_date DESC
");
$stmt->execute([$uid, $uid]); $transactions = $stmt->fetchAll();
?>

<div class="page-header fu1">
  <div>
    <h1>My Statement</h1>
    <p>Complete history of your contributions and payouts.</p>
  </div>
  <button onclick="window.print()" class="btn btn-outline btn-sm"><i class="bi bi-printer"></i> Print Statement</button>
</div>

<!-- Member card -->
<div class="card fu2 mb-16" style="background:linear-gradient(135deg,#0a3d1f,#1a6e3a);border:none">
  <div class="card-body" style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
    <div style="width:60px;height:60px;border-radius:50%;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:800;color:#fff;font-family:var(--fh);border:3px solid rgba(255,255,255,.2)">
      <?=strtoupper(substr($profile['full_name'],0,1))?>
    </div>
    <div style="flex:1">
      <div style="color:#fff;font-size:18px;font-weight:800;font-family:var(--fh)"><?=e($profile['full_name'])?></div>
      <div style="color:rgba(255,255,255,.6);font-size:13px;margin-top:2px"><?=e($profile['phone'])?> · <?=e($profile['network'])?></div>
      <div style="color:rgba(255,255,255,.5);font-size:12px;margin-top:4px">Member Code: <strong style="color:#f5c842"><?=e($profile['member_code'])?></strong></div>
    </div>
    <div style="text-align:right">
      <div style="color:rgba(255,255,255,.6);font-size:11px;text-transform:uppercase;letter-spacing:.5px">Statement Date</div>
      <div style="color:#fff;font-weight:700;font-size:14px"><?=date('F j, Y')?></div>
    </div>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid fu2">
  <div class="stat-card green">
    <div class="stat-icon green"><i class="bi bi-arrow-up-circle-fill"></i></div>
    <div><div class="stat-label">Total Contributed</div><div class="stat-value" style="font-size:20px"><?=money($totalIn)?></div><div class="stat-sub"><?=$totalContribs?> payments</div></div>
  </div>
  <div class="stat-card amber">
    <div class="stat-icon amber"><i class="bi bi-arrow-down-circle-fill"></i></div>
    <div><div class="stat-label">Total Received</div><div class="stat-value" style="font-size:20px"><?=money($totalOut)?></div><div class="stat-sub">Payouts received</div></div>
  </div>
  <div class="stat-card blue">
    <div class="stat-icon blue"><i class="bi bi-calculator"></i></div>
    <div><div class="stat-label">Net Position</div><div class="stat-value" style="font-size:20px"><?=money($totalOut - $totalIn)?></div><div class="stat-sub">Received minus paid</div></div>
  </div>
  <div class="stat-card <?=$failedCount>0?'red':'green'?>">
    <div class="stat-icon <?=$failedCount>0?'red':'green'?>"><i class="bi bi-<?=$failedCount>0?'exclamation-triangle':'check-circle'?>"></i></div>
    <div><div class="stat-label">Failed Payments</div><div class="stat-value"><?=$failedCount?></div><div class="stat-sub"><?=$failedCount>0?'Needs attention':'All good'?></div></div>
  </div>
</div>

<!-- Transactions -->
<div class="card fu3">
  <div class="card-header">
    <div class="card-title"><i class="bi bi-clock-history"></i>Transaction History (<?=count($transactions)?>)</div>
    <input type="text" id="table-search" class="form-control" placeholder="Search..." style="width:180px;padding:7px 12px;font-size:13px">
  </div>
  <?php if($transactions): ?>
  <div class="tbl-wrap">
    <table class="data-table">
      <thead>
        <tr><th>Date</th><th>Type</th><th>Group</th><th>Round</th><th>Amount</th><th>Method</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach($transactions as $t): ?>
        <tr data-searchable>
          <td>
            <div class="fw7 text-sm"><?=$t['txn_date']?date('M j, Y',strtotime($t['txn_date'])):'—'?></div>
            <div class="text-xs text-muted"><?=$t['txn_date']?date('g:i A',strtotime($t['txn_date'])):'—'?></div>
          </td>
          <td>
            <?php if($t['type']==='CONTRIBUTION'): ?>
              <span style="background:#ebf8ff;color:#1e40af;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700"><i class="bi bi-arrow-up-short"></i>Paid</span>
            <?php else: ?>
              <span style="background:#f0faf3;color:#065f46;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700"><i class="bi bi-arrow-down-short"></i>Received</span>
            <?php endif; ?>
          </td>
          <td class="fw7 text-sm"><?=e($t['gname'])?></td>
          <td class="text-sm text-muted">Round <?=e($t['round_number'])?></td>
          <td class="fw7" style="color:<?=$t['type']==='PAYOUT'?'var(--g600)':'var(--n700)'?>"><?=money($t['amount'])?></td>
          <td><span class="net net-<?=strtolower(e($t['method']))?>"><?=e($t['method'])?></span></td>
          <td><span class="badge badge-<?=strtolower(e($t['status']))?>"><?=e($t['status'])?></span></td>
          <td>
            <?php if($t['type']==='CONTRIBUTION'&&$t['status']==='CONFIRMED'): ?>
              <a href="<?=APP_URL?>/receipts/view.php?id=<?=e($t['ref_id'])?>" target="_blank" class="btn btn-ghost btn-sm"><i class="bi bi-receipt"></i>Receipt</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <div class="empty-state"><div class="empty-icon"><i class="bi bi-receipt"></i></div><h3>No transactions yet</h3><p>Your contribution and payout history will appear here.</p></div>
  <?php endif; ?>
</div>

<style>
@media print {
  .sidebar,.topbar,.hamburger,.btn,.form-control{display:none!important}
  .main-wrap{margin-left:0!important}
  .card{box-shadow:none!important}
  body{background:#fff!important}
}
</style>

<?php require '../includes/footer.php'; ?>
