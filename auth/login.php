<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/../config/db.php';
if(isset($_SESSION['user'])){header('Location:'.APP_URL.'/dashboard/');exit;}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $phone=trim($_POST['phone']??'');$password=$_POST['password']??'';
  try{
    $phone=normalize_phone($phone);
    $stmt=db()->prepare('SELECT * FROM users WHERE phone=? AND is_active=1');
    $stmt->execute([$phone]);$u=$stmt->fetch();
    if($u&&password_verify($password,$u['password'])){
      $_SESSION['user']=['id'=>$u['id'],'phone'=>$u['phone'],'full_name'=>$u['full_name'],'role'=>$u['role'],'network'=>$u['network'],'member_code'=>$u['member_code']];
      header('Location:'.APP_URL.'/dashboard/');exit;
    }
    $error='Invalid phone number or password. Please try again.';
  }catch(InvalidArgumentException $e){$error='Please enter a valid Ghana phone number (e.g. 0244 123 456).';}
}
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign in — <?=APP_NAME?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?=APP_URL?>/assets/css/style.css">
</head><body>
<div class="auth-wrap">
  <div class="auth-left">
    <div>
      <div style="display:flex;align-items:center;gap:11px">
        <img src="<?=APP_URL?>/assets/images/logo.png" alt="<?=APP_NAME?>" style="width:44px;height:44px;border-radius:11px;background:#fff;padding:3px;object-fit:contain">
        <div><div style="color:#fff;font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;font-weight:800"><?=APP_NAME?></div><div style="color:rgba(255,255,255,.35);font-size:10.5px">Digital Cooperative</div></div>
      </div>
      <div class="auth-headline">Save together,<br>grow together.</div>
      <div class="auth-sub">The modern way to manage your Susu savings group — transparent, automated, and always on time.</div>
      <div class="auth-features">
        <?php $feats=[['bi-phone','Pay contributions via MTN MoMo'],['bi-arrow-repeat','Automatic rotation & payout scheduling'],['bi-shield-check','Full audit trail for every transaction'],['bi-telephone','USSD access for any feature phone']]; foreach($feats as $f): ?>
        <div class="auth-feat"><div class="auth-feat-icon"><i class="bi <?=$f[0]?>"></i></div><span class="auth-feat-text"><?=$f[1]?></span></div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="auth-footer-text">Capstone Project · Ghana <?=date('Y')?></div>
  </div>
  <div class="auth-right">
    <div class="auth-card">
      <div class="auth-card-title">Welcome back</div>
      <div class="auth-card-sub">Sign in with your registered phone number.</div>
      <?php if($error): ?>
      <div class="alert alert-danger" style="margin-bottom:20px"><i class="bi bi-exclamation-triangle-fill"></i><span><?=e($error)?></span></div>
      <?php endif; ?>
      <form method="post">
        <div class="form-group">
          <label class="form-label">Phone Number</label>
          <div class="input-group"><span class="input-pfx">🇬🇭</span><input type="tel" name="phone" class="form-control" placeholder="0244 123 456" value="<?=e($_POST['phone']??'')?>" required></div>
        </div>
        <div class="form-group" style="margin-bottom:24px">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn btn-primary btn-full btn-lg"><i class="bi bi-arrow-right-circle-fill"></i>Sign In</button>
      </form>
      <div class="auth-divider"><span>New to <?=APP_NAME?>?</span></div>
      <div style="text-align:center"><a href="<?=APP_URL?>/auth/signup.php" class="auth-link">Create a free account</a></div>
    </div>
  </div>
</div>
<script src="<?=APP_URL?>/assets/js/app.js"></script>
</body></html>
