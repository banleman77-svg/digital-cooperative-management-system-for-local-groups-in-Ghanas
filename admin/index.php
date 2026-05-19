<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/../config/db.php';
$pageTitle='Administrator Dashboard';
$topbarChip='Admin';

require '../includes/header.php';

// Triple-layer admin protection
if ($user['role'] !== 'ADMIN') {
    audit('UNAUTHORIZED_ADMIN_ACCESS', 'User attempted to access admin area');
    flash('danger', 'Access denied. Administrator privileges required.');
    header('Location: ' . APP_URL . '/dashboard/');
    exit;
}

// System-wide statistics
$totalUsers     = db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalGroups    = db()->query("SELECT COUNT(*) FROM groups_")->fetchColumn();
$activeCycles   = db()->query("SELECT COUNT(*) FROM cycles WHERE status='ACTIVE'")->fetchColumn();
$totalContribs  = db()->query("SELECT COALESCE(SUM(amount),0) FROM contributions WHERE status='CONFIRMED'")->fetchColumn();
$totalPayouts   = db()->query("SELECT COALESCE(SUM(amount),0) FROM payouts WHERE status='COMPLETED'")->fetchColumn();
$pendingPayouts = db()->query("SELECT COUNT(*) FROM payouts WHERE status IN ('PENDING','PROCESSING')")->fetchColumn();
$treasurers     = db()->query("SELECT COUNT(*) FROM users WHERE role='TREASURER'")->fetchColumn();
$collectors     = db()->query("SELECT COUNT(*) FROM users WHERE role='COLLECTOR'")->fetchColumn();
$members        = db()->query("SELECT COUNT(*) FROM users WHERE role='MEMBER'")->fetchColumn();
$todayActions   = db()->query("SELECT COUNT(*) FROM audit_log WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$failedTxns     = db()->query("SELECT COUNT(*) FROM contributions WHERE status='FAILED'")->fetchColumn();

// Recent activity
$recentActivity = db()->query("SELECT a.*,u.full_name,u.role FROM audit_log a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC LIMIT 8")->fetchAll();

// Top groups by activity
$topGroups = db()->query("
  SELECT g.name, g.code, g.status,
         (SELECT COUNT(*) FROM memberships m WHERE m.group_id=g.id AND m.is_active=1) AS mc,
         (SELECT COALESCE(SUM(c.amount),0) FROM contributions c
            JOIN rounds r ON r.id=c.round_id
            JOIN cycles cy ON cy.id=r.cycle_id
            WHERE cy.group_id=g.id AND c.status='CONFIRMED') AS total
  FROM groups_ g
  ORDER BY total DESC
  LIMIT 5
")->fetchAll();
?>

<!-- ════════════════════════════════════════════════════════════
     HERO BANNER WITH ADMIN ILLUSTRATION
═════════════════════════════════════════════════════════════ -->
<div class="admin-hero fu1">
  <div class="admin-hero-content">
    <div class="admin-hero-badge">
      <i class="bi bi-shield-fill-check"></i> Administrator Mode
    </div>
    <h1 class="admin-hero-title">Welcome back, <?=e(explode(' ',$user['full_name'])[0])?>!</h1>
    <p class="admin-hero-sub">You have full control of the Susu Connect platform. Monitor every group, manage every user, and oversee all transactions across Ghana.</p>
    <div class="admin-hero-stats">
      <div class="hero-stat">
        <div class="hero-stat-num"><?=$totalUsers?></div>
        <div class="hero-stat-label">Total Users</div>
      </div>
      <div class="hero-stat">
        <div class="hero-stat-num"><?=$totalGroups?></div>
        <div class="hero-stat-label">Active Groups</div>
      </div>
      <div class="hero-stat">
        <div class="hero-stat-num"><?=money($totalContribs+$totalPayouts)?></div>
        <div class="hero-stat-label">Money Moved</div>
      </div>
    </div>
  </div>

  <!-- SVG Illustration of Administrator -->
  <div class="admin-hero-art">
    <svg viewBox="0 0 400 380" xmlns="http://www.w3.org/2000/svg">
      <!-- Background circles -->
      <circle cx="320" cy="80" r="60" fill="#f5c842" opacity="0.15"/>
      <circle cx="350" cy="200" r="35" fill="#f5c842" opacity="0.2"/>
      <circle cx="60" cy="280" r="45" fill="#f5c842" opacity="0.12"/>

      <!-- Dashboard floating cards behind person -->
      <g opacity="0.85">
        <rect x="30" y="80" width="100" height="65" rx="8" fill="#fff" opacity="0.95"/>
        <rect x="40" y="92" width="60" height="6" rx="3" fill="#1a6e3a"/>
        <rect x="40" y="105" width="40" height="4" rx="2" fill="#80d09a"/>
        <rect x="40" y="115" width="70" height="4" rx="2" fill="#d4d4d4"/>
        <rect x="40" y="125" width="50" height="4" rx="2" fill="#d4d4d4"/>
        <circle cx="115" cy="125" r="8" fill="#f5c842"/>

        <rect x="280" y="240" width="100" height="60" rx="8" fill="#fff" opacity="0.95"/>
        <rect x="290" y="252" width="55" height="6" rx="3" fill="#1a6e3a"/>
        <rect x="290" y="265" width="35" height="4" rx="2" fill="#d4d4d4"/>
        <path d="M 290 285 L 305 275 L 320 282 L 335 270 L 350 278 L 365 268" stroke="#2e9e56" stroke-width="2" fill="none"/>
      </g>

      <!-- Person body -->
      <g>
        <!-- Suit/torso -->
        <path d="M 130 280 L 130 200 Q 130 175 155 175 L 245 175 Q 270 175 270 200 L 270 280 Z" fill="#1a6e3a"/>

        <!-- Shirt collar (V-neck) -->
        <path d="M 175 175 L 200 220 L 225 175 Z" fill="#fff"/>

        <!-- Tie -->
        <path d="M 195 180 L 205 180 L 207 210 L 210 245 L 200 260 L 190 245 L 193 210 Z" fill="#f5c842"/>
        <rect x="195" y="175" width="10" height="8" fill="#0a3d1f"/>

        <!-- Suit lapels -->
        <path d="M 145 175 L 145 245 L 175 200 L 175 175 Z" fill="#0a3d1f"/>
        <path d="M 255 175 L 255 245 L 225 200 L 225 175 Z" fill="#0a3d1f"/>

        <!-- Suit buttons -->
        <circle cx="200" cy="225" r="2.5" fill="#f5c842"/>
        <circle cx="200" cy="245" r="2.5" fill="#f5c842"/>

        <!-- Pocket square -->
        <path d="M 152 200 L 168 200 L 168 215 L 152 215 Z" fill="#f5c842"/>

        <!-- Neck -->
        <rect x="188" y="155" width="24" height="25" fill="#d4a574" rx="2"/>

        <!-- Head -->
        <ellipse cx="200" cy="135" rx="35" ry="42" fill="#d4a574"/>

        <!-- Hair (short professional) -->
        <path d="M 168 120 Q 165 95 200 92 Q 235 95 232 120 L 230 105 Q 215 88 200 90 Q 185 88 170 105 Z" fill="#2d1810"/>
        <path d="M 168 120 Q 175 115 188 115 Q 200 110 212 115 Q 225 115 232 120" stroke="#2d1810" stroke-width="2" fill="none"/>

        <!-- Eyes -->
        <ellipse cx="187" cy="135" rx="3" ry="4" fill="#2d1810"/>
        <ellipse cx="213" cy="135" rx="3" ry="4" fill="#2d1810"/>
        <ellipse cx="188" cy="133" rx="1" ry="1.5" fill="#fff"/>
        <ellipse cx="214" cy="133" rx="1" ry="1.5" fill="#fff"/>

        <!-- Eyebrows -->
        <path d="M 180 125 Q 187 122 194 125" stroke="#2d1810" stroke-width="2" fill="none" stroke-linecap="round"/>
        <path d="M 206 125 Q 213 122 220 125" stroke="#2d1810" stroke-width="2" fill="none" stroke-linecap="round"/>

        <!-- Nose -->
        <path d="M 200 140 Q 196 150 199 155 Q 200 156 201 155 Q 204 150 200 140" fill="#b08d5f" opacity="0.4"/>

        <!-- Mouth (slight smile) -->
        <path d="M 188 158 Q 200 165 212 158" stroke="#2d1810" stroke-width="2" fill="none" stroke-linecap="round"/>

        <!-- Ears -->
        <ellipse cx="167" cy="138" rx="5" ry="8" fill="#d4a574"/>
        <ellipse cx="233" cy="138" rx="5" ry="8" fill="#d4a574"/>

        <!-- Arms -->
        <!-- Left arm (holding tablet) -->
        <path d="M 135 200 Q 105 220 100 260 Q 100 280 115 285" stroke="#1a6e3a" stroke-width="28" fill="none" stroke-linecap="round"/>
        <!-- Hand left -->
        <circle cx="115" cy="285" r="14" fill="#d4a574"/>

        <!-- Right arm (gesturing forward) -->
        <path d="M 265 200 Q 295 215 305 245" stroke="#1a6e3a" stroke-width="28" fill="none" stroke-linecap="round"/>
        <!-- Hand right -->
        <circle cx="305" cy="245" r="14" fill="#d4a574"/>

        <!-- Tablet/device in left hand -->
        <g transform="translate(85, 270) rotate(-15)">
          <rect x="0" y="0" width="55" height="38" rx="4" fill="#0a3d1f"/>
          <rect x="3" y="3" width="49" height="32" rx="2" fill="#f0faf3"/>
          <rect x="6" y="6" width="20" height="3" rx="1" fill="#1a6e3a"/>
          <rect x="6" y="12" width="35" height="2" rx="1" fill="#80d09a"/>
          <rect x="6" y="17" width="30" height="2" rx="1" fill="#d4d4d4"/>
          <circle cx="42" cy="20" r="6" fill="#f5c842"/>
          <rect x="6" y="26" width="15" height="6" rx="2" fill="#1a6e3a"/>
        </g>

        <!-- Lanyard/badge -->
        <line x1="195" y1="170" x2="195" y2="195" stroke="#f5c842" stroke-width="1.5"/>
        <line x1="205" y1="170" x2="205" y2="195" stroke="#f5c842" stroke-width="1.5"/>
        <rect x="188" y="195" width="24" height="18" rx="2" fill="#fff" stroke="#0a3d1f" stroke-width="1"/>
        <text x="200" y="207" text-anchor="middle" font-size="6" fill="#0a3d1f" font-weight="bold" font-family="Arial">ADMIN</text>
      </g>

      <!-- Floating elements -->
      <g opacity="0.9">
        <!-- Star -->
        <path d="M 340 150 L 343 159 L 352 159 L 345 165 L 348 174 L 340 169 L 332 174 L 335 165 L 328 159 L 337 159 Z" fill="#f5c842"/>
        <!-- Check mark in circle -->
        <circle cx="80" cy="180" r="12" fill="#2e9e56"/>
        <path d="M 75 180 L 79 184 L 86 177" stroke="#fff" stroke-width="2.5" fill="none" stroke-linecap="round"/>
        <!-- Dots -->
        <circle cx="50" cy="120" r="3" fill="#f5c842"/>
        <circle cx="370" cy="320" r="4" fill="#1a6e3a" opacity="0.5"/>
        <circle cx="20" cy="220" r="2" fill="#1a6e3a" opacity="0.6"/>
      </g>

      <!-- Ground shadow -->
      <ellipse cx="200" cy="350" rx="100" ry="10" fill="#000" opacity="0.08"/>
    </svg>
  </div>
</div>

<!-- Stats grid -->
<div class="stats-grid fu2">
  <div class="stat-card green">
    <div class="stat-icon green"><i class="bi bi-people-fill"></i></div>
    <div>
      <div class="stat-label">Total Users</div>
      <div class="stat-value"><?=$totalUsers?></div>
      <div class="stat-sub"><?=$members?> members · <?=$treasurers?> treasurers</div>
    </div>
  </div>
  <div class="stat-card blue">
    <div class="stat-icon blue"><i class="bi bi-collection-fill"></i></div>
    <div>
      <div class="stat-label">Active Groups</div>
      <div class="stat-value"><?=$totalGroups?></div>
      <div class="stat-sub"><?=$activeCycles?> cycles running</div>
    </div>
  </div>
  <div class="stat-card amber">
    <div class="stat-icon amber"><i class="bi bi-arrow-up-circle-fill"></i></div>
    <div>
      <div class="stat-label">Contributions</div>
      <div class="stat-value" style="font-size:20px"><?=money($totalContribs)?></div>
      <div class="stat-sub">Total collected</div>
    </div>
  </div>
  <div class="stat-card purple">
    <div class="stat-icon purple"><i class="bi bi-send-fill"></i></div>
    <div>
      <div class="stat-label">Payouts</div>
      <div class="stat-value" style="font-size:20px"><?=money($totalPayouts)?></div>
      <div class="stat-sub"><?=$pendingPayouts?> pending</div>
    </div>
  </div>
</div>

<!-- Quick Actions -->
<div class="quick-actions fu3 mb-24">
  <a href="<?=APP_URL?>/admin/users.php" class="quick-action-card">
    <div class="qa-icon" style="background:#dbeafe;color:#1e40af"><i class="bi bi-people-fill"></i></div>
    <div class="qa-text">
      <div class="qa-title">Manage Users</div>
      <div class="qa-sub">View, edit, or suspend users</div>
    </div>
    <i class="bi bi-arrow-right qa-arrow"></i>
  </a>
  <a href="<?=APP_URL?>/admin/groups.php" class="quick-action-card">
    <div class="qa-icon" style="background:#d1fae5;color:#065f46"><i class="bi bi-collection-fill"></i></div>
    <div class="qa-text">
      <div class="qa-title">All Groups</div>
      <div class="qa-sub">Oversee every Susu group</div>
    </div>
    <i class="bi bi-arrow-right qa-arrow"></i>
  </a>
  <a href="<?=APP_URL?>/admin/reports.php" class="quick-action-card">
    <div class="qa-icon" style="background:#fef3c7;color:#92400e"><i class="bi bi-bar-chart-fill"></i></div>
    <div class="qa-text">
      <div class="qa-title">System Reports</div>
      <div class="qa-sub">Platform analytics</div>
    </div>
    <i class="bi bi-arrow-right qa-arrow"></i>
  </a>
  <a href="<?=APP_URL?>/admin/settings.php" class="quick-action-card">
    <div class="qa-icon" style="background:#f5f3ff;color:#7c3aed"><i class="bi bi-gear-fill"></i></div>
    <div class="qa-text">
      <div class="qa-title">Settings</div>
      <div class="qa-sub">System configuration</div>
    </div>
    <i class="bi bi-arrow-right qa-arrow"></i>
  </a>
</div>

<div class="g75 fu4">
  <!-- Recent Activity -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-activity"></i>Recent System Activity</div>
      <a href="<?=APP_URL?>/audit/" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <?php if($recentActivity): ?>
    <div style="padding:8px 0">
      <?php foreach($recentActivity as $a): ?>
      <div style="display:flex;align-items:center;gap:12px;padding:11px 20px;border-bottom:1px solid var(--n100)">
        <div style="width:36px;height:36px;border-radius:50%;background:var(--g50);display:flex;align-items:center;justify-content:center;color:var(--g600);flex-shrink:0">
          <i class="bi bi-<?php
            $a['action'] = $a['action'] ?? '';
            echo match(true){
              str_contains($a['action'],'LOGIN') => 'box-arrow-in-right',
              str_contains($a['action'],'LOGOUT') => 'box-arrow-right',
              str_contains($a['action'],'GROUP') => 'people-fill',
              str_contains($a['action'],'PAYOUT') => 'send-fill',
              str_contains($a['action'],'CASH') => 'cash',
              str_contains($a['action'],'MEMBER') => 'person-plus',
              str_contains($a['action'],'CYCLE') => 'arrow-repeat',
              default => 'activity',
            };
          ?>"></i>
        </div>
        <div style="flex:1;min-width:0">
          <div class="fw7 text-sm"><?=e($a['full_name']??'System')?> <span style="font-weight:400;color:var(--n500)">— <?=e(str_replace('_',' ',strtolower($a['action'])))?></span></div>
          <div class="text-xs text-muted"><?=e($a['details']??'')?></div>
        </div>
        <div class="text-xs text-muted" style="flex-shrink:0"><?=date('M j, g:ia',strtotime($a['created_at']))?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state"><div class="empty-icon"><i class="bi bi-activity"></i></div><p>No activity yet</p></div>
    <?php endif; ?>
  </div>

  <!-- Top Groups -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-trophy-fill" style="color:#f5c842"></i>Top Groups</div>
    </div>
    <?php if($topGroups): ?>
      <?php foreach($topGroups as $i => $g): ?>
      <div style="display:flex;align-items:center;gap:12px;padding:13px 20px;border-bottom:1px solid var(--n100)">
        <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#f5c842,#d97706);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;flex-shrink:0">
          <?=$i+1?>
        </div>
        <div style="flex:1;min-width:0">
          <div class="fw7 text-sm truncate"><?=e($g['name'])?></div>
          <div class="text-xs text-muted"><?=$g['mc']?> members</div>
        </div>
        <div style="text-align:right;flex-shrink:0">
          <div class="fw7 text-sm" style="color:var(--g600)"><?=money($g['total'])?></div>
          <span class="badge badge-<?=strtolower($g['status'])?>" style="font-size:9px"><?=e($g['status'])?></span>
        </div>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
    <div class="empty-state"><div class="empty-icon"><i class="bi bi-trophy"></i></div><p>No groups yet</p></div>
    <?php endif; ?>
  </div>
</div>

<style>
.admin-hero {
  background: linear-gradient(135deg, #0a3d1f 0%, #1a6e3a 50%, #228244 100%);
  border-radius: 24px;
  padding: 32px 40px;
  margin-bottom: 24px;
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
  gap: 32px;
  box-shadow: 0 20px 50px rgba(10,61,31,.25);
}

.admin-hero::before {
  content: '';
  position: absolute;
  top: -50%; right: -10%;
  width: 500px; height: 500px;
  background: radial-gradient(circle, rgba(245,200,66,.1) 0%, transparent 70%);
  border-radius: 50%;
}

.admin-hero-content {
  flex: 1;
  position: relative;
  z-index: 2;
  max-width: 480px;
}

.admin-hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  background: rgba(245,200,66,.25);
  color: #fef3c7;
  border: 1px solid rgba(245,200,66,.4);
  padding: 6px 14px;
  border-radius: 999px;
  font-size: 11.5px;
  font-weight: 700;
  font-family: var(--fh);
  letter-spacing: .5px;
  text-transform: uppercase;
  margin-bottom: 16px;
}

.admin-hero-title {
  color: #fff;
  font-family: var(--fh);
  font-size: 32px;
  font-weight: 800;
  letter-spacing: -.7px;
  line-height: 1.1;
  margin-bottom: 12px;
}

.admin-hero-sub {
  color: rgba(255,255,255,.7);
  font-size: 14.5px;
  line-height: 1.6;
  margin-bottom: 24px;
}

.admin-hero-stats {
  display: flex;
  gap: 24px;
}

.hero-stat-num {
  color: #f5c842;
  font-family: var(--fh);
  font-size: 24px;
  font-weight: 800;
  letter-spacing: -.5px;
  line-height: 1;
}

.hero-stat-label {
  color: rgba(255,255,255,.55);
  font-size: 11px;
  margin-top: 4px;
  text-transform: uppercase;
  letter-spacing: .5px;
  font-family: var(--fh);
  font-weight: 600;
}

.admin-hero-art {
  width: 340px;
  flex-shrink: 0;
  position: relative;
  z-index: 1;
}

.admin-hero-art svg {
  width: 100%;
  height: auto;
  filter: drop-shadow(0 10px 30px rgba(0,0,0,.2));
}

.quick-actions {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
}

.quick-action-card {
  background: #fff;
  border: 1px solid var(--n200);
  border-radius: 14px;
  padding: 18px;
  display: flex;
  align-items: center;
  gap: 14px;
  text-decoration: none;
  color: inherit;
  transition: all .2s;
  box-shadow: var(--sh-sm);
}

.quick-action-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--sh-md);
  border-color: var(--g300);
}

.qa-icon {
  width: 44px; height: 44px;
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 20px;
  flex-shrink: 0;
}

.qa-text { flex: 1; min-width: 0; }
.qa-title { font-family: var(--fh); font-weight: 700; font-size: 14px; color: var(--n900); }
.qa-sub { font-size: 11.5px; color: var(--n500); margin-top: 2px; }
.qa-arrow { color: var(--n300); font-size: 18px; transition: all .2s; }
.quick-action-card:hover .qa-arrow { color: var(--g600); transform: translateX(3px); }

@media(max-width:1024px) {
  .admin-hero { flex-direction: column; text-align: center; padding: 24px; }
  .admin-hero-content { max-width: none; }
  .admin-hero-art { width: 240px; }
  .admin-hero-stats { justify-content: center; }
  .quick-actions { grid-template-columns: 1fr 1fr; }
}

@media(max-width:600px) {
  .admin-hero-title { font-size: 22px; }
  .admin-hero-sub { font-size: 13px; }
  .admin-hero-stats { gap: 16px; }
  .hero-stat-num { font-size: 20px; }
  .quick-actions { grid-template-columns: 1fr; }
}
</style>

<?php require '../includes/footer.php'; ?>
