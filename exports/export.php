<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/../config/db.php';
require_login();

$type = $_GET['type'] ?? 'contributions';
$gid  = (int)($_GET['group_id'] ?? 0);

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="susu_'.e($type).'_'.date('Y-m-d').'.xls"');
header('Cache-Control: max-age=0');

$uid = $_SESSION['user']['id'];

if($type === 'contributions' && $gid) {
    $stmt = db()->prepare("
        SELECT u.full_name AS 'Member Name', u.phone AS 'Phone', u.member_code AS 'Member Code',
               u.network AS 'Network', r.round_number AS 'Round', r.due_date AS 'Due Date',
               c.amount AS 'Amount (GHS)', c.method AS 'Method', c.status AS 'Status',
               c.confirmed_at AS 'Confirmed At', c.momo_reference AS 'MoMo Reference'
        FROM contributions c
        JOIN users u ON u.id=c.member_id
        JOIN rounds r ON r.id=c.round_id
        JOIN cycles cy ON cy.id=r.cycle_id
        WHERE cy.group_id=?
        ORDER BY r.round_number, u.full_name
    ");
    $stmt->execute([$gid]);
} elseif($type === 'members' && $gid) {
    $stmt = db()->prepare("
        SELECT u.full_name AS 'Full Name', u.phone AS 'Phone', u.member_code AS 'Member Code',
               u.network AS 'Network', u.role AS 'Role', m.rotation_position AS 'Rotation Position',
               CASE WHEN m.is_active=1 THEN 'Active' ELSE 'Inactive' END AS 'Status',
               m.joined_at AS 'Joined Date'
        FROM memberships m
        JOIN users u ON u.id=m.user_id
        WHERE m.group_id=?
        ORDER BY m.rotation_position
    ");
    $stmt->execute([$gid]);
} elseif($type === 'audit') {
    $stmt = db()->prepare("
        SELECT a.created_at AS 'Date/Time', u.full_name AS 'User', u.phone AS 'Phone',
               a.action AS 'Action', a.details AS 'Details', a.ip_address AS 'IP Address'
        FROM audit_log a
        LEFT JOIN users u ON u.id=a.user_id
        ORDER BY a.created_at DESC
        LIMIT 1000
    ");
    $stmt->execute([]);
} else {
    // Default: all contributions for managed groups
    $stmt = db()->prepare("
        SELECT g.name AS 'Group', u.full_name AS 'Member', u.phone AS 'Phone',
               r.round_number AS 'Round', c.amount AS 'Amount (GHS)', c.method AS 'Method',
               c.status AS 'Status', c.confirmed_at AS 'Date'
        FROM contributions c
        JOIN users u ON u.id=c.member_id
        JOIN rounds r ON r.id=c.round_id
        JOIN cycles cy ON cy.id=r.cycle_id
        JOIN groups_ g ON g.id=cy.group_id
        WHERE g.treasurer_id=?
        ORDER BY g.name, r.round_number, u.full_name
    ");
    $stmt->execute([$uid]);
}

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if(!$rows) {
    echo "<table><tr><td>No data found.</td></tr></table>";
    exit;
}

// Build Excel table
echo '<table border="1">';

// Header row
echo '<tr style="background:#1a6e3a;color:#fff;font-weight:bold">';
foreach(array_keys($rows[0]) as $col) {
    echo '<th>'.htmlspecialchars($col).'</th>';
}
echo '</tr>';

// Data rows
foreach($rows as $i => $row) {
    $bg = $i % 2 === 0 ? '#f0faf3' : '#ffffff';
    echo "<tr style='background:$bg'>";
    foreach($row as $val) {
        echo '<td>'.htmlspecialchars($val ?? '').'</td>';
    }
    echo '</tr>';
}

echo '</table>';
exit;
