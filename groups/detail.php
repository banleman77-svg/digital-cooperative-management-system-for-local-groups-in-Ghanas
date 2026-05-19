<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/../config/db.php';
require '../includes/cycle_engine.php';
$gid=(int)($_GET['id']??0);
if(!$gid){header('Location:'.APP_URL.'/groups/');exit;}
$stmt=db()->prepare("SELECT g.*,u.full_name AS tn FROM groups_ g JOIN users u ON u.id=g.treasurer_id WHERE g.id=?");
$stmt->execute([$gid]);$group=$stmt->fetch();
if(!$group){flash('danger','Group not found.');header('Location:'.APP_URL.'/groups/');exit;}
$isManager=($group['treasurer_id']==$_SESSION['user']['id']||$group['collector_id']==$_SESSION['user']['id']);
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['action'])&&$isManager){
  $action=$_POST['action'];
  if($action==='start_cycle'){
    try{start_cycle($gid);flash('success','Cycle started!');}
    catch(Exception $e){flash('danger',$e->getMessage());}
    header('Location:'.APP_URL.'/groups/detail.php?id='.$gid);exit;
  }
  if($action==='remove_member'){
    $mid=(int)$_POST['member_id'];
    if($group['status']==='ACTIVE'){
      flash('danger','Cannot remove a member while a cycle is active.');
    } else {
      db()->prepare('UPDATE memberships SET is_active=0 WHERE group_id=? AND user_id=?')->execute([$gid,$mid]);
      $mn=db()->prepare('SELECT full_name FROM users WHERE id=?');$mn->execute([$mid]);$mn=$mn->fetchColumn();
      flash('success',"Member {$mn} has been removed from the group.");
    }
    header('Location:'.APP_URL.'/groups/detail.php?id='.$gid);exit;
  }
  if($action==='toggle_invite'){
    $newState=$group['invite_active']?0:1;
    if(!$group['invite_token']){
      $token=bin2hex(random_bytes(8));
      db()->prepare('UPDATE groups_ SET invite_active=?,invite_token=? WHERE id=?')->execute([$newState,$token,$gid]);
    } else {
      db()->prepare('UPDATE groups_ SET invite_active=? WHERE id=?')->execute([$newState,$gid]);
    }
    flash('success',$newState?'Invite link enabled.':'Invite link disabled.');
    header('Location:'.APP_URL.'/groups/detail.php?id='.$gid);exit;
  }
  if($action==='reactivate_member'){
    $mid=(int)$_POST['member_id'];
    db()->prepare('UPDATE memberships SET is_active=1 WHERE group_id=? AND user_id=?')->execute([$gid,$mid]);
    flash('success','Member reactivated successfully.');
    header('Location:'.APP_URL.'/groups/detail.php?id='.$gid);exit;
  }
}
$pageTitle=$group['name'];
$topbarActions=$isManager&&$group['status']==='PENDING'?[['href'=>APP_URL.'/groups/add_member.php?id='.$gid,'icon'=>'person-plus','label'=>'Add Member']]:[];
require '../includes/header.php';
$members=db()->prepare("SELECT m.*,u.full_name,u.phone,u.network FROM memberships m JOIN users u ON u.id=m.user_id WHERE m.group_id=? ORDER BY m.rotation_position");
$members->execute([$gid]);$members=$members->fetchAll();
$mc=count(array_filter($members,fn($m)=>$m['is_active']));
$cycle=db()->prepare("SELECT * FROM cycles WHERE group_id=? AND status='ACTIVE' LIMIT 1");
$cycle->execute([$gid]);$cycle=$cycle->fetch();
$rounds=[];
if($cycle){
  $rs=db()->prepare("SELECT r.*,u.full_name AS rn FROM rounds r JOIN users u ON u.id=r.recipient_id WHERE r.cycle_id=? ORDER BY r.round_number");
  $rs->execute([$cycle['id']]);$rounds=$rs->fetchAll();
}
$pool=$group['contribution_amount']*$mc;
?>
<div class="page-header fu1">
  <div>
    <h1><?=e($group['name'])?></h1>
    <p><code style="background:var(--g50);color:var(--g700);padding:1px 7px;border-radius:4px;font-size:12px"><?=e($group['code'])?></code>
    <?=$group['location']?' · '.e($group['location']):''?> · Managed by <?=e($group['tn'])?>
    </p>
  </div>
  <?php if($isManager&&$group['status']==='PENDING'&&$mc>=2&&!$cycle): ?>
  <form method="post">
    <input type="hidden" name="action" value="start_cycle">
    <button type="submit" class="btn btn-primary" data-confirm="Start the first cycle? This will schedule <?=$mc?> rounds.">
      <i class="bi bi-play-fill"></i>Start First Cycle
    </button>
  </form>
  <?php endif; ?>
</div>

<div class="stats-grid fu2">
  <div class="stat-card green"><div class="stat-icon green"><i class="bi bi-cash-stack"></i></div><div><div class="stat-label">Contribution</div><div class="stat-value" style="font-size:20px"><?=money($group['contribution_amount'])?></div><div class="stat-sub">per <?=strtolower($group['frequency'])?></div></div></div>
  <div class="stat-card blue"><div class="stat-icon blue"><i class="bi bi-people-fill"></i></div><div><div class="stat-label">Members</div><div class="stat-value"><?=$mc?>/<?=$group['max_members']?></div><div class="stat-sub">active</div></div></div>
  <div class="stat-card amber"><div class="stat-icon amber"><i class="bi bi-wallet2"></i></div><div><div class="stat-label">Pool/Round</div><div class="stat-value" style="font-size:20px"><?=money($pool)?></div><div class="stat-sub">total payout</div></div></div>
  <div class="stat-card purple"><div class="stat-icon purple"><i class="bi bi-arrow-repeat"></i></div><div><div class="stat-label">Cycle</div><div class="stat-value"><?=$cycle?'#'.$cycle['cycle_number']:'—'?></div><div class="stat-sub"><?=$cycle?$cycle['status']:'Not started'?></div></div></div>
</div>

<div class="g57 fu3">
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-list-ol"></i>Rotation Order</div>
      <?php if($isManager&&$group['status']==='PENDING'): ?>
      <a href="<?=APP_URL?>/groups/add_member.php?id=<?=$gid?>" class="btn btn-outline btn-sm"><i class="bi bi-plus"></i>Add</a>
      <?php endif; ?>
    </div>
    <?php foreach($members as $m): ?>
    <div class="member-item">
      <span class="rot-num"><?=$m['rotation_position']?></span>
      <div class="m-info">
        <div class="m-name"><?=e($m['full_name'])?></div>
        <div class="m-phone"><?=e($m['phone'])?></div>
      </div>
      <span class="net net-<?=strtolower($m['network'])?>"><?=e($m['network'])?></span>
      <?php if($m['is_active']): ?>
        <?php if($isManager&&$m['user_id']!==$_SESSION['user']['id']): ?>
        <form method="post" style="margin:0">
          <input type="hidden" name="action" value="remove_member">
          <input type="hidden" name="member_id" value="<?=$m['user_id']?>">
          <button type="submit" class="btn btn-sm" style="background:var(--red2);color:var(--red);border:1px solid var(--red3);padding:5px 10px;font-size:11px" data-confirm="Remove <?=e($m['full_name'])?> from this group?">
            <i class="bi bi-person-dash"></i> Remove
          </button>
        </form>
        <?php endif; ?>
      <?php else: ?>
        <span class="badge badge-failed">Removed</span>
        <?php if($isManager): ?>
        <form method="post" style="margin:0">
          <input type="hidden" name="action" value="reactivate_member">
          <input type="hidden" name="member_id" value="<?=$m['user_id']?>">
          <button type="submit" class="btn btn-sm btn-outline" style="font-size:11px;padding:5px 10px">
            <i class="bi bi-person-check"></i> Restore
          </button>
        </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if(!$members): ?><div class="empty-state"><div class="empty-icon"><i class="bi bi-person-plus"></i></div><p>No members yet. Add members to get started.</p></div><?php endif; ?>
  </div>

  <div class="card">
    <div class="card-header"><div class="card-title"><i class="bi bi-arrow-repeat"></i>Current Cycle Rounds</div></div>
    <?php if($rounds): ?>
    <div class="tbl-wrap"><table class="data-table">
      <thead><tr><th>Round</th><th>Recipient</th><th>Due Date</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach($rounds as $r): ?>
      <tr>
        <td class="fw7">Round <?=$r['round_number']?></td>
        <td><?=e($r['rn'])?></td>
        <td class="text-sm text-muted"><?=e($r['due_date'])?></td>
        <td><span class="badge badge-<?=strtolower($r['status'])?>"><?=e($r['status'])?></span></td>
        <td><a href="<?=APP_URL?>/rounds/detail.php?id=<?=$r['id']?>" class="btn btn-ghost btn-sm">View</a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php else: ?>
    <div class="empty-state">
      <div class="empty-icon"><i class="bi bi-calendar-x"></i></div>
      <h3>No cycle yet</h3>
      <p><?=$mc<2?'Add at least 2 members to start a cycle.':($isManager?'Click "Start First Cycle" above.':'Waiting for treasurer to start the cycle.')?></p>
    </div>
    <?php endif; ?>
  </div>
</div>
</div><!-- /g57 -->

<!-- Invite Link & Export -->
<div class="g2 fu4 mt-24">
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-link-45deg"></i>Group Invitation Link</div>
      <?php if($isManager): ?>
      <form method="post" style="margin:0">
        <input type="hidden" name="action" value="toggle_invite">
        <button type="submit" class="btn btn-sm <?=$group['invite_active']?'btn-danger':'btn-outline'?>">
          <?=$group['invite_active']?'<i class="bi bi-x-circle"></i> Disable':'<i class="bi bi-check-circle"></i> Enable'?>
        </button>
      </form>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <?php if($group['invite_active']&&$group['invite_token']): ?>
        <p class="text-sm text-muted mb-8">Share this link with anyone to let them join the group directly:</p>
        <div style="display:flex;gap:8px;align-items:center">
          <input type="text" class="form-control" value="<?=APP_URL?>/invite/join.php?token=<?=e($group['invite_token'])?>" id="inviteLink" readonly style="font-size:12px">
          <button class="btn btn-outline btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('inviteLink').value);this.innerHTML='<i class=\'bi bi-check-lg\'></i> Copied!'">
            <i class="bi bi-clipboard"></i> Copy
          </button>
        </div>
        <p class="form-hint mt-8">Anyone with this link can join while it is enabled. Disable it to stop new joins.</p>
      <?php elseif($isManager): ?>
        <div class="empty-state" style="padding:20px">
          <div class="empty-icon" style="width:44px;height:44px;font-size:20px"><i class="bi bi-link-45deg"></i></div>
          <p>Enable the invite link to let members join without being added manually.</p>
        </div>
      <?php else: ?>
        <p class="text-sm text-muted">Invitation link is currently disabled.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><div class="card-title"><i class="bi bi-download"></i>Export Data</div></div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:10px">
      <a href="<?=APP_URL?>/exports/export.php?type=contributions&group_id=<?=$gid?>" class="btn btn-outline btn-full">
        <i class="bi bi-file-earmark-spreadsheet"></i> Export Contributions (Excel)
      </a>
      <a href="<?=APP_URL?>/exports/export.php?type=members&group_id=<?=$gid?>" class="btn btn-outline btn-full">
        <i class="bi bi-people"></i> Export Members List (Excel)
      </a>
    </div>
  </div>
</div>

<?php require '../includes/footer.php'; ?>
