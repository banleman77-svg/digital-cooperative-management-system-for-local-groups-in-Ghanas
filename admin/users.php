<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/../config/db.php';
$pageTitle='Manage Users';
$topbarChip='Admin';
require '../includes/header.php';

if ($user['role'] !== 'ADMIN') {
    audit('UNAUTHORIZED_ADMIN_ACCESS', 'Tried to access users.php');
    flash('danger', 'Access denied. Administrator only.');
    header('Location: ' . APP_URL . '/dashboard/');
    exit;
}

// Handle actions
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['action'])){
    $action=$_POST['action'];
    $uid=(int)$_POST['user_id'];
    if($uid===$user['id']){ flash('danger','You cannot modify your own account here.'); }
    elseif($action==='suspend'){
        db()->prepare('UPDATE users SET is_active=0 WHERE id=?')->execute([$uid]);
        audit('SUSPEND_USER',"Suspended user ID $uid");
        flash('success','User suspended successfully.');
    } elseif($action==='reactivate'){
        db()->prepare('UPDATE users SET is_active=1 WHERE id=?')->execute([$uid]);
        audit('REACTIVATE_USER',"Reactivated user ID $uid");
        flash('success','User reactivated successfully.');
    } elseif($action==='change_role'){
        $newRole=$_POST['new_role'];
        if(in_array($newRole,['MEMBER','TREASURER','COLLECTOR','ADMIN'])){
            db()->prepare('UPDATE users SET role=? WHERE id=?')->execute([$newRole,$uid]);
            audit('CHANGE_ROLE',"User ID $uid role changed to $newRole");
            flash('success','User role updated.');
        }
    }
    header('Location:'.APP_URL.'/admin/users.php');exit;
}

$users = db()->query("SELECT u.*,
    (SELECT COUNT(*) FROM memberships m WHERE m.user_id=u.id AND m.is_active=1) AS groups_count,
    (SELECT COALESCE(SUM(c.amount),0) FROM contributions c WHERE c.member_id=u.id AND c.status='CONFIRMED') AS total_contributed
    FROM users u
    ORDER BY u.created_at DESC")->fetchAll();

$counts = [
    'all' => count($users),
    'admin' => count(array_filter($users,fn($u)=>$u['role']==='ADMIN')),
    'treasurer' => count(array_filter($users,fn($u)=>$u['role']==='TREASURER')),
    'collector' => count(array_filter($users,fn($u)=>$u['role']==='COLLECTOR')),
    'member' => count(array_filter($users,fn($u)=>$u['role']==='MEMBER')),
    'inactive' => count(array_filter($users,fn($u)=>!$u['is_active'])),
];
?>

<div class="page-header fu1">
  <div>
    <h1>Manage Users</h1>
    <p>View and manage every user registered on the platform.</p>
  </div>
</div>

<!-- Filter pills -->
<div class="filter-pills fu2 mb-16">
  <span class="filter-pill active">All <span class="filter-count"><?=$counts['all']?></span></span>
  <span class="filter-pill" data-filter="ADMIN">Admins <span class="filter-count"><?=$counts['admin']?></span></span>
  <span class="filter-pill" data-filter="TREASURER">Treasurers <span class="filter-count"><?=$counts['treasurer']?></span></span>
  <span class="filter-pill" data-filter="COLLECTOR">Collectors <span class="filter-count"><?=$counts['collector']?></span></span>
  <span class="filter-pill" data-filter="MEMBER">Members <span class="filter-count"><?=$counts['member']?></span></span>
  <span class="filter-pill" data-filter="INACTIVE">Suspended <span class="filter-count"><?=$counts['inactive']?></span></span>
</div>

<div class="card fu3">
  <div class="card-header">
    <div class="card-title"><i class="bi bi-people"></i>All Users (<?=count($users)?>)</div>
    <input type="text" id="table-search" class="form-control" placeholder="Search by name or phone..." style="width:240px;padding:7px 12px;font-size:13px">
  </div>
  <?php if($users): ?>
  <div class="tbl-wrap">
    <table class="data-table">
      <thead><tr><th>User</th><th>Phone</th><th>Role</th><th>Network</th><th>Groups</th><th>Contributed</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($users as $u): ?>
      <tr data-searchable data-role="<?=$u['role']?>" data-active="<?=$u['is_active']?'YES':'NO'?>">
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--g500),var(--g700));color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex-shrink:0">
              <?=strtoupper(substr($u['full_name'],0,1))?>
            </div>
            <div>
              <div class="fw7 text-sm"><?=e($u['full_name'])?></div>
              <div class="text-xs text-muted"><?=e($u['member_code'])?></div>
            </div>
          </div>
        </td>
        <td class="text-sm"><?=e($u['phone'])?></td>
        <td>
          <span class="badge <?php
            echo match($u['role']){
              'ADMIN' => 'badge-failed',
              'TREASURER' => 'badge-paid',
              'COLLECTOR' => 'badge-pending',
              default => 'badge-active',
            };
          ?>"><?=e($u['role'])?></span>
        </td>
        <td><span class="net net-<?=strtolower($u['network'])?>"><?=e($u['network'])?></span></td>
        <td><?=$u['groups_count']?></td>
        <td class="fw7" style="color:var(--g700)"><?=money($u['total_contributed'])?></td>
        <td>
          <?php if($u['is_active']): ?>
            <span class="badge badge-active">Active</span>
          <?php else: ?>
            <span class="badge badge-failed">Suspended</span>
          <?php endif; ?>
        </td>
        <td class="text-xs text-muted"><?=date('M j, Y',strtotime($u['created_at']))?></td>
        <td>
          <?php if($u['id']!==$user['id']): ?>
          <div style="display:flex;gap:5px;flex-wrap:wrap">
            <?php if($u['is_active']): ?>
              <form method="post" style="margin:0">
                <input type="hidden" name="action" value="suspend">
                <input type="hidden" name="user_id" value="<?=$u['id']?>">
                <button type="submit" class="btn btn-sm" style="background:var(--red2);color:var(--red);border:1px solid var(--red3);padding:4px 9px;font-size:11px" data-confirm="Suspend <?=e($u['full_name'])?>?">
                  <i class="bi bi-pause-circle"></i>
                </button>
              </form>
            <?php else: ?>
              <form method="post" style="margin:0">
                <input type="hidden" name="action" value="reactivate">
                <input type="hidden" name="user_id" value="<?=$u['id']?>">
                <button type="submit" class="btn btn-outline btn-sm" style="padding:4px 9px;font-size:11px">
                  <i class="bi bi-play-circle"></i>
                </button>
              </form>
            <?php endif; ?>
          </div>
          <?php else: ?>
            <span class="text-xs text-muted">You</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <div class="empty-state"><div class="empty-icon"><i class="bi bi-people"></i></div><p>No users yet</p></div>
  <?php endif; ?>
</div>

<style>
.filter-pills { display: flex; gap: 8px; flex-wrap: wrap; }
.filter-pill {
  background: var(--white); border: 1.5px solid var(--n200);
  padding: 7px 14px; border-radius: 999px;
  font-size: 12.5px; font-weight: 600;
  color: var(--n600); cursor: pointer;
  transition: all .15s; font-family: var(--fh);
  display: inline-flex; align-items: center; gap: 7px;
}
.filter-pill:hover { background: var(--g50); border-color: var(--g300); }
.filter-pill.active { background: var(--g600); color: #fff; border-color: var(--g600); }
.filter-count {
  background: rgba(0,0,0,.08); color: inherit;
  padding: 1px 7px; border-radius: 999px; font-size: 11px;
}
.filter-pill.active .filter-count { background: rgba(255,255,255,.25); color: #fff; }
</style>

<script>
document.querySelectorAll('.filter-pill').forEach(p => {
  p.addEventListener('click', function() {
    document.querySelectorAll('.filter-pill').forEach(x => x.classList.remove('active'));
    this.classList.add('active');
    const filter = this.dataset.filter;
    document.querySelectorAll('tr[data-searchable]').forEach(row => {
      if (!filter) { row.style.display = ''; return; }
      if (filter === 'INACTIVE') { row.style.display = row.dataset.active === 'NO' ? '' : 'none'; }
      else { row.style.display = row.dataset.role === filter ? '' : 'none'; }
    });
  });
});
</script>

<?php require '../includes/footer.php'; ?>
