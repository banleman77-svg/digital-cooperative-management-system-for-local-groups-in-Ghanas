<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__."/../config/db.php";
$pageTitle = 'Add Member';
require '../includes/header.php';

$gid = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM groups_ WHERE id=? AND treasurer_id=?');
$stmt->execute([$gid, $user['id']]); $group = $stmt->fetch();
if (!$group) { flash('danger','Access denied or group not found.'); header('Location:'.APP_URL.'/groups/'); exit; }
if ($group['status'] !== 'PENDING') { flash('warning','Cannot add members after the group is activated.'); header('Location:'.APP_URL.'/groups/detail.php?id='.$gid); exit; }

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $phone     = trim($_POST['phone'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    try {
        $phone = normalize_phone($phone);
    } catch (InvalidArgumentException $e) {
        flash('danger', 'Invalid Ghana phone number.'); header('Location:'.$_SERVER['REQUEST_URI']); exit;
    }

    $db = db();
    $existing = $db->prepare('SELECT id FROM users WHERE phone=?');
    $existing->execute([$phone]); $existingUser = $existing->fetch();

    if ($existingUser) {
        $uid = $existingUser['id'];
    } else {
        // Auto-create account for the member
        $code    = gen_member_code();
        $network = detect_network($phone);
        $hash    = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT); // random temp password
        $db->prepare('INSERT INTO users (phone,full_name,password,role,network,member_code) VALUES (?,?,?,?,?,?)')->execute([$phone, $full_name ?: $phone, $hash, 'MEMBER', $network, $code]);
        $uid = $db->lastInsertId();
    }

    $already = $db->prepare('SELECT id FROM memberships WHERE group_id=? AND user_id=?');
    $already->execute([$gid, $uid]);
    if ($already->fetch()) {
        flash('warning', 'This person is already in the group.');
    } else {
        $maxPos = $db->prepare('SELECT MAX(rotation_position) FROM memberships WHERE group_id=?');
        $maxPos->execute([$gid]); $nextPos = ($maxPos->fetchColumn() ?: 0) + 1;
        $db->prepare('INSERT INTO memberships (group_id,user_id,rotation_position) VALUES (?,?,?)')->execute([$gid,$uid,$nextPos]);
        flash('success', "Member added at rotation position #{$nextPos}.");
    }
    header('Location:'.APP_URL.'/groups/detail.php?id='.$gid); exit;
}
?>

<div class="row justify-content-center">
  <div class="col-lg-5">
    <div class="page-header">
      <div>
        <h1 class="page-header-title">Add Member</h1>
        <p class="page-header-sub">Adding to: <strong><?= e($group['name']) ?></strong></p>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><i class="bi bi-person-plus me-2"></i>Member Details</div>
      <div class="card-body">
        <form method="post">
          <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" name="full_name" class="form-control" placeholder="e.g. Kofi Mensah" required>
          </div>
          <div class="mb-4">
            <label class="form-label">Phone Number (MoMo) <span style="color:#ef4444">*</span></label>
            <input type="tel" name="phone" class="form-control" placeholder="0244 123 456" required>
            <div class="form-text">If this person isn't registered yet, an account will be created for them automatically.</div>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-brand"><i class="bi bi-check-lg"></i> Add Member</button>
            <a href="<?= APP_URL ?>/groups/detail.php?id=<?= $gid ?>" class="btn btn-outline-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require '../includes/footer.php'; ?>
