<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/../config/db.php';
$pageTitle='System Reports';
$topbarChip='Admin';
require '../includes/header.php';

if ($user['role'] !== 'ADMIN') {
    audit('UNAUTHORIZED_ADMIN_ACCESS', 'Tried to access admin/reports.php');
    flash('danger', 'Access denied. Administrator only.');
    header('Location: ' . APP_URL . '/dashboard/');
    exit;
}

// Network breakdown
$networkData = db()->query("SELECT network,COUNT(*) AS cnt FROM users WHERE is_active=1 GROUP BY network")->fetchAll();
$roleData    = db()->query("SELECT role,COUNT(*) AS cnt FROM users WHERE is_active=1 GROUP BY role")->fetchAll();

// Monthly data (last 6 months)
$monthly = [];
for($i=5;$i>=0;$i--){
  $m=date('Y-m',strtotime("-$i months"));
  $l=date('M Y',strtotime("-$i months"));
  $c=db()->prepare("SELECT COALESCE(SUM(amount),0) FROM contributions WHERE status='CONFIRMED' AND DATE_FORMAT(confirmed_at,'%Y-%m')=?");
  $c->execute([$m]);$cAmt=(float)$c->fetchColumn();
  $p=db()->prepare("SELECT COALESCE(SUM(amount),0) FROM payouts WHERE status='COMPLETED' AND DATE_FORMAT(processed_at,'%Y-%m')=?");
  $p->execute([$m]);$pAmt=(float)$p->fetchColumn();
  $monthly[]=['label'=>$l,'contrib'=>$cAmt,'payout'=>$pAmt];
}

// Top performers
$topMembers=db()->query("SELECT u.full_name,u.phone,COALESCE(SUM(c.amount),0) AS total FROM users u LEFT JOIN contributions c ON c.member_id=u.id AND c.status='CONFIRMED' WHERE u.role!='ADMIN' GROUP BY u.id ORDER BY total DESC LIMIT 5")->fetchAll();

$totalRev = db()->query("SELECT COALESCE(SUM(amount),0) FROM contributions WHERE status='CONFIRMED'")->fetchColumn();
$totalOut = db()->query("SELECT COALESCE(SUM(amount),0) FROM payouts WHERE status='COMPLETED'")->fetchColumn();
$avgGroup = db()->query("SELECT COALESCE(AVG(contribution_amount),0) FROM groups_")->fetchColumn();
$successR = db()->query("SELECT (SUM(CASE WHEN status='CONFIRMED' THEN 1 ELSE 0 END) / COUNT(*)) * 100 FROM contributions WHERE status != 'PENDING'")->fetchColumn();
?>

<div class="page-header fu1">
  <div><h1>System Reports</h1><p>Platform-wide analytics and performance metrics.</p></div>
</div>

<div class="stats-grid fu2">
  <div class="stat-card green"><div class="stat-icon green"><i class="bi bi-graph-up-arrow"></i></div><div><div class="stat-label">Total Money Moved</div><div class="stat-value" style="font-size:19px"><?=money($totalRev+$totalOut)?></div><div class="stat-sub">In + Out</div></div></div>
  <div class="stat-card blue"><div class="stat-icon blue"><i class="bi bi-bullseye"></i></div><div><div class="stat-label">Success Rate</div><div class="stat-value"><?=round($successR??0)?>%</div><div class="stat-sub">Confirmed payments</div></div></div>
  <div class="stat-card amber"><div class="stat-icon amber"><i class="bi bi-cash-stack"></i></div><div><div class="stat-label">Avg Contribution</div><div class="stat-value" style="font-size:19px"><?=money($avgGroup)?></div><div class="stat-sub">Per group</div></div></div>
  <div class="stat-card purple"><div class="stat-icon purple"><i class="bi bi-activity"></i></div><div><div class="stat-label">Today's Activity</div><div class="stat-value"><?=db()->query("SELECT COUNT(*) FROM audit_log WHERE DATE(created_at)=CURDATE()")->fetchColumn()?></div><div class="stat-sub">Actions logged</div></div></div>
</div>

<!-- Charts -->
<div class="g2 fu3">
  <div class="card">
    <div class="card-header"><div class="card-title"><i class="bi bi-bar-chart-line"></i>6-Month Trend</div></div>
    <div class="card-body"><canvas id="trendChart" height="200"></canvas></div>
  </div>
  <div class="card">
    <div class="card-header"><div class="card-title"><i class="bi bi-pie-chart-fill"></i>Network Distribution</div></div>
    <div class="card-body"><canvas id="networkChart" height="200"></canvas></div>
  </div>
</div>

<!-- Role distribution -->
<div class="g2 fu4 mt-24">
  <div class="card">
    <div class="card-header"><div class="card-title"><i class="bi bi-person-badge"></i>User Roles</div></div>
    <div class="card-body"><canvas id="roleChart" height="200"></canvas></div>
  </div>

  <div class="card">
    <div class="card-header"><div class="card-title"><i class="bi bi-trophy-fill" style="color:#f5c842"></i>Top Contributors</div></div>
    <?php foreach($topMembers as $i=>$m): ?>
    <div style="display:flex;align-items:center;gap:12px;padding:13px 20px;border-bottom:1px solid var(--n100)">
      <div style="width:30px;height:30px;border-radius:50%;background:<?=$i===0?'linear-gradient(135deg,#fbbf24,#d97706)':($i===1?'linear-gradient(135deg,#94a3b8,#475569)':($i===2?'linear-gradient(135deg,#fb923c,#9a3412)':'linear-gradient(135deg,#1a6e3a,#0a3d1f)'))?>;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;flex-shrink:0">
        <?=$i+1?>
      </div>
      <div style="flex:1;min-width:0">
        <div class="fw7 text-sm truncate"><?=e($m['full_name'])?></div>
        <div class="text-xs text-muted"><?=e($m['phone'])?></div>
      </div>
      <div class="fw7 text-sm" style="color:var(--g600)"><?=money($m['total'])?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
const monthly = <?=json_encode($monthly)?>;
const netData = <?=json_encode($networkData)?>;
const roleData = <?=json_encode($roleData)?>;

new Chart(document.getElementById('trendChart'),{
  type:'bar',
  data:{
    labels:monthly.map(m=>m.label),
    datasets:[
      {label:'Contributions',data:monthly.map(m=>m.contrib),backgroundColor:'rgba(34,130,68,.7)',borderRadius:6},
      {label:'Payouts',data:monthly.map(m=>m.payout),backgroundColor:'rgba(245,200,66,.7)',borderRadius:6}
    ]
  },
  options:{responsive:true,plugins:{legend:{position:'bottom'}},scales:{y:{beginAtZero:true},x:{grid:{display:false}}}}
});

new Chart(document.getElementById('networkChart'),{
  type:'doughnut',
  data:{
    labels:netData.map(n=>n.network),
    datasets:[{data:netData.map(n=>n.cnt),backgroundColor:['#f5c842','#e53e3e','#3182ce','#9ca3af'],borderWidth:0}]
  },
  options:{responsive:true,plugins:{legend:{position:'bottom',labels:{padding:14}}}}
});

new Chart(document.getElementById('roleChart'),{
  type:'doughnut',
  data:{
    labels:roleData.map(r=>r.role),
    datasets:[{data:roleData.map(r=>r.cnt),backgroundColor:['#1a6e3a','#3182ce','#f59e0b','#e53e3e'],borderWidth:0}]
  },
  options:{responsive:true,plugins:{legend:{position:'bottom',labels:{padding:14}}}}
});
</script>

<?php require '../includes/footer.php'; ?>
