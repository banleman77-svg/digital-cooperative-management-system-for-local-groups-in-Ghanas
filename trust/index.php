<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/../config/db.php';
$pageTitle='Trust Score';
$topbarChip='Reputation';
require '../includes/header.php';

$uid = $user['id'];
$trust = calculate_trust_score($uid);
$colors = trust_tier_color($trust['tier']);

// All members ranked by trust score
$allMembers = db()->query("
    SELECT u.id, u.full_name, u.phone, u.member_code, u.role, u.network,
           u.trust_score, u.total_contributions, u.missed_contributions, u.last_score_update
    FROM users u
    WHERE u.role IN ('MEMBER','TREASURER','COLLECTOR') AND u.is_active=1
    ORDER BY u.trust_score DESC, u.total_contributions DESC
    LIMIT 50
")->fetchAll();

// Get warnings
$myWarnings = db()->prepare("
    SELECT w.*, g.name AS group_name, r.round_number
    FROM default_warnings w
    JOIN groups_ g ON g.id=w.group_id
    JOIN rounds r ON r.id=w.round_id
    WHERE w.member_id=? AND w.resolved=0
    ORDER BY w.created_at DESC
");
$myWarnings->execute([$uid]);
$myWarnings = $myWarnings->fetchAll();

$myRank = 0;
foreach ($allMembers as $i => $m) {
    if ($m['id'] == $uid) { $myRank = $i + 1; break; }
}
?>

<div class="page-header fu1">
  <div>
    <h1>Trust Score System</h1>
    <p>Build your reputation. Reliable members earn higher trust scores.</p>
  </div>
</div>

<!-- ════════════════════════════════════
     HERO TRUST SCORE CARD
════════════════════════════════════ -->
<div class="trust-hero fu1">
  <!-- Decorative SVG background -->
  <svg class="trust-hero-bg" viewBox="0 0 800 300" preserveAspectRatio="xMidYMid slice">
    <defs>
      <linearGradient id="trustGrad" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#0a3d1f"/>
        <stop offset="100%" stop-color="#1a6e3a"/>
      </linearGradient>
    </defs>
    <rect width="800" height="300" fill="url(#trustGrad)"/>
    <!-- Circuit/connection pattern -->
    <g opacity="0.08" stroke="#f5c842" stroke-width="1.5" fill="none">
      <circle cx="100" cy="80" r="40"/>
      <circle cx="700" cy="220" r="50"/>
      <circle cx="500" cy="60" r="25"/>
      <line x1="100" y1="80" x2="500" y2="60"/>
      <line x1="500" y1="60" x2="700" y2="220"/>
      <line x1="100" y1="80" x2="700" y2="220"/>
      <circle cx="100" cy="80" r="4" fill="#f5c842" opacity="0.3"/>
      <circle cx="700" cy="220" r="4" fill="#f5c842" opacity="0.3"/>
      <circle cx="500" cy="60" r="4" fill="#f5c842" opacity="0.3"/>
    </g>
    <g opacity="0.05">
      <text x="50" y="200" font-size="120" font-weight="900" fill="#f5c842" font-family="sans-serif">TRUST</text>
    </g>
  </svg>

  <div class="trust-hero-content">
    <!-- Left: gauge & score -->
    <div class="trust-gauge-wrap">
      <svg class="trust-gauge" viewBox="0 0 200 200">
        <defs>
          <linearGradient id="gaugeGrad" x1="0" y1="0" x2="1" y2="0">
            <stop offset="0%" stop-color="#ef4444"/>
            <stop offset="50%" stop-color="#f59e0b"/>
            <stop offset="100%" stop-color="#10b981"/>
          </linearGradient>
        </defs>
        <!-- Background track -->
        <circle cx="100" cy="100" r="80" fill="none" stroke="rgba(255,255,255,.1)" stroke-width="14"/>
        <!-- Score arc -->
        <circle cx="100" cy="100" r="80"
                fill="none"
                stroke="url(#gaugeGrad)"
                stroke-width="14"
                stroke-linecap="round"
                stroke-dasharray="<?=round($trust['score']/100 * 502.4, 2)?> 502.4"
                transform="rotate(-90 100 100)"
                style="transition: stroke-dasharray 1s ease"/>
        <!-- Inner shadow ring -->
        <circle cx="100" cy="100" r="65" fill="rgba(255,255,255,.05)"/>
      </svg>
      <div class="trust-gauge-text">
        <div class="trust-score-num"><?=number_format($trust['score'], 1)?></div>
        <div class="trust-score-label">Trust Score</div>
      </div>
    </div>

    <!-- Right: details -->
    <div class="trust-details">
      <div class="trust-tier-badge" style="background:<?=$colors['bg']?>;color:<?=$colors['fg']?>">
        <i class="bi bi-<?=$colors['icon']?>"></i>
        <span><?=$trust['tier']?></span>
      </div>

      <h2 class="trust-greeting">
        <?php if ($trust['tier'] === 'EXCELLENT'): ?>
          Outstanding reputation! 🌟
        <?php elseif ($trust['tier'] === 'GOOD'): ?>
          Great work, keep it up! 👏
        <?php elseif ($trust['tier'] === 'FAIR'): ?>
          You're doing okay 💪
        <?php elseif ($trust['tier'] === 'POOR'): ?>
          Time to improve ⚠️
        <?php elseif ($trust['tier'] === 'CRITICAL'): ?>
          Action required! 🚨
        <?php else: ?>
          Welcome! Build your score 🌱
        <?php endif; ?>
      </h2>
      <p class="trust-tagline">
        <?= match($trust['tier']) {
          'EXCELLENT' => 'You are among the most trusted members. Groups will eagerly welcome you.',
          'GOOD'      => 'Your reputation is strong. A few more on-time payments will make you EXCELLENT.',
          'FAIR'      => 'Your reputation is acceptable but improving consistency will boost your score.',
          'POOR'      => 'Multiple missed contributions are affecting your reputation. Pay on time to recover.',
          'CRITICAL'  => 'Your reputation is severely damaged. Immediate action needed to restore trust.',
          default     => 'Make your first contribution to start building your trust score.',
        } ?>
      </p>

      <div class="trust-stats">
        <div class="ts-stat">
          <div class="ts-stat-num"><?=$trust['confirmed']?></div>
          <div class="ts-stat-label">On Time</div>
        </div>
        <div class="ts-stat">
          <div class="ts-stat-num" style="color:#fbbf24"><?=$trust['expected']?></div>
          <div class="ts-stat-label">Expected</div>
        </div>
        <div class="ts-stat">
          <div class="ts-stat-num" style="color:#f87171"><?=$trust['missed']?></div>
          <div class="ts-stat-label">Missed</div>
        </div>
        <div class="ts-stat">
          <div class="ts-stat-num" style="color:#fb923c">#<?=$myRank?:'—'?></div>
          <div class="ts-stat-label">Your Rank</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════
     SCORE BREAKDOWN
════════════════════════════════════ -->
<div class="g2 fu2 mb-24">
  <!-- How score is calculated -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-calculator"></i> How Your Score Is Calculated</div>
    </div>
    <div class="card-body">
      <div class="score-formula">
        <div class="formula-step">
          <div class="formula-icon" style="background:#d1fae5;color:#065f46"><i class="bi bi-1-circle-fill"></i></div>
          <div class="formula-text">
            <strong>Start with 100 points</strong>
            <p>Every member begins with a perfect score.</p>
          </div>
        </div>
        <div class="formula-step">
          <div class="formula-icon" style="background:#fed7d7;color:#991b1b"><i class="bi bi-dash-circle-fill"></i></div>
          <div class="formula-text">
            <strong>Missed contribution → -6 to -60 points</strong>
            <p>Each missed payment reduces your score proportionally.</p>
          </div>
        </div>
        <div class="formula-step">
          <div class="formula-icon" style="background:#fef3c7;color:#92400e"><i class="bi bi-x-circle-fill"></i></div>
          <div class="formula-text">
            <strong>Failed MoMo payment → -5 points</strong>
            <p>Insufficient funds or canceled transactions hurt your score.</p>
          </div>
        </div>
        <div class="formula-step">
          <div class="formula-icon" style="background:#fed7d7;color:#991b1b"><i class="bi bi-exclamation-triangle-fill"></i></div>
          <div class="formula-text">
            <strong>Active warning → -3 points</strong>
            <p>Warnings from your treasurer reduce your score until resolved.</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Tier breakdown -->
  <div class="card">
    <div class="card-header"><div class="card-title"><i class="bi bi-bar-chart-steps"></i> Trust Tiers</div></div>
    <div class="card-body">
      <div class="tier-list">
        <?php $tiers = [
          ['EXCELLENT', '90-100', 'shield-fill-check', '#d1fae5', '#065f46', 'Top performer — full access'],
          ['GOOD',      '75-89',  'shield-check',      '#dbeafe', '#1e40af', 'Reliable member — recommended'],
          ['FAIR',      '60-74',  'shield',            '#fef3c7', '#92400e', 'Acceptable — monitor closely'],
          ['POOR',      '40-59',  'shield-exclamation','#fed7d7', '#991b1b', 'At risk — may need intervention'],
          ['CRITICAL',  '0-39',   'shield-fill-x',     '#7f1d1d', '#fff',    'Severe — group entry restricted'],
        ];
        foreach($tiers as $t):
          $isMe = $t[0] === $trust['tier'];
        ?>
        <div class="tier-row <?=$isMe?'tier-current':''?>">
          <div class="tier-icon" style="background:<?=$t[3]?>;color:<?=$t[4]?>">
            <i class="bi bi-<?=$t[2]?>"></i>
          </div>
          <div class="tier-info">
            <div class="tier-name">
              <?=$t[0]?>
              <?php if($isMe): ?><span class="tier-you">YOU</span><?php endif; ?>
            </div>
            <div class="tier-desc"><?=$t[5]?></div>
          </div>
          <div class="tier-range"><?=$t[1]?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════
     LEADERBOARD
════════════════════════════════════ -->
<div class="card fu3">
  <div class="card-header">
    <div class="card-title"><i class="bi bi-trophy-fill" style="color:#f5c842"></i> Top Members Leaderboard</div>
    <input type="text" id="table-search" class="form-control" placeholder="Search..." style="width:200px;padding:7px 12px;font-size:13px">
  </div>
  <?php if($allMembers): ?>
  <div class="tbl-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th style="width:50px">#</th>
          <th>Member</th>
          <th>Trust Score</th>
          <th>Tier</th>
          <th>Contributions</th>
          <th>Missed</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($allMembers as $i => $m):
          $mTier = match(true) {
            $m['trust_score'] >= 90 => 'EXCELLENT',
            $m['trust_score'] >= 75 => 'GOOD',
            $m['trust_score'] >= 60 => 'FAIR',
            $m['trust_score'] >= 40 => 'POOR',
            default => 'CRITICAL',
          };
          $mColors = trust_tier_color($mTier);
          $isMe = $m['id'] == $uid;
        ?>
        <tr data-searchable <?=$isMe?'style="background:linear-gradient(90deg,#f0faf3,#fff);font-weight:600"':''?>>
          <td>
            <?php if($i < 3): ?>
              <div style="width:30px;height:30px;border-radius:50%;background:<?=['#fbbf24','#94a3b8','#fb923c'][$i]?>;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px">
                <?=$i+1?>
              </div>
            <?php else: ?>
              <span class="text-muted text-sm">#<?=$i+1?></span>
            <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,var(--g500),var(--g700));color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800">
                <?=strtoupper(substr($m['full_name'],0,1))?>
              </div>
              <div>
                <div class="fw7 text-sm"><?=e($m['full_name'])?> <?php if($isMe): ?><span class="text-xs" style="color:var(--g600);font-weight:700">(You)</span><?php endif; ?></div>
                <div class="text-xs text-muted"><?=e($m['phone'])?></div>
              </div>
            </div>
          </td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:60px;height:6px;background:var(--n100);border-radius:3px;overflow:hidden">
                <div style="height:100%;width:<?=$m['trust_score']?>%;background:linear-gradient(90deg,<?=$m['trust_score']<40?'#ef4444':($m['trust_score']<75?'#f59e0b':'#10b981')?>,<?=$m['trust_score']<40?'#dc2626':($m['trust_score']<75?'#d97706':'#059669')?>)"></div>
              </div>
              <span class="fw7" style="font-size:13px;color:<?=$m['trust_score']<40?'#ef4444':($m['trust_score']<75?'#f59e0b':'#10b981')?>"><?=number_format($m['trust_score'],1)?></span>
            </div>
          </td>
          <td>
            <span class="badge" style="background:<?=$mColors['bg']?>;color:<?=$mColors['fg']?>;border-color:<?=$mColors['bg']?>">
              <i class="bi bi-<?=$mColors['icon']?>" style="font-size:9px"></i>
              <?=$mTier?>
            </span>
          </td>
          <td class="fw7"><?=$m['total_contributions']?></td>
          <td><?=$m['missed_contributions']>0?'<span style="color:var(--red);font-weight:700">'.$m['missed_contributions'].'</span>':'<span class="text-muted">0</span>'?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <div class="empty-state"><div class="empty-icon"><i class="bi bi-people"></i></div><p>No members yet</p></div>
  <?php endif; ?>
</div>

<!-- My Warnings -->
<?php if($myWarnings): ?>
<div class="card mt-24 fu4">
  <div class="card-header">
    <div class="card-title" style="color:var(--red)"><i class="bi bi-exclamation-triangle-fill"></i> Active Warnings (<?=count($myWarnings)?>)</div>
  </div>
  <div class="card-body">
    <?php foreach($myWarnings as $w): ?>
    <div class="warning-item">
      <div class="warning-level warning-<?=strtolower($w['warning_level'])?>"><?=$w['warning_level']?></div>
      <div class="warning-content">
        <div class="warning-title"><?=e($w['group_name'])?> — Round <?=e($w['round_number'])?></div>
        <div class="warning-reason"><?=e($w['reason'])?></div>
        <div class="warning-date"><?=date('M j, Y', strtotime($w['created_at']))?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<style>
/* Trust Hero */
.trust-hero {
  position: relative;
  border-radius: 24px;
  overflow: hidden;
  margin-bottom: 24px;
  box-shadow: 0 20px 50px rgba(10,61,31,.25);
  min-height: 280px;
}
.trust-hero-bg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}
.trust-hero-content {
  position: relative;
  z-index: 2;
  padding: 36px 40px;
  display: flex;
  align-items: center;
  gap: 40px;
}

/* Gauge */
.trust-gauge-wrap {
  position: relative;
  width: 200px;
  height: 200px;
  flex-shrink: 0;
}
.trust-gauge {
  width: 100%;
  height: 100%;
}
.trust-gauge-text {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #fff;
}
.trust-score-num {
  font-family: var(--fh);
  font-size: 48px;
  font-weight: 800;
  letter-spacing: -1px;
  line-height: 1;
}
.trust-score-label {
  font-size: 11px;
  color: rgba(255,255,255,.6);
  text-transform: uppercase;
  letter-spacing: 1.5px;
  font-weight: 700;
  font-family: var(--fh);
  margin-top: 4px;
}

/* Details */
.trust-details { flex: 1; min-width: 0; }
.trust-tier-badge {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 6px 14px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 800;
  font-family: var(--fh);
  letter-spacing: .8px;
  margin-bottom: 14px;
}
.trust-greeting {
  font-family: var(--fh);
  font-size: 26px;
  font-weight: 800;
  color: #fff;
  margin-bottom: 8px;
  line-height: 1.2;
}
.trust-tagline {
  color: rgba(255,255,255,.7);
  font-size: 14px;
  line-height: 1.6;
  margin-bottom: 22px;
  max-width: 480px;
}
.trust-stats {
  display: flex;
  gap: 28px;
  flex-wrap: wrap;
}
.ts-stat-num {
  color: #f5c842;
  font-family: var(--fh);
  font-size: 26px;
  font-weight: 800;
  line-height: 1;
}
.ts-stat-label {
  color: rgba(255,255,255,.5);
  font-size: 11px;
  margin-top: 4px;
  text-transform: uppercase;
  letter-spacing: .5px;
  font-weight: 700;
  font-family: var(--fh);
}

/* Score formula */
.score-formula { display: flex; flex-direction: column; gap: 14px; }
.formula-step {
  display: flex; align-items: flex-start; gap: 12px;
  padding: 12px; background: var(--n50); border-radius: 12px;
}
.formula-icon {
  width: 36px; height: 36px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 17px; flex-shrink: 0;
}
.formula-text strong {
  display: block; font-family: var(--fh); font-size: 14px;
  color: var(--n900); margin-bottom: 3px;
}
.formula-text p { font-size: 12.5px; color: var(--n500); margin: 0; }

/* Tier list */
.tier-list { display: flex; flex-direction: column; gap: 10px; }
.tier-row {
  display: flex; align-items: center; gap: 12px;
  padding: 11px 14px;
  background: var(--n50); border-radius: 12px;
  transition: all .15s;
}
.tier-row.tier-current {
  background: linear-gradient(90deg, var(--g50), var(--white));
  border: 1.5px solid var(--g300);
  box-shadow: 0 4px 12px rgba(34,130,68,.15);
}
.tier-icon {
  width: 36px; height: 36px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 17px; flex-shrink: 0;
}
.tier-info { flex: 1; min-width: 0; }
.tier-name {
  font-family: var(--fh); font-size: 13.5px; font-weight: 800;
  color: var(--n900);
  display: flex; align-items: center; gap: 8px;
}
.tier-you {
  background: var(--g600); color: #fff;
  font-size: 9px; padding: 2px 6px; border-radius: 4px;
  letter-spacing: .5px;
}
.tier-desc { font-size: 12px; color: var(--n500); margin-top: 2px; }
.tier-range {
  font-family: var(--fh); font-weight: 700; font-size: 13px;
  color: var(--n400); flex-shrink: 0;
}

/* Warnings */
.warning-item {
  display: flex; align-items: flex-start; gap: 14px;
  padding: 14px; background: #fef2f2;
  border-radius: 12px; margin-bottom: 10px;
  border: 1px solid #fecaca;
}
.warning-level {
  padding: 4px 10px; border-radius: 6px;
  font-size: 10.5px; font-weight: 800; font-family: var(--fh);
  letter-spacing: .5px; color: #fff; flex-shrink: 0;
}
.warning-low { background: #fbbf24; }
.warning-medium { background: #f97316; }
.warning-high { background: #ef4444; }
.warning-critical { background: #7f1d1d; }
.warning-content { flex: 1; }
.warning-title { font-family: var(--fh); font-weight: 700; font-size: 13.5px; color: var(--n900); }
.warning-reason { font-size: 13px; color: var(--n600); margin-top: 4px; }
.warning-date { font-size: 11px; color: var(--n400); margin-top: 6px; }

@media(max-width:768px) {
  .trust-hero-content { flex-direction: column; text-align: center; padding: 24px; gap: 24px; }
  .trust-stats { justify-content: center; gap: 18px; }
  .trust-greeting { font-size: 20px; }
}
</style>

<?php require '../includes/footer.php'; ?>
