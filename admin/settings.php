<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/../config/db.php';
$pageTitle='System Settings';
$topbarChip='Admin';
require '../includes/header.php';

if ($user['role'] !== 'ADMIN') {
    audit('UNAUTHORIZED_ADMIN_ACCESS', 'Tried to access admin/settings.php');
    flash('danger', 'Access denied. Administrator only.');
    header('Location: ' . APP_URL . '/dashboard/');
    exit;
}

$momoOK = !empty(MOMO_COLLECTIONS_SUBSCRIPTION_KEY) && !empty(MOMO_COLLECTIONS_API_USER_ID);
$smsOK  = AT_API_KEY !== 'your-africas-talking-api-key';
?>

<div class="page-header fu1">
  <div><h1>System Settings</h1><p>Configure integrations, security, and platform behaviour.</p></div>
</div>

<div class="g2 fu2">
  <!-- MoMo Status -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-phone-fill"></i>MTN MoMo Integration</div>
      <span class="badge <?=$momoOK?'badge-active':'badge-failed'?>"><?=$momoOK?'Configured':'Not Set'?></span>
    </div>
    <div class="card-body">
      <div style="display:flex;flex-direction:column;gap:10px">
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--n100)">
          <span class="text-muted text-sm">Environment</span>
          <span class="fw7"><?=MOMO_ENVIRONMENT?></span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--n100)">
          <span class="text-muted text-sm">Currency</span>
          <span class="fw7"><?=MOMO_CURRENCY?></span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--n100)">
          <span class="text-muted text-sm">Collections API</span>
          <?php if($momoOK): ?>
            <span class="text-success fw7"><i class="bi bi-check-circle-fill"></i> Active</span>
          <?php else: ?>
            <span class="text-danger fw7"><i class="bi bi-x-circle-fill"></i> Not configured</span>
          <?php endif; ?>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0">
          <span class="text-muted text-sm">Disbursements API</span>
          <?php if(MOMO_DISBURSEMENTS_API_USER_ID): ?>
            <span class="text-success fw7"><i class="bi bi-check-circle-fill"></i> Active</span>
          <?php else: ?>
            <span class="text-danger fw7"><i class="bi bi-x-circle-fill"></i> Not configured</span>
          <?php endif; ?>
        </div>
      </div>
      <a href="<?=APP_URL?>/momo/provision.php" class="btn btn-primary btn-full mt-16"><i class="bi bi-gear"></i>Configure MoMo</a>
    </div>
  </div>

  <!-- SMS Status -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-chat-text-fill"></i>SMS Notifications</div>
      <span class="badge <?=$smsOK?'badge-active':'badge-failed'?>"><?=$smsOK?'Configured':'Not Set'?></span>
    </div>
    <div class="card-body">
      <div style="display:flex;flex-direction:column;gap:10px">
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--n100)">
          <span class="text-muted text-sm">Provider</span>
          <span class="fw7">Africa's Talking</span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--n100)">
          <span class="text-muted text-sm">Username</span>
          <span class="fw7"><?=AT_USERNAME?></span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--n100)">
          <span class="text-muted text-sm">Sender ID</span>
          <span class="fw7"><?=AT_SENDER?></span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0">
          <span class="text-muted text-sm">SMS Sent</span>
          <span class="fw7"><?=db()->query("SELECT COUNT(*) FROM sms_log WHERE status='SENT'")->fetchColumn()?></span>
        </div>
      </div>
      <?php if(!$smsOK): ?>
      <div class="alert alert-info mt-16"><i class="bi bi-info-circle"></i><span class="text-sm">Add your Africa's Talking API key in <code>config/db.php</code> to enable SMS.</span></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="g2 fu3 mt-24">
  <!-- App info -->
  <div class="card">
    <div class="card-header"><div class="card-title"><i class="bi bi-info-circle"></i>System Information</div></div>
    <div class="card-body">
      <div style="display:flex;flex-direction:column;gap:10px">
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--n100)"><span class="text-muted text-sm">App Name</span><span class="fw7"><?=APP_NAME?></span></div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--n100)"><span class="text-muted text-sm">App URL</span><span class="fw7" style="font-size:12px"><?=APP_URL?></span></div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--n100)"><span class="text-muted text-sm">PHP Version</span><span class="fw7"><?=PHP_VERSION?></span></div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--n100)"><span class="text-muted text-sm">Database</span><span class="fw7"><?=DB_NAME?></span></div>
        <div style="display:flex;justify-content:space-between;padding:8px 0"><span class="text-muted text-sm">Server</span><span class="fw7" style="font-size:12px"><?=e($_SERVER['SERVER_SOFTWARE']??'XAMPP')?></span></div>
      </div>
    </div>
  </div>

  <!-- Security & maintenance -->
  <div class="card">
    <div class="card-header"><div class="card-title"><i class="bi bi-shield-lock-fill"></i>Security & Maintenance</div></div>
    <div class="card-body">
      <div style="display:flex;flex-direction:column;gap:10px">
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--n100)"><span class="text-muted text-sm">Total Audit Entries</span><span class="fw7"><?=db()->query("SELECT COUNT(*) FROM audit_log")->fetchColumn()?></span></div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--n100)"><span class="text-muted text-sm">Failed Logins (7d)</span><span class="fw7"><?=db()->query("SELECT COUNT(*) FROM audit_log WHERE action='FAILED_LOGIN' AND created_at>DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetchColumn()?></span></div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--n100)"><span class="text-muted text-sm">Suspended Users</span><span class="fw7"><?=db()->query("SELECT COUNT(*) FROM users WHERE is_active=0")->fetchColumn()?></span></div>
        <div style="display:flex;justify-content:space-between;padding:8px 0"><span class="text-muted text-sm">Locked Groups</span><span class="fw7"><?=db()->query("SELECT COUNT(*) FROM groups_ WHERE status='LOCKED'")->fetchColumn()?></span></div>
      </div>
      <a href="<?=APP_URL?>/audit/" class="btn btn-outline btn-full mt-16"><i class="bi bi-journal-check"></i>View Full Audit Log</a>
    </div>
  </div>
</div>

<?php require '../includes/footer.php'; ?>
