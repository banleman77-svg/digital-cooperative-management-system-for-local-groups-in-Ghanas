<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/../config/db.php';

$token = $_GET['token'] ?? '';
$stmt  = db()->prepare("SELECT * FROM groups_ WHERE invite_token=? AND invite_active=1 AND status='PENDING'");
$stmt->execute([$token]); $group = $stmt->fetch();
?>
<!doctype html>
<html><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Join Group — Susu Connect</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?=APP_URL?>/assets/css/style.css">
</head>
<body style="background:#f0faf3;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px">

<?php if(!$group): ?>
<div class="auth-card" style="text-align:center">
  <div style="font-size:48px;margin-bottom:16px">❌</div>
  <div class="auth-card-title">Invalid Link</div>
  <div class="auth-card-sub">This invitation link is invalid or has expired. Please ask your treasurer for a new link.</div>
  <a href="<?=APP_URL?>/auth/login.php" class="btn btn-primary btn-full mt-16">Go to Login</a>
</div>
<?php else: ?>

<?php
$mc = db()->prepare("SELECT COUNT(*) FROM memberships WHERE group_id=? AND is_active=1");
$mc->execute([$group['id']]); $mc = $mc->fetchColumn();

$errors = []; $success = false;

if($_SERVER['REQUEST_METHOD']==='POST') {
    $fn    = trim($_POST['full_name']??'');
    $phone = trim($_POST['phone']??'');
    $pw    = $_POST['password']??'';
    $pw2   = $_POST['password2']??'';

    if(!$fn) $errors[]='Full name is required.';
    if(strlen($pw)<6) $errors[]='Password must be at least 6 characters.';
    if($pw!==$pw2) $errors[]='Passwords do not match.';

    try { $phone=normalize_phone($phone); }
    catch(Exception){ $errors[]='Enter a valid Ghana phone number.'; }

    if(empty($errors)) {
        $db = db();
        $ex = $db->prepare('SELECT id FROM users WHERE phone=?');
        $ex->execute([$phone]); $ex=$ex->fetch();

        if($ex) {
            $uid = $ex['id'];
        } else {
            $hash=$pw?password_hash($pw,PASSWORD_DEFAULT):'';
            $code=gen_member_code();
            $net=detect_network($phone);
            $db->prepare('INSERT INTO users(phone,full_name,password,role,network,member_code) VALUES(?,?,?,?,?,?)')->execute([$phone,$fn,$hash,'MEMBER',$net,$code]);
            $uid=(int)$db->lastInsertId();
        }

        // Check not already member
        $alr=$db->prepare('SELECT id FROM memberships WHERE group_id=? AND user_id=?');
        $alr->execute([$group['id'],$uid]);
        if($alr->fetch()) {
            $errors[]='You are already a member of this group.';
        } else {
            $maxPos=$db->prepare('SELECT MAX(rotation_position) FROM memberships WHERE group_id=?');
            $maxPos->execute([$group['id']]); $next=($maxPos->fetchColumn()??0)+1;
            $db->prepare('INSERT INTO memberships(group_id,user_id,rotation_position) VALUES(?,?,?)')->execute([$group['id'],$uid,$next]);
            audit('JOIN_GROUP',"Joined group {$group['name']} via invite link");
            $success=true;
        }
    }
}
?>

<div class="auth-card" style="max-width:440px;width:100%">
  <?php if($success): ?>
    <div style="text-align:center">
      <div style="font-size:56px;margin-bottom:16px">🎉</div>
      <div class="auth-card-title">You're in!</div>
      <div class="auth-card-sub">You have successfully joined <strong><?=e($group['name'])?></strong>. Your treasurer will assign your rotation position.</div>
      <a href="<?=APP_URL?>/auth/login.php" class="btn btn-primary btn-full mt-16"><i class="bi bi-arrow-right-circle"></i> Login to your account</a>
    </div>
  <?php else: ?>
    <!-- Group info card -->
    <div style="background:linear-gradient(135deg,#0a3d1f,#1a6e3a);border-radius:12px;padding:16px;margin-bottom:24px;display:flex;align-items:center;gap:12px">
      <img src="<?=APP_URL?>/assets/images/logo.png" alt="Logo" style="width:44px;height:44px;border-radius:10px;background:#fff;padding:3px;object-fit:contain">
      <div>
        <div style="color:#fff;font-weight:800;font-size:15px"><?=e($group['name'])?></div>
        <div style="color:rgba(255,255,255,.6);font-size:12px"><?=e($group['code'])?> · <?=$mc?> members · <?=money($group['contribution_amount'])?>/<?=strtolower($group['frequency'])?></div>
      </div>
    </div>

    <div class="auth-card-title">Join this group</div>
    <div class="auth-card-sub">Fill in your details to join <?=e($group['name'])?></div>

    <?php foreach($errors as $err): ?>
      <div class="alert alert-danger mb-8"><i class="bi bi-exclamation-triangle-fill"></i><span><?=e($err)?></span></div>
    <?php endforeach; ?>

    <form method="post">
      <div class="form-group">
        <label class="form-label">Full Name <span class="req">*</span></label>
        <input type="text" name="full_name" class="form-control" value="<?=e($_POST['full_name']??'')?>" placeholder="e.g. Ama Owusu" required>
      </div>
      <div class="form-group">
        <label class="form-label">Phone Number (MoMo) <span class="req">*</span></label>
        <div class="input-group">
          <span class="input-pfx">🇬🇭</span>
          <input type="tel" name="phone" class="form-control" value="<?=e($_POST['phone']??'')?>" placeholder="0244 123 456" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Set Password <span class="req">*</span></label>
        <input type="password" name="password" class="form-control" placeholder="Min. 6 characters" required>
      </div>
      <div class="form-group mb-24">
        <label class="form-label">Confirm Password</label>
        <input type="password" name="password2" class="form-control" placeholder="Repeat password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-full btn-lg"><i class="bi bi-person-check-fill"></i> Join Group</button>
    </form>

    <div style="text-align:center;margin-top:16px;font-size:13px;color:var(--n400)">
      Already have an account? <a href="<?=APP_URL?>/auth/login.php" class="auth-link">Sign in</a>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<script src="<?=APP_URL?>/assets/js/app.js"></script>
</body></html>
