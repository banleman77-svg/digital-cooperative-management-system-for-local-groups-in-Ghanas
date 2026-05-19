<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__."/../config/db.php";
$pageTitle = 'Create Group';
require '../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $desc    = trim($_POST['description'] ?? '');
    $loc     = trim($_POST['location'] ?? '');
    $amount  = (float)($_POST['contribution_amount'] ?? 0);
    $freq    = $_POST['frequency'] ?? 'WEEKLY';
    $maxm    = (int)($_POST['max_members'] ?? 20);

    if (!$name || $amount <= 0) {
        flash('danger', 'Group name and contribution amount are required.');
    } else {
        $code = gen_group_code();
        $db   = db();
        $stmt = $db->prepare('INSERT INTO groups_ (code,name,description,location,treasurer_id,contribution_amount,frequency,max_members,status) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$code,$name,$desc,$loc,$user['id'],$amount,$freq,$maxm,'PENDING']);
        $gid = $db->lastInsertId();

        // Treasurer is position 1
        $db->prepare('INSERT INTO memberships (group_id,user_id,rotation_position) VALUES (?,?,1)')->execute([$gid,$user['id']]);

        flash('success', "Group \"{$name}\" created! Code: {$code}. Now add members.");
        header('Location: ' . APP_URL . '/groups/detail.php?id=' . $gid); exit;
    }
}
?>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="page-header">
      <div>
        <h1 class="page-header-title">Create a New Susu Group</h1>
        <p class="page-header-sub">Set up the group details. You'll add members next.</p>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><i class="bi bi-people me-2"></i>Group Details</div>
      <div class="card-body">
        <form method="post">
          <div class="mb-3">
            <label class="form-label">Group Name <span style="color:#ef4444">*</span></label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Makola Market Traders Susu" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="2" placeholder="Optional short description"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Location</label>
            <input type="text" name="location" class="form-control" placeholder="e.g. Makola Market, Accra Central">
          </div>
          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label">Contribution Amount (GHS) <span style="color:#ef4444">*</span></label>
              <div class="input-group">
                <span class="input-group-text" style="background:#fafbfc;border:1.5px solid #e2e8f0;border-right:none;border-radius:8px 0 0 8px;font-size:13px">GHS</span>
                <input type="number" name="contribution_amount" step="0.01" min="1" class="form-control" style="border-radius:0 8px 8px 0 !important" required>
              </div>
              <div class="form-text">Amount each member pays per round.</div>
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Contribution Frequency <span style="color:#ef4444">*</span></label>
              <select name="frequency" class="form-select">
                <option value="DAILY">Daily</option>
                <option value="WEEKLY" selected>Weekly</option>
                <option value="BIWEEKLY">Bi-weekly (every 2 weeks)</option>
                <option value="MONTHLY">Monthly</option>
              </select>
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Maximum Members</label>
              <input type="number" name="max_members" value="20" min="2" max="100" class="form-control">
            </div>
          </div>
          <div class="d-flex gap-2 pt-2">
            <button type="submit" class="btn btn-brand"><i class="bi bi-check-lg"></i> Create Group</button>
            <a href="<?= APP_URL ?>/groups/" class="btn btn-outline-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require '../includes/footer.php'; ?>
