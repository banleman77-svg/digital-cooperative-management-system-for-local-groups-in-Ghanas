<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/../config/db.php';
if(isset($_SESSION['user'])){header('Location:'.APP_URL.'/dashboard/');exit;}
$errors=[];$old=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
  $old=$_POST;
  $fn=trim($_POST['full_name']??'');$phone=trim($_POST['phone']??'');
  $role=$_POST['role']??'MEMBER';$pw=$_POST['password']??'';$pw2=$_POST['password2']??'';
  if(!$fn)$errors[]='Full name is required.';
  if(strlen($pw)<6)$errors[]='Password must be at least 6 characters.';
  if($pw!==$pw2)$errors[]='Passwords do not match.';
  try{$phone=normalize_phone($phone);}catch(InvalidArgumentException){$errors[]='Enter a valid Ghana phone number (e.g. 0244 123 456).';}
  if(empty($errors)){
    $s=db()->prepare('SELECT id FROM users WHERE phone=?');$s->execute([$phone]);
    if($s->fetch())$errors[]='An account with this phone number already exists.';
  }
  if(empty($errors)){
    $hash=password_hash($pw,PASSWORD_DEFAULT);$code=gen_member_code();$net=detect_network($phone);
    db()->prepare('INSERT INTO users(phone,full_name,password,role,network,member_code)VALUES(?,?,?,?,?,?)')->execute([$phone,$fn,$hash,$role,$net,$code]);
    $id=db()->lastInsertId();
    $_SESSION['user']=['id'=>$id,'phone'=>$phone,'full_name'=>$fn,'role'=>$role,'network'=>$net,'member_code'=>$code];
    flash('success',"Welcome, {$fn}! Your member code is {$code}.");
    header('Location:'.APP_URL.'/dashboard/');exit;
  }
}
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Create account — <?=APP_NAME?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?=APP_URL?>/assets/css/style.css">
</head><body>
<div class="auth-wrap">
  <div class="auth-left">
    <div>
      <div style="display:flex;align-items:center;gap:11px">
        <img src="<?=APP_URL?>/assets/images/logo.png" alt="<?=APP_NAME?>" style="width:44px;height:44px;border-radius:11px;background:#fff;padding:3px;object-fit:contain">
        <div><div style="color:#fff;font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;font-weight:800"><?=APP_NAME?></div></div>
      </div>
      <div class="auth-headline">Join your<br>Susu group.</div>
      <div class="auth-sub">Register with your Ghana MoMo number and start saving with your group today.</div>
    </div>
    <div class="auth-footer-text">Capstone Project · Ghana</div>
  </div>
  <div class="auth-right">
    <div class="auth-card">
      <div class="auth-card-title">Create your account</div>
      <div class="auth-card-sub">Join and start managing your Susu savings group.</div>
      <?php foreach($errors as $e): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><span><?=e($e)?></span></div><?php endforeach; ?>
      <form method="post">
        <div class="form-group">
          <label class="form-label">Full Name <span class="req">*</span></label>
          <input type="text" name="full_name" class="form-control" value="<?=e($old['full_name']??'')?>" placeholder="e.g. Ama Owusu" required>
        </div>
        <div class="form-group">
          <label class="form-label">Phone Number (MoMo) <span class="req">*</span></label>
          <div class="input-group"><span class="input-pfx">🇬🇭</span><input type="tel" name="phone" class="form-control" value="<?=e($old['phone']??'')?>" placeholder="0244 123 456" required></div>
          <p class="form-hint">This must be your active Mobile Money number.</p>
        </div>
        <div class="form-group">
          <label class="form-label">I am a&hellip;</label>
          <select name="role" class="form-select">
            <option value="MEMBER" <?=($old['role']??'')==='MEMBER'?'selected':''?>>Group Member</option>
            <option value="TREASURER" <?=($old['role']??'')==='TREASURER'?'selected':''?>>Group Treasurer</option>
            <option value="COLLECTOR" <?=($old['role']??'')==='COLLECTOR'?'selected':''?>>Susu Collector</option>
            <option value="ADMIN" <?=($old['role']??'')==='ADMIN'?'selected':''?>>System Administrator</option>
          </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:24px">
          <div class="form-group" style="margin:0">
            <label class="form-label">Password <span class="req">*</span></label>
            <input type="password" name="password" class="form-control" placeholder="Min. 6 chars" required>
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="password2" class="form-control" placeholder="Repeat" required>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-full btn-lg"><i class="bi bi-person-check-fill"></i>Create Account</button>
      </form>
      <div class="auth-divider"><span>Already have an account?</span></div>
      <div style="text-align:center"><a href="<?=APP_URL?>/auth/login.php" class="auth-link">Sign in instead</a></div>
    </div>
  </div>
</div>
<script src="<?=APP_URL?>/assets/js/app.js"></script>
</body></html>
