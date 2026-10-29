<?php
/** Loaded by every page: autoload, secure session, helpers. */
spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/../classes/' . $class . '.php';
    if (is_file($file)) { require $file; }
});

// ---- Sessions -------------------------------------------------------------
const SESSION_TIMEOUT = 1800; // 30 minutes of inactivity
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']),
]);
session_start();
if (isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > SESSION_TIMEOUT) {
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['flash'] = ['type' => 'info', 'msg' => 'You were logged out after 30 minutes of inactivity.'];
}
$_SESSION['last_seen'] = time();

// ---- Helpers --------------------------------------------------------------
function e(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function redirect(string $url): never { header('Location: ' . $url); exit; }
function money(float $v): string { return '₱' . number_format($v, 2); }

function flash(string $type, string $msg): void { $_SESSION['flash'] = ['type' => $type, 'msg' => $msg]; }
function pull_flash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void
{
    $ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', (string)$_POST['csrf']);
    if (!$ok) { http_response_code(419); exit('Your session expired. Go back, refresh the page and try again.'); }
}

function is_logged_in(): bool { return !empty($_SESSION['user']); }
function current_user(): array { return $_SESSION['user'] ?? []; }
function is_admin(): bool { return (current_user()['role'] ?? '') === 'admin'; }

function require_login(): void
{
    if (!is_logged_in()) {
        flash('info', 'Log in to manage your reservations.');
        redirect('login.php');
    }
}

function login_user(array $user): void
{
    session_regenerate_id(true);              // prevents session fixation
    $_SESSION['user'] = $user;
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/** Reservation service for the logged-in user. */
function reservations(): Reservation
{
    $u = current_user();
    return new Reservation(Database::connect(), (int)$u['id'], is_admin());
}
