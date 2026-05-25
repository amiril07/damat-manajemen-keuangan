<?php
// ============================================================
// includes/auth.php — Manajemen sesi & autentikasi (FIXED)
// ============================================================

require_once __DIR__ . '/db.php';

if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 86400);

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => false, 
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function isLoggedIn(): bool {
    startSecureSession();
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
        $inPages = str_contains(str_replace('\\', '/', $script), '/pages/');
        header('Location: ' . ($inPages ? '/index.php' : 'index.php'));
        exit;
    }
}

function currentUser(): array {
    startSecureSession();
    if (empty($_SESSION['user_id'])) {
        logoutClean();
    }

    $db   = getDB();
    $stmt = $db->prepare('SELECT id, username, monthly_budget FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        logoutClean();
    }

    return $user;
}

function login(string $username, string $password): bool {
    $db   = getDB();
    $stmt = $db->prepare('SELECT id, password FROM users WHERE username = ?');
    $stmt->execute([trim($username)]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        startSecureSession();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        return true;
    }
    return false;
}

// FIXED: Penanganan tipe data budget
function register(string $username, string $password, $budget): bool {
    $db   = getDB();
    
    // Pastikan budget adalah angka
    $budgetFloat = (float)$budget;

    // Cek duplikat username
    $chk  = $db->prepare('SELECT id FROM users WHERE username = ?');
    $chk->execute([trim($username)]);
    if ($chk->fetch()) return false;

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $ins  = $db->prepare(
        'INSERT INTO users (username, password, monthly_budget) VALUES (?, ?, ?)'
    );
    return $ins->execute([trim($username), $hash, $budgetFloat]);
}

function logout(): void {
    startSecureSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
    header('Location: /index.php');
    exit;
}

function logoutClean(): void {
    $_SESSION = [];
    session_destroy();
    $script  = $_SERVER['SCRIPT_FILENAME'] ?? '';
    $inPages = str_contains(str_replace('\\', '/', $script), '/damat/');
    header('Location: ' . ($inPages ? '/index.php' : 'index.php'));
    exit;
}

function updateBudget(int $userId, float $budget): bool {
    $db   = getDB();
    $stmt = $db->prepare('UPDATE users SET monthly_budget = ? WHERE id = ?');
    return $stmt->execute([(float)$budget, $userId]);
}