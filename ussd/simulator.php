<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__."/../config/db.php";
$pageTitle = 'USSD Simulator';
require '../includes/header.php';

// Handle simulator AJAX calls
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['simulate'])) {
    require_once __DIR__ . '/endpoint.php';  // loads handle_ussd()
    $phone = trim($_POST['phone'] ?? $user['phone']);
    $text  = $_POST['text'] ?? '';
    header('Content-Type: text/plain');
    echo handle_ussd($phone, $text); exit;
}
?>

<div class="page-header">
  <div>
    <h1 class="page-header-title">USSD Simulator</h1>
    <p class="page-header-sub">Demonstrates exactly what a member sees on a feature phone. Same backend as production.</p>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="phone-frame">
      <div class="phone-notch"></div>
      <div class="phone-screen" id="screen">Dial *384*12345# to begin.</div>
      <div class="phone-input-area">
        <input class="phone-input" id="input" type="text" placeholder="Enter your response..." autocomplete="off">
        <button class="phone-btn" id="send-btn"><i class="bi bi-send-fill"></i> Send</button>
        <button class="phone-end-btn" id="end-btn"><i class="bi bi-telephone-x-fill"></i> End Session</button>
      </div>
    </div>
    <div class="text-center mt-3">
      <label style="font-size:12px;color:#718096;font-weight:600">Testing as:</label>
      <input type="text" id="phone-num" value="<?= e($user['phone']) ?>" class="form-control form-control-sm mt-1 text-center" style="max-width:200px;margin:0 auto">
    </div>
    <div class="text-center mt-2">
      <button class="btn btn-brand" id="start-btn"><i class="bi bi-telephone-fill"></i> Dial *384*12345#</button>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-info-circle me-2"></i>How it works</div>
      <div class="card-body">
        <p style="font-size:14px;color:#374151;line-height:1.7">This simulator uses the <strong>exact same backend endpoint</strong> as what Africa's Talking would call in production (<code>/ussd/endpoint.php</code>). A real member would dial <strong>*384*12345#</strong> from any phone — no internet required — and see the same menus.</p>

        <div class="row g-3 mt-1">
          <div class="col-md-4"><div class="stat-card"><div class="stat-icon green"><i class="bi bi-phone"></i></div><div><div class="stat-label">Works on</div><div class="stat-value" style="font-size:16px">Any phone</div></div></div></div>
          <div class="col-md-4"><div class="stat-card"><div class="stat-icon blue"><i class="bi bi-wifi-off"></i></div><div><div class="stat-label">Data needed</div><div class="stat-value" style="font-size:16px">None</div></div></div></div>
          <div class="col-md-4"><div class="stat-card"><div class="stat-icon amber"><i class="bi bi-translate"></i></div><div><div class="stat-label">Language</div><div class="stat-value" style="font-size:16px">English</div></div></div></div>
        </div>

        <hr class="my-4">
        <h6 style="font-weight:600;font-size:13px;color:#374151;margin-bottom:12px">Menu Reference</h6>
        <div class="row g-2">
          <?php $menus = [['1','My Balance','View total contributed and received'],['2','Next Contribution','See what is due and when'],['3','Next Payout','Check your payout schedule'],['4','Pay Contribution','Trigger MTN MoMo payment prompt'],['5','Group Info','Group code, members, frequency']]; ?>
          <?php foreach($menus as [$num,$title,$desc]): ?>
            <div class="col-md-6">
              <div style="display:flex;align-items:flex-start;gap:10px;padding:10px;background:#f8fafc;border-radius:8px">
                <span style="width:24px;height:24px;background:#006b3f;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0"><?= $num ?></span>
                <div><div style="font-weight:600;font-size:13px"><?= $title ?></div><div style="font-size:12px;color:#718096"><?= $desc ?></div></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const screen  = document.getElementById('screen');
const inputEl = document.getElementById('input');
const phoneEl = document.getElementById('phone-num');
let chain = '', active = false;

async function send(text) {
  const fd = new FormData();
  fd.append('simulate', '1');
  fd.append('phone',    phoneEl.value.trim());
  fd.append('text',     text);
  const res = await fetch('<?= APP_URL ?>/ussd/simulator.php', { method: 'POST', body: fd });
  const raw = await res.text();
  const isEnd = raw.startsWith('END');
  screen.textContent = raw.replace(/^(CON|END)\s*/, '');
  if (isEnd) { active = false; chain = ''; }
}

document.getElementById('start-btn').addEventListener('click', () => {
  chain = ''; active = true;
  screen.textContent = 'Connecting...';
  send('');
});

document.getElementById('send-btn').addEventListener('click', async () => {
  if (!active) { alert('Start a session first by clicking "Dial *384*12345#".'); return; }
  const val = inputEl.value.trim();
  if (!val) return;
  chain = chain ? chain + '*' + val : val;
  inputEl.value = '';
  await send(chain);
});

document.getElementById('end-btn').addEventListener('click', () => {
  active = false; chain = '';
  screen.textContent = 'Session ended.';
  inputEl.value = '';
});

inputEl.addEventListener('keydown', e => { if (e.key === 'Enter') document.getElementById('send-btn').click(); });
</script>

<?php require '../includes/footer.php'; ?>
