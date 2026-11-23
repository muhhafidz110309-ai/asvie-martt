<?php

require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hapus semua data session
$_SESSION = [];
session_unset();
session_destroy();

// Kembali ke halaman login
header('Location: login.php');
exit;