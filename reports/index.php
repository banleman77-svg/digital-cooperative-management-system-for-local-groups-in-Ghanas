<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__."/../config/db.php";
$pageTitle = 'Reports';
$topbarChip = 'Analytics';
require '../includes/header.php';

$uid = $user['id'];

// Total contributions system-wide for this user's groups
$groups = db()->prepare("SELECT g.* FROM groups_ g WHERE g.treasurer_id=? OR g.collector_id=? OR EXISTS(SELECT 1 FROM memberships m WHERE m.group_id=g.id AND m.user_id=?)")->execute([$uid,$uid,$uid]);

// Personal stats
$stmt = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM contributions WHERE member_id=? AND status='CONFIRMED'"); $stmt->execute([$uid]); $myIn = $stmt->fetchColumn();
$stmt = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM payouts WHERE recipient_id=? AND status='COMPLETED'"); $stmt->execute([$uid]); $myOut = $stmt->fetchColumn();
$stmt = db()->prepare("SELECT COUNT(*) FROM memberships WHERE user_id=? AND is_active=1"); $stmt->execute([$uid]); $myGroups = $stmt->fetchColumn();
$stmt = db()->prepare("SELECT COUNT(*) FROM contributions WHERE member_id=? AND status='CONFIRMED'"); $stmt->execute([$uid]); $myContribs = $stmt->fetchColumn();

// Recent contributions
$stmt = db()->prepare("SELECT c.*, u.full_name, g.name AS group_name, r.round_number FROM contributions c JOIN users u ON u.id=c.member_id JOIN rounds r ON r.id=c.round_id JOIN cycles cy ON cy.id=r.cycle_id JOIN groups_ g ON g.id=cy.group_id WHERE c.member_id=? ORDER BY c.created_at DESC LIMIT 10"); $stmt->execute([$uid]); $recentContribs = $stmt->fetchAll();

// Managed group summaries
$stmt = db()->prepare("SELECT g.*, (SELECT COUNT(*) FROM memberships m WHERE m.group_id=g.id AND m.is_active=1) AS mc, (SELECT COALESCE(SUM(c.amount),0) FROM contributions c JOIN rounds r ON r.id=c.round_id JOIN cycles cy ON cy.id=r.cycle_id WHERE cy.group_id=g.id AND c.status='CONFIRMED') AS total_collected FROM groups_ g WHERE g.treasurer_id=? ORDER BY g.created_at DESC"); $stmt->execute([$uid]); $managedGroups = $stmt->fetchAll();

// Defaulters across all open rounds in managed groups
$stmt = db()->prepare("SELECT u.full_name, u.phone, g.name AS group_name, r.due_date, g.contribution_amount FROM rounds r JOIN cycles cy ON cy.id=r.cycle_id JOIN groups_ g ON g.id=cy.group_id JOIN memberships m ON m.group_id=g.id AND m.is_active=1 LEFT JOIN contributions c ON c.round_id=r.id AND c.member_id=m.user_id JOIN users u ON u.id=m.user_id WHERE g.treasurer_id=? AND r.status='OPEN' AND (c.id IS NULL OR c.status != 'CONFIRMED') ORDER BY r.due_date"); $stmt->execute([$uid]); $defaulters = $stmt->fetchAll();
?>

<div class="page-header fade-up">
  <div>
    <h1>Reports & Analytics</h1>
    <p>Track contributions, payouts, and group performance.</p>
  </div>
</div>

<!-- Personal stats -->
<div class="stats-grid fade-up-2">
  <div class="stat-card green">
    <div class="stat-icon green"><i class="bi bi-arrow-up-circle-fill"></i></div>
    <div>
      <div class="stat-label">Total Contributed</div>
      <div class="stat-value"><?= money($myIn) ?></div>
      <div class="stat-sub"><?= $myContribs ?> confirmed payments</div>
    </div>
  </div>
  <div class="stat-card amber">
    <div class="stat-icon amber"><i class="bi bi-arrow-down-circle-fill"></i></div>
    <div>
      <div class="stat-label">Total Received</div>
      <div class="stat-value"><?= money($myOut) ?></div>
      <div class="stat-sub">Payouts disbursed</div>
    </div>
  </div>
  <div class="stat-card blue">
    <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
    <div>
      <div class="stat-label">Active Groups</div>
      <div class="stat-value"><?= $myGroups ?></div>
      <div class="stat-sub">Memberships</div>
    </div>
  </div>
  <div class="stat-card purple">
    <div class="stat-icon purple"><i class="bi bi-graph-up"></i></div>
    <div>
      <div class="stat-label">Net Position</div>
      <div class="stat-value"><?= money($myOut - $myIn) ?></div>
      <div class="stat-sub">Received minus paid</div>
    </div>
  </div>
</div>

<div class="g2 fade-up-3">
  <!-- Managed groups report -->
  <div class="card">
    <div class="card-header">
      <div class="card-header-title"><i class="bi bi-shield-check"></i> Groups You Manage</div>
    </div>
    <?php if($managedGroups): ?>
    <div class="tbl-wrap">
      <table class="data-table">
        <thead><tr><th>Group</th><th>Members</th><th>Collected</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach($managedGroups as $g): ?>
          <tr>
            <td>
              <div class="fw7"><a href="<?= APP_URL ?>/groups/detail.php?id=<?= $g['id'] ?>" style="color:var(--g700)"><?= e($g['name']) ?></a></div>
              <div class="text-sm text-muted"><?= e($g['code']) ?></div>
            </td>
            <td><?= $g['mc'] ?>/<?= $g['max_members'] ?></td>
            <td class="fw7" style="color:var(--g700)"><?= money($g['total_collected']) ?></td>
            <td><span class="badge badge-<?= strtolower(e($g['status'])) ?>"><?= e($g['status']) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <div class="empty-state"><div class="empty-icon"><i class="bi bi-people"></i></div><p>No groups managed yet.</p></div>
    <?php endif; ?>
  </div>

  <!-- Defaulters report -->
  <div class="card">
    <div class="card-header">
      <div class="card-header-title"><i class="bi bi-exclamation-triangle"></i> Current Defaulters</div>
      <span class="badge badge-failed"><?= count($defaulters) ?> pending</span>
    </div>
    <?php if($defaulters): ?>
    <div class="tbl-wrap">
      <table class="data-table">
        <thead><tr><th>Member</th><th>Group</th><th>Due</th><th>Amount</th></tr></thead>
        <tbody>
          <?php foreach($defaulters as $d): ?>
          <tr>
            <td>
              <div class="fw7"><?= e($d['full_name']) ?></div>
              <div class="text-sm text-muted"><?= e($d['phone']) ?></div>
            </td>
            <td class="text-sm"><?= e($d['group_name']) ?></td>
            <td class="text-sm text-muted"><?= e($d['due_date']) ?></td>
            <td style="color:var(--red)" class="fw7"><?= money($d['contribution_amount']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <div class="empty-state"><div class="empty-icon"><i class="bi bi-check-circle"></i></div><h3>All clear!</h3><p>No defaulters in your groups.</p></div>
    <?php endif; ?>
  </div>
</div>

<!-- Recent contributions -->
<div class="card mt-24 fade-up-4">
  <div class="card-header">
    <div class="card-header-title"><i class="bi bi-clock-history"></i> Recent Contributions</div>
  </div>
  <?php if($recentContribs): ?>
  <div class="tbl-wrap">
    <table class="data-table">
      <thead><tr><th>Group</th><th>Round</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach($recentContribs as $c): ?>
        <tr>
          <td class="fw7"><?= e($c['group_name']) ?></td>
          <td class="text-sm text-muted">Round <?= e($c['round_number']) ?></td>
          <td class="fw7"><?= money($c['amount']) ?></td>
          <td><span class="net net-<?= strtolower(e($c['method'])) ?>"><?= e($c['method']) ?></span></td>
          <td><span class="badge badge-<?= strtolower(e($c['status'])) ?>"><?= e($c['status']) ?></span></td>
          <td class="text-sm text-muted"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <div class="empty-state"><div class="empty-icon"><i class="bi bi-receipt"></i></div><p>No contributions yet.</p></div>
  <?php endif; ?>
</div>

<?php require '../includes/footer.php'; ?>
