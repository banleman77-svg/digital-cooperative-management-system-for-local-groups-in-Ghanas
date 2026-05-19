<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/../config/db.php';
$pageTitle='Dashboard';
$topbarChip='Active';
$topbarActions=[['href'=>APP_URL.'/groups/create.php','icon'=>'plus-lg','label'=>'New Group']];
require '../includes/header.php';
$uid=$user['id'];
$stmt=db()->prepare("SELECT COALESCE(SUM(amount),0) FROM contributions WHERE member_id=? AND status='CONFIRMED'");$stmt->execute([$uid]);$totalIn=$stmt->fetchColumn();
$stmt=db()->prepare("SELECT COALESCE(SUM(amount),0) FROM payouts WHERE recipient_id=? AND status='COMPLETED'");$stmt->execute([$uid]);$totalOut=$stmt->fetchColumn();
$stmt=db()->prepare("SELECT COUNT(DISTINCT g.id) FROM groups_ g WHERE g.treasurer_id=? OR g.collector_id=? OR EXISTS(SELECT 1 FROM memberships m WHERE m.group_id=g.id AND m.user_id=?)");$stmt->execute([$uid,$uid,$uid]);$gCount=$stmt->fetchColumn();
$stmt=db()->prepare("SELECT COUNT(*) FROM rounds WHERE recipient_id=? AND status IN ('PENDING','OPEN','CLOSED')");$stmt->execute([$uid]);$upCount=$stmt->fetchColumn();
$stmt=db()->prepare("SELECT g.*,(SELECT COUNT(*) FROM memberships m WHERE m.group_id=g.id AND m.is_active=1) AS mc FROM groups_ g WHERE g.treasurer_id=? OR g.collector_id=? ORDER BY g.created_at DESC LIMIT 6");$stmt->execute([$uid,$uid]);$managed=$stmt->fetchAll();
$stmt=db()->prepare("SELECT g.*,m.rotation_position FROM groups_ g JOIN memberships m ON m.group_id=g.id WHERE m.user_id=? AND m.is_active=1 AND g.treasurer_id!=? ORDER BY g.created_at DESC LIMIT 5");$stmt->execute([$uid,$uid]);$member=$stmt->fetchAll();
$stmt=db()->prepare("SELECT r.*,g.name AS gname,g.contribution_amount,cy.total_rounds FROM rounds r JOIN cycles cy ON cy.id=r.cycle_id JOIN groups_ g ON g.id=cy.group_id WHERE r.recipient_id=? AND r.status IN ('PENDING','OPEN','CLOSED') ORDER BY r.due_date LIMIT 6");$stmt->execute([$uid]);$upcoming=$stmt->fetchAll();
$stmt=db()->prepare("SELECT c.*,g.name AS gname FROM contributions c JOIN rounds r ON r.id=c.round_id JOIN cycles cy ON cy.id=r.cycle_id JOIN groups_ g ON g.id=cy.group_id WHERE c.member_id=? ORDER BY c.created_at DESC LIMIT 5");$stmt->execute([$uid]);$recent=$stmt->fetchAll();
?>
<div class="page-header fu1">
  <div><h1>Akwaaba, <?=e(explode(' ',$user['full_name'])[0])?> 👋</h1><p>Here's your Susu overview for today — <?=date('l, F j, Y')?></p></div>
</div>

<div class="stats-grid fu2">
  <div class="stat-card green"><div class="stat-icon green"><i class="bi bi-people-fill"></i></div><div><div class="stat-label">Groups</div><div class="stat-value"><?=$gCount?></div><div class="stat-sub">Active memberships</div></div></div>
  <div class="stat-card blue"><div class="stat-icon blue"><i class="bi bi-arrow-up-circle-fill"></i></div><div><div class="stat-label">Total Contributed</div><div class="stat-value" style="font-size:19px"><?=money($totalIn)?></div><div class="stat-sub">Confirmed payments</div></div></div>
  <div class="stat-card amber"><div class="stat-icon amber"><i class="bi bi-arrow-down-circle-fill"></i></div><div><div class="stat-label">Total Received</div><div class="stat-value" style="font-size:19px"><?=money($totalOut)?></div><div class="stat-sub">Payouts received</div></div></div>
  <div class="stat-card purple"><div class="stat-icon purple"><i class="bi bi-calendar-event-fill"></i></div><div><div class="stat-label">Upcoming Payouts</div><div class="stat-value"><?=$upCount?></div><div class="stat-sub">Scheduled rounds</div></div></div>
</div>

<div class="g75 fu3" style="margin-bottom:20px">
  <div>
    <div class="card mb-16">
      <div class="card-header">
        <div class="card-title"><i class="bi bi-shield-check"></i>Groups You Manage</div>
        <a href="<?=APP_URL?>/groups/create.php" class="btn btn-outline btn-sm"><i class="bi bi-plus"></i>New</a>
      </div>
      <?php if($managed): ?>
      <div class="tbl-wrap"><table class="data-table">
        <thead><tr><th>Group</th><th>Contribution</th><th>Members</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach($managed as $g): ?>
        <tr data-searchable>
          <td><div class="fw7"><?=e($g['name'])?></div><div class="text-sm text-muted"><?=e($g['code'])?></div></td>
          <td><?=money($g['contribution_amount'])?><span class="text-xs text-muted">/<?=strtolower($g['frequency'])?></span></td>
          <td><?=$g['mc']?>/<?=$g['max_members']?></td>
          <td><span class="badge badge-<?=strtolower($g['status'])?>"><?=e($g['status'])?></span></td>
          <td><a href="<?=APP_URL?>/groups/detail.php?id=<?=$g['id']?>" class="btn btn-ghost btn-sm">View</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php else: ?>
      <div class="empty-state"><div class="empty-icon"><i class="bi bi-people"></i></div><h3>No groups yet</h3><p>Create your first Susu group.</p><a href="<?=APP_URL?>/groups/create.php" class="btn btn-primary btn-sm"><i class="bi bi-plus"></i>Create Group</a></div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-header"><div class="card-title"><i class="bi bi-person-badge"></i>Groups You Belong To</div></div>
      <?php if($member): ?>
      <div class="tbl-wrap"><table class="data-table">
        <thead><tr><th>Group</th><th>Amount</th><th>Position</th><th></th></tr></thead>
        <tbody>
        <?php foreach($member as $g): ?>
        <tr>
          <td><div class="fw7"><?=e($g['name'])?></div><div class="text-sm text-muted"><?=e($g['code'])?></div></td>
          <td><?=money($g['contribution_amount'])?></td>
          <td><span class="rot-num"><?=$g['rotation_position']?></span></td>
          <td><a href="<?=APP_URL?>/groups/detail.php?id=<?=$g['id']?>" class="btn btn-ghost btn-sm">View</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php else: ?>
      <div class="empty-state"><div class="empty-icon"><i class="bi bi-person-plus"></i></div><p>Ask your treasurer to add you to a group.</p></div>
      <?php endif; ?>
    </div>
  </div>

  <div style="display:flex;flex-direction:column;gap:16px">
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="bi bi-calendar-check"></i>Upcoming Payouts</div></div>
      <?php if($upcoming): ?>
        <?php foreach($upcoming as $r): ?>
        <a href="<?=APP_URL?>/rounds/detail.php?id=<?=$r['id']?>" style="display:flex;align-items:center;justify-content:space-between;padding:13px 20px;border-bottom:1px solid var(--n100);color:inherit;transition:background .15s" onmouseover="this.style.background='#f0faf3'" onmouseout="this.style.background=''">
          <div><div class="fw7 text-sm"><?=e($r['gname'])?></div><div class="text-xs text-muted">Round <?=$r['round_number']?> · Due <?=e($r['due_date'])?></div></div>
          <div class="fw7" style="color:var(--g600);font-size:14px"><?=money($r['contribution_amount']*$r['total_rounds'])?></div>
        </a>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-state" style="padding:28px"><div class="empty-icon" style="width:44px;height:44px;font-size:20px"><i class="bi bi-calendar-x"></i></div><p>No upcoming payouts.</p></div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-header"><div class="card-title"><i class="bi bi-clock-history"></i>Recent Activity</div></div>
      <?php if($recent): ?>
        <?php foreach($recent as $c): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 20px;border-bottom:1px solid var(--n100)">
          <div><div class="fw7 text-sm"><?=e($c['gname'])?></div><div class="text-xs text-muted"><?=date('M j',strtotime($c['created_at']))?></div></div>
          <div style="text-align:right"><div class="fw7 text-sm" style="color:var(--g600)"><?=money($c['amount'])?></div><span class="badge badge-<?=strtolower($c['status'])?>"><?=e($c['status'])?></span></div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-state" style="padding:28px"><p>No recent activity.</p></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Charts Section -->
<div class="g2 fu4 mt-24">
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-bar-chart-line"></i>Monthly Contributions</div>
    </div>
    <div class="card-body">
      <canvas id="contribChart" height="180"></canvas>
    </div>
  </div>
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-pie-chart"></i>Payment Methods</div>
    </div>
    <div class="card-body">
      <canvas id="methodChart" height="180"></canvas>
    </div>
  </div>
</div>

<?php
// Chart data: contributions per month (last 6 months)
$chartData = [];
for($i=5;$i>=0;$i--){
  $month = date('Y-m', strtotime("-$i months"));
  $label = date('M Y', strtotime("-$i months"));
  $stmt2 = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM contributions WHERE member_id=? AND status='CONFIRMED' AND DATE_FORMAT(confirmed_at,'%Y-%m')=?");
  $stmt2->execute([$uid,$month]); $amt=$stmt2->fetchColumn();
  $chartData[] = ['label'=>$label,'amount'=>(float)$amt];
}

// Payment method breakdown
$methods = db()->prepare("SELECT method, COUNT(*) as cnt FROM contributions WHERE member_id=? AND status='CONFIRMED' GROUP BY method");
$methods->execute([$uid]); $methods=$methods->fetchAll();
?>

<script>
// Monthly contributions chart
const ctx1 = document.getElementById('contribChart').getContext('2d');
new Chart(ctx1, {
  type: 'bar',
  data: {
    labels: <?=json_encode(array_column($chartData,'label'))?>,
    datasets: [{
      label: 'GHS Contributed',
      data: <?=json_encode(array_column($chartData,'amount'))?>,
      backgroundColor: 'rgba(34,130,68,.7)',
      borderColor: '#1a6e3a',
      borderWidth: 2,
      borderRadius: 6,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.05)' } },
      x: { grid: { display: false } }
    }
  }
});

// Method chart
const ctx2 = document.getElementById('methodChart').getContext('2d');
new Chart(ctx2, {
  type: 'doughnut',
  data: {
    labels: <?=json_encode(array_column($methods,'method'))?>,
    datasets: [{
      data: <?=json_encode(array_column($methods,'cnt'))?>,
      backgroundColor: ['#2e9e56','#3182ce','#f59e0b'],
      borderWidth: 0,
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { position: 'bottom', labels: { padding: 16, font: { size: 12 } } }
    }
  }
});
</script>

<?php require '../includes/footer.php'; ?>
