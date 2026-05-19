<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/../config/db.php';
require_login();

$cid = (int)($_GET['id']??0);
$stmt = db()->prepare("SELECT c.*,u.full_name,u.phone,u.member_code,u.network,r.round_number,r.due_date,g.name AS gname,g.code AS gcode,g.contribution_amount,cy.cycle_number FROM contributions c JOIN users u ON u.id=c.member_id JOIN rounds r ON r.id=c.round_id JOIN cycles cy ON cy.id=r.cycle_id JOIN groups_ g ON g.id=cy.group_id WHERE c.id=?");
$stmt->execute([$cid]); $c = $stmt->fetch();

if(!$c){ die('Receipt not found.'); }

$date = $c['confirmed_at'] ? date('F j, Y \a\t g:i A', strtotime($c['confirmed_at'])) : date('F j, Y');
$ref  = $c['momo_reference'] ?: 'CASH-'.strtoupper(substr(md5($c['id']),0,8));
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Receipt — <?=e($c['gname'])?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:'Plus Jakarta Sans',sans-serif; background:#f0faf3; display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px; }
  .receipt { background:#fff; width:380px; border-radius:20px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,.15); }
  .receipt-header { background:linear-gradient(135deg,#0a3d1f,#1a6e3a); padding:28px 24px; text-align:center; }
  .receipt-logo { font-size:36px; margin-bottom:8px; }
  .receipt-title { color:#fff; font-size:18px; font-weight:800; }
  .receipt-sub { color:rgba(255,255,255,.6); font-size:12px; margin-top:4px; }
  .receipt-amount { background:#f5c842; margin:0 24px; border-radius:12px; padding:20px; text-align:center; margin-top:-1px; }
  .receipt-amount .label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:#92400e; }
  .receipt-amount .value { font-size:36px; font-weight:800; color:#0a3d1f; margin-top:4px; }
  .receipt-body { padding:20px 24px; }
  .receipt-row { display:flex; justify-content:space-between; align-items:flex-start; padding:10px 0; border-bottom:1px solid #f0f4f0; }
  .receipt-row:last-child { border-bottom:none; }
  .receipt-row .key { font-size:12px; color:#6b7280; font-weight:600; }
  .receipt-row .val { font-size:13px; color:#111827; font-weight:700; text-align:right; max-width:200px; }
  .receipt-status { margin:16px 24px; background:#f0faf3; border:1px solid #b3e5c0; border-radius:10px; padding:12px 16px; display:flex; align-items:center; gap:10px; }
  .receipt-status .dot { width:10px; height:10px; background:#2e9e56; border-radius:50%; flex-shrink:0; }
  .receipt-status span { font-size:13px; font-weight:700; color:#065f46; }
  .receipt-footer { padding:16px 24px 24px; text-align:center; }
  .receipt-ref { background:#f9fafb; border-radius:8px; padding:10px; font-size:11px; color:#6b7280; font-family:monospace; word-break:break-all; }
  .receipt-note { font-size:11px; color:#9ca3af; margin-top:12px; line-height:1.5; }
  .print-btn { display:block; margin:20px auto 0; background:#0a3d1f; color:#fff; border:none; border-radius:10px; padding:12px 32px; font-size:14px; font-weight:700; cursor:pointer; font-family:'Plus Jakarta Sans',sans-serif; }
  .print-btn:hover { background:#1a6e3a; }
  @media print {
    body { background:#fff; padding:0; }
    .print-btn { display:none; }
    .receipt { box-shadow:none; }
  }
</style>
</head>
<body>
<div class="receipt">
  <div class="receipt-header">
    <img src="<?=APP_URL?>/assets/images/logo.png" alt="Logo" style="width:60px;height:60px;border-radius:12px;background:#fff;padding:4px;object-fit:contain;margin:0 auto 8px;display:block">
    <div class="receipt-title">Susu Connect</div>
    <div class="receipt-sub">Official Contribution Receipt</div>
  </div>

  <div class="receipt-amount">
    <div class="label">Amount Paid</div>
    <div class="value"><?=money($c['contribution_amount'])?></div>
  </div>

  <div class="receipt-status">
    <div class="dot"></div>
    <span>Payment Confirmed ✓</span>
  </div>

  <div class="receipt-body">
    <div class="receipt-row">
      <span class="key">Member Name</span>
      <span class="val"><?=e($c['full_name'])?></span>
    </div>
    <div class="receipt-row">
      <span class="key">Phone (MoMo)</span>
      <span class="val"><?=e($c['phone'])?></span>
    </div>
    <div class="receipt-row">
      <span class="key">Member Code</span>
      <span class="val"><?=e($c['member_code'])?></span>
    </div>
    <div class="receipt-row">
      <span class="key">Group Name</span>
      <span class="val"><?=e($c['gname'])?></span>
    </div>
    <div class="receipt-row">
      <span class="key">Group Code</span>
      <span class="val"><?=e($c['gcode'])?></span>
    </div>
    <div class="receipt-row">
      <span class="key">Cycle</span>
      <span class="val">Cycle #<?=e($c['cycle_number'])?></span>
    </div>
    <div class="receipt-row">
      <span class="key">Round</span>
      <span class="val">Round <?=e($c['round_number'])?></span>
    </div>
    <div class="receipt-row">
      <span class="key">Payment Method</span>
      <span class="val"><?=e($c['method'])?></span>
    </div>
    <div class="receipt-row">
      <span class="key">Date & Time</span>
      <span class="val"><?=$date?></span>
    </div>
  </div>

  <div class="receipt-footer">
    <div class="receipt-ref">
      <strong>Reference:</strong> <?=e($ref)?>
    </div>
    <div class="receipt-note">
      This is an official digital receipt generated by Susu Connect.<br>
      Keep this for your records. For queries contact your group treasurer.
    </div>
    <button class="print-btn" onclick="window.print()">
      🖨️ Print Receipt
    </button>
  </div>
</div>
</body>
</html>
