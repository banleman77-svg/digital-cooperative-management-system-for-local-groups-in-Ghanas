<?php
if (session_status() === PHP_SESSION_NONE) session_start();
session_destroy();
header('Location: ' . (defined('APP_URL') ? APP_URL : '/susu_php') . '/auth/login.php');
exit;
