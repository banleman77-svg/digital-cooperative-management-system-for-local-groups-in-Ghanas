<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/../config/db.php';
$pageTitle='Audit Log';
$topbarChip='Security';
require '../includes/header.php';

$page  = max(1,(int)($_GET['page']??1));
$limit = 20;
$offset= ($page-1)*$limit;

$total = db()->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
$pages = ceil($total/$limit);

$logs = db()->prepare("SELECT a.*,u.full_name,u.phone FROM audit_log a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC LIMIT $limit OFFSET $offset");
$logs->execute();$logs=$logs->fetchAll();

$actionColors = [
  'LOGIN'           => 'badge-active',
  'LOGOUT'          => 'badge-closed',
  'CREATE_GROUP'    => 'badge-paid',
  'ADD_MEMBER'      => 'badge-paid',
  'REMOVE_MEMBER'   => 'badge-failed',
  'START_CYCLE'     => 'badge-active',
  'RECORD_CASH'     => 'badge-confirmed',
  'TRIGGER_PAYOUT'  => 'badge-processing',
  'COMPLETE_PAYOUT' => 'badge-active',
  'MOMO_PAYMENT'    => 'badge-processing',
];
?>

<div class="page-header fu1">
  <div>
    <h1>Audit Log</h1>
    <p>Complete record of every action taken in the system.</p>
  </div>
  <a href="<?=APP_URL?>/exports/audit_export.php" class="btn btn-outline btn-sm">
    <i class="bi bi-download"></i> Export Excel
  </a>
</div>

<!-- Stats -->
<div class="stats-grid fu2">
  <div class="stat-card green">
    <div class="stat-icon green"><i class="bi bi-journal-check"></i></div>
    <div><div class="stat-label">Total Actions</div><div class="stat-value"><?=$total?></div><div class="stat-sub">All time</div></div>
  </div>
  <div class="stat-card blue">
    <div class="stat-icon blue"><i class="bi bi-calendar-day"></i></div>
    <div><div class="stat-label">Today</div>
    <div class="stat-value"><?=db()->query("SELECT COUNT(*) FROM audit_log WHERE DATE(created_at)=CURDATE()")->fetchColumn()?></div>
    <div class="stat-sub">Actions today</div></div>
  </div>
  <div class="stat-card amber">
    <div class="stat-icon amber"><i class="bi bi-people"></i></div>
    <div><div class="stat-label">Active Users</div>
    <div class="stat-value"><?=db()->query("SELECT COUNT(DISTINCT user_id) FROM audit_log WHERE created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetchColumn()?></div>
    <div class="stat-sub">Last 7 days</div></div>
  </div>
  <div class="stat-card purple">
    <div class="stat-icon purple"><i class="bi bi-shield-check"></i></div>
    <div><div class="stat-label">Payouts Logged</div>
    <div class="stat-value"><?=db()->query("SELECT COUNT(*) FROM audit_log WHERE action LIKE '%PAYOUT%'")->fetchColumn()?></div>
    <div class="stat-sub">Financial actions</div></div>
  </div>
</div>

<div class="card fu3">
  <div class="card-header">
    <div class="card-title"><i class="bi bi-journal-text"></i>Activity Log</div>
    <input type="text" id="table-search" class="form-control" placeholder="Search actions..." style="width:200px;padding:7px 12px;font-size:13px">
  </div>
  <?php if($logs): ?>
  <div class="tbl-wrap">
    <table class="data-table">
      <thead>
        <tr><th>Time</th><th>User</th><th>Action</th><th>Details</th><th>IP Address</th></tr>
      </thead>
      <tbody>
      <?php foreach($logs as $l): ?>
        <tr data-searchable>
          <td>
            <div class="fw7 text-sm"><?=date('M j, Y',strtotime($l['created_at']))?></div>
            <div class="text-xs text-muted"><?=date('g:i A',strtotime($l['created_at']))?></div>
          </td>
          <td>
            <?php if($l['full_name']): ?>
              <div class="fw7 text-sm"><?=e($l['full_name'])?></div>
              <div class="text-xs text-muted"><?=e($l['phone']??'')?></div>
            <?php else: ?>
              <span class="text-muted text-sm">System</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge <?=$actionColors[$l['action']]??'badge-pending'?>"><?=e(str_replace('_',' ',$l['action']))?></span>
          </td>
          <td class="text-sm" style="max-width:300px;word-break:break-word"><?=e($l['details']??'—')?></td>
          <td class="text-xs text-muted"><?=e($l['ip_address']??'—')?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if($pages > 1): ?>
  <div style="display:flex;justify-content:center;gap:8px;padding:16px">
    <?php for($i=1;$i<=$pages;$i++): ?>
      <a href="?page=<?=$i?>" class="btn btn-sm <?=$i===$page?'btn-primary':'btn-ghost'?>"><?=$i?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

  <?php else: ?>
    <div class="empty-state">
      <div class="empty-icon"><i class="bi bi-journal"></i></div>
      <h3>No activity yet</h3>
      <p>Actions will appear here as users interact with the system.</p>
    </div>
  <?php endif; ?>
</div>

<?php require '../includes/footer.php'; ?>
