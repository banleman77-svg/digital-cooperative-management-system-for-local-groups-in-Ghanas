<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/../config/db.php';
$pageTitle='Groups';
$topbarActions=[['href'=>APP_URL.'/groups/create.php','icon'=>'plus-lg','label'=>'New Group']];
require '../includes/header.php';
$uid=$user['id'];
$stmt=db()->prepare("SELECT g.*,(SELECT COUNT(*) FROM memberships m WHERE m.group_id=g.id AND m.is_active=1) AS mc FROM groups_ g WHERE g.treasurer_id=? OR g.collector_id=? OR EXISTS(SELECT 1 FROM memberships m WHERE m.group_id=g.id AND m.user_id=?) GROUP BY g.id ORDER BY g.created_at DESC");
$stmt->execute([$uid,$uid,$uid]);$groups=$stmt->fetchAll();
?>
<div class="page-header fu1">
  <div><h1>All Groups</h1><p>Groups you manage or belong to.</p></div>
</div>
<div class="card fu2">
  <div class="card-header">
    <div class="card-title"><i class="bi bi-people"></i><?=count($groups)?> Groups</div>
    <div style="display:flex;gap:8px;align-items:center">
      <input type="text" id="table-search" class="form-control" placeholder="Search groups..." style="width:200px;padding:7px 12px;font-size:13px">
    </div>
  </div>
  <?php if($groups): ?>
  <div class="tbl-wrap"><table class="data-table">
    <thead><tr><th>Name</th><th>Code</th><th>Contribution</th><th>Frequency</th><th>Members</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach($groups as $g): ?>
    <tr data-searchable>
      <td><div class="fw7"><?=e($g['name'])?></div><div class="text-sm text-muted"><?=e($g['location']?:'—')?></div></td>
      <td><code style="background:var(--g50);color:var(--g700);padding:2px 8px;border-radius:5px;font-size:11.5px"><?=e($g['code'])?></code></td>
      <td class="fw7"><?=money($g['contribution_amount'])?></td>
      <td class="text-sm"><?=e($g['frequency'])?></td>
      <td><?=$g['mc']?>/<?=$g['max_members']?></td>
      <td><span class="badge badge-<?=strtolower($g['status'])?>"><?=e($g['status'])?></span></td>
      <td><a href="<?=APP_URL?>/groups/detail.php?id=<?=$g['id']?>" class="btn btn-ghost btn-sm">View</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?>
  <div class="empty-state"><div class="empty-icon"><i class="bi bi-people"></i></div><h3>No groups yet</h3><p>Create your first Susu group to get started.</p><a href="<?=APP_URL?>/groups/create.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i>Create Group</a></div>
  <?php endif; ?>
</div>
<?php require '../includes/footer.php'; ?>
