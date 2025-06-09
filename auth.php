<?php session_start();

if (!isset($_SESSION['user'])) {
    header("Location: /index.html");
    exit;
}

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");


