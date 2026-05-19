<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__."/../config/db.php";
$pageTitle = 'MoMo Setup';
$topbarChip = 'MTN MoMo';
require '../includes/header.php';
require_once '../momo/MomoService.php';

if (!in_array($user['role'], ['ADMIN', 'TREASURER'])) {
    flash('danger', 'Access denied. Treasurers only.');
    header('Location: ' . APP_URL . '/dashboard/');
    exit;
}

$result = null;
$error  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $subKey = trim($_POST['subscription_key'] ?? '');
    $product = $_POST['product'] ?? 'collections';

    if ($action === 'provision' && $subKey) {
        try {
            // Temporarily use the provided key
            if ($product === 'collections') {
                define('MOMO_COLLECTIONS_SUBSCRIPTION_KEY_TEMP', $subKey);
                $service = new class($subKey) extends MomoBaseService {
                    public function __construct($key) {
                        $this->baseUrl = MOMO_BASE_URL;
                        $this->environment = MOMO_ENVIRONMENT;
                        $this->currency = MOMO_CURRENCY;
                        $this->subscriptionKey = $key;
                        $this->apiUserId = '';
                        $this->apiKey = '';
                        $this->productPath = 'collection';
                    }
                };
            } else {
                $service = new class($subKey) extends MomoBaseService {
                    public function __construct($key) {
                        $this->baseUrl = MOMO_BASE_URL;
                        $this->environment = MOMO_ENVIRONMENT;
                        $this->currency = MOMO_CURRENCY;
                        $this->subscriptionKey = $key;
                        $this->apiUserId = '';
                        $this->apiKey = '';
                        $this->productPath = 'disbursement';
                    }
                };
            }

            $apiUserId = $service->createApiUser();
            $apiKey    = $service->generateApiKey($apiUserId);

            $result = [
                'product'     => strtoupper($product),
                'sub_key'     => $subKey,
                'api_user_id' => $apiUserId,
                'api_key'     => $apiKey,
                'env_key'     => strtoupper($product) === 'COLLECTIONS' ? 'MOMO_COLLECTIONS' : 'MOMO_DISBURSEMENTS',
            ];
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Read current config values for display
$configPath = __DIR__ . '/../config/db.php';
$configContent = file_get_contents($configPath);
$collKey = MOMO_COLLECTIONS_SUBSCRIPTION_KEY;
$disbKey = MOMO_DISBURSEMENTS_SUBSCRIPTION_KEY;
$collUser = MOMO_COLLECTIONS_API_USER_ID;
$collApiKey = MOMO_COLLECTIONS_API_KEY;
$disbUser = MOMO_DISBURSEMENTS_API_USER_ID;
$disbApiKey = MOMO_DISBURSEMENTS_API_KEY;
?>

<div class="page-header fade-up">
  <div>
    <h1>MTN MoMo Setup</h1>
    <p>Configure your MoMo API credentials for live sandbox payments.</p>
  </div>
</div>

<?php if($error): ?>
  <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i><span><?= e($error) ?></span><button class="alert-close">&times;</button></div>
<?php endif; ?>

<?php if($result): ?>
  <div class="alert alert-success">
    <i class="bi bi-check-circle"></i>
    <div>
      <strong>Credentials generated for <?= $result['product'] ?>!</strong><br>
      Copy the values below into your <code>config/db.php</code> file.
    </div>
  </div>
  <div class="card mb-24 fade-up">
    <div class="card-header">
      <div class="card-header-title"><i class="bi bi-key"></i> Generated Credentials — <?= $result['product'] ?></div>
    </div>
    <div class="card-body">
      <p style="font-size:13.5px;color:var(--n600);margin-bottom:16px">Open <code>C:\xampp\htdocs\susu_php\config\db.php</code> in Notepad and update these lines:</p>
      <pre style="background:var(--n900);color:#4ade80;padding:20px;border-radius:var(--r-md);font-size:13px;overflow-x:auto;line-height:1.8">define('<?= $result['env_key'] ?>_SUBSCRIPTION_KEY', '<?= e($result['sub_key']) ?>');
define('<?= $result['env_key'] ?>_API_USER_ID',      '<?= e($result['api_user_id']) ?>');
define('<?= $result['env_key'] ?>_API_KEY',          '<?= e($result['api_key']) ?>');</pre>
      <p style="font-size:12.5px;color:var(--n400);margin-top:12px">After saving, restart the page and test a payment.</p>
    </div>
  </div>
<?php endif; ?>

<!-- Status card -->
<div class="g2 fade-up-2 mb-24">
  <div class="card">
    <div class="card-header">
      <div class="card-header-title"><i class="bi bi-collection"></i> Collections Status</div>
      <span class="badge <?= $collUser ? 'badge-active' : 'badge-failed' ?>"><?= $collUser ? 'Configured' : 'Not configured' ?></span>
    </div>
    <div class="card-body">
      <div style="display:flex;flex-direction:column;gap:10px">
        <div style="display:flex;justify-content:space-between"><span class="text-muted text-sm">Subscription Key</span><span class="fw7" style="font-size:12px"><?= $collKey ? substr($collKey,0,12).'...' : 'Not set' ?></span></div>
        <div style="display:flex;justify-content:space-between"><span class="text-muted text-sm">API User ID</span><span class="fw7" style="font-size:12px"><?= $collUser ? substr($collUser,0,16).'...' : 'Not set' ?></span></div>
        <div style="display:flex;justify-content:space-between"><span class="text-muted text-sm">API Key</span><span class="fw7" style="font-size:12px"><?= $collApiKey ? '••••••••' : 'Not set' ?></span></div>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-header">
      <div class="card-header-title"><i class="bi bi-send"></i> Disbursements Status</div>
      <span class="badge <?= $disbUser ? 'badge-active' : 'badge-failed' ?>"><?= $disbUser ? 'Configured' : 'Not configured' ?></span>
    </div>
    <div class="card-body">
      <div style="display:flex;flex-direction:column;gap:10px">
        <div style="display:flex;justify-content:space-between"><span class="text-muted text-sm">Subscription Key</span><span class="fw7" style="font-size:12px"><?= $disbKey ? substr($disbKey,0,12).'...' : 'Not set' ?></span></div>
        <div style="display:flex;justify-content:space-between"><span class="text-muted text-sm">API User ID</span><span class="fw7" style="font-size:12px"><?= $disbUser ? substr($disbUser,0,16).'...' : 'Not set' ?></span></div>
        <div style="display:flex;justify-content:space-between"><span class="text-muted text-sm">API Key</span><span class="fw7" style="font-size:12px"><?= $disbApiKey ? '••••••••' : 'Not set' ?></span></div>
      </div>
    </div>
  </div>
</div>

<!-- Provision form -->
<div class="card fade-up-3">
  <div class="card-header">
    <div class="card-header-title"><i class="bi bi-plus-circle"></i> Generate New Credentials</div>
  </div>
  <div class="card-body">
    <div class="alert alert-info mb-16">
      <i class="bi bi-info-circle"></i>
      <div>
        <strong>Before you start:</strong> Go to <a href="https://momodeveloper.mtn.com" target="_blank" style="color:var(--blue)">momodeveloper.mtn.com</a>, sign up, then subscribe to <strong>Collections</strong> and <strong>Disbursements</strong> products. Copy your Primary Key for each product and paste it below.
      </div>
    </div>

    <form method="post">
      <input type="hidden" name="action" value="provision">
      <div class="g2">
        <div class="form-group">
          <label class="form-label">Product <span class="req">*</span></label>
          <select name="product" class="form-select">
            <option value="collections">Collections (receive payments)</option>
            <option value="disbursements">Disbursements (send payouts)</option>
          </select>
          <p class="form-hint">Provision Collections first, then Disbursements separately.</p>
        </div>
        <div class="form-group">
          <label class="form-label">Subscription Primary Key <span class="req">*</span></label>
          <input type="text" name="subscription_key" class="form-control" placeholder="Paste your Primary Key from momodeveloper.mtn.com" required>
          <p class="form-hint">Found in your profile page under the product subscription.</p>
        </div>
      </div>
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-key"></i> Generate API User & Key
      </button>
    </form>
  </div>
</div>

<!-- Step by step guide -->
<div class="card mt-24 fade-up-4">
  <div class="card-header">
    <div class="card-header-title"><i class="bi bi-list-ol"></i> Setup Guide</div>
  </div>
  <div class="card-body">
    <div style="display:flex;flex-direction:column;gap:16px">
      <?php $steps = [
        ['1','Sign up on MTN Developer Portal','Go to momodeveloper.mtn.com and create a free account with your email.'],
        ['2','Subscribe to Collections','Click Products → Collections → Subscribe → Default. Copy the Primary Key.'],
        ['3','Generate Collections credentials','Paste the Collections Primary Key above and click Generate. Copy the 3 lines into config/db.php.'],
        ['4','Subscribe to Disbursements','Click Products → Disbursements → Subscribe → Default. Copy the Primary Key.'],
        ['5','Generate Disbursements credentials','Paste the Disbursements Primary Key above and click Generate. Copy the 3 lines into config/db.php.'],
        ['6','Test a payment','Go to any round, add a Ghana test number, and click Pay with MoMo. The member will receive a prompt.'],
      ];
      foreach($steps as $s): ?>
      <div style="display:flex;gap:14px;align-items:flex-start">
        <div style="width:28px;height:28px;border-radius:50%;background:var(--g600);color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex-shrink:0;font-family:var(--fh)"><?= $s[0] ?></div>
        <div>
          <div class="fw7" style="font-size:14px;margin-bottom:3px"><?= $s[1] ?></div>
          <div class="text-muted text-sm"><?= $s[2] ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php require '../includes/footer.php'; ?>
