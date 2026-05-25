<?php
// ============================================================
// includes/config.php  — Konfigurasi koneksi database & API
// ============================================================

define('DB_HOST', 'mariadb_container');
define('DB_NAME', 'user_amiril');
define('DB_USER', 'amiril');
define('DB_PASS', 'rillZFb48A3exDB');
define('DB_PORT', '3306');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'Damat');
define('APP_VERSION', '1.0.0');

// Durasi sesi: 2 jam
define('SESSION_LIFETIME', 7200);

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Error reporting (Aktif untuk mempermudah debugging di localhost)
error_reporting(E_ALL);
ini_set('display_errors', 1);
