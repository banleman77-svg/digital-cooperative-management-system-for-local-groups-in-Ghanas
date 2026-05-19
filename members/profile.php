<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__."/../config/db.php";
$pageTitle = 'My Profile';
require '../includes/header.php';

$uid = $user['id'];
$stmt = db()->prepare('SELECT * FROM users WHERE id=?');
$stmt->execute([$uid]);
$profile = $stmt->fetch();

$errors = []; $success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $language  = $_POST['language'] ?? 'en';
    $new_pass  = $_POST['new_password'] ?? '';
    $conf_pass = $_POST['confirm_password'] ?? '';

    if (!$full_name) $errors[] = 'Full name is required.';

    if ($new_pass) {
        if (strlen($new_pass) < 6) $errors[] = 'New password must be at least 6 characters.';
        if ($new_pass !== $conf_pass) $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        if ($new_pass) {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            db()->prepare('UPDATE users SET full_name=?, email=?, password=? WHERE id=?')->execute([$full_name, $email, $hash, $uid]);
        } else {
            db()->prepare('UPDATE users SET full_name=?, email=? WHERE id=?')->execute([$full_name, $email, $uid]);
        }
        $_SESSION['user']['full_name'] = $full_name;
        flash('success', 'Profile updated successfully.');
        header('Location: ' . APP_URL . '/members/profile.php');
        exit;
    }
}

// Stats
$stmt = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM contributions WHERE member_id=? AND status='CONFIRMED'"); $stmt->execute([$uid]); $totalIn = $stmt->fetchColumn();
$stmt = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM payouts WHERE recipient_id=? AND status='COMPLETED'"); $stmt->execute([$uid]); $totalOut = $stmt->fetchColumn();
$stmt = db()->prepare("SELECT COUNT(*) FROM memberships WHERE user_id=? AND is_active=1"); $stmt->execute([$uid]); $groupCount = $stmt->fetchColumn();
?>

<div class="page-header fade-up">
  <div>
    <h1>My Profile</h1>
    <p>Manage your account details and security settings.</p>
  </div>
</div>

<?php foreach($errors as $err): ?>
  <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i><span><?= e($err) ?></span><button class="alert-close">&times;</button></div>
<?php endforeach; ?>

<div class="g2 fade-up-2">
  <!-- Profile card -->
  <div class="card">
    <div class="card-header">
      <div class="card-header-title"><i class="bi bi-person-circle"></i> Account Details</div>
    </div>
    <div class="card-body">
      <!-- Avatar -->
      <div style="text-align:center;margin-bottom:24px">
        <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--g400),var(--g700));display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:800;color:#fff;margin:0 auto;font-family:var(--fh);box-shadow:0 4px 16px rgba(34,130,68,.3)">
          <?= strtoupper(substr($profile['full_name'],0,1)) ?>
        </div>
        <div style="margin-top:12px">
          <div class="fw7" style="font-size:17px"><?= e($profile['full_name']) ?></div>
          <div class="text-muted text-sm"><?= e($profile['phone']) ?></div>
          <div style="margin-top:8px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap">
            <span class="badge badge-active"><?= e($profile['role']) ?></span>
            <span class="net net-<?= strtolower($profile['network']) ?>"><?= e($profile['network']) ?></span>
          </div>
        </div>
      </div>

      <!-- Info rows -->
      <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:20px">
        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--n100)">
          <span class="text-muted text-sm">Member Code</span>
          <code style="background:var(--g50);color:var(--g700);padding:2px 8px;border-radius:5px;font-size:12px"><?= e($profile['member_code']) ?></code>
        </div>
        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--n100)">
          <span class="text-muted text-sm">Phone</span>
          <span class="fw7"><?= e($profile['phone']) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--n100)">
          <span class="text-muted text-sm">Network</span>
          <span class="net net-<?= strtolower($profile['network']) ?>"><?= e($profile['network']) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--n100)">
          <span class="text-muted text-sm">Joined</span>
          <span class="fw7"><?= date('M j, Y', strtotime($profile['created_at'])) ?></span>
        </div>
      </div>

      <!-- Mini stats -->
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
        <div style="background:var(--g50);border-radius:10px;padding:12px;text-align:center">
          <div class="fw7" style="color:var(--g700)"><?= money($totalIn) ?></div>
          <div class="text-muted text-sm">Paid in</div>
        </div>
        <div style="background:#fffaf0;border-radius:10px;padding:12px;text-align:center">
          <div class="fw7" style="color:#c05621"><?= money($totalOut) ?></div>
          <div class="text-muted text-sm">Received</div>
        </div>
        <div style="background:var(--blue-light);border-radius:10px;padding:12px;text-align:center">
          <div class="fw7" style="color:var(--blue)"><?= $groupCount ?></div>
          <div class="text-muted text-sm">Groups</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit form -->
  <div class="card">
    <div class="card-header">
      <div class="card-header-title"><i class="bi bi-pencil"></i> Edit Profile</div>
    </div>
    <div class="card-body">
      <form method="post">
        <div class="form-group">
          <label class="form-label">Full Name <span class="req">*</span></label>
          <input type="text" name="full_name" class="form-control" value="<?= e($profile['full_name']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-control" value="<?= e($profile['email'] ?? '') ?>" placeholder="Optional">
        </div>
        <div class="form-group">
          <label class="form-label">Phone Number</label>
          <input type="text" class="form-control" value="<?= e($profile['phone']) ?>" disabled style="background:var(--n50);color:var(--n500)">
          <p class="form-hint">Phone number cannot be changed as it is linked to your MoMo wallet.</p>
        </div>

        <div style="background:var(--n50);border-radius:var(--r-md);padding:16px;margin-bottom:18px">
          <div class="fw7 mb-8" style="font-size:13px">Change Password</div>
          <div class="form-group mb-8">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current">
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password">
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-full">
          <i class="bi bi-check-lg"></i> Save Changes
        </button>
      </form>
    </div>
  </div>
</div>

<?php require '../includes/footer.php'; ?>
