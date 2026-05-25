<?php
// ============================================================
// pages/pengaturan.php  —  Pengaturan akun & anggaran
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/transactions.php';

requireLogin();

$msg     = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_budget') {
        $budget = abs((float)($_POST['budget'] ?? 0));
        if (updateBudget($_SESSION['user_id'], $budget)) {
            $msg     = 'Anggaran berhasil diperbarui!';
            $msgType = 'success';
        } else {
            $msg     = 'Gagal memperbarui anggaran.';
            $msgType = 'error';
        }
    }

    if ($action === 'update_name') {
        $name = htmlspecialchars(trim($_POST['name'] ?? ''));
        if ($name) {
            $db   = getDB();
            $stmt = $db->prepare('UPDATE users SET username = ? WHERE id = ?');
            $stmt->execute([$name, $_SESSION['user_id']]);
            $msg     = 'Username berhasil diperbarui!';
            $msgType = 'success';
        }
    }

    if ($action === 'update_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';

        if (strlen($new) < 6) {
            $msg     = 'Password baru minimal 6 karakter.';
            $msgType = 'error';
        } else {
            $db   = getDB();
            $stmt = $db->prepare('SELECT password FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $row  = $stmt->fetch();

            if ($row && password_verify($current, $row['password'])) {
                $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->prepare('UPDATE users SET password = ? WHERE id = ?')
                   ->execute([$hash, $_SESSION['user_id']]);
                $msg     = 'Password berhasil diubah!';
                $msgType = 'success';
            } else {
                $msg     = 'Password saat ini tidak sesuai.';
                $msgType = 'error';
            }
        }
    }
}

$user            = currentUser();
$displayUsername = $user['username'] ?? 'User';
$initials        = strtoupper(substr($displayUsername, 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pengaturan — Damat</title>
  <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon/favicon-32x32.png">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block"/>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
/* ===== BASE: Sidebar/nav desktop ===== */
.sidebar-logo    { display:flex;align-items:center;gap:12px;padding:24px 20px; }
.nav-icon-google { font-size:20px;margin-right:10px;vertical-align:middle; }
.nav-item        { display:flex;align-items:center;padding:11px 20px;text-decoration:none;color:rgba(255,255,255,.65);transition:all .18s;border-radius:8px;margin:1px 8px; }
.nav-item.active { color:#fff;background:rgba(255,255,255,.12); }
.nav-item:hover  { color:#fff;background:rgba(255,255,255,.07); }

/* ===== DESKTOP: Settings layout ===== */
.settings-wrapper { width:100%;max-width:960px;margin:0 auto; }
.settings-grid {
  display:grid;grid-template-columns:1fr 1fr;gap:20px;
}
.settings-grid .card-full { grid-column:1 / -1; }
.settings-card {
  background:#fff;border-radius:var(--r-lg);
  padding:28px 30px;border:1px solid var(--cream-mid);
}
.settings-card-title {
  font-weight:600;font-size:15px;margin:0 0 6px;
  display:flex;align-items:center;gap:8px;color:var(--brown-700);
}
.settings-card-desc {
  font-size:13px;color:var(--text-muted);margin:0 0 20px;line-height:1.55;
}
.settings-card-danger { border:1.5px solid #F4C5B8; }
.settings-card-danger .settings-card-title { color:var(--terracotta); }
.settings-alert { margin-bottom:24px; }
@media (max-width:700px) and (min-width:601px) {
  .settings-grid { grid-template-columns:1fr; }
  .settings-grid .card-full { grid-column:1; }
}

/* ===== DESKTOP: hide mobile shell ===== */
@media (min-width: 601px) {
  .mob-shell { display:none !important; }
  .mob-nav   { display:none !important; }
  .hamburger { display:flex; }
}

/* ===== TABLET 601-1024px ===== */
@media (min-width:601px) and (max-width:1024px) {
  .sidebar { transform:translateX(-100%);transition:transform .3s; }
  .sidebar.open { transform:translateX(0); }
  .main-content { margin-left:0 !important; }
  .hamburger { display:flex !important; }
}
@media (min-width:901px) and (max-width:1024px) {
  .sidebar { transform:none; }
  .main-content { margin-left:240px !important; }
  .hamburger { display:none !important; }
}

/* ===== MOBILE-ONLY ===== */
@media (max-width: 600px) {

  .app-layout { display:none !important; }
  .mob-shell  { display:flex !important; flex-direction:column; min-height:100vh; background:#F5F0E8; }

  /* ---------- HEADER ---------- */
  .mob-header {
    background:#8B5E30;
    padding:32px 18px 24px;
    position:relative;
  }
  .mob-hdr-top {
    display:flex;align-items:flex-start;justify-content:space-between;
    margin-bottom:6px;
  }
  .mob-greeting     { font-size:20px;font-weight:700;color:#fff;line-height:1.2; }
  .mob-greeting-sub { font-size:12px;color:rgba(255,255,255,.6);margin-top:3px; }
  .mob-avatar {
    width:38px;height:38px;border-radius:50%;
    background:rgba(255,255,255,.18);border:2px solid rgba(255,255,255,.3);
    display:flex;align-items:center;justify-content:center;
    color:#fff;font-weight:700;font-size:15px;flex-shrink:0;
  }

  /* ---------- ALERT ---------- */
  .mob-alert {
    margin:12px 14px 0;border-radius:12px;
    padding:12px 14px;font-size:13px;font-weight:500;
    display:flex;align-items:center;gap:8px;
  }
  .mob-alert.success {
    background:#EDF5EB;color:#3A6B35;border:1px solid #C3DFC0;
  }
  .mob-alert.error {
    background:#FEF0E6;color:#8B3A10;border:1px solid #F4C5A8;
  }
  .mob-alert .material-symbols-rounded { font-size:18px;flex-shrink:0; }

  /* ---------- PROFILE CARD (avatar besar) ---------- */
  .mob-profile-card {
    background:#FDFAF5;margin:14px 14px 0;border-radius:16px;
    border:1px solid #EAE4D8;
    padding:20px 16px;
    display:flex;align-items:center;gap:14px;
  }
  .mob-profile-avatar {
    width:52px;height:52px;border-radius:50%;
    background:#8B5E30;
    display:flex;align-items:center;justify-content:center;
    color:#fff;font-size:22px;font-weight:700;flex-shrink:0;
  }
  .mob-profile-name { font-size:16px;font-weight:700;color:#2C1E0F; }
  .mob-profile-role { font-size:12px;color:#9C8070;margin-top:2px; }

  /* ---------- SECTION CARDS ---------- */
  .mob-settings-section {
    background:#FDFAF5;margin:12px 14px 0;border-radius:16px;
    border:1px solid #EAE4D8;overflow:hidden;
  }
  .mob-settings-section-title {
    display:flex;align-items:center;gap:8px;
    padding:14px 16px 12px;
    font-size:13px;font-weight:700;color:#2C1E0F;
    border-bottom:1px solid #F0EAE0;
  }
  .mob-settings-section-title .material-symbols-rounded { font-size:18px;color:#8B5E30; }

  /* ---------- FORM ROWS ---------- */
  .mob-form-row { padding:14px 16px;border-bottom:1px solid #F0EAE0; }
  .mob-form-row:last-child { border-bottom:none; }
  .mob-form-lbl {
    font-size:11.5px;font-weight:600;color:#9C8070;
    text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;
  }
  .mob-form-input {
    width:100%;padding:11px 14px;
    border:1.5px solid #D4C9B8;border-radius:10px;
    background:#fff;font-size:15px;color:#2C1E0F;
    outline:none;transition:border-color .18s;
    font-family:inherit;box-sizing:border-box;
  }
  .mob-form-input:focus { border-color:#8B5E30; }

  /* ---------- BUTTONS ---------- */
  .mob-btn-primary {
    display:flex;align-items:center;justify-content:center;gap:6px;
    width:100%;padding:12px;border-radius:10px;
    background:#8B5E30;color:#fff;border:none;
    font-size:14px;font-weight:600;cursor:pointer;
    font-family:inherit;transition:background .15s;margin-top:14px;
  }
  .mob-btn-primary:active { background:#6E4420; }
  .mob-btn-primary .material-symbols-rounded { font-size:18px; }

  .mob-btn-danger {
    display:flex;align-items:center;justify-content:center;gap:6px;
    width:100%;padding:12px;border-radius:10px;
    background:#FEF0E6;color:#C4600A;border:1.5px solid #F4C5A8;
    font-size:14px;font-weight:600;cursor:pointer;
    font-family:inherit;transition:background .15s;text-decoration:none;
  }
  .mob-btn-danger:active { background:#F4C5A8; }
  .mob-btn-danger .material-symbols-rounded { font-size:18px; }

  /* ---------- BOTTOM NAV ---------- */
  .mob-nav {
    position:fixed;bottom:0;left:0;right:0;
    background:#FDFAF5;border-top:1px solid #EAE4D8;
    z-index:300;display:flex;align-items:center;
    height:60px;padding:0 6px;
    padding-bottom:env(safe-area-inset-bottom);
  }
  .mob-nav-i {
    flex:1;display:flex;flex-direction:column;align-items:center;
    justify-content:center;gap:2px;text-decoration:none;
    padding:4px;color:#9C8070;transition:color .15s;
  }
  .mob-nav-i .material-symbols-rounded { font-size:22px;display:block; }
  .mob-nav-i span.lbl { font-size:9.5px;font-weight:500; }
  .mob-nav-i.on { color:#6E4420; }

  /* ── DamGPT bottom nav icon & badge HOT ── */
  .mob-nav-damgpt { position:relative;display:flex;flex-direction:column;align-items:center; }
  .mob-nav-hot {
    position:absolute;top:-5px;right:-8px;
    background:#DB1A1A;
    color:#fff;font-size:6.5px;font-weight:800;letter-spacing:.3px;
    padding:1px 4px;border-radius:99px;line-height:1.6;text-transform:uppercase;
    white-space:nowrap;
  }
  .mob-nav-fab {
    flex:0 0 56px;display:flex;align-items:center;
    justify-content:center;position:relative;margin-top:-22px;
  }
  .mob-fab {
    width:54px;height:54px;border-radius:50%;
    background:#8B5E30;
    border:3px solid #FDFAF5;
    display:flex;align-items:center;justify-content:center;
    color:#fff;text-decoration:none;
  }
  .mob-fab .material-symbols-rounded { font-size:25px; }

  .mob-foot { height:72px; }
  input,select,textarea { font-size:16px !important; }
}
  </style>
</head>
<body>

<div id="sidebar-overlay"
     style="display:none;position:fixed;inset:0;background:rgba(44,32,24,.4);z-index:99"
     onclick="this.style.display='none';document.querySelector('.sidebar').classList.remove('open')"></div>

<!-- ══════════════════════════════════════════════════
     MOBILE SHELL  — hanya tampil di ≤ 600px
     ══════════════════════════════════════════════════ -->
<div class="mob-shell" style="display:none">

  <!-- HEADER -->
  <div class="mob-header">
    <div class="mob-hdr-top">
      <div>
        <div class="mob-greeting">Pengaturan</div>
        <div class="mob-greeting-sub">Kelola akun & anggaran kamu</div>
      </div>
      <div class="mob-avatar"><?= $initials ?></div>
    </div>
  </div>

  <!-- ALERT -->
  <?php if ($msg): ?>
  <div class="mob-alert <?= $msgType ?>">
    <span class="material-symbols-rounded">
      <?= $msgType === 'success' ? 'check_circle' : 'error' ?>
    </span>
    <?= htmlspecialchars($msg) ?>
  </div>
  <?php endif; ?>

  <!-- PROFIL CARD -->
  <div class="mob-profile-card">
    <div class="mob-profile-avatar"><?= $initials ?></div>
    <div>
      <div class="mob-profile-name"><?= htmlspecialchars($displayUsername) ?></div>
      <div class="mob-profile-role">Anggota Damat</div>
    </div>
  </div>

  <!-- UBAH USERNAME -->
  <div class="mob-settings-section">
    <div class="mob-settings-section-title">
      <span class="material-symbols-rounded">person</span> Profil
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="update_name">
      <div class="mob-form-row">
        <div class="mob-form-lbl">Username</div>
        <input type="text" name="name" class="mob-form-input"
               value="<?= htmlspecialchars($displayUsername) ?>" required>
      </div>
      <div class="mob-form-row">
        <button type="submit" class="mob-btn-primary">
          <span class="material-symbols-rounded">save</span> Simpan Nama Baru
        </button>
      </div>
    </form>
  </div>

  <!-- GANTI PASSWORD -->
  <div class="mob-settings-section">
    <div class="mob-settings-section-title">
      <span class="material-symbols-rounded">lock</span> Keamanan
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="update_password">
      <div class="mob-form-row">
        <div class="mob-form-lbl">Password Saat Ini</div>
        <input type="password" name="current_password" class="mob-form-input"
               placeholder="••••••••" required>
      </div>
      <div class="mob-form-row">
        <div class="mob-form-lbl">Password Baru</div>
        <input type="password" name="new_password" class="mob-form-input"
               placeholder="Min. 6 karakter" required>
      </div>
      <div class="mob-form-row">
        <button type="submit" class="mob-btn-primary">
          <span class="material-symbols-rounded">lock_reset</span> Ganti Password
        </button>
      </div>
    </form>
  </div>

  <!-- ANGGARAN BULANAN -->
  <div class="mob-settings-section">
    <div class="mob-settings-section-title">
      <span class="material-symbols-rounded">payments</span> Anggaran Bulanan
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="update_budget">
      <div class="mob-form-row">
        <div class="mob-form-lbl">Anggaran (Rp)</div>
        <input type="number" name="budget" class="mob-form-input"
               value="<?= (float)($user['monthly_budget'] ?? 0) ?>"
               min="0" step="1000" placeholder="0" required>
      </div>
      <div class="mob-form-row">
        <button type="submit" class="mob-btn-primary">
          <span class="material-symbols-rounded">update</span> Perbarui Anggaran
        </button>
      </div>
    </form>
  </div>

  <!-- KELUAR -->
  <div class="mob-settings-section" style="margin-bottom:0;">
    <div class="mob-settings-section-title" style="color:#C4600A;">
      <span class="material-symbols-rounded" style="color:#C4600A;">warning</span> Sesi Aktif
    </div>
    <div class="mob-form-row">
      <a href="../logout.php" class="mob-btn-danger">
        <span class="material-symbols-rounded">logout</span> Keluar dari Akun
      </a>
    </div>
  </div>

  <div class="mob-foot"></div>
</div>
<!-- end mob-shell -->

<!-- BOTTOM NAV (mobile) -->
<nav class="mob-nav" style="display:none">
  <a href="dashboard.php" class="mob-nav-i">
    <span class="material-symbols-rounded">home</span>
    <span class="lbl">Beranda</span>
  </a>
  <a href="transaksi.php" class="mob-nav-i">
    <span class="material-symbols-rounded">receipt_long</span>
    <span class="lbl">Transaksi</span>
  </a>
  <div class="mob-nav-fab">
    <a href="tambah.php" class="mob-fab">
      <span class="material-symbols-rounded">add</span>
    </a>
  </div>
  <a href="damgpt.php" class="mob-nav-i mob-nav-damgpt">
    <span style="position:relative;display:inline-block;">
      <span class="material-symbols-rounded" style="font-size:22px;font-variation-settings:'FILL' 1,'wght' 600;display:block;">assistant</span>
      <span class="mob-nav-hot">HOT</span>
    </span>
    <span class="lbl">DamGPT</span>
  </a>
  <a href="pengaturan.php" class="mob-nav-i on">
    <span class="material-symbols-rounded" style="font-variation-settings:'FILL' 1">person</span>
    <span class="lbl">Profil</span>
  </a>
</nav>

<!-- ══════════════════════════════════════════════════
     DESKTOP LAYOUT  — hanya tampil di > 600px
     ══════════════════════════════════════════════════ -->
<div class="app-layout">
  <aside class="sidebar">
    <div class="sidebar-logo">
      <img src="../assets/favicon/favicon-32x32.png" alt="" style="width:26px;border-radius:5px">
      <span style="font-weight:700;font-size:20px;color:#fff">Damat</span>
    </div>
    <nav class="sidebar-nav">
      <a href="dashboard.php"  class="nav-item"><span class="material-symbols-rounded nav-icon-google">grid_view</span>Dashboard</a>
      <a href="transaksi.php"  class="nav-item"><span class="material-symbols-rounded nav-icon-google">history</span>Riwayat</a>
      <a href="tambah.php"     class="nav-item"><span class="material-symbols-rounded nav-icon-google">add_circle</span>Tambah</a>
      <a href="damgpt.php"     class="nav-item"><span class="material-symbols-rounded nav-icon-google" style="font-variation-settings:'FILL' 1,'wght' 600;">assistant</span>DamGPT<span class="badge-hot">HOT</span></a>
      <a href="pengaturan.php" class="nav-item active"><span class="material-symbols-rounded nav-icon-google">settings</span>Pengaturan</a>
    </nav>
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-avatar"><?= $initials ?></div>
        <div class="sidebar-user-info">
          <div class="sidebar-user-name"><?= htmlspecialchars($displayUsername) ?></div>
          <div style="font-size:11px;opacity:.6">Anggota Damat</div>
        </div>
      </div>
      <a href="../logout.php" class="nav-item" style="color:rgba(255,255,255,.5)">
        <span class="material-symbols-rounded nav-icon-google" style="font-size:18px">logout</span>Keluar
      </a>
    </div>
  </aside>

  <div class="main-content">
    <header class="topbar">
      <button class="hamburger" style="display:none;background:none;border:none;padding:6px;cursor:pointer">
        <span class="material-symbols-rounded" style="font-size:22px;vertical-align:middle">menu</span>
      </button>
      <h1 class="topbar-title">Pengaturan</h1>
      <div></div>
    </header>

    <main class="page-body">
      <div class="settings-wrapper">
        <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?> settings-alert">
          <span class="material-symbols-rounded" style="font-size:18px;vertical-align:middle;margin-right:6px">
            <?= $msgType === 'success' ? 'check_circle' : 'error' ?>
          </span>
          <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; ?>

        <div class="settings-grid">
          <div class="settings-card">
            <h3 class="settings-card-title">
              <span class="material-symbols-rounded" style="color:var(--brown-500)">person</span> Profil
            </h3>
            <p class="settings-card-desc">Ubah nama tampilan akun kamu.</p>
            <form method="POST">
              <input type="hidden" name="action" value="update_name">
              <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="name" class="form-control"
                       value="<?= htmlspecialchars($displayUsername) ?>" required>
              </div>
              <button type="submit" class="btn btn-primary">Simpan Nama Baru</button>
            </form>
          </div>

          <div class="settings-card">
            <h3 class="settings-card-title">
              <span class="material-symbols-rounded" style="color:var(--brown-500)">lock</span> Keamanan
            </h3>
            <p class="settings-card-desc">Ganti password untuk menjaga keamanan akunmu.</p>
            <form method="POST">
              <input type="hidden" name="action" value="update_password">
              <div class="form-group">
                <label class="form-label">Password Saat Ini</label>
                <input type="password" name="current_password" class="form-control"
                       placeholder="••••••••" required>
              </div>
              <div class="form-group">
                <label class="form-label">Password Baru</label>
                <input type="password" name="new_password" class="form-control"
                       placeholder="Min. 6 karakter" required>
              </div>
              <button type="submit" class="btn btn-primary">Ganti Password</button>
            </form>
          </div>

          <div class="settings-card card-full">
            <h3 class="settings-card-title">
              <span class="material-symbols-rounded" style="color:var(--brown-500)">payments</span> Anggaran Bulanan
            </h3>
            <p class="settings-card-desc">Anggaran digunakan untuk menghitung peringatan impulsif saat pengeluaran mendekati batas.</p>
            <form method="POST" style="display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap;">
              <input type="hidden" name="action" value="update_budget">
              <div class="form-group" style="flex:1;min-width:200px;margin-bottom:0;">
                <label class="form-label">Anggaran (Rp)</label>
                <input type="number" name="budget" class="form-control"
                       value="<?= (float)($user['monthly_budget'] ?? 0) ?>"
                       min="0" step="1000" placeholder="0" required>
              </div>
              <button type="submit" class="btn btn-primary" style="white-space:nowrap;margin-bottom:0;">
                Perbarui Anggaran
              </button>
            </form>
          </div>

          <div class="settings-card settings-card-danger card-full">
            <h3 class="settings-card-title">
              <span class="material-symbols-rounded" style="color:var(--brown-500)">warning</span> Zona Keluar
            </h3>
            <p class="settings-card-desc">Pastikan kamu mengingat password kamu sebelum keluar dari sesi ini.</p>
            <a href="../logout.php" class="btn btn-danger btn-sm">Keluar dari Akun</a>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<script src="../assets/js/app.js"></script>
<script>
const IS_MOBILE = window.innerWidth <= 600;

if (IS_MOBILE) {
  document.querySelector('.mob-shell').style.display = 'flex';
  document.querySelector('.mob-nav').style.display   = 'flex';
  document.querySelector('.app-layout').style.display = 'none';
} else {
  document.querySelector('.mob-shell').style.display  = 'none';
  document.querySelector('.mob-nav').style.display    = 'none';
  document.querySelector('.app-layout').style.display = '';
}

// Hamburger
document.querySelector('.hamburger')?.addEventListener('click', () => {
  document.querySelector('.sidebar')?.classList.toggle('open');
  document.getElementById('sidebar-overlay').style.display = 'block';
});
</script>
</body>
</html>