<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

echo "INDEX START<br>";

if (!isset($_SESSION['user'])) {
    echo "REDIRECT LOGIN";
    exit;
}

echo "ROLE: " . ($_SESSION['user']['role'] ?? 'khong co role') . "<br>";

if (($_SESSION['user']['role'] ?? '') === 'admin') {
    echo "GO DASHBOARD";
} else {
    echo "GO DASHBOARD USER";
}
exit;