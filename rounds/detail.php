<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/../config/db.php';
require '../includes/cycle_engine.php';
$rid=(int)($_GET['id']??0);
$stmt=db()->prepare("SELECT r.*,cy.group_id,cy.cycle_number,cy.total_rounds,g.name AS gn,g.contribution_amount,g.treasurer_id,g.collector_id,u.full_name AS rn,u.phone AS rp FROM rounds r JOIN cycles cy ON cy.id=r.cycle_id JOIN groups_ g ON g.id=cy.group_id JOIN users u ON u.id=r.recipient_id WHERE r.id=?");
$stmt->execute([$rid]);$round=$stmt->fetch();
if(!$round){flash('danger','Round not found.');header('Location:'.APP_URL.'/dashboard/');exit;}
$uid=$_SESSION['user']['id'];
$isManager=($round['treasurer_id']==$uid||$round['collector_id']==$uid);
$pageTitle="Round {$round['round_number']} — {$round['gn']}";
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['action'])){
  $action=$_POST['action'];
  if($action==='cash'&&$isManager){
    $mid=(int)$_POST['member_id'];$amt=(float)$_POST['amount'];
    $db=db();
    $ex=$db->prepare('SELECT id,status FROM contributions WHERE round_id=? AND member_id=?');$ex->execute([$rid,$mid]);$ex=$ex->fetch();
    if($ex&&$ex['status']==='CONFIRMED'){flash('warning','Already contributed.');}
    else{
      if($ex){$db->prepare("UPDATE contributions SET amount=?,method='CASH',status='CONFIRMED',confirmed_at=NOW() WHERE id=?")->execute([$amt,$ex['id']]);confirm_contribution($ex['id']);}
      else{$db->prepare("INSERT INTO contributions(round_id,member_id,amount,method,status,confirmed_at)VALUES(?,?,?,'CASH','CONFIRMED',NOW())")->execute([$rid,$mid,$amt]);confirm_contribution((int)$db->lastInsertId());}
      flash('success','Cash contribution recorded.');
      // Send SMS notification
      $notifyM = db()->prepare('SELECT phone,full_name FROM users WHERE id=?');
      $notifyM->execute([$mid]); $notifyM=$notifyM->fetch();
      if($notifyM){ send_sms($notifyM['phone'],"Dear {$notifyM['full_name']}, your Susu contribution of GHS {$amt} for {$round['gn']} Round {$round['round_number']} has been recorded. Thank you! - Susu Connect"); }
    }
    header('Location:'.$_SERVER['REQUEST_URI']);exit;
  }
  if($action==='payout'&&$isManager&&$round['status']==='CLOSED'){
    $db=db();
    $total=$db->prepare("SELECT COALESCE(SUM(amount),0) FROM contributions WHERE round_id=? AND status='CONFIRMED'");$total->execute([$rid]);$total=$total->fetchColumn();
    $pe=$db->prepare('SELECT id FROM payouts WHERE round_id=?');$pe->execute([$rid]);$pe=$pe->fetch();
    if(!$pe){$db->prepare("INSERT INTO payouts(round_id,recipient_id,amount,status)VALUES(?,?,?,'PENDING')")->execute([$rid,$round['recipient_id'],$total]);$pid=(int)$db->lastInsertId();}
    else{$pid=$pe['id'];}
    require_once '../momo/MomoService.php';
    try{
      $momo=new MomoDisbursementService();
      $res=$momo->transfer($total,$round['rp'],(string)$pid,"Susu payout R{$round['round_number']}");
      $db->prepare("UPDATE payouts SET status='PROCESSING',momo_reference=? WHERE id=?")->execute([$res['reference_id'],$pid]);
      flash('success',money($total)." payout sent to {$round['rn']}. Awaiting MoMo confirmation.");
    }catch(Exception $e){
      flash('warning',"Payout recorded but MoMo failed: {$e->getMessage()}. Mark complete manually after cash payment.");
    }
    header('Location:'.$_SERVER['REQUEST_URI']);exit;
  }
  if($action==='warn'&&$isManager){
    $mid=(int)$_POST['member_id'];
    issue_warning($mid, $rid, $round['group_id'], 'MEDIUM', "Missed contribution for Round {$round['round_number']}", $uid);
    audit('ISSUE_WARNING', "Issued warning to user $mid for Round {$round['round_number']}");
    // Recalculate trust score
    if(function_exists('calculate_trust_score')) calculate_trust_score($mid);
    // SMS the member
    $w=db()->prepare('SELECT phone,full_name FROM users WHERE id=?');$w->execute([$mid]);$w=$w->fetch();
    if($w){ send_sms($w['phone'], "Dear {$w['full_name']}, you have received a warning for missing your Susu contribution in {$round['gn']} Round {$round['round_number']}. Pay now to restore your trust score. - Susu Connect"); }
    flash('warning','Warning issued. Member trust score has been updated.');
    header('Location:'.$_SERVER['REQUEST_URI']);exit;
  }
  if($action==='complete'&&$isManager){
    $pid=(int)$_POST['payout_id'];complete_payout($pid);
    flash('success','Payout marked as completed. Next round is now open.');
    header('Location:'.$_SERVER['REQUEST_URI']);exit;
  }
}
require '../includes/header.php';
$contribs=db()->prepare("SELECT c.*,u.full_name,u.phone FROM contributions c JOIN users u ON u.id=c.member_id WHERE c.round_id=? ORDER BY u.full_name");
$contribs->execute([$rid]);$contribs=$contribs->fetchAll();
$confIds=array_column(array_filter($contribs,fn($c)=>$c['status']==='CONFIRMED'),'member_id');
$collected=array_sum(array_column(array_filter($contribs,fn($c)=>$c['status']==='CONFIRMED'),'amount'));
$expected=$round['contribution_amount']*$round['total_rounds'];
$pct=$expected>0?min(100,round($collected/$expected*100)):0;
$allM=db()->prepare("SELECT u.id,u.full_name,u.phone FROM memberships m JOIN users u ON u.id=m.user_id JOIN cycles cy ON cy.group_id=m.group_id WHERE cy.id=(SELECT cycle_id FROM rounds WHERE id=?) AND m.is_active=1 ORDER BY u.full_name");
$allM->execute([$rid]);$allM=$allM->fetchAll();
$defaulters=array_filter($allM,fn($m)=>!in_array($m['id'],$confIds));
$payout=db()->prepare('SELECT * FROM payouts WHERE round_id=?');$payout->execute([$rid]);$payout=$payout->fetch();
$myContrib=array_filter($contribs,fn($c)=>$c['member_id']==$uid);$myContrib=reset($myContrib);
?>
<nav style="font-size:13px;color:var(--n400);margin-bottom:16px">
  <a href="<?=APP_URL?>/groups/detail.php?id=<?=$round['group_id']?>" style="color:var(--g600)"><?=e($round['gn'])?></a>
  <span class="mx-2"> / </span> Round <?=$round['round_number']?>
</nav>

<div class="page-header fu1">
  <div>
    <h1>Round <?=$round['round_number']?> of <?=$round['total_rounds']?></h1>
    <p>Cycle #<?=$round['cycle_number']?> · Recipient: <strong><?=e($round['rn'])?></strong> · Due <?=e($round['due_date'])?></p>
  </div>
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <span class="badge badge-<?=strtolower($round['status'])?>" style="font-size:12px"><?=e($round['status'])?></span>
    <?php if($isManager&&$round['status']==='CLOSED'&&(!$payout||$payout['status']==='PENDING')): ?>
    <button type="button" class="btn btn-primary" onclick="openModal('pinModal')">
      <i class="bi bi-send-fill"></i>Send Payout <?=money($collected)?>
    </button>
    <!-- PIN Modal -->
    <div class="modal-backdrop" id="pinModal">
      <div class="modal">
        <div class="modal-header">
          <div class="modal-title"><i class="bi bi-shield-lock" style="color:var(--g600)"></i> Confirm Payout</div>
          <button class="modal-close" onclick="closeModal('pinModal')">&times;</button>
        </div>
        <div class="modal-body">
          <p class="text-sm text-muted mb-16">You are about to send <strong><?=money($collected)?></strong> to <strong><?=e($round['rn'])?></strong>. Enter your 4-digit PIN to confirm.</p>
          <form method="post" id="payoutForm">
            <input type="hidden" name="action" value="payout">
            <div class="form-group">
              <label class="form-label">Treasurer PIN <span class="req">*</span></label>
              <input type="password" name="payout_pin" class="form-control" maxlength="4" placeholder="Enter 4-digit PIN" pattern="[0-9]{4}" inputmode="numeric" required style="letter-spacing:8px;font-size:20px;text-align:center">
              <p class="form-hint">If you have not set a PIN, leave blank and click Confirm.</p>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button class="btn btn-ghost" onclick="closeModal('pinModal')">Cancel</button>
          <button class="btn btn-primary" onclick="document.getElementById('payoutForm').submit()"><i class="bi bi-send-fill"></i>Confirm Payout</button>
        </div>
      </div>
    </div>
    <?php endif; ?>
    <?php if($isManager&&$payout&&$payout['status']==='PROCESSING'): ?>
    <form method="post" style="margin:0">
      <input type="hidden" name="action" value="complete">
      <input type="hidden" name="payout_id" value="<?=$payout['id']?>">
      <button type="submit" class="btn btn-outline btn-sm" data-confirm="Mark payout as completed?">
        <i class="bi bi-check-circle"></i>Mark Complete
      </button>
    </form>
    <?php endif; ?>
  </div>
</div>

<div class="stats-grid fu2">
  <div class="stat-card green"><div class="stat-icon green"><i class="bi bi-cash"></i></div><div><div class="stat-label">Collected</div><div class="stat-value" style="font-size:20px"><?=money($collected)?></div><div class="progress" style="width:90px"><div class="progress-bar" style="width:<?=$pct?>%"></div></div></div></div>
  <div class="stat-card blue"><div class="stat-icon blue"><i class="bi bi-bullseye"></i></div><div><div class="stat-label">Expected</div><div class="stat-value" style="font-size:20px"><?=money($expected)?></div><div class="stat-sub"><?=$pct?>% collected</div></div></div>
  <div class="stat-card <?=count($defaulters)?'red':'green'?>"><div class="stat-icon <?=count($defaulters)?'red':'green'?>"><i class="bi bi-<?=count($defaulters)?'person-exclamation':'person-check'?>"></i></div><div><div class="stat-label">Defaulters</div><div class="stat-value"><?=count($defaulters)?></div><div class="stat-sub"><?=count($defaulters)?'yet to contribute':'all paid'?></div></div></div>
</div>

<div class="g75 fu3">
  <div>
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="bi bi-list-check"></i>Contributions (<?=count($contribs)?>)</div>
        <?php if($isManager): ?>
        <button class="btn btn-ghost btn-sm" onclick="window.print()"><i class="bi bi-printer"></i>Print</button>
        <?php endif; ?>
      </div>
      <?php if($contribs): ?>
      <div class="tbl-wrap"><table class="data-table">
        <thead><tr><th>Member</th><th>Amount</th><th>Method</th><th>Status</th><th>Time</th><th></th></tr></thead>
        <tbody>
        <?php foreach($contribs as $c): ?>
        <tr>
          <td><div class="fw7"><?=e($c['full_name'])?></div><div class="text-xs text-muted"><?=e($c['phone'])?></div></td>
          <td class="fw7"><?=money($c['amount'])?></td>
          <td><span class="net net-<?=strtolower($c['method'])?>"><?=e($c['method'])?></span></td>
          <td><span class="badge badge-<?=strtolower($c['status'])?>"><?=e($c['status'])?></span></td>
          <td class="text-xs text-muted"><?=$c['confirmed_at']?date('M j, g:ia',strtotime($c['confirmed_at'])):'—'?></td>
          <td>
            <?php if($c['status']==='CONFIRMED'): ?>
              <a href="<?=APP_URL?>/receipts/view.php?id=<?=$c['id']?>" target="_blank" class="btn btn-sm btn-outline" style="font-size:11px;padding:5px 10px"><i class="bi bi-receipt"></i>Receipt</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php else: ?><div class="empty-state"><div class="empty-icon"><i class="bi bi-inbox"></i></div><p>No contributions yet.</p></div><?php endif; ?>
    </div>
  </div>

  <div style="display:flex;flex-direction:column;gap:16px">
    <?php if($round['status']==='OPEN'): ?>
    <!-- Round is OPEN — anyone can pay -->
    <div class="alert alert-info mb-16">
      <i class="bi bi-info-circle-fill"></i>
      <div>
        <strong>Round is OPEN!</strong> All <?=count($allM)?> members can contribute now. Whoever pays first will be marked confirmed. Round will close automatically when everyone has paid.
      </div>
    </div>

    <!-- Pay Your Own Contribution -->
    <?php
    // Check if current user is a member of this group
    $myMembership = db()->prepare("SELECT 1 FROM memberships m JOIN cycles cy ON cy.group_id=m.group_id WHERE cy.id=(SELECT cycle_id FROM rounds WHERE id=?) AND m.user_id=? AND m.is_active=1");
    $myMembership->execute([$rid, $uid]);
    $iAmMember = (bool)$myMembership->fetch();
    ?>
    <?php if($iAmMember): ?>
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="bi bi-phone"></i>Pay Your Contribution</div></div>
      <div class="card-body">
        <?php if($myContrib&&$myContrib['status']==='CONFIRMED'): ?>
        <div class="alert alert-success" style="margin-bottom:0"><i class="bi bi-check-circle-fill"></i><span>You have already contributed <?=money($myContrib['amount'])?> for this round. Thank you!</span></div>
        <?php else: ?>
        <p class="text-sm text-muted mb-16">Pay <strong><?=money($round['contribution_amount'])?></strong> from your MoMo wallet (<?=e($_SESSION['user']['phone'])?>).</p>
        <form method="post" action="<?=APP_URL?>/momo/contribute.php">
          <input type="hidden" name="round_id" value="<?=$rid?>">
          <button type="submit" class="btn btn-primary btn-full"><i class="bi bi-phone-fill"></i>Pay with MoMo</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if($isManager): ?>
    <!-- Record cash -->
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="bi bi-cash"></i>Record Cash Payment</div></div>
      <div class="card-body">
        <form method="post">
          <input type="hidden" name="action" value="cash">
          <div class="form-group">
            <label class="form-label">Member</label>
            <select name="member_id" class="form-select" required>
              <option value="">Select member...</option>
              <?php foreach($defaulters as $m): ?>
              <option value="<?=$m['id']?>"><?=e($m['full_name'])?> (<?=e($m['phone'])?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Amount (GHS)</label>
            <div class="input-group"><span class="input-pfx">GHS</span><input type="number" name="amount" step="0.01" value="<?=$round['contribution_amount']?>" class="form-control" required></div>
          </div>
          <button type="submit" class="btn btn-outline btn-full"><i class="bi bi-cash"></i>Record Cash</button>
        </form>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Defaulters with quick actions -->
    <?php if($defaulters): ?>
    <div class="card">
      <div class="card-header">
        <div class="card-title" style="color:var(--red)"><i class="bi bi-exclamation-triangle"></i>Defaulters (<?=count($defaulters)?>)</div>
        <span class="text-xs text-muted">Click to record payment</span>
      </div>
      <?php foreach($defaulters as $m): ?>
      <div class="member-item" style="padding:13px 16px;display:flex;align-items:center;gap:10px">
        <div style="width:34px;height:34px;border-radius:50%;background:var(--red2);color:var(--red);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;flex-shrink:0">
          <?=strtoupper(substr($m['full_name'],0,1))?>
        </div>
        <div class="m-info" style="flex:1;min-width:0">
          <div class="m-name"><?=e($m['full_name'])?></div>
          <div class="m-phone"><?=e($m['phone'])?></div>
        </div>
        <?php if($isManager): ?>
          <!-- Quick MoMo pay -->
          <form method="post" action="<?=APP_URL?>/momo/contribute.php" style="margin:0">
            <input type="hidden" name="round_id" value="<?=$rid?>">
            <input type="hidden" name="member_id" value="<?=$m['id']?>">
            <button type="submit" class="btn btn-sm btn-outline" style="padding:5px 10px;font-size:11px" data-confirm="Send MoMo prompt to <?=e($m['full_name'])?>?">
              <i class="bi bi-phone"></i> MoMo
            </button>
          </form>
          <!-- Quick cash record -->
          <form method="post" style="margin:0">
            <input type="hidden" name="action" value="cash">
            <input type="hidden" name="member_id" value="<?=$m['id']?>">
            <input type="hidden" name="amount" value="<?=$round['contribution_amount']?>">
            <button type="submit" class="btn btn-primary btn-sm" style="padding:5px 10px;font-size:11px" data-confirm="Record GHS <?=$round['contribution_amount']?> cash from <?=e($m['full_name'])?>?">
              <i class="bi bi-cash"></i> Cash
            </button>
          </form>
          <!-- Issue warning -->
          <form method="post" style="margin:0">
            <input type="hidden" name="action" value="warn">
            <input type="hidden" name="member_id" value="<?=$m['id']?>">
            <button type="submit" class="btn btn-sm" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;padding:5px 10px;font-size:11px" data-confirm="Issue warning to <?=e($m['full_name'])?>? This reduces their trust score.">
              <i class="bi bi-exclamation-triangle"></i> Warn
            </button>
          </form>
        <?php else: ?>
          <span class="badge badge-pending">Pending</span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php if($isManager): ?>
      <div style="padding:12px 16px;background:var(--g50);border-top:1px solid var(--g100);text-align:center;font-size:12px;color:var(--g700)">
        <i class="bi bi-info-circle"></i> All members can pay simultaneously when round is OPEN
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Payout status -->
    <?php if($payout): ?>
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="bi bi-send"></i>Payout Status</div></div>
      <div class="card-body">
        <div style="display:flex;flex-direction:column;gap:12px">
          <div style="display:flex;justify-content:space-between;padding-bottom:10px;border-bottom:1px solid var(--n100)"><span class="text-muted text-sm">Amount</span><span class="fw7"><?=money($payout['amount'])?></span></div>
          <div style="display:flex;justify-content:space-between;padding-bottom:10px;border-bottom:1px solid var(--n100)"><span class="text-muted text-sm">Status</span><span class="badge badge-<?=strtolower($payout['status'])?>"><?=e($payout['status'])?></span></div>
          <div style="display:flex;justify-content:space-between"><span class="text-muted text-sm">Recipient</span><span class="fw7"><?=e($round['rn'])?></span></div>
          <?php if($payout['momo_reference']): ?>
          <div style="display:flex;justify-content:space-between"><span class="text-muted text-sm">Reference</span><code style="font-size:11px;color:var(--g700)"><?=e(substr($payout['momo_reference'],0,20))?>...</code></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require '../includes/footer.php'; ?>
