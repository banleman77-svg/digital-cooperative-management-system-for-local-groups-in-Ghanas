<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/../config/db.php';
header('Location:'.APP_URL.'/statements/index.php');exit;
