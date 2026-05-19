<?php
/**
 * Susu Connect — System Setup & Auto-Fix Script
 * Run this once at: http://localhost/susu_php/setup.php
 * It will fix all APP_URL errors and verify your system is working.
 */

// ── Step 1: Find config ────────────────────────────────────
$configPath = __DIR__ . '/config/db.php';
if (!file_exists($configPath)) {
    die('<p style="color:red">ERROR: config/db.php not found. Make sure files are in C:\\xampp\\htdocs\\susu_php\\</p>');
}

session_start();
require_once $configPath;

echo '<!doctype html><html><head>
<title>Susu Connect Setup</title>
<style>
  body{font-family:Arial,sans-serif;max-width:700px;margin:40px auto;padding:20px;background:#f0faf3}
  h1{color:#006b3f;margin-bottom:4px}
  .card{background:#fff;border-radius:12px;padding:24px;margin-bottom:16px;box-shadow:0 2px 8px rgba(0,0,0,.08)}
  .ok{color:#065f46;background:#d1fae5;padding:6px 12px;border-radius:6px;font-size:14px;margin:4px 0;display:block}
  .fix{color:#92400e;background:#fef3c7;padding:6px 12px;border-radius:6px;font-size:14px;margin:4px 0;display:block}
  .err{color:#991b1b;background:#fee2e2;padding:6px 12px;border-radius:6px;font-size:14px;margin:4px 0;display:block}
  .btn{display:inline-block;background:#006b3f;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;font-size:15px;margin-top:12px}
  h2{color:#374151;font-size:16px;margin-bottom:12px}
  code{background:#f3f4f6;padding:2px 6px;border-radius:4px;font-size:13px}
</style></head><body>';

echo '<h1>🐷 Susu Connect Setup</h1>';
echo '<p style="color:#6b7280;margin-bottom:24px">This script fixes all errors and verifies your system is working correctly.</p>';

$totalFixed = 0;
$totalErrors = 0;

// ── Step 2: Fix all PHP files ──────────────────────────────
echo '<div class="card"><h2>Step 1 — Fixing PHP Files</h2>';

$inject = "if(session_status()===PHP_SESSION_NONE)session_start();\nrequire_once __DIR__.'/../config/db.php';\n";
$injectRoot = "if(session_status()===PHP_SESSION_NONE)session_start();\nrequire_once __DIR__.'/config/db.php';\n";

$files = [
    'dashboard/index.php'    => $inject,
    'groups/index.php'       => $inject,
    'groups/create.php'      => $inject,
    'groups/detail.php'      => $inject,
    'groups/add_member.php'  => $inject,
    'rounds/detail.php'      => $inject,
    'reports/index.php'      => $inject,
    'members/profile.php'    => $inject,
    'momo/provision.php'     => $inject,
    'momo/contribute.php'    => $inject,
    'momo/check_status.php'  => $inject,
    'ussd/simulator.php'     => $inject,
];

foreach ($files as $file => $inj) {
    $path = __DIR__ . '/' . $file;
    if (!file_exists($path)) {
        echo "<span class='fix'>SKIP: $file (not found)</span>";
        continue;
    }
    $content = file_get_contents($path);
    if (strpos($content, "require_once __DIR__.'/../config/db.php'") !== false ||
        strpos($content, 'require_once __DIR__."/../config/db.php"') !== false) {
        echo "<span class='ok'>✓ OK: $file</span>";
        continue;
    }
    // Fix: inject after opening <?php tag
    $fixed = preg_replace('/^<\?php\s*\n/', "<?php\n" . $inj, $content, 1);
    if ($fixed === $content) {
        // Try alternate
        $fixed = str_replace("<?php\n", "<?php\n" . $inj, $content);
    }
    file_put_contents($path, $fixed);
    echo "<span class='fix'>🔧 FIXED: $file</span>";
    $totalFixed++;
}

echo '</div>';

// ── Step 3: Test database connection ──────────────────────
echo '<div class="card"><h2>Step 2 — Database Connection</h2>';
try {
    $pdo = db();
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $required = ['users','groups_','memberships','cycles','rounds','contributions','payouts','momo_transactions'];
    $found = 0;
    foreach ($required as $t) {
        if (in_array($t, $tables)) {
            echo "<span class='ok'>✓ Table exists: <code>$t</code></span>";
            $found++;
        } else {
            echo "<span class='err'>✗ Missing table: <code>$t</code></span>";
            $totalErrors++;
        }
    }
    if ($found === count($required)) {
        echo "<span class='ok' style='margin-top:8px;font-weight:700'>✓ All " . count($required) . " tables found in database!</span>";
    }
} catch (Exception $e) {
    echo "<span class='err'>✗ Database connection FAILED: " . $e->getMessage() . "</span>";
    echo "<span class='fix'>Fix: Open config/db.php and make sure DB_NAME matches your phpMyAdmin database name.</span>";
    $totalErrors++;
}
echo '</div>';

// ── Step 4: Test key pages load ────────────────────────────
echo '<div class="card"><h2>Step 3 — File Check</h2>';
$keyFiles = [
    'index.php'              => 'Root redirect',
    'auth/login.php'         => 'Login page',
    'auth/signup.php'        => 'Signup page',
    'dashboard/index.php'    => 'Dashboard',
    'groups/index.php'       => 'Groups list',
    'groups/create.php'      => 'Create group',
    'groups/detail.php'      => 'Group detail',
    'rounds/detail.php'      => 'Round detail',
    'momo/MomoService.php'   => 'MoMo service',
    'momo/contribute.php'    => 'MoMo contribute',
    'ussd/endpoint.php'      => 'USSD endpoint',
    'ussd/simulator.php'     => 'USSD simulator',
    'includes/header.php'    => 'Header include',
    'includes/footer.php'    => 'Footer include',
    'includes/cycle_engine.php' => 'Cycle engine',
    'assets/css/style.css'   => 'Stylesheet',
    'assets/js/app.js'       => 'JavaScript',
    'config/db.php'          => 'Config',
];
$allOk = true;
foreach ($keyFiles as $file => $label) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        echo "<span class='ok'>✓ $label</span>";
    } else {
        echo "<span class='err'>✗ MISSING: $label ($file)</span>";
        $totalErrors++;
        $allOk = false;
    }
}
if ($allOk) echo "<span class='ok' style='font-weight:700;margin-top:8px'>✓ All files present!</span>";
echo '</div>';

// ── Step 5: Check user count ───────────────────────────────
echo '<div class="card"><h2>Step 4 — Data Check</h2>';
try {
    $users = db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $groups = db()->query("SELECT COUNT(*) FROM groups_")->fetchColumn();
    $contribs = db()->query("SELECT COUNT(*) FROM contributions")->fetchColumn();
    echo "<span class='ok'>✓ Users in database: <strong>$users</strong></span>";
    echo "<span class='ok'>✓ Groups in database: <strong>$groups</strong></span>";
    echo "<span class='ok'>✓ Contributions in database: <strong>$contribs</strong></span>";
} catch (Exception $e) {
    echo "<span class='err'>Could not query data: " . $e->getMessage() . "</span>";
}
echo '</div>';

// ── Summary ────────────────────────────────────────────────
echo '<div class="card" style="background:#f0fdf4;border:2px solid #86efac">';
if ($totalErrors === 0) {
    echo '<h2 style="color:#065f46">✅ System is fully working!</h2>';
    echo '<p style="color:#047857;margin-bottom:16px">All files are present, database is connected, and all errors have been fixed.</p>';
    echo '<a href="' . APP_URL . '/dashboard/" class="btn">Open System →</a>';
    echo ' <a href="' . APP_URL . '/auth/login.php" class="btn" style="background:#1a6e3a;margin-left:8px">Go to Login →</a>';
} else {
    echo '<h2 style="color:#991b1b">⚠️ ' . $totalErrors . ' issue(s) found</h2>';
    echo '<p style="color:#7f1d1d">See the red items above and fix them. Then refresh this page.</p>';
    echo '<p style="color:#7f1d1d;margin-top:8px"><strong>Most common fix:</strong> Open <code>config/db.php</code> and change <code>susu_db</code> to <code>susu_group</code></p>';
}
echo '</div>';

if ($totalFixed > 0) {
    echo '<div class="card" style="background:#fffbeb;border:1px solid #fcd34d">';
    echo '<p style="color:#92400e"><strong>Note:</strong> ' . $totalFixed . ' file(s) were automatically fixed. Refresh this page to verify.</p>';
    echo '</div>';
}

echo '</body></html>';
