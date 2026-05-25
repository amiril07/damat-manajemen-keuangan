<?php
// ============================================================
// index.php  —  Login & Register (Split-Screen Layout)
// ============================================================
require_once __DIR__ . '/damat/includes/auth.php';

startSecureSession();

if (isLoggedIn()) {
    header('Location: /damat/pages/dashboard.php');
    exit;
}

$error   = '';
$success = '';
$mode    = $_GET['mode'] ?? 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $username = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (login($username, $password)) {
            header('Location: /damat/pages/dashboard.php');
            exit;
        }
        $error = 'Email atau password salah.';
        $mode  = 'login';

    } elseif ($action === 'register') {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $budget   = $_POST['budget'] ?? 0;

        if (!$email || !$password) {
            $error = 'Email dan password wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid.';
        } elseif (strlen($password) < 6) {
            $error = 'Password minimal 6 karakter.';
        } else {
            if (register($email, $password, $budget)) {
                $success = 'Akun berhasil dibuat! Silakan login.';
                $mode    = 'login';
            } else {
                $error = 'Email sudah terdaftar. Gunakan email lain.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Damat — Manajemen Keuangan Mikro</title>
  <link rel="icon" type="image/png" sizes="32x32" href="../damat/assets/favicon/favicon-32x32.png">
  
  <link rel="icon" type="image/png" sizes="192x192" href="assets/favicon/favicon-192x192.png">
  <link rel="icon" type="image/png" sizes="512x512" href="assets/favicon/favicon-512x512.png">
  
  <!--<link rel="apple-touch-icon" sizes="180x180" href="assets/favicon/apple-touch-icon.png">-->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
  <link rel="manifest" href="manifest.json">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; }

    html, body {
      height: 100%;
      margin: 0;
      padding: 0;
      font-family: 'Inter', sans-serif;
      background: #faf7f4;
    }

    /* ── Split Layout ── */
    .split-layout {
      display: flex;
      min-height: 100vh;
    }

    /* ── LEFT PANEL ── */
    .split-left {
      flex: 0 0 38%;
      position: relative;
      overflow: hidden;
      display: none; /* hidden on mobile, shown on md+ */
    }

    /* Background image/video fills the left panel */
    .split-left-media {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    /* Dark overlay for readability */
    .split-left-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(
        160deg,
        rgba(62, 40, 20, 0.72) 0%,
        rgba(120, 72, 20, 0.50) 60%,
        rgba(62, 40, 20, 0.80) 100%
      );
    }

    /* Content on top of the image */
    .split-left-content {
      position: relative;
      z-index: 1;
      height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 48px 52px;
    }

    .split-brand-name {
      font-size: 30px;
      font-weight: 800;
      color: #fff;
      letter-spacing: -0.5px;
    }

    .split-tagline {
      max-width: 380px;
    }

    .split-tagline h1 {
      font-size: 38px;
      font-weight: 800;
      color: #fff;
      line-height: 1.2;
      margin: 0 0 16px;
      letter-spacing: -1px;
    }

    .split-tagline p {
      font-size: 16px;
      color: rgba(255,255,255,0.75);
      line-height: 1.65;
      margin: 0;
    }

    .split-stats {
      display: flex;
      gap: 32px;
    }

    .split-stat {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .split-stat-num {
      font-size: 26px;
      font-weight: 800;
      color: #f5c97a;
      letter-spacing: -0.5px;
    }

    .split-stat-label {
      font-size: 12px;
      color: rgba(255,255,255,0.65);
      text-transform: uppercase;
      letter-spacing: 0.8px;
    }

    /* ── RIGHT PANEL ── */
    .split-right {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 24px;
      background: #faf7f4;
      overflow-y: auto;
    }

    .auth-box {
      width: 100%;
      max-width: 480px;
    }

    /* Mobile: show compact logo on right side */
    .mobile-brand {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 36px;
    }

    .mobile-brand-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: var(--brown-500, #92400e);
    }

    .mobile-brand-name {
      font-size: 20px;
      font-weight: 800;
      color: var(--brown-700, #78350f);
      letter-spacing: -0.5px;
    }

    .auth-title {
      font-size: 26px;
      font-weight: 800;
      color: var(--brown-700, #78350f);
      margin: 0 0 6px;
      letter-spacing: -0.5px;
    }

    .auth-sub {
      font-size: 14px;
      color: var(--text-muted, #78716c);
      margin: 0 0 28px;
      line-height: 1.5;
    }

    .alert {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 12px 16px;
      border-radius: 12px;
      font-size: 14px;
      margin-bottom: 20px;
    }
    .alert-error   { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
    .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }

    .form-group {
      margin-bottom: 16px;
    }

    .form-label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: var(--brown-700, #78350f);
      margin-bottom: 6px;
    }

    .form-control {
      width: 100%;
      padding: 11px 14px;
      border: 1.5px solid #e7e0d8;
      border-radius: 12px;
      font-size: 14px;
      font-family: 'Inter', sans-serif;
      background: #fff;
      color: #1c1917;
      transition: border-color 0.15s;
      outline: none;
    }

    .form-control:focus {
      border-color: var(--brown-500, #92400e);
      box-shadow: 0 0 0 3px rgba(146, 64, 14, 0.1);
    }

    .form-control::placeholder {
      color: #c4b5a5;
    }

    .btn-primary {
      width: 100%;
      padding: 13px 20px;
      background: var(--brown-600, #92400e);
      color: #fff;
      border: none;
      border-radius: 12px;
      font-size: 15px;
      font-weight: 600;
      font-family: 'Inter', sans-serif;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: background 0.15s, transform 0.1s;
      margin-top: 8px;
    }

    .btn-primary:hover  { background: #7c2d12; }
    .btn-primary:active { transform: scale(0.98); }

    .auth-switch {
      text-align: center;
      margin-top: 24px;
      font-size: 14px;
      color: var(--text-muted, #78716c);
    }

    .auth-switch a {
      color: var(--brown-600, #92400e);
      font-weight: 600;
      text-decoration: none;
    }

    .auth-switch a:hover { color: var(--brown-800); }

    .demo-box {
      margin-top: 20px;
      text-align: center;
      padding: 12px 16px;
      background: #f5ede4;
      border-radius: 12px;
      border: 1px solid #e8d8c8;
    }

    .demo-box p {
      font-size: 12.5px;
      color: var(--text-muted, #78716c);
      margin: 0;
    }

    /* ── Responsive ── */
    @media (min-width: 768px) {
      .split-left  { display: flex; flex-direction: column; }
      .split-right { padding: 48px 64px; }
      .mobile-brand { display: none; }
    }

    @media (min-width: 1024px) {
      .split-right { padding: 56px 80px; }
    }

    /* Divider */
    .divider {
      border: none;
      border-top: 1px solid #e8d8c8;
      margin: 20px 0;
    }
  </style>
</head>
<body>

<div class="split-layout">

  <!-- ═══════════ LEFT PANEL ═══════════ -->
  <div class="split-left">

    <!--
      OPSI A — Gambar statis (ganti URL dengan foto milik sendiri):
      <img src="assets/images/auth-bg.jpg" alt="" class="split-left-media">

      OPSI B — Video background (uncomment di bawah):
    -->
    <img
      src="https://pascasarjana.umsu.ac.id/wp-content/uploads/2023/08/istilah-dalam-manajemen-keuangan.jpg"
      alt=""
      class="split-left-media"
    >

    <!--
    <video class="split-left-media" autoplay muted loop playsinline>
      <source src="assets/video/finance-bg.mp4" type="video/mp4">
    </video>
    -->

    <div class="split-left-overlay"></div>

    <div class="split-left-content">
      <!-- Brand mark -->
        <span class="split-brand-name">Damat</span>

      <!-- Hero tagline -->
      <div class="split-tagline">
        <p>Daftar sekali, manfaatkan selamanya</p>
        <h1>Kelola keuanganmu dengan cerdas</h1>
      </div>
    </div>
  </div>

  <!-- ═══════════ RIGHT PANEL ═══════════ -->
  <div class="split-right">
    <div class="auth-box">

      <!-- Logo (hanya muncul di mobile) -->
      <div class="mobile-brand">
        <div class="mobile-brand-dot"></div>
        <span class="mobile-brand-name">Damat</span>
      </div>

      <!-- Alert: Error -->
      <?php if ($error): ?>
        <div class="alert alert-error">
          <span class="material-symbols-rounded" style="font-size:18px;flex-shrink:0">error</span>
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <!-- Alert: Success -->
      <?php if ($success): ?>
        <div class="alert alert-success">
          <span class="material-symbols-rounded" style="font-size:18px;flex-shrink:0">check_circle</span>
          <?= htmlspecialchars($success) ?>
        </div>
      <?php endif; ?>

      <?php if ($mode === 'login'): ?>
      <!-- ─── LOGIN FORM ─── -->
      <h2 class="auth-title">Selamat datang kembali</h2>
      <p class="auth-sub">Silakan isi data akun Anda untuk melanjutkan ke halaman Damat</p>

      <form method="POST" autocomplete="on">
        <input type="hidden" name="action" value="login">

        <div class="form-group">
          <label class="form-label" for="login-email">Email</label>
          <input
            type="email" id="login-email" name="email"
            class="form-control"
            placeholder="kamu@email.com"
            required
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="login-pass">Password</label>
          <input
            type="password" id="login-pass" name="password"
            class="form-control"
            placeholder="••••••••"
            required
          >
        </div>

        <button type="submit" class="btn-primary">
          <span class="material-symbols-rounded" style="font-size:20px">login</span>
          Masuk
        </button>
      </form>

      <p class="auth-switch">
        Belum punya akun?
        <a href="?mode=register">Daftar sekarang</a>
      </p>

      <hr class="divider">

      <?php else: ?>
      <!-- ─── REGISTER FORM ─── -->
      <h2 class="auth-title">Daftar Akun</h2>
      <p class="auth-sub">Silakan daftar akun Anda untuk mengakses fitur Damat</p>

      <form method="POST" autocomplete="off">
        <input type="hidden" name="action" value="register">

        <div class="form-group">
          <label class="form-label" for="reg-email">Email</label>
          <input
            type="email" id="reg-email" name="email"
            class="form-control"
            placeholder="email@gmail.com"
            required
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="reg-pass">Password</label>
          <input
            type="password" id="reg-pass" name="password"
            class="form-control"
            placeholder="Min. 6 karakter"
            required
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="reg-budget">Anggaran Bulanan (Rp)</label>
          <input
            type="number" id="reg-budget" name="budget"
            class="form-control"
            placeholder="Contoh: 3.000.000"
            min="0" step="50000"
            value="<?= htmlspecialchars($_POST['budget'] ?? '') ?>"
          >
        </div>

        <button type="submit" class="btn-primary">
          <span class="material-symbols-rounded" style="font-size:20px">person_add</span>
          Buat Akun
        </button>
      </form>

      <p class="auth-switch">
        Sudah punya akun?
        <a href="?mode=login">Masuk ke akun</a>
      </p>
      <?php endif; ?>

    </div>
  </div>
  <!-- end split-right -->

</div>
<!-- end split-layout -->

<script src="assets/js/app.js"></script>
</body>
</html>