<?php
// ============================================================
// includes/db.php  — PDO singleton connection
// ============================================================

require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dbHost = defined('DB_HOST') ? DB_HOST : 'mariadb_container';
        $hostParts = explode(':', $dbHost);
        $host = $hostParts[0];
        $port = isset($hostParts[1]) ? $hostParts[1] : '3306';

        // Ambil DB_NAME, DB_USER, DB_PASS dengan fallback aman
        $dbName    = defined('DB_NAME') ? DB_NAME : 'user_amiril';
        $dbUser    = defined('DB_USER') ? DB_USER : 'amiril';
        $dbPass    = defined('DB_PASS') ? DB_PASS : 'rillZFb48A3exDB';
        
        // SOLUSI UTAMA: Jika DB_CHARSET belum ada di config.php, otomatis pakai utf8mb4
        $charset   = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';

        // Tambahkan parameter port ke dalam DSN
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $host, $port, $dbName, $charset
        );
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        
        try {
            $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            // Log detail error ke file untuk debugging, tapi kirim pesan umum ke user
            error_log("Connection Error: " . $e->getMessage());
            die(json_encode(['error' => 'Koneksi database gagal.']));
        }
    }
    return $pdo;
}