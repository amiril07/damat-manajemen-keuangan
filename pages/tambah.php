<?php
// ============================================================
// pages/tambah.php  —  Form tambah transaksi + Upload Struk OCR
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/transactions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_tx') {
        $type     = in_array($_POST['type'] ?? '', ['income','expense']) ? $_POST['type'] : 'expense';
        $category = htmlspecialchars(trim($_POST['category'] ?? ''));
        $amount   = abs((float)($_POST['amount'] ?? 0));
        $note     = htmlspecialchars(trim($_POST['note'] ?? ''));
        $date     = $_POST['date'] ?? date('Y-m-d');

        if ($amount > 0 && $category) {
            addTransaction($_SESSION['user_id'], $type, $category, $amount, $note, $date);
            header('Location: dashboard.php?added=1');
            exit;
        }
    }
}

$user    = currentUser();
$summary = getMonthlySummary($user['id']);
$budget  = (float)($user['monthly_budget'] ?? 0);
$expense = (float)($summary['expense'] ?? 0);
$income  = (float)($summary['income']  ?? 0);

$pct       = $budget > 0 ? min(($expense / $budget) * 100, 100) : 0;
$isDanger  = $pct >= 80;
$isWarning = $pct >= 60 && !$isDanger;
$barClass  = $isDanger ? 'danger' : ($isWarning ? 'warn' : '');
$barColor  = $isDanger ? '#C4600A' : ($isWarning ? '#D97706' : '#5F8B5A');

$userName = $user['username'] ?? 'User';
$initials = strtoupper(substr($userName, 0, 1));

function fmtRp(float $n): string { return 'Rp '.number_format($n,0,',','.'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tambah Transaksi — Damat</title>
  <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon/favicon-32x32.png">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block"/>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
/* ── Sidebar/Nav base ── */
.sidebar-logo    { display:flex;align-items:center;gap:12px;padding:24px 20px; }
.nav-icon-google { font-size:20px;margin-right:10px;vertical-align:middle; }
.nav-item        { display:flex;align-items:center;padding:11px 20px;text-decoration:none;color:rgba(255,255,255,.65);transition:all .18s;border-radius:8px;margin:1px 8px; }
.nav-item.active { color:#fff;background:rgba(255,255,255,.12); }
.nav-item:hover  { color:#fff;background:rgba(255,255,255,.07); }

/* ── Desktop layout ── */
.tambah-wrapper { width:100%;max-width:960px;margin:0 auto; }
.tambah-grid    { display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start; }
.tambah-wrapper .tx-form-card { max-width:100%;width:100%; }
.desk-budget-wrap { background:var(--surface);border-radius:var(--r-lg);padding:18px 24px;border:1px solid var(--border-light);margin-bottom:20px; }
.desk-budget-label { display:flex;justify-content:space-between;font-size:13px;color:var(--text-muted);margin-bottom:9px; }
.desk-budget-label strong { color:var(--text-primary); }
.desk-btrack { height:8px;background:var(--border-light);border-radius:99px;overflow:hidden; }
.desk-bfill  { height:100%;border-radius:99px;transition:width .4s;background:var(--sage); }
.desk-bfill.warn   { background:var(--warning); }
.desk-bfill.danger { background:var(--terra); }
.type-btn { display:flex;align-items:center;justify-content:center;gap:8px; }
.type-btn .material-symbols-rounded { font-size:20px; }

/* ── Desktop Upload Struk Card ── */
.struk-card {
  background:var(--surface);border-radius:var(--r-lg);
  border:1px solid var(--border-light);overflow:hidden;
}
.struk-card-header {
  padding:16px 20px 12px;border-bottom:1px solid var(--border-light);
  display:flex;align-items:center;gap:10px;
}
.struk-card-icon {
  width:36px;height:36px;border-radius:10px;background:var(--brown-50);
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.struk-card-icon .material-symbols-rounded { font-size:20px;color:var(--brown-500); }
.struk-card-title { font-size:14px;font-weight:700;color:var(--brown-700); }
.struk-card-sub   { font-size:12px;color:var(--text-muted);margin-top:1px; }
.struk-card-body  { padding:16px 20px; }

/* Dropzone */
.dropzone {
  border:2px dashed var(--border);border-radius:var(--r-md);
  padding:28px 20px;text-align:center;cursor:pointer;transition:all .2s;
  background:var(--bg);position:relative;
}
.dropzone:hover, .dropzone.drag-over {
  border-color:var(--brown-400);background:var(--brown-50);
}
.dropzone input[type="file"] { position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%; }
.dropzone-icon { font-size:36px;color:var(--brown-300);margin-bottom:8px; }
.dropzone-label { font-size:13.5px;font-weight:600;color:var(--text-secondary);margin-bottom:4px; }
.dropzone-sub   { font-size:12px;color:var(--text-muted); }

/* Preview image */
.struk-preview-wrap { position:relative;margin-top:12px;display:none; }
.struk-preview-img  { width:100%;max-height:200px;object-fit:cover;border-radius:var(--r-md);border:1px solid var(--border-light); }
.struk-preview-clear { position:absolute;top:8px;right:8px;width:28px;height:28px;border-radius:50%;background:rgba(0,0,0,.55);border:none;color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer; }
.struk-preview-clear .material-symbols-rounded { font-size:16px; }

/* OCR result panel */
.ocr-result-panel {
  margin-top:14px;background:var(--bg);border-radius:var(--r-md);
  border:1px solid var(--border-light);padding:14px 16px;display:none;
}
.ocr-result-panel .ocr-row { display:flex;justify-content:space-between;align-items:center;font-size:13px;padding:5px 0;border-bottom:1px solid var(--border-light); }
.ocr-result-panel .ocr-row:last-child { border-bottom:none; }
.ocr-result-panel .ocr-lbl  { color:var(--text-muted);font-weight:500; }
.ocr-result-panel .ocr-val  { font-weight:600;color:var(--text-primary);text-align:right;max-width:60%; }
.ocr-confidence { display:flex;align-items:center;gap:6px;margin-top:10px;font-size:12px; }
.ocr-confidence-bar { flex:1;height:5px;background:var(--border-light);border-radius:99px;overflow:hidden; }
.ocr-confidence-fill { height:100%;border-radius:99px;background:var(--sage);transition:width .4s; }

/* Scan button */
.btn-scan {
  width:100%;padding:12px;border-radius:var(--r-md);border:none;
  background:linear-gradient(135deg,var(--brown-500),var(--brown-700));
  color:#fff;font-family:var(--font-body);font-size:14px;font-weight:700;
  cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;
  transition:all .18s;margin-top:12px;
}
.btn-scan:hover { filter:brightness(1.08); }
.btn-scan:active { transform:scale(.98); }
.btn-scan:disabled { background:var(--brown-200);cursor:not-allowed; }
.btn-scan .material-symbols-rounded { font-size:20px; }

/* Loading spinner */
.scan-spinner { display:inline-block;width:18px;height:18px;border:2.5px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }

/* Apply to form button */
.btn-apply-ocr {
  width:100%;padding:10px;border-radius:var(--r-md);border:1.5px solid var(--brown-400);
  background:var(--brown-50);color:var(--brown-700);
  font-family:var(--font-body);font-size:13.5px;font-weight:700;
  cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;
  transition:all .18s;margin-top:8px;
}
.btn-apply-ocr:hover { background:var(--brown-100); }

/* Tips list */
.tips-list { display:flex;flex-direction:column;gap:14px; }
.tip-item  { background:var(--surface);border-radius:var(--r-lg);padding:18px 20px;border:1px solid var(--border-light);display:flex;gap:14px;align-items:flex-start; }
.tip-icon  { flex-shrink:0;width:38px;height:38px;border-radius:10px;background:var(--brown-50);display:flex;align-items:center;justify-content:center; }
.tip-icon .material-symbols-rounded { font-size:20px;color:var(--brown-500); }
.tip-body strong { font-size:13.5px;font-weight:600;color:var(--brown-700);display:block;margin-bottom:4px; }
.tip-body p      { font-size:13px;color:var(--text-muted);line-height:1.55;margin:0; }

/* OCR error/info messages */
.ocr-msg { margin-top:10px;padding:10px 13px;border-radius:var(--r-md);font-size:13px;font-weight:500;display:flex;align-items:flex-start;gap:8px;display:none; }
.ocr-msg.error   { background:#FEF0E6;color:#8B3A10;border:1px solid #F4C5A0; }
.ocr-msg.warning { background:#FFFBEB;color:#7c5a00;border:1px solid #FDE68A; }
.ocr-msg.success { background:#EDF5EB;color:#3A6B35;border:1px solid #C3DEC0; }
.ocr-msg .material-symbols-rounded { font-size:18px;flex-shrink:0;margin-top:1px; }

/* ══════════════════════════════
   MOBILE ≤ 600px
   ══════════════════════════════ */
@media (max-width: 600px) {
  .app-layout  { display:none !important; }
  .mob-shell   { display:flex !important;flex-direction:column;min-height:100vh;background:var(--bg);padding-bottom:76px; }

  /* Header */
  .mob-header  { background:#8B5E30;padding:32px 18px 26px;position:relative;overflow:hidden; }
  .mob-hdr-top { display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;position:relative;z-index:1; }
  .mob-hdr-back { width:36px;height:36px;border-radius:50%;background:#3D2510;display:flex;align-items:center;justify-content:center;color:#fff;cursor:pointer;flex-shrink:0;text-decoration:none; }
  .mob-hdr-back .material-symbols-rounded { font-size:20px; }
  .mob-hdr-title { font-size:17px;font-weight:700;color:#fff;flex:1;text-align:center; }
  .mob-avatar { width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.18);border:2px solid rgba(255,255,255,.3);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:14px;flex-shrink:0;text-decoration:none; }

  /* Mini stat pills */
  .mob-stat-row { display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;position:relative;z-index:1;margin-bottom:10px; }
  .mob-stat-pill { background:#3D2510;border-radius:12px;padding:4px 5px 8px 8px;display:flex;flex-direction:column;gap:3px; }
  .mob-stat-pill-lbl { font-size:9.5px;color:rgba(255,255,255,.6);font-weight:600;text-transform:uppercase;letter-spacing:.06em; }
  .mob-stat-pill-val { font-size:13px;font-weight:700;color:#fff;letter-spacing:-.3px; }
  .mob-stat-pill-val.danger { color:#FFBE8A; }
  .mob-stat-pill-val.warn   { color:#FFE999; }

  /* Budget bar */
  .mob-budget { background:var(--surface);margin:0 14px;border-radius:14px;padding:13px 15px;border:1px solid var(--border-light);transform:translateY(-16px);margin-bottom:-2px; }
  .mob-budget-hdr { display:flex;justify-content:space-between;align-items:center;margin-bottom:7px; }
  .mob-budget-lbl { font-size:12.5px;font-weight:600;color:var(--text-primary); }
  .mob-budget-pct { font-size:13px;font-weight:700; }
  .mob-btrack { height:7px;background:var(--border-light);border-radius:99px;overflow:hidden; }
  .mob-bfill  { height:100%;border-radius:99px;background:var(--sage);transition:width .6s; }
  .mob-bfill.warn   { background:var(--warning); }
  .mob-bfill.danger { background:var(--terra); }
  .mob-budget-meta { display:flex;justify-content:space-between;margin-top:6px;font-size:11px;color:var(--text-muted); }

  /* Mode switcher tabs */
  .mob-mode-tabs { display:flex;margin:14px 14px 0;background:var(--surface);border-radius:12px;padding:4px;border:1px solid var(--border-light); }
  .mob-mode-tab  { flex:1;padding:9px 6px;border-radius:9px;border:none;background:none;font-family:var(--font-body);font-size:13px;font-weight:600;color:var(--text-muted);cursor:pointer;transition:all .18s;display:flex;align-items:center;justify-content:center;gap:6px; }
  .mob-mode-tab .material-symbols-rounded { font-size:17px; }
  .mob-mode-tab.active { background:var(--brown-600);color:#fff; }
  /* Badge Sedang dalam Pengembangan */
.badge-dev-container {
    position: absolute;
    top: 265px;        /* Mengatur tinggi/rendahnya badge melayang. Semakin minus, semakin ke atas */
    left: 50%;         /* Dorong ke tengah halaman */
    transform: translateX(15%); /* Sentralisasi sempurna agar benar-benar di tengah */
    z-index: 10;       /* Memastikan badge berada di lapisan paling depan (tembus elemen lain) */
    display: flex;
    justify-content: center;
    width: auto;       /* Lebar otomatis mengikuti isi teks */
}

/* 3. Desain badge tetap dipertahankan keindahannya */
.badge-dev {
    display: inline-block;
    background-color: #DB1A1A; 
    color: #ffffff;           
    font-size: 8px;        
    font-weight: 700;       
    padding: 3px 10px;       
    border-radius: 20px;       
    text-transform: uppercase;
    letter-spacing: 0.3px;   
    white-space: nowrap;
}

/* Membuat seluruh card scan tidak bisa diklik dan kursor berbentuk tanda silang/dilarang */
.card-scan-disabled {
    cursor: not-allowed !important;
    pointer-events: none; /* Mematikan semua interaksi klik */
    opacity: 0.85;        /* Membuat card sedikit redup menandakan tidak aktif */
}

/* Memastikan elemen di dalam card yang mati tetap ikut menggunakan kursor dilarang */
.card-scan-disabled * {
    cursor: not-allowed !important;
}

  /* Type toggle */
  .mob-type-toggle { display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:14px 14px 6px; }
  .mob-type-btn { display:flex;align-items:center;justify-content:center;gap:8px;padding:13px 10px;border-radius:14px;border:2px solid var(--border);background:var(--surface);cursor:pointer;font-family:var(--font-body);font-size:14px;font-weight:600;color:var(--text-muted);transition:all .18s; }
  .mob-type-btn .material-symbols-rounded { font-size:20px; }
  .mob-type-btn.active-income  { border-color:var(--sage);background:var(--sage-bg);color:var(--sage); }
  .mob-type-btn.active-expense { border-color:var(--terra);background:var(--terra-bg);color:var(--terra); }

  /* Form card */
  .mob-form-card { background:var(--surface);margin:10px 14px 0;border-radius:16px;padding:18px 16px;border:1px solid var(--border-light); }
  .mob-form-card .form-group { margin-bottom:14px; }
  .mob-form-card .form-label { font-size:11.5px; }
  .mob-form-card .form-control { background:var(--bg);border-radius:10px;padding:12px 14px;font-size:16px !important; }
  .mob-form-card .form-control:focus { border-color:var(--brown-400);background:var(--surface); }

  /* Amount display */
  .mob-amount-display { font-family:var(--font-display);font-size:32px;font-weight:800;color:var(--text-primary);letter-spacing:-1px;text-align:center;padding:10px 0 14px;border-bottom:1px solid var(--border-light);margin-bottom:16px; }
  .mob-amount-display.expense { color:var(--expense-clr); }
  .mob-amount-display.income  { color:var(--income-clr); }
  .mob-amount-display span.prefix { font-size:18px;opacity:.7; }

  /* Category pills */
  .mob-cat-grid { display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:16px; }
  .mob-cat-pill { display:flex;flex-direction:column;align-items:center;gap:5px;padding:10px 6px;border-radius:12px;border:1.5px solid var(--border);background:var(--bg);cursor:pointer;transition:all .15s;font-size:10.5px;font-weight:500;color:var(--text-secondary);text-align:center; }
  .mob-cat-pill .material-symbols-rounded { font-size:20px;color:var(--text-muted); }
  .mob-cat-pill.selected { border-color:var(--brown-400);background:var(--brown-50);color:var(--brown-600); }
  .mob-cat-pill.selected .material-symbols-rounded { color:var(--brown-500); }
  .mob-cat-pill:active { transform:scale(.95); }

  /* Submit button */
  .mob-submit-btn { width:100%;padding:15px;border-radius:14px;border:none;background:var(--brown-700);color:#fff;font-family:var(--font-body);font-size:15px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;transition:all .18s;margin-top:4px; }
  .mob-submit-btn:active { transform:scale(.98); }
  .mob-submit-btn.income  { background:var(--income-clr); }
  .mob-submit-btn.expense { background:var(--expense-clr); }
  .mob-cancel-link { display:block;text-align:center;margin-top:12px;font-size:13px;color:var(--text-muted);text-decoration:none;padding:6px; }

  /* ── Mobile Struk Upload Panel ── */
  .mob-struk-card { background:var(--surface);margin:10px 14px 0;border-radius:16px;border:1px solid var(--border-light);overflow:hidden; }
  .mob-struk-header { padding:14px 16px;background:linear-gradient(135deg,var(--brown-700),var(--brown-600));display:flex;align-items:center;gap:10px; }
  .mob-struk-header-icon { width:34px;height:34px;background:rgba(255,255,255,.15);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
  .mob-struk-header-icon .material-symbols-rounded { font-size:18px;color:#fff; }
  .mob-struk-header-title { font-size:14px;font-weight:700;color:#fff; }
  .mob-struk-header-sub   { font-size:11px;color:rgba(255,255,255,.7);margin-top:1px; }
  .mob-struk-body { padding:14px 16px; }

  /* Mobile dropzone */
  .mob-dropzone { position:relative;border:2px dashed var(--border);border-radius:14px;padding:22px 16px;text-align:center;cursor:pointer;transition:all .2s;background:var(--bg); }
  .mob-dropzone:active, .mob-dropzone.drag-over { border-color:var(--brown-400);background:var(--brown-50); }
  .mob-dropzone input[type="file"] { position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%; }
  .mob-dropzone-icon { font-size:32px;color:var(--brown-300);margin-bottom:7px; }
  .mob-dropzone-label { font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:3px; }
  .mob-dropzone-sub   { font-size:11.5px;color:var(--text-muted); }

  /* Mobile preview */
  .mob-struk-preview-wrap { position:relative;margin-top:12px;display:none; }
  .mob-struk-preview-img  { width:100%;max-height:180px;object-fit:cover;border-radius:12px;border:1px solid var(--border-light); }
  .mob-struk-preview-clear { position:absolute;top:8px;right:8px;width:28px;height:28px;border-radius:50%;background:rgba(0,0,0,.55);border:none;color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer; }
  .mob-struk-preview-clear .material-symbols-rounded { font-size:16px; }

  /* Mobile scan button */
  .mob-btn-scan { width:100%;padding:13px;border-radius:12px;border:none;background:linear-gradient(135deg,var(--brown-500),var(--brown-700));color:#fff;font-family:var(--font-body);font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:12px;transition:all .18s; }
  .mob-btn-scan:disabled { background:var(--brown-200);cursor:not-allowed; }
  .mob-btn-scan .material-symbols-rounded { font-size:20px; }

  /* Mobile OCR result */
  .mob-ocr-result { margin-top:12px;background:var(--bg);border-radius:12px;border:1px solid var(--border-light);overflow:hidden;display:none; }
  .mob-ocr-result-hdr { padding:10px 14px;background:var(--sage-bg);border-bottom:1px solid var(--border-light);display:flex;align-items:center;gap:7px;font-size:13px;font-weight:700;color:var(--sage); }
  .mob-ocr-result-hdr .material-symbols-rounded { font-size:17px; }
  .mob-ocr-row { display:flex;justify-content:space-between;padding:9px 14px;border-bottom:1px solid var(--border-light);font-size:12.5px; }
  .mob-ocr-row:last-child { border-bottom:none; }
  .mob-ocr-lbl { color:var(--text-muted);font-weight:500; }
  .mob-ocr-val { font-weight:700;color:var(--text-primary);text-align:right;max-width:58%; }
  .mob-btn-apply-ocr { width:100%;padding:12px;border-radius:12px;border:1.5px solid var(--brown-400);background:var(--brown-50);color:var(--brown-700);font-family:var(--font-body);font-size:13.5px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;transition:all .18s;margin-top:10px; }
  .mob-btn-apply-ocr:active { background:var(--brown-100); }

  /* Mobile OCR messages */
  .mob-ocr-msg { margin-top:10px;padding:10px 12px;border-radius:10px;font-size:12.5px;font-weight:500;display:flex;align-items:flex-start;gap:7px;display:none; }
  .mob-ocr-msg.error   { background:#FEF0E6;color:#8B3A10;border:1px solid #F4C5A0; }
  .mob-ocr-msg.success { background:#EDF5EB;color:#3A6B35;border:1px solid #C3DEC0; }
  .mob-ocr-msg.warning { background:#FFFBEB;color:#7c5a00;border:1px solid #FDE68A; }
  .mob-ocr-msg .material-symbols-rounded { font-size:17px;flex-shrink:0; }

  /* Bottom nav */
  .mob-nav { position:fixed;bottom:0;left:0;right:0;background:var(--surface);border-top:1px solid var(--border-light);z-index:300;display:flex;align-items:center;height:60px;padding:0 6px;padding-bottom:env(safe-area-inset-bottom); }
  .mob-nav-i { flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;text-decoration:none;padding:4px;color:var(--text-muted);transition:color .15s; }
  .mob-nav-i .material-symbols-rounded,.mob-nav-i .material-symbols-outlined { font-size:22px;display:block; }
  .mob-nav-i span.lbl { font-size:9.5px;font-weight:500; }
  .mob-nav-i.on { color:var(--brown-600); }

  /* ── DamGPT bottom nav icon & badge HOT ── */
  .mob-nav-damgpt { position:relative;display:flex;flex-direction:column;align-items:center; }
  .mob-nav-hot {
    position:absolute;top:-5px;right:-8px;
    background:#DB1A1A;
    color:#fff;font-size:6.5px;font-weight:800;letter-spacing:.3px;
    padding:1px 4px;border-radius:99px;line-height:1.6;text-transform:uppercase;
    white-space:nowrap;
  }
  .mob-nav-fab { flex:0 0 56px;display:flex;align-items:center;justify-content:center;position:relative;margin-top:-22px; }
  .mob-fab { width:54px;height:54px;border-radius:50%;background:var(--brown-500);border:3px solid var(--surface);display:flex;align-items:center;justify-content:center;color:#fff; }
  .mob-fab .material-symbols-rounded { font-size:25px; }
  .mob-foot { height:16px; }
  .desk-budget-wrap { display:none !important; }
  input,select,textarea { font-size:16px !important; }
}
@media (min-width:601px) { .mob-shell { display:none !important; } .mob-nav { display:none !important; } }
@media (min-width:601px) and (max-width:1024px) {
  .sidebar { transform:translateX(-100%);transition:transform .3s; }
  .sidebar.open { transform:translateX(0); }
  .main-content { margin-left:0 !important; }
  .hamburger { display:flex !important; }
  .tambah-grid { grid-template-columns:1fr !important; }
}
@media (min-width:901px) and (max-width:1024px) {
  .sidebar { transform:none; }
  .main-content { margin-left:240px !important; }
  .hamburger { display:none !important; }
}
  </style>
</head>
<body>

<div id="sidebar-overlay" style="display:none;position:fixed;inset:0;background:rgba(44,32,24,.4);z-index:99"
  onclick="this.style.display='none';document.querySelector('.sidebar').classList.remove('open')"></div>

<!-- ══════════════════════════════════════════════════
     MOBILE SHELL
     ══════════════════════════════════════════════════ -->
<div class="mob-shell" style="display:none">

  <!-- Header -->
  <div class="mob-header">
    <div class="mob-hdr-top">
      <a href="dashboard.php" class="mob-hdr-back">
        <span class="material-symbols-rounded">arrow_back</span>
      </a>
      <div class="mob-hdr-title">Tambah Transaksi</div>
      <a href="pengaturan.php" class="mob-avatar"><?= $initials ?></a>
    </div>
    <div class="mob-stat-row">
      <div class="mob-stat-pill">
        <span class="mob-stat-pill-lbl">Pemasukan</span>
        <span class="mob-stat-pill-val"><?= fmtRp($income) ?></span>
      </div>
      <div class="mob-stat-pill">
        <span class="mob-stat-pill-lbl">Pengeluaran</span>
        <span class="mob-stat-pill-val <?= $isDanger?'danger':($isWarning?'warn':'') ?>"><?= fmtRp($expense) ?></span>
      </div>
      <div class="mob-stat-pill">
        <span class="mob-stat-pill-lbl">Sisa</span>
        <span class="mob-stat-pill-val <?= $budget>0&&($budget-$expense)<=0?'danger':'' ?>">
          <?= $budget>0 ? fmtRp(max(0,$budget-$expense)) : '—' ?>
        </span>
      </div>
    </div>
  </div>

  <?php if ($budget > 0): ?>
  <div class="mob-budget">
    <div class="mob-budget-hdr">
      <span class="mob-budget-lbl">Anggaran Bulanan</span>
      <span class="mob-budget-pct" style="color:<?= $barColor ?>"><?= round($pct) ?>%</span>
    </div>
    <div class="mob-btrack"><div class="mob-bfill <?= $barClass ?>" style="width:<?= $pct ?>%"></div></div>
    <div class="mob-budget-meta">
      <span><?= fmtRp($expense) ?> terpakai</span>
      <span>Sisa <?= fmtRp(max(0,$budget-$expense)) ?></span>
    </div>
  </div>
  <?php else: ?>
  <div style="height:14px"></div>
  <?php endif; ?>

  <!-- Mode tabs: Manual / Scan Struk -->
    <div class="badge-dev-container">
        <span class="badge-dev">Dalam Pengembangan</span>
    </div>
  <div class="mob-mode-tabs">
    <button class="mob-mode-tab active" id="mob-tab-manual" onclick="mobSetMode('manual')">
      <span class="material-symbols-rounded">edit_note</span> Manual
    </button>
    <button class="mob-mode-tab" id="mob-tab-struk" onclick="mobSetMode('struk') style: cursor:not-allowed;">
      <span class="material-symbols-rounded">document_scanner</span> Scan Struk
    </button>
  </div>

  <!-- ── PANEL MANUAL ── -->
  <div id="mob-panel-manual">
    <div class="mob-type-toggle">
      <button type="button" class="mob-type-btn" id="mob-type-income" onclick="mobSetType('income')">
        <span class="material-symbols-rounded">trending_up</span> Pemasukan
      </button>
      <button type="button" class="mob-type-btn active-expense" id="mob-type-expense" onclick="mobSetType('expense')">
        <span class="material-symbols-rounded">trending_down</span> Pengeluaran
      </button>
    </div>

    <div class="mob-form-card">
      <form method="POST" id="mob-tx-form" data-budget="<?= $budget ?>" data-expense="<?= $expense ?>">
        <input type="hidden" name="action" value="add_tx">
        <input type="hidden" name="type" id="mob-tx-type" value="expense">

        <div class="mob-amount-display expense" id="mob-amount-display">
          <span class="prefix">Rp&nbsp;</span><span id="mob-amount-text">0</span>
        </div>

        <div class="form-group">
          <label class="form-label">Jumlah (Rp)</label>
          <input type="number" name="amount" id="mob-amount-input" class="form-control"
                 placeholder="Contoh: 50000" min="0" step="1000" required
                 oninput="mobUpdateAmount(this.value)">
        </div>
        <div class="form-group">
          <label class="form-label">Kategori</label>
          <div class="mob-cat-grid" id="mob-cat-grid"></div>
          <input type="hidden" name="category" id="mob-category-hidden" required>
        </div>
        <div class="form-group">
          <label class="form-label">Catatan (opsional)</label>
          <input type="text" name="note" id="mob-note-input" class="form-control" placeholder="Keterangan singkat...">
        </div>
        <div class="form-group" style="margin-bottom:18px;">
          <label class="form-label">Tanggal</label>
          <input type="date" name="date" id="mob-date-input" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>

        <button type="submit" class="mob-submit-btn expense" id="mob-submit-btn">
          <span class="material-symbols-rounded" style="font-size:20px">check_circle</span>
          Simpan Pengeluaran
        </button>
      </form>
      <a href="dashboard.php" class="mob-cancel-link">← Batalkan</a>
    </div>
  </div>

  <!-- ── PANEL SCAN STRUK ── -->
  <div id="mob-panel-struk" style="display:none">
    <div class="mob-struk-card">
      <div class="mob-struk-header">
        <div class="mob-struk-header-icon">
          <span class="material-symbols-rounded">receipt</span>
        </div>
        <div>
          <div class="mob-struk-header-title">Upload Struk / Nota</div>
          <div class="mob-struk-header-sub">AI akan membaca & mengisi form otomatis</div>
        </div>
      </div>
      <div class="mob-struk-body">

        <!-- Dropzone -->
        <div class="mob-dropzone" id="mob-dropzone">
          <input type="file" id="mob-struk-input" accept="image/*" capture="environment">
          <div class="mob-dropzone-icon">
            <span class="material-symbols-rounded" style="font-size:36px;color:var(--brown-300)">add_photo_alternate</span>
          </div>
          <div class="mob-dropzone-label">Ketuk untuk foto struk</div>
          <div class="mob-dropzone-sub">atau pilih dari galeri · JPG, PNG · maks 5MB</div>
        </div>

        <!-- Preview -->
        <div class="mob-struk-preview-wrap" id="mob-preview-wrap">
          <img id="mob-preview-img" class="mob-struk-preview-img" src="" alt="Preview struk">
          <button class="mob-struk-preview-clear" onclick="mobClearStruk()">
            <span class="material-symbols-rounded">close</span>
          </button>
        </div>

        <!-- Scan button -->
        <button class="mob-btn-scan" id="mob-btn-scan" disabled onclick="mobScanStruk()">
          <span class="material-symbols-rounded">document_scanner</span>
          <span id="mob-scan-label">Scan dengan OCR + AI</span>
        </button>

        <!-- Messages -->
        <div class="mob-ocr-msg" id="mob-ocr-msg">
          <span class="material-symbols-rounded" id="mob-ocr-msg-icon">info</span>
          <span id="mob-ocr-msg-text"></span>
        </div>

        <!-- OCR Result -->
        <div class="mob-ocr-result" id="mob-ocr-result">
          <div class="mob-ocr-result-hdr">
            <span class="material-symbols-rounded">auto_awesome</span> Hasil Scan AI
          </div>
          <div class="mob-ocr-row">
            <span class="mob-ocr-lbl">Merchant</span>
            <span class="mob-ocr-val" id="mob-res-merchant">—</span>
          </div>
          <div class="mob-ocr-row">
            <span class="mob-ocr-lbl">Total</span>
            <span class="mob-ocr-val" id="mob-res-amount">—</span>
          </div>
          <div class="mob-ocr-row">
            <span class="mob-ocr-lbl">Kategori</span>
            <span class="mob-ocr-val" id="mob-res-category">—</span>
          </div>
          <div class="mob-ocr-row">
            <span class="mob-ocr-lbl">Tanggal</span>
            <span class="mob-ocr-val" id="mob-res-date">—</span>
          </div>
          <div class="mob-ocr-row">
            <span class="mob-ocr-lbl">Item</span>
            <span class="mob-ocr-val" id="mob-res-items">—</span>
          </div>
          <button class="mob-btn-apply-ocr" id="mob-btn-apply">
            <span class="material-symbols-rounded" style="font-size:18px">check_circle</span>
            Gunakan Data Ini
          </button>
        </div>

      </div>
    </div>
    <div class="mob-foot"></div>
  </div>

  <div class="mob-foot"></div>
</div><!-- /mob-shell -->

<!-- Bottom Nav -->
<nav class="mob-nav" style="display:none">
  <a href="dashboard.php" class="mob-nav-i">
    <span class="material-symbols-rounded">home</span><span class="lbl">Beranda</span>
  </a>
  <a href="transaksi.php" class="mob-nav-i">
    <span class="material-symbols-rounded">receipt_long</span><span class="lbl">Transaksi</span>
  </a>
  <div class="mob-nav-fab">
    <div class="mob-fab"><span class="material-symbols-rounded">add</span></div>
  </div>
  <a href="damgpt.php" class="mob-nav-i mob-nav-damgpt">
    <span style="position:relative;display:inline-block;">
      <span class="material-symbols-rounded" style="font-size:22px;font-variation-settings:'FILL' 1,'wght' 600;display:block;">assistant</span>
      <span class="mob-nav-hot">HOT</span>
    </span>
    <span class="lbl">DamGPT</span>
  </a>
  <a href="pengaturan.php" class="mob-nav-i">
    <span class="material-symbols-rounded">person</span><span class="lbl">Profil</span>
  </a>
</nav>

<!-- ══════════════════════════════════════════════════
     DESKTOP LAYOUT
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
      <a href="tambah.php"     class="nav-item active"><span class="material-symbols-rounded nav-icon-google">add_circle</span>Tambah</a>
      <a href="damgpt.php"     class="nav-item"><span class="material-symbols-rounded nav-icon-google" style="font-variation-settings:'FILL' 1,'wght' 600;">assistant</span>DamGPT<span class="badge-hot">HOT</span></a>
      <a href="pengaturan.php" class="nav-item"><span class="material-symbols-rounded nav-icon-google">settings</span>Pengaturan</a>
    </nav>
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-avatar"><?= $initials ?></div>
        <div class="sidebar-user-info">
          <div class="sidebar-user-name"><?= htmlspecialchars($userName) ?></div>
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
      <div style="display:flex;align-items:center;gap:12px;">
        <button class="hamburger" style="background:none;border:none;padding:6px;cursor:pointer">
          <span class="material-symbols-rounded" style="font-size:22px;vertical-align:middle">menu</span>
        </button>
        <div>
          <div style="font-family:var(--font-display);font-size:17px;font-weight:700;color:var(--text-primary)">Tambah Transaksi</div>
          <div style="font-size:12px;color:var(--text-muted)">Catat manual atau scan struk / nota</div>
        </div>
      </div>
      <span style="font-size:12px;color:var(--text-muted);background:var(--surface);border:1px solid var(--border-light);padding:5px 12px;border-radius:99px;display:flex;align-items:center;gap:5px">
        <span class="material-symbols-rounded" style="font-size:13px">calendar_today</span>
        <?= date('d M Y') ?>
      </span>
    </header>

    <main class="page-body">
      <div class="tambah-wrapper">

        <?php if ($budget > 0): ?>
        <div class="desk-budget-wrap">
          <div class="desk-budget-label">
            <span>Pengeluaran bulan ini: <strong><?= fmtRp($expense) ?></strong></span>
            <span>Anggaran: <strong><?= fmtRp($budget) ?></strong> · <strong style="color:<?= $barColor ?>"><?= round($pct) ?>%</strong></span>
          </div>
          <div class="desk-btrack"><div class="desk-bfill <?= $barClass ?>" style="width:<?= $pct ?>%"></div></div>
        </div>
        <?php endif; ?>

        <div class="tambah-grid">

          <!-- Kolom Kiri: Form Manual -->
          <div>
            <div class="tx-form-card">
              <form method="POST" id="tx-form" data-budget="<?= $budget ?>" data-expense="<?= $expense ?>">
                <input type="hidden" name="action" value="add_tx">
                <input type="hidden" name="type" id="tx-type" value="expense">
                <div class="type-toggle">
                  <button type="button" class="type-btn" id="type-income">
                    <span class="material-symbols-rounded">trending_up</span> Pemasukan
                  </button>
                  <button type="button" class="type-btn active-expense" id="type-expense">
                    <span class="material-symbols-rounded">trending_down</span> Pengeluaran
                  </button>
                </div>
                <div class="form-group">
                  <label class="form-label">Kategori</label>
                  <select name="category" id="tx-category" class="form-control" required>
                    <option value="">Pilih kategori...</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Jumlah (Rp)</label>
                  <input type="number" name="amount" id="tx-amount" class="form-control" placeholder="0" min="0" step="1000" required>
                </div>
                <div class="form-group">
                  <label class="form-label">Catatan (opsional)</label>
                  <input type="text" name="note" id="tx-note" class="form-control" placeholder="Keterangan singkat...">
                </div>
                <div class="form-group">
                  <label class="form-label">Tanggal</label>
                  <input type="date" name="date" id="tx-date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div style="display:flex;gap:12px;margin-top:8px">
                  <a href="dashboard.php" class="btn btn-ghost" style="flex:1;text-align:center">Batal</a>
                  <button type="submit" class="btn btn-primary" style="flex:2">Simpan Transaksi</button>
                </div>
              </form>
            </div>
          </div>

          <!-- Kolom Kanan: Upload Struk + Tips -->
          <div style="display:flex;flex-direction:column;gap:16px;">

            <!-- Upload Struk Card -->
            <div class="struk-card">
              <div class="struk-card-header">
                <div class="struk-card-icon">
                  <span class="material-symbols-rounded">document_scanner</span>
                </div>
                <div>
                  <div class="struk-card-title">Upload Struk / Nota</div>
                  <div class="struk-card-sub">OCR + AI akan mengisi form otomatis</div>
                </div>
              </div>
              <div class="struk-card-body">

                <div class="dropzone" id="desk-dropzone">
                  <input type="file" id="desk-struk-input" accept="image/*">
                  <div class="dropzone-icon">
                    <span class="material-symbols-rounded" style="font-size:40px;color:var(--brown-300)">add_photo_alternate</span>
                  </div>
                  <div class="dropzone-label">Drag & drop atau klik untuk upload</div>
                  <div class="dropzone-sub">JPG, PNG, WEBP · maks 5MB</div>
                </div>

                <div class="struk-preview-wrap" id="desk-preview-wrap">
                  <img id="desk-preview-img" class="struk-preview-img" src="" alt="Preview">
                  <button class="struk-preview-clear" onclick="deskClearStruk()">
                    <span class="material-symbols-rounded">close</span>
                  </button>
                </div>

                <button class="btn-scan" id="desk-btn-scan" disabled onclick="deskScanStruk()">
                  <span class="material-symbols-rounded">document_scanner</span>
                  <span id="desk-scan-label">Scan dengan OCR + AI</span>
                </button>

                <div class="ocr-msg" id="desk-ocr-msg">
                  <span class="material-symbols-rounded" id="desk-ocr-msg-icon">info</span>
                  <span id="desk-ocr-msg-text"></span>
                </div>

                <div class="ocr-result-panel" id="desk-ocr-result">
                  <div style="font-size:13px;font-weight:700;color:var(--sage);display:flex;align-items:center;gap:6px;margin-bottom:10px;">
                    <span class="material-symbols-rounded" style="font-size:17px">auto_awesome</span> Hasil Scan AI
                  </div>
                  <div class="ocr-row">
                    <span class="ocr-lbl">Merchant</span><span class="ocr-val" id="desk-res-merchant">—</span>
                  </div>
                  <div class="ocr-row">
                    <span class="ocr-lbl">Total Bayar</span><span class="ocr-val" id="desk-res-amount">—</span>
                  </div>
                  <div class="ocr-row">
                    <span class="ocr-lbl">Kategori</span><span class="ocr-val" id="desk-res-category">—</span>
                  </div>
                  <div class="ocr-row">
                    <span class="ocr-lbl">Tanggal</span><span class="ocr-val" id="desk-res-date">—</span>
                  </div>
                  <div class="ocr-row">
                    <span class="ocr-lbl">Item Dibeli</span><span class="ocr-val" id="desk-res-items">—</span>
                  </div>
                  <div class="ocr-confidence">
                    <span style="font-size:12px;color:var(--text-muted);white-space:nowrap">Akurasi AI</span>
                    <div class="ocr-confidence-bar"><div class="ocr-confidence-fill" id="desk-confidence-fill" style="width:0%"></div></div>
                    <span style="font-size:12px;font-weight:700;color:var(--text-secondary)" id="desk-confidence-pct">—</span>
                  </div>
                  <button class="btn-apply-ocr" id="desk-btn-apply">
                    <span class="material-symbols-rounded" style="font-size:18px">check_circle</span>
                    Terapkan ke Form
                  </button>
                </div>

              </div>
            </div>

            <!-- Tips -->
            <div class="tips-list">
              <div class="tip-item">
                <div class="tip-icon"><span class="material-symbols-rounded">lightbulb</span></div>
                <div class="tip-body">
                  <strong>Foto yang jelas = hasil lebih akurat</strong>
                  <p>Pastikan struk rata, tidak buram, dan teks terbaca. Cahaya cukup sangat membantu OCR.</p>
                </div>
              </div>
              <div class="tip-item">
                <div class="tip-icon"><span class="material-symbols-rounded">tune</span></div>
                <div class="tip-body">
                  <strong>Selalu periksa hasil scan</strong>
                  <p>AI bisa salah membaca angka. Setelah terapkan, cek kembali nominal dan kategori sebelum menyimpan.</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<!-- Impulse Modal -->
<div class="modal-overlay" id="impulse-modal">
  <div class="modal-box">
    <div class="modal-icon">
      <span class="material-symbols-rounded" style="font-size:48px;color:#f59e0b">report_problem</span>
    </div>
    <h3 class="modal-title">Peringatan Impulsif!</h3>
    <p class="modal-body">
      Pengeluaran ini akan membuat total pengeluaranmu bulan ini mencapai
      <strong class="impulse-pct">—%</strong> dari anggaran.<br>
      Sisa anggaran tersisa: <strong class="impulse-remaining">—</strong>
    </p>
    <div class="modal-tips">
      <h4 style="display:flex;align-items:center;gap:6px;">
        <span class="material-symbols-rounded" style="font-size:18px">psychology</span> Sebelum melanjutkan, tanya dirimu:
      </h4>
      <ul>
        <li>Apakah ini kebutuhan atau keinginan?</li>
        <li>Bisakah ditunda 24 jam?</li>
        <li>Apakah ada alternatif yang lebih hemat?</li>
        <li>Apakah ini sesuai prioritas keuanganmu?</li>
      </ul>
    </div>
    <div class="modal-actions">
      <button id="impulse-cancel" class="btn btn-ghost btn-block">
        <span class="material-symbols-rounded" style="font-size:18px;vertical-align:middle;margin-right:4px">arrow_back</span> Batalkan
      </button>
      <button id="impulse-proceed" class="btn btn-danger btn-block">Tetap Lanjutkan</button>
    </div>
  </div>
</div>

<div id="toast-container"></div>
<script src="../assets/js/app.js"></script>
<script>
// ── Kategori data ──────────────────────────────────────────
const CATEGORIES = {
  income:  [
    {key:'Gaji',icon:'savings'},{key:'Freelance',icon:'laptop_mac'},
    {key:'Bonus',icon:'redeem'},{key:'Investasi',icon:'monitoring'},
    {key:'Hadiah',icon:'card_giftcard'},{key:'Lainnya',icon:'category'},
  ],
  expense: [
    {key:'Makanan',icon:'restaurant'},{key:'Transportasi',icon:'commute'},
    {key:'Belanja',icon:'shopping_bag'},{key:'Tagihan',icon:'payments'},
    {key:'Kesehatan',icon:'medical_services'},{key:'Hiburan',icon:'sports_esports'},
    {key:'Pendidikan',icon:'school'},{key:'Pakaian',icon:'checkroom'},
    {key:'Lainnya',icon:'category'},
  ]
};

const IS_MOBILE = window.innerWidth <= 600;

// ── OCR shared state ───────────────────────────────────────
let currentOcrData = null;

function fmtRpJs(n) {
  return 'Rp ' + Number(n).toLocaleString('id-ID');
}

// ── Shared: kirim gambar ke ocr_struk.php ─────────────────
async function runOcr(fileInput, callbacks) {
  const file = fileInput.files[0];
  if (!file) return;

  const fd = new FormData();
  fd.append('struk', file);

  callbacks.onLoading();

  try {
    const res  = await fetch('ocr_struk.php', { method:'POST', body:fd });
    const data = await res.json();

    if (!res.ok || data.error) {
      callbacks.onError(data.error || 'Terjadi kesalahan server.');
      if (data.detail) console.warn('OCR detail:', data.detail);
      return;
    }

    currentOcrData = data;
    callbacks.onSuccess(data);

  } catch(err) {
    callbacks.onError('Gagal menghubungi server OCR. Periksa koneksi.');
    console.error(err);
  }
}

// ══════════════════════════════════════════════════════════
//  MOBILE LOGIC
// ══════════════════════════════════════════════════════════
if (IS_MOBILE) {
  document.querySelector('.mob-shell').style.display    = 'flex';
  document.querySelector('.mob-nav').style.display      = 'flex';
  document.querySelector('.app-layout').style.display   = 'none';

  let mobCurrentType = 'expense';
  let mobSelectedCat = '';
  let mobCurrentMode = 'manual';

  // ── Mode switch (Manual / Scan Struk) ──
  window.mobSetMode = function(mode) {
    mobCurrentMode = mode;
    document.getElementById('mob-panel-manual').style.display = mode==='manual' ? '' : 'none';
    document.getElementById('mob-panel-struk').style.display  = mode==='struk'  ? '' : 'none';
    document.getElementById('mob-tab-manual').classList.toggle('active', mode==='manual');
    document.getElementById('mob-tab-struk').classList.toggle('active', mode==='struk');
  };

  // ── Type toggle ──
  window.mobSetType = function(type) {
    mobCurrentType = type;
    document.getElementById('mob-tx-type').value = type;
    document.getElementById('mob-type-income').className  = 'mob-type-btn' + (type==='income'  ? ' active-income'  : '');
    document.getElementById('mob-type-expense').className = 'mob-type-btn' + (type==='expense' ? ' active-expense' : '');
    document.getElementById('mob-amount-display').className = 'mob-amount-display ' + type;
    const btn = document.getElementById('mob-submit-btn');
    btn.className = 'mob-submit-btn ' + type;
    btn.innerHTML = `<span class="material-symbols-rounded" style="font-size:20px">check_circle</span> Simpan ${type==='income'?'Pemasukan':'Pengeluaran'}`;
    mobRenderCats(type);
    mobSelectedCat = '';
    document.getElementById('mob-category-hidden').value = '';
  };

  function mobRenderCats(type) {
    const grid = document.getElementById('mob-cat-grid');
    grid.innerHTML = '';
    CATEGORIES[type].forEach(cat => {
      const pill = document.createElement('button');
      pill.type = 'button'; pill.className = 'mob-cat-pill'; pill.dataset.cat = cat.key;
      pill.innerHTML = `<span class="material-symbols-rounded">${cat.icon}</span>${cat.key}`;
      pill.addEventListener('click', () => {
        document.querySelectorAll('.mob-cat-pill').forEach(p => p.classList.remove('selected'));
        pill.classList.add('selected');
        mobSelectedCat = cat.key;
        document.getElementById('mob-category-hidden').value = cat.key;
      });
      grid.appendChild(pill);
    });
  }

  window.mobUpdateAmount = function(val) {
    document.getElementById('mob-amount-text').textContent = (parseInt(val)||0).toLocaleString('id-ID');
  };

  mobRenderCats('expense');

  // ── Mobile form submit ──
  document.getElementById('mob-tx-form').addEventListener('submit', function(e) {
    if (!mobSelectedCat) {
      e.preventDefault();
      const grid = document.getElementById('mob-cat-grid');
      grid.style.outline = '2px solid var(--terra)'; grid.style.borderRadius = '10px';
      setTimeout(() => grid.style.outline = '', 1500);
      return;
    }
    const type   = mobCurrentType;
    const amount = parseFloat(document.getElementById('mob-amount-input').value) || 0;
    if (type === 'expense') {
      e.preventDefault();
      ImpulseWarning.check({
        expense: parseFloat(this.dataset.expense||0),
        budget:  parseFloat(this.dataset.budget||0),
        amount,
        onProceed: () => this.submit(),
        onCancel:  () => typeof toast==='function' && toast('Transaksi dibatalkan. Tetap semangat hemat! 💪'),
      });
    }
  });

  // ── Mobile struk: file input ──
  const mobStrukinput = document.getElementById('mob-struk-input');
  mobStrukinput.addEventListener('change', function() {
    if (!this.files[0]) return;
    const url = URL.createObjectURL(this.files[0]);
    document.getElementById('mob-preview-img').src = url;
    document.getElementById('mob-preview-wrap').style.display = 'block';
    document.getElementById('mob-dropzone').style.display     = 'none';
    document.getElementById('mob-btn-scan').disabled          = false;
    mobShowOcrMsg('', '');
    document.getElementById('mob-ocr-result').style.display   = 'none';
  });

  window.mobClearStruk = function() {
    mobStrukinput.value = '';
    document.getElementById('mob-preview-wrap').style.display = 'none';
    document.getElementById('mob-dropzone').style.display     = '';
    document.getElementById('mob-btn-scan').disabled          = true;
    document.getElementById('mob-ocr-result').style.display   = 'none';
    mobShowOcrMsg('', '');
    currentOcrData = null;
  };

  function mobShowOcrMsg(type, text) {
    const el   = document.getElementById('mob-ocr-msg');
    const icon = document.getElementById('mob-ocr-msg-icon');
    const txt  = document.getElementById('mob-ocr-msg-text');
    if (!type) { el.style.display = 'none'; return; }
    el.className = 'mob-ocr-msg ' + type;
    icon.textContent = type==='error' ? 'error' : type==='warning' ? 'warning' : 'check_circle';
    txt.textContent  = text;
    el.style.display = 'flex';
  }

  window.mobScanStruk = function() {
    const scanBtn   = document.getElementById('mob-btn-scan');
    const scanLabel = document.getElementById('mob-scan-label');

    runOcr(mobStrukinput, {
      onLoading() {
        scanBtn.disabled    = true;
        scanLabel.innerHTML = '<span class="scan-spinner"></span>&nbsp;Memproses OCR...';
        mobShowOcrMsg('', '');
        document.getElementById('mob-ocr-result').style.display = 'none';
      },
      onError(msg) {
        scanBtn.disabled    = false;
        scanLabel.textContent = 'Scan dengan OCR + AI';
        mobShowOcrMsg('error', msg);
      },
      onSuccess(data) {
        scanBtn.disabled    = false;
        scanLabel.textContent = 'Scan dengan OCR + AI';

        const confidencePct = Math.round((data.confidence||0) * 100);
        const confMsg = confidencePct >= 80 ? `✅ Tingkat akurasi ${confidencePct}%` :
                        confidencePct >= 50 ? `⚠️ Akurasi ${confidencePct}% — periksa kembali sebelum menyimpan` :
                                              `⚠️ Akurasi rendah (${confidencePct}%) — koreksi manual diperlukan`;
        const confType = confidencePct >= 80 ? 'success' : 'warning';
        mobShowOcrMsg(confType, confMsg);

        document.getElementById('mob-res-merchant').textContent  = data.merchant || '—';
        document.getElementById('mob-res-amount').textContent    = data.amount ? fmtRpJs(data.amount) : '—';
        document.getElementById('mob-res-category').textContent  = data.category || '—';
        document.getElementById('mob-res-date').textContent      = data.date || '—';
        document.getElementById('mob-res-items').textContent     = data.items?.length ? data.items.join(', ') : '—';
        document.getElementById('mob-ocr-result').style.display  = 'block';
      }
    });
  };

  // Apply OCR data → switch to manual panel & fill form
  document.getElementById('mob-btn-apply').addEventListener('click', function() {
    if (!currentOcrData) return;
    const d = currentOcrData;

    mobSetMode('manual');

    // Set type
    mobSetType(d.type || 'expense');

    // Set amount
    const amountInput = document.getElementById('mob-amount-input');
    amountInput.value = d.amount || 0;
    window.mobUpdateAmount(d.amount || 0);

    // Set date
    document.getElementById('mob-date-input').value = d.date || '<?= date('Y-m-d') ?>';

    // Set note
    const noteText = [d.merchant, d.items?.join(', ')].filter(Boolean).join(' — ');
    document.getElementById('mob-note-input').value = d.note || noteText;

    // Set category & re-render pills
    mobRenderCats(d.type || 'expense');
    setTimeout(() => {
      const pills = document.querySelectorAll('.mob-cat-pill');
      pills.forEach(pill => {
        if (pill.dataset.cat === d.category) {
          pill.click();
        }
      });
    }, 50);

    // Scroll ke form
    document.getElementById('mob-panel-manual').scrollIntoView({ behavior:'smooth', block:'start' });
  });

// ══════════════════════════════════════════════════════════
//  DESKTOP LOGIC
// ══════════════════════════════════════════════════════════
} else {
  document.querySelector('.mob-shell').style.display  = 'none';
  document.querySelector('.mob-nav').style.display    = 'none';

  // Desktop struk input
  const deskInput = document.getElementById('desk-struk-input');
  const dropzone  = document.getElementById('desk-dropzone');

  deskInput.addEventListener('change', function() {
    if (!this.files[0]) return;
    const url = URL.createObjectURL(this.files[0]);
    document.getElementById('desk-preview-img').src         = url;
    document.getElementById('desk-preview-wrap').style.display = 'block';
    document.getElementById('desk-btn-scan').disabled          = false;
    deskShowMsg('', '');
    document.getElementById('desk-ocr-result').style.display   = 'none';
  });

  // Drag & drop
  ['dragover','dragenter'].forEach(e => dropzone.addEventListener(e, ev => { ev.preventDefault(); dropzone.classList.add('drag-over'); }));
  ['dragleave','drop'].forEach(e => dropzone.addEventListener(e, ev => { ev.preventDefault(); dropzone.classList.remove('drag-over'); }));
  dropzone.addEventListener('drop', function(ev) {
    const file = ev.dataTransfer.files[0];
    if (!file) return;
    const dt = new DataTransfer(); dt.items.add(file); deskInput.files = dt.files;
    deskInput.dispatchEvent(new Event('change'));
  });

  window.deskClearStruk = function() {
    deskInput.value = '';
    document.getElementById('desk-preview-wrap').style.display = 'none';
    document.getElementById('desk-btn-scan').disabled           = true;
    document.getElementById('desk-ocr-result').style.display    = 'none';
    deskShowMsg('', '');
    currentOcrData = null;
  };

  function deskShowMsg(type, text) {
    const el   = document.getElementById('desk-ocr-msg');
    const icon = document.getElementById('desk-ocr-msg-icon');
    const txt  = document.getElementById('desk-ocr-msg-text');
    if (!type) { el.style.display = 'none'; return; }
    el.className = 'ocr-msg ' + type;
    icon.textContent = type==='error' ? 'error' : type==='warning' ? 'warning' : 'check_circle';
    txt.textContent  = text;
    el.style.display = 'flex';
  }

  window.deskScanStruk = function() {
    const scanBtn   = document.getElementById('desk-btn-scan');
    const scanLabel = document.getElementById('desk-scan-label');

    runOcr(deskInput, {
      onLoading() {
        scanBtn.disabled    = true;
        scanLabel.innerHTML = '<span class="scan-spinner"></span>&nbsp;Memproses...';
        deskShowMsg('', '');
        document.getElementById('desk-ocr-result').style.display = 'none';
      },
      onError(msg) {
        scanBtn.disabled    = false;
        scanLabel.textContent = 'Scan dengan OCR + AI';
        deskShowMsg('error', msg);
      },
      onSuccess(data) {
        scanBtn.disabled    = false;
        scanLabel.textContent = 'Scan dengan OCR + AI';

        const pct = Math.round((data.confidence||0) * 100);
        document.getElementById('desk-confidence-fill').style.width = pct + '%';
        document.getElementById('desk-confidence-fill').style.background = pct>=80?'var(--sage)':pct>=50?'var(--warning)':'var(--terra)';
        document.getElementById('desk-confidence-pct').textContent = pct + '%';

        document.getElementById('desk-res-merchant').textContent  = data.merchant || '—';
        document.getElementById('desk-res-amount').textContent    = data.amount ? fmtRpJs(data.amount) : '—';
        document.getElementById('desk-res-category').textContent  = data.category || '—';
        document.getElementById('desk-res-date').textContent      = data.date || '—';
        document.getElementById('desk-res-items').textContent     = data.items?.length ? data.items.join(', ') : '—';
        document.getElementById('desk-ocr-result').style.display  = 'block';

        const confMsg = pct>=80 ? `✅ Akurasi ${pct}% — data siap diterapkan` :
                        pct>=50 ? `⚠️ Akurasi ${pct}% — periksa kembali sebelum menyimpan` :
                                  `⚠️ Akurasi rendah (${pct}%) — koreksi manual diperlukan`;
        deskShowMsg(pct>=80?'success':'warning', confMsg);
      }
    });
  };

  // Desktop: Apply OCR → fill form
  document.getElementById('desk-btn-apply').addEventListener('click', function() {
    if (!currentOcrData) return;
    const d = currentOcrData;

    // Type toggle
    const typeInc = document.getElementById('type-income');
    const typeExp = document.getElementById('type-expense');
    const typeHid = document.getElementById('tx-type');
    if (d.type === 'income') {
      typeHid.value = 'income';
      typeInc.classList.add('active-income');    typeInc.classList.remove('active-expense');
      typeExp.classList.remove('active-income'); typeExp.classList.remove('active-expense');
    } else {
      typeHid.value = 'expense';
      typeExp.classList.add('active-expense');   typeExp.classList.remove('active-income');
      typeInc.classList.remove('active-expense');typeInc.classList.remove('active-income');
    }
    // Trigger app.js category rebuild if available
    if (typeof updateCategorySelect === 'function') updateCategorySelect(d.type||'expense');

    // Amount
    document.getElementById('tx-amount').value = d.amount || 0;

    // Date
    document.getElementById('tx-date').value = d.date || '<?= date('Y-m-d') ?>';

    // Note
    const noteText = [d.merchant, d.items?.join(', ')].filter(Boolean).join(' — ');
    document.getElementById('tx-note').value = d.note || noteText;

    // Category select
    const catSelect = document.getElementById('tx-category');
    if (catSelect) {
      Array.from(catSelect.options).forEach(opt => {
        if (opt.value === d.category) catSelect.value = d.category;
      });
    }

    // Scroll ke form
    document.getElementById('tx-form').scrollIntoView({ behavior:'smooth', block:'start' });
    if (typeof toast === 'function') toast('✅ Data struk berhasil diterapkan ke form!');
  });
}

// Hamburger
document.querySelector('.hamburger')?.addEventListener('click', () => {
  document.getElementById('sidebar-overlay').style.display = 'block';
  document.querySelector('.sidebar').classList.add('open');
});
</script>
</body>
</html>