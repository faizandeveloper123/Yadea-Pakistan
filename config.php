<?php
session_start();

define('DB_HOST', 'localhost');
define('DB_PORT', '3307');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hiifi_lms');

// Base URL (local web root)
define('BASE_URL', '/HIIFI LMS/');

function db_connect() {
    static $conn = null;
    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        if ($conn->connect_error) {
            die('Database connection failed: ' . $conn->connect_error);
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

function db_query($sql) {
    return db_connect()->query($sql);
}

function db_prepare($sql) {
    return db_connect()->prepare($sql);
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
}

function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function get_setting($key, $default = '') {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $res = @db_query("SELECT setting_key, setting_value FROM settings");
        if ($res) { while ($row = $res->fetch_assoc()) { $cache[$row['setting_key']] = $row['setting_value']; } }
    }
    return $cache[$key] ?? $default;
}