<?php
// ── Database Configuration ─────────────────────────────────────
// XAMPP defaults: host=127.0.0.1, user=root, password=''
// Change DB_PASS if you set a MySQL password in XAMPP.

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'susu_group');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ── App Settings ───────────────────────────────────────────────
define('APP_NAME', 'Susu Connect');
define('APP_URL',  'http://localhost/susu_php');

// ── MTN MoMo Sandbox ──────────────────────────────────────────
// Get these from https://momodeveloper.mtn.com
define('MOMO_BASE_URL',     'https://sandbox.momodeveloper.mtn.com');
define('MOMO_ENVIRONMENT',  'sandbox');  // change to 'ghana' for production
define('MOMO_CURRENCY',     'EUR');      // sandbox uses EUR; production uses GHS

define('MOMO_COLLECTIONS_SUBSCRIPTION_KEY', 'your-collections-primary-key');
define('MOMO_COLLECTIONS_API_USER_ID',      '');
define('MOMO_COLLECTIONS_API_KEY',          '');

define('MOMO_DISBURSEMENTS_SUBSCRIPTION_KEY', 'your-disbursements-primary-key');
define('MOMO_DISBURSEMENTS_API_USER_ID',      '');
define('MOMO_DISBURSEMENTS_API_KEY',          '');

// ── PDO Connection (singleton) ─────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ── Helpers ────────────────────────────────────────────────────

/** Normalize a Ghana phone to 233XXXXXXXXX format */
function normalize_phone(string $phone): string {
    $digits = preg_replace('/\D/', '', $phone);
    if (str_starts_with($digits, '233') && strlen($digits) === 12) return $digits;
    if (str_starts_with($digits, '0')   && strlen($digits) === 10) return '233' . substr($digits, 1);
    if (strlen($digits) === 9) return '233' . $digits;
    throw new InvalidArgumentException("Invalid Ghana phone number: $phone");
}

/** Detect MTN / TELECEL / AT from phone prefix */
function detect_network(string $phone): string {
    try { $phone = normalize_phone($phone); } catch (Exception $e) { return 'UNKNOWN'; }
    $prefix = substr($phone, 3, 2);
    if (in_array($prefix, ['24','25','53','54','55','59'])) return 'MTN';
    if (in_array($prefix, ['20','50']))                     return 'TELECEL';
    if (in_array($prefix, ['26','27','56','57']))           return 'AT';
    return 'UNKNOWN';
}

/** Generate a random group code like GRP-7K3PQA */
function gen_group_code(): string {
    return 'GRP-' . strtoupper(bin2hex(random_bytes(3)));
}

/** Generate a random member code like MEM-4X9KMT */
function gen_member_code(): string {
    return 'MEM-' . strtoupper(bin2hex(random_bytes(3)));
}

/** Get the currently logged-in user (from session) */
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

/** Require login — redirect to login page if not authenticated */
function require_login(): void {
    if (!isset($_SESSION['user'])) {
        header('Location: ' . APP_URL . '/auth/login.php');
        exit;
    }
}

/** Flash message helpers */
function flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array {
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/** Escape output */
function e(mixed $val): string {
    return htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Format amount */
function money(mixed $amount): string {
    return 'GHS ' . number_format((float)$amount, 2);
}

// ── Africa's Talking (SMS) ─────────────────────────────────
define('AT_USERNAME', 'sandbox');
define('AT_API_KEY',  'your-africas-talking-api-key');
define('AT_SENDER',   'SusuConn');

// ── Language strings ───────────────────────────────────────
function lang(string $key): string {
    $lang = $_SESSION['lang'] ?? 'en';
    $strings = [
        'en' => [
            'welcome'      => 'Welcome',
            'dashboard'    => 'Dashboard',
            'groups'       => 'Groups',
            'contribute'   => 'Pay Contribution',
            'payout'       => 'Send Payout',
            'defaulters'   => 'Defaulters',
            'confirmed'    => 'Confirmed',
            'pending'      => 'Pending',
        ],
        'tw' => [
            'welcome'      => 'Akwaaba',
            'dashboard'    => 'Nhyehyɛe',
            'groups'       => 'Ekuɔ',
            'contribute'   => 'Tua Sika',
            'payout'       => 'Fa Sika',
            'defaulters'   => 'Wɔn a wɔnntua',
            'confirmed'    => 'Wɔakyerɛ',
            'pending'      => 'Ɛtwɛn',
        ],
    ];
    return $strings[$lang][$key] ?? $strings['en'][$key] ?? $key;
}

// ── Audit logging ──────────────────────────────────────────
function audit(string $action, string $details = ''): void {
    try {
        $uid = $_SESSION['user']['id'] ?? null;
        $ip  = $_SERVER['REMOTE_ADDR'] ?? '';
        db()->prepare('INSERT INTO audit_log(user_id,action,details,ip_address) VALUES(?,?,?,?)')->execute([$uid,$action,$details,$ip]);
    } catch (Exception) {}
}

// ── SMS via Africa's Talking ───────────────────────────────
function send_sms(string $phone, string $message): bool {
    try {
        $url  = 'https://api.sandbox.africastalking.com/version1/messaging';
        $data = http_build_query([
            'username' => AT_USERNAME,
            'to'       => '+' . normalize_phone($phone),
            'message'  => $message,
            'from'     => AT_SENDER,
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_HTTPHEADER     => [
                'apiKey: ' . AT_API_KEY,
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $status = ($code === 201 || $code === 200) ? 'SENT' : 'FAILED';
        db()->prepare('INSERT INTO sms_log(recipient,message,status,response) VALUES(?,?,?,?)')->execute([$phone,$message,$status,$response]);
        return $status === 'SENT';
    } catch (Exception) { return false; }
}

// ── Trust Score System ─────────────────────────────────────
function calculate_trust_score(int $userId): array {
    $db = db();

    // Total expected contributions across all groups
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT r.id) AS expected
        FROM rounds r
        JOIN cycles cy ON cy.id=r.cycle_id
        JOIN memberships m ON m.group_id=cy.group_id AND m.user_id=?
        WHERE r.status IN ('OPEN','CLOSED','PAID') AND m.is_active=1
    ");
    $stmt->execute([$userId]);
    $expected = (int)$stmt->fetchColumn();

    // Confirmed contributions
    $stmt = $db->prepare("SELECT COUNT(*) FROM contributions WHERE member_id=? AND status='CONFIRMED'");
    $stmt->execute([$userId]);
    $confirmed = (int)$stmt->fetchColumn();

    // Failed contributions
    $stmt = $db->prepare("SELECT COUNT(*) FROM contributions WHERE member_id=? AND status='FAILED'");
    $stmt->execute([$userId]);
    $failed = (int)$stmt->fetchColumn();

    // Active warnings
    $stmt = $db->prepare("SELECT COUNT(*) FROM default_warnings WHERE member_id=? AND resolved=0");
    $stmt->execute([$userId]);
    $warnings = (int)$stmt->fetchColumn();

    // Calculate
    if ($expected === 0) {
        $score = 100;
        $tier = 'NEW';
    } else {
        $missed = max(0, $expected - $confirmed);
        $missRate = $missed / $expected;
        $score = max(0, round(100 - ($missRate * 60) - ($failed * 5) - ($warnings * 3), 1));

        if ($score >= 90)      $tier = 'EXCELLENT';
        elseif ($score >= 75)  $tier = 'GOOD';
        elseif ($score >= 60)  $tier = 'FAIR';
        elseif ($score >= 40)  $tier = 'POOR';
        else                   $tier = 'CRITICAL';
    }

    // Update cache
    try {
        $db->prepare("UPDATE users SET trust_score=?, total_contributions=?, missed_contributions=?, last_score_update=NOW() WHERE id=?")
            ->execute([$score, $confirmed, max(0, $expected - $confirmed), $userId]);
    } catch (Exception) {}

    return [
        'score'     => $score,
        'tier'      => $tier,
        'expected'  => $expected,
        'confirmed' => $confirmed,
        'missed'    => max(0, $expected - $confirmed),
        'failed'    => $failed,
        'warnings'  => $warnings,
    ];
}

function trust_tier_color(string $tier): array {
    return match($tier) {
        'EXCELLENT' => ['bg' => '#d1fae5', 'fg' => '#065f46', 'icon' => 'shield-fill-check'],
        'GOOD'      => ['bg' => '#dbeafe', 'fg' => '#1e40af', 'icon' => 'shield-check'],
        'FAIR'      => ['bg' => '#fef3c7', 'fg' => '#92400e', 'icon' => 'shield'],
        'POOR'      => ['bg' => '#fed7d7', 'fg' => '#991b1b', 'icon' => 'shield-exclamation'],
        'CRITICAL'  => ['bg' => '#7f1d1d', 'fg' => '#fff',    'icon' => 'shield-fill-x'],
        default     => ['bg' => '#f3f4f6', 'fg' => '#374151', 'icon' => 'shield-plus'],
    };
}

function issue_warning(int $memberId, int $roundId, int $groupId, string $level, string $reason, int $issuedBy): void {
    db()->prepare("INSERT INTO default_warnings (member_id, round_id, group_id, warning_level, reason, issued_by) VALUES (?,?,?,?,?,?)")
        ->execute([$memberId, $roundId, $groupId, $level, $reason, $issuedBy]);
}
