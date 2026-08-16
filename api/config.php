<?php
/**
 * EVEE CRM - Database configuration & shared helpers
 * XAMPP defaults: user root, empty password.
 */

declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'evee_crm';
const DB_USER = 'root';
const DB_PASS = '';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function cors(): void
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function respond($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function fail(string $message, int $code = 400): void
{
    respond(['error' => $message], $code);
}

function json_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '[]', true);
    return is_array($data) ? $data : [];
}

/** '' -> null so UNIQUE keys allow multiple rows. */
function normalize_optional(?string $value): ?string
{
    if ($value === null) return null;
    $value = trim($value);
    return $value === '' ? null : $value;
}

/* ----------------------- AUTH / PASSWORD HELPERS ----------------------- */

/** True when the stored value is already a bcrypt/argon2 hash. */
function is_hashed_password(string $value): bool
{
    return str_starts_with($value, '$2y$')
        || str_starts_with($value, '$2a$')
        || str_starts_with($value, '$2b$')
        || str_starts_with($value, '$argon2');
}

/**
 * Hash a plain-text password before storing. Passwords that are already
 * hashed (bcrypt/argon2) pass through untouched so the API is idempotent.
 * Returns null for empty input (no password set).
 */
function hash_password(?string $plain): ?string
{
    if ($plain === null || $plain === '') return null;
    if (is_hashed_password($plain)) return $plain;
    return password_hash($plain, PASSWORD_BCRYPT);
}

/** Verify a plain-text attempt against a stored value (hash or legacy plain). */
function verify_password(string $plain, ?string $stored): bool
{
    if ($stored === null || $stored === '') return false;
    if (is_hashed_password($stored)) {
        return password_verify($plain, $stored);
    }
    // Legacy rows saved as plain text before hashing was introduced.
    return hash_equals($stored, $plain);
}

/* ----------------------- EMAIL NOTIFICATION (best effort) ----------------------- */

/**
 * Best-effort outbound email for notifications. On XAMPP this relies on the
 * configured PHP mail transport (Mercury / sendmail). Failures are swallowed
 * so notification creation never breaks because mail could not be sent.
 * Returns true when mail() accepted the message.
 */
function send_notification_email(string $to, string $subject, string $body): bool
{
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $headers = "MIME-Version: 1.0\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "From: Evee CRM <no-reply@evee.local>\r\n"
        . "X-Mailer: Evee CRM Notification\r\n";
    try {
        return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
    } catch (Throwable $e) {
        return false;
    }
}
