<?php
// Root redirect
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config/db.php';
$dest = isset($_SESSION['user']) ? APP_URL . '/dashboard/' : APP_URL . '/auth/login.php';
header('Location: ' . $dest); exit;
