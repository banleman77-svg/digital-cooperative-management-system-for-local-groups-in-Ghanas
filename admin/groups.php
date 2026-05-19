<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/../config/db.php';
$pageTitle='All Groups';
$topbarChip='Admin';
require '../includes/header.php';

if ($user['role'] !== 'ADMIN') {
    audit('UNAUTHORIZED_ADMIN_ACCESS', 'Tried to access admin/groups.php');
    flash('danger', 'Access denied. Administrator only.');
    header('Location: ' . APP_URL . '/dashboard/');
    exit;
}

if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['action'])){
    $action=$_POST['action'];$gid=(int)$_POST['group_id'];
    if($action==='lock'){
        db()->prepare("UPDATE groups_ SET status='LOCKED' WHERE id=?")->execute([$gid]);
        audit('LOCK_GROUP',"Locked group ID $gid");
        flash('success','Group locked.');
    } elseif($action==='unlock'){
        db()->prepare("UPDATE groups_ SET status='PENDING' WHERE id=?")->execute([$gid]);
        audit('UNLOCK_GROUP',"Unlocked group ID $gid");
        flash('success','Group unlocked.');
    }
    header('Location:'.APP_URL.'/admin/groups.php');exit;
}

$groups = db()->query("SELECT g.*,u.full_name AS treasurer_name,u.phone AS treasurer_phone,
    (SELECT COUNT(*) FROM memberships m WHERE m.group_id=g.id AND m.is_active=1) AS mc,
    (SELECT COUNT(*) FROM cycles cy WHERE cy.group_id=g.id) AS cycle_count,
    (SELECT COALESCE(SUM(c.amount),0) FROM contributions c JOIN rounds r ON r.id=c.round_id JOIN cycles cy ON cy.id=r.cycle_id WHERE cy.group_id=g.id AND c.status='CONFIRMED') AS total_collected,
    (SELECT COALESCE(SUM(p.amount),0) FROM payouts p JOIN rounds r ON r.id=p.round_id JOIN cycles cy ON cy.id=r.cycle_id WHERE cy.group_id=g.id AND p.status='COMPLETED') AS total_paid
    FROM groups_ g
    JOIN users u ON u.id=g.treasurer_id
    ORDER BY g.created_at DESC")->fetchAll();
?>

<div class="page-header fu1">
  <div><h1>All Groups</h1><p>Every Susu group on the platform — monitor and intervene if needed.</p></div>
  <a href="<?=APP_URL?>/exports/export.php?type=audit" class="btn btn-outline btn-sm"><i class="bi bi-download"></i> Export</a>
</div>

<!-- Quick stats -->
<div class="stats-grid fu2">
  <div class="stat-card green"><div class="stat-icon green"><i class="bi bi-collection-fill"></i></div><div><div class="stat-label">Total Groups</div><div class="stat-value"><?=count($groups)?></div><div class="stat-sub">All time</div></div></div>
  <div class="stat-card blue"><div class="stat-icon blue"><i class="bi bi-play-circle-fill"></i></div><div><div class="stat-label">Active Cycles</div><div class="stat-value"><?=count(array_filter($groups,fn($g)=>$g['status']==='ACTIVE'))?></div><div class="stat-sub">Currently running</div></div></div>
  <div class="stat-card amber"><div class="stat-icon amber"><i class="bi bi-currency-exchange"></i></div><div><div class="stat-label">Total Collected</div><div class="stat-value" style="font-size:18px"><?=money(array_sum(array_column($groups,'total_collected')))?></div><div class="stat-sub">All groups</div></div></div>
  <div class="stat-card purple"><div class="stat-icon purple"><i class="bi bi-people"></i></div><div><div class="stat-label">Total Members</div><div class="stat-value"><?=array_sum(array_column($groups,'mc'))?></div><div class="stat-sub">Across all groups</div></div></div>
</div>

<div class="card fu3">
  <div class="card-header">
    <div class="card-title"><i class="bi bi-list-ul"></i>Groups Directory</div>
    <input type="text" id="table-search" class="form-control" placeholder="Search by name, code, treasurer..." style="width:280px;padding:7px 12px;font-size:13px">
  </div>
  <?php if($groups): ?>
  <div class="tbl-wrap">
    <table class="data-table">
      <thead><tr><th>Group</th><th>Treasurer</th><th>Contribution</th><th>Members</th><th>Cycles</th><th>Collected</th><th>Paid Out</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($groups as $g): ?>
      <tr data-searchable>
        <td>
          <a href="<?=APP_URL?>/groups/detail.php?id=<?=$g['id']?>" style="color:var(--g700);text-decoration:none">
            <div class="fw7 text-sm"><?=e($g['name'])?></div>
            <div class="text-xs text-muted"><?=e($g['code'])?> · <?=e($g['location']?:'No location')?></div>
          </a>
        </td>
        <td>
          <div class="fw7 text-sm"><?=e($g['treasurer_name'])?></div>
          <div class="text-xs text-muted"><?=e($g['treasurer_phone'])?></div>
        </td>
        <td><?=money($g['contribution_amount'])?><div class="text-xs text-muted"><?=strtolower($g['frequency'])?></div></td>
        <td><?=$g['mc']?>/<?=$g['max_members']?></td>
        <td><?=$g['cycle_count']?></td>
        <td class="fw7" style="color:var(--g700)"><?=money($g['total_collected'])?></td>
        <td class="fw7" style="color:var(--blue)"><?=money($g['total_paid'])?></td>
        <td><span class="badge badge-<?=strtolower($g['status'])?>"><?=e($g['status'])?></span></td>
        <td>
          <div style="display:flex;gap:5px">
            <a href="<?=APP_URL?>/groups/detail.php?id=<?=$g['id']?>" class="btn btn-ghost btn-sm" style="padding:4px 9px;font-size:11px"><i class="bi bi-eye"></i></a>
            <?php if($g['status']==='LOCKED'): ?>
              <form method="post" style="margin:0">
                <input type="hidden" name="action" value="unlock"><input type="hidden" name="group_id" value="<?=$g['id']?>">
                <button type="submit" class="btn btn-outline btn-sm" style="padding:4px 9px;font-size:11px"><i class="bi bi-unlock"></i></button>
              </form>
            <?php else: ?>
              <form method="post" style="margin:0">
                <input type="hidden" name="action" value="lock"><input type="hidden" name="group_id" value="<?=$g['id']?>">
                <button type="submit" class="btn btn-sm" style="background:var(--red2);color:var(--red);border:1px solid var(--red3);padding:4px 9px;font-size:11px" data-confirm="Lock <?=e($g['name'])?>? Members will not be able to contribute."><i class="bi bi-lock"></i></button>
              </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <div class="empty-state"><div class="empty-icon"><i class="bi bi-collection"></i></div><p>No groups have been created yet.</p></div>
  <?php endif; ?>
</div>

<?php require '../includes/footer.php'; ?>
