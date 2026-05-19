<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/../config/db.php';
require_login();
$user    = current_user();
$flashes = get_flashes();
$curFile = basename($_SERVER['PHP_SELF']);
$curDir  = basename(dirname($_SERVER['PHP_SELF']));

// Dark mode
$darkMode = $_SESSION['dark_mode'] ?? false;
if(isset($_GET['toggle_dark'])){$_SESSION['dark_mode']=!$darkMode;header('Location:'.$_SERVER['HTTP_REFERER']??APP_URL.'/dashboard/');exit;}

// Language toggle
if(isset($_GET['lang'])){$_SESSION['lang']=$_GET['lang']==='tw'?'tw':'en';header('Location:'.$_SERVER['HTTP_REFERER']??APP_URL.'/dashboard/');exit;}

function nav(string $d,string $f=''):string{global $curDir,$curFile;return(!$f&&$curDir===$d)||($f&&$curFile===$f)?'active':'';}
?>
<!doctype html>
<html lang="en" <?=$darkMode?'data-theme="dark"':''?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title><?=e($pageTitle??'Dashboard')?> — <?=APP_NAME?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="icon" type="image/png" href="<?=APP_URL?>/assets/images/logo.png">
<link rel="stylesheet" href="<?=APP_URL?>/assets/css/style.css">
<?php if($darkMode): ?>
<style>
  :root{--n50:#1a1a2e;--n100:#16213e;--n200:#0f3460;--n300:#394867;--n400:#9ca3af;--n500:#d1d5db;--n700:#f3f4f6;--n900:#f9fafb;--white:#1e293b;--g50:#0a2016;--g100:#0d2d1f}
  body{background:#0f172a}
  .card{background:#1e293b;border-color:#334155}
  .card-header{background:#1e293b!important;border-color:#334155!important}
  .data-table thead th{background:#0f172a;color:#94a3b8}
  .data-table tbody td{border-color:#334155;color:#e2e8f0}
  .data-table tbody tr:hover td{background:#0f172a}
  .topbar{background:#1e293b;border-color:#334155}
  .form-control,.form-select{background:#0f172a!important;border-color:#334155!important;color:#e2e8f0!important}
  .stat-card{background:#1e293b;border-color:#334155}
  .btn-ghost{background:#1e293b;border-color:#334155;color:#e2e8f0}
</style>
<?php endif; ?>
</head>
<body>

<div class="sidebar-overlay" id="overlay"></div>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <a href="<?=APP_URL?>/dashboard/" class="brand-link">
      <img src="<?=APP_URL?>/assets/images/logo.png" alt="<?=APP_NAME?>" style="width:40px;height:40px;border-radius:10px;background:#fff;padding:3px;object-fit:contain;flex-shrink:0">
      <div class="brand-text">
        <div class="name"><?=APP_NAME?></div>
        <div class="sub">Digital Cooperative</div>
      </div>
    </a>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section">
      <div class="nav-section-label">Main</div>
      <a href="<?=APP_URL?>/dashboard/" class="nav-item <?=nav('dashboard')?>"><i class="bi bi-grid-1x2-fill nav-icon"></i><span class="nav-label">Dashboard</span></a>
      <a href="<?=APP_URL?>/groups/" class="nav-item <?=nav('groups')?>"><i class="bi bi-people-fill nav-icon"></i><span class="nav-label">Groups</span></a>
    </div>

    <div class="nav-section">
      <div class="nav-section-label">Finance</div>
      <a href="<?=APP_URL?>/statements/" class="nav-item <?=nav('statements')?>"><i class="bi bi-file-text-fill nav-icon"></i><span class="nav-label">My Statement</span></a>
      <a href="<?=APP_URL?>/reports/" class="nav-item <?=nav('reports')?>"><i class="bi bi-bar-chart-line-fill nav-icon"></i><span class="nav-label">Reports</span></a>
      <a href="<?=APP_URL?>/audit/" class="nav-item <?=nav('audit')?>"><i class="bi bi-journal-check nav-icon"></i><span class="nav-label">Audit Log</span></a>
      <a href="<?=APP_URL?>/trust/" class="nav-item <?=nav('trust')?>"><i class="bi bi-shield-fill-check nav-icon"></i><span class="nav-label">Trust Score</span><span class="nav-badge">NEW</span></a>
    </div>

    <div class="nav-section">
      <div class="nav-section-label">Tools</div>
      <a href="<?=APP_URL?>/ussd/simulator.php" class="nav-item <?=nav('ussd','simulator.php')?>"><i class="bi bi-phone-fill nav-icon"></i><span class="nav-label">USSD Simulator</span></a>
      <?php if(in_array($user['role'],['ADMIN','TREASURER'])): ?>
      <a href="<?=APP_URL?>/momo/provision.php" class="nav-item <?=nav('momo','provision.php')?>"><i class="bi bi-key-fill nav-icon"></i><span class="nav-label">MoMo Setup</span></a>
      <?php endif; ?>
    </div>

    <?php if($user['role']==='ADMIN'): ?>
    <div class="nav-section">
      <div class="nav-section-label" style="color:#f5c842">👑 Administrator</div>
      <a href="<?=APP_URL?>/admin/" class="nav-item <?=nav('admin','index.php')?>"><i class="bi bi-speedometer2 nav-icon"></i><span class="nav-label">Admin Dashboard</span></a>
      <a href="<?=APP_URL?>/admin/users.php" class="nav-item <?=nav('admin','users.php')?>"><i class="bi bi-people nav-icon"></i><span class="nav-label">Manage Users</span></a>
      <a href="<?=APP_URL?>/admin/groups.php" class="nav-item <?=nav('admin','groups.php')?>"><i class="bi bi-collection nav-icon"></i><span class="nav-label">All Groups</span></a>
      <a href="<?=APP_URL?>/admin/reports.php" class="nav-item <?=nav('admin','reports.php')?>"><i class="bi bi-bar-chart nav-icon"></i><span class="nav-label">System Reports</span></a>
      <a href="<?=APP_URL?>/admin/settings.php" class="nav-item <?=nav('admin','settings.php')?>"><i class="bi bi-gear nav-icon"></i><span class="nav-label">Settings</span></a>
    </div>
    <?php endif; ?>

    <div class="nav-section">
      <div class="nav-section-label">Account</div>
      <a href="<?=APP_URL?>/members/profile.php" class="nav-item <?=nav('members')?>"><i class="bi bi-person-circle nav-icon"></i><span class="nav-label">My Profile</span></a>
      <!-- Dark mode toggle -->
      <a href="?toggle_dark=1" class="nav-item">
        <i class="bi bi-<?=$darkMode?'sun':'moon'?>-fill nav-icon"></i>
        <span class="nav-label"><?=$darkMode?'Light Mode':'Dark Mode'?></span>
      </a>
      <!-- Language toggle -->
      <a href="?lang=<?=($_SESSION['lang']??'en')==='en'?'tw':'en'?>" class="nav-item">
        <i class="bi bi-translate nav-icon"></i>
        <span class="nav-label">
          <?=($_SESSION['lang']??'en')==='en'?'Switch to Twi':'Sesa English'?>
        </span>
      </a>
    </div>
  </nav>

  <div class="sidebar-footer">
    <div class="user-pill">
      <div class="u-avatar"><?=strtoupper(substr($user['full_name'],0,1))?></div>
      <div class="u-info">
        <div class="u-name"><?=e(substr($user['full_name'],0,20))?></div>
        <div class="u-role"><?=e($user['role'])?> · <?=e($user['network'])?></div>
      </div>
      <a href="<?=APP_URL?>/auth/logout.php" class="u-logout" title="Sign out"><i class="bi bi-box-arrow-right"></i></a>
    </div>
  </div>
</aside>

<div class="main-wrap">
  <header class="topbar">
    <button class="hamburger" id="hamburger"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><?=e($pageTitle??'Dashboard')?></div>
    <?php if(!empty($topbarChip)): ?><div class="topbar-chip"><?=e($topbarChip)?></div><?php endif; ?>
    <?php foreach($topbarActions??[] as $a): ?>
      <a href="<?=e($a['href'])?>" class="btn btn-primary btn-sm"><i class="bi bi-<?=e($a['icon']??'plus-lg')?>"></i><?=e($a['label'])?></a>
    <?php endforeach; ?>
  </header>
  <main class="page-body">
    <?php foreach($flashes as $f): ?>
      <div class="alert alert-<?=e($f['type'])?>">
        <i class="bi bi-<?=$f['type']==='success'?'check-circle-fill':($f['type']==='danger'?'exclamation-triangle-fill':'info-circle-fill')?>"></i>
        <span><?=e($f['message'])?></span>
        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
      </div>
    <?php endforeach; ?>
