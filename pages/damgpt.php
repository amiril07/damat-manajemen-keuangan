<?php
// ============================================================
// pages/damgpt.php  —  DamGPT: Asisten Keuangan AI
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/transactions.php';

requireLogin();

// ── Handle AJAX request ──────────────────────────────────────
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    header('Content-Type: application/json; charset=utf-8');

    $body        = json_decode(file_get_contents('php://input'), true);
    $userMessage = trim($body['message'] ?? '');
    $history     = $body['history'] ?? [];

    if ($userMessage === '') {
        echo json_encode(['error' => 'Pesan tidak boleh kosong']);
        exit;
    }

    $user   = currentUser();
    $budget = (float)($user['monthly_budget'] ?? 0);
    // Build financial context dengan aman (tidak bergantung fungsi opsional)
    $financialContext = '';
    if (function_exists('buildFinancialContext')) {
        $financialContext = buildFinancialContext($user['id'], $budget);
    } else {
        $summary = getMonthlySummary($user['id']);
        $financialContext = "\n\n=== DATA KEUANGAN USER ===\n" .
            "Anggaran bulanan: Rp " . number_format($budget, 0, ',', '.') . "\n" .
            "Pemasukan bulan ini: Rp " . number_format($summary['income'], 0, ',', '.') . "\n" .
            "Pengeluaran bulan ini: Rp " . number_format($summary['expense'], 0, ',', '.') . "\n" .
            "Saldo bersih: Rp " . number_format($summary['balance'], 0, ',', '.') . "\n" .
            "=== AKHIR DATA KEUANGAN ===";
    }

    $systemInstruction =
        "Kamu adalah DamGPT, asisten keuangan master pribadi yang cerdas, ramah, dan suportif dari aplikasi Damat. " .
        "Kamu membantu user memahami kondisi keuangan mereka, memberikan saran hemat, dan menjawab pertanyaan seputar keuangan pribadi. " .
        "Jawab user dengan bahasa seperti konsultan keuangan profesioanl" .
        "Gunakan bahasa Indonesia yang santai namun sedikit gaul dengan bahasa zaman sekarang. Jawab secara ringkas dan to-the-point. " .
        "Saat memberikan saran keuangan, selalu dasarkan pada data nyata user yang tersedia. " .
        "Jika user bertanya tentang hal di luar keuangan, arahkan kembali dengan sopan ke topik keuangan. " .
        "Format angka Rupiah dengan titik pemisah ribuan (contoh: Rp 1.500.000). " .
        "Jangan pernah menyebut nama model AI atau perusahaan pembuatmu kalaupun ditanya jawab saja kamu diciptakan oleh seorang developer asal Bangka Belitung." .
        $financialContext;

    $messages   = [];
    $messages[] = ['role' => 'system', 'content' => $systemInstruction];
    foreach ($history as $turn) {
        $role       = ($turn['role'] === 'assistant') ? 'assistant' : 'user';
        $messages[] = ['role' => $role, 'content' => $turn['text']];
    }
    $messages[] = ['role' => 'user', 'content' => $userMessage];

    $payload = json_encode([
        'model'       => $model,
        'messages'    => $messages,
        'temperature' => 0.7,
        'max_tokens'  => 1024,
    ]);

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $groqApiKey,
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        http_response_code(500);
        echo json_encode(['error' => 'Gagal terhubung ke DamGPT: ' . $curlError]);
        exit;
    }

    $data = json_decode($response, true);

    if ($httpCode !== 200) {
        http_response_code($httpCode);
        echo json_encode(['error' => $data['error']['message'] ?? 'Respons DamGPT tidak valid']);
        exit;
    }

    if (empty($data['choices'][0]['message']['content'])) {
        http_response_code(500);
        echo json_encode(['error' => 'DamGPT tidak memberikan respons valid.']);
        exit;
    }

    echo json_encode(['reply' => $data['choices'][0]['message']['content']]);
    exit;
}

// ── Render halaman HTML ───────────────────────────────────────
$user            = currentUser();
$displayUsername = $user['username'] ?? 'User';
$initials        = strtoupper(substr($displayUsername, 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DamGPT — Damat</title>
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

/* ===== DESKTOP: Chat layout ===== */
.chat-wrap { display:flex;flex-direction:column;height:calc(100vh - 64px);overflow:hidden; }

.damgpt-hero {
  background:var(--brown-700);border-radius:var(--r-xl);
  padding:20px 24px;margin:20px 24px 0;color:#fff;
  display:flex;align-items:center;gap:16px;flex-shrink:0;
  border:1px solid rgba(255,255,255,.05);
}
.damgpt-hero-icon {
  width:48px;height:48px;background:rgba(255,255,255,.12);
  border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.damgpt-hero-icon .material-symbols-outlined { font-size:26px;color:#fff; }
.damgpt-hero-title { font-family:var(--font-display);font-size:16.5px;font-weight:700;letter-spacing:-.01em; }
.damgpt-hero-sub   { font-size:12.5px;opacity:.85;line-height:1.5; }

.suggestions {
  display:flex;gap:8px;flex-wrap:wrap;
  padding:14px 24px 0;flex-shrink:0;
}
.suggestion-chip {
  background:var(--surface);border:1px solid var(--border);
  color:var(--text-secondary);font-size:12.5px;
  padding:6px 14px;border-radius:20px;cursor:pointer;
  transition:all .2s;white-space:nowrap;
}
.suggestion-chip:hover { background:var(--brown-50);border-color:var(--brown-300);color:var(--brown-700); }

.chat-messages {
  flex:1;overflow-y:auto;padding:16px 24px;
  display:flex;flex-direction:column;gap:14px;scroll-behavior:smooth;
}
.bubble { display:flex;gap:12px;max-width:80%;animation:fadeUp .25s ease both; }
@keyframes fadeUp {
  from { opacity:0;transform:translateY(8px); }
  to   { opacity:1;transform:translateY(0); }
}
.bubble.user { align-self:flex-end;flex-direction:row-reverse; }
.bubble.user .bubble-content { background:var(--brown-600);color:#fff;border-radius:18px 4px 18px 18px; }
.bubble.ai  { align-self:flex-start; }
.bubble.ai .bubble-content  { background:var(--surface);color:var(--text-primary);border:1px solid var(--border-light);border-radius:4px 18px 18px 18px; }

.bubble-avatar {
  width:36px;height:36px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-size:13px;font-weight:700;flex-shrink:0;margin-top:2px;
}
.bubble.user .bubble-avatar { background:var(--brown-300);color:var(--brown-800); }
.bubble.ai   .bubble-avatar { background:var(--brown-700);color:#fff; }
.bubble.ai   .bubble-avatar .material-symbols-outlined { font-size:20px;display:block; }

.bubble-content { padding:12px 16px;font-size:14px;line-height:1.65; }
.bubble-content p  { margin:0 0 8px; }
.bubble-content p:last-child { margin-bottom:0; }
.bubble-content strong { font-weight:600; }
.bubble-content ul, .bubble-content ol { padding-left:18px;margin:6px 0; }
.bubble-content li { margin-bottom:4px; }

.bubble-time { font-size:10.5px;color:var(--text-muted);margin-top:4px;text-align:right; }
.bubble.ai .bubble-time { text-align:left; }

.typing-indicator {
  display:flex;align-items:center;gap:5px;padding:12px 16px;
  background:var(--surface);border:1px solid var(--border-light);
  border-radius:4px 18px 18px 18px;width:fit-content;
}
.typing-dot {
  width:7px;height:7px;background:var(--brown-300);border-radius:50%;
  animation:typingBounce .9s infinite ease-in-out;
}
.typing-dot:nth-child(2) { animation-delay:.15s; }
.typing-dot:nth-child(3) { animation-delay:.30s; }
@keyframes typingBounce {
  0%,80%,100% { transform:scale(.6);opacity:.4; }
  40%         { transform:scale(1);opacity:1; }
}

.chat-input-wrap {
  padding:14px 24px 20px;flex-shrink:0;
  background:var(--bg);border-top:1px solid var(--border-light);
}
.chat-input-row {
  display:flex;gap:10px;align-items:flex-end;
  background:var(--surface);border:1.5px solid var(--border);
  border-radius:var(--r-lg);padding:10px 12px;transition:border-color .2s;
}
.chat-input-row:focus-within { border-color:var(--brown-400); }
#chat-input {
  flex:1;border:none;outline:none;background:transparent;
  font-family:var(--font-body);font-size:14px;color:var(--text-primary);
  resize:none;max-height:120px;line-height:1.5;
}
#chat-input::placeholder { color:var(--text-muted); }
#send-btn {
  width:38px;height:38px;background:var(--brown-600);border:none;
  border-radius:10px;color:#fff;display:flex;align-items:center;
  justify-content:center;flex-shrink:0;transition:background .2s,transform .1s;cursor:pointer;
}
#send-btn:hover:not(:disabled) { background:var(--brown-700); }
#send-btn:active:not(:disabled) { transform:scale(.93); }
#send-btn:disabled { background:var(--brown-200);cursor:not-allowed; }
#send-btn .material-symbols-rounded { font-size:20px; }
.chat-disclaimer { text-align:center;font-size:11.5px;color:var(--text-muted);margin-top:8px; }

.chat-empty {
  flex:1;display:flex;flex-direction:column;align-items:center;
  justify-content:center;gap:10px;color:var(--text-muted);padding:24px;text-align:center;
}
.chat-empty .material-symbols-rounded { font-size:48px;color:var(--brown-200);font-variation-settings:'FILL' 1; }
.chat-empty-title { font-size:15px;font-weight:600;color:var(--text-secondary); }
.chat-empty-sub   { font-size:13px;max-width:280px;line-height:1.6; }

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
  .damgpt-hero-sub { display:none; }
}
@media (min-width:901px) and (max-width:1024px) {
  .sidebar { transform:none; }
  .main-content { margin-left:240px !important; }
  .hamburger { display:none !important; }
}

/* ===== MOBILE-ONLY ===== */
@media (max-width: 600px) {

  .app-layout { display:none !important; }
  .mob-shell  {
    display:flex !important;
    flex-direction:column;
    height:100vh;
    height:100dvh;
    background:#F5F0E8;
    overflow:hidden;
  }

  /* ---------- HEADER ---------- */
  .mob-header {
    background:#8B5E30;
    padding:14px 18px 12px;
    flex-shrink:0;
  }
  .mob-hdr-top {
    display:flex;align-items:center;justify-content:space-between;
    margin-bottom:0;
  }
  .mob-hdr-left { display:flex;align-items:center;gap:10px; }
  .mob-back-btn {
    display:flex;align-items:center;justify-content:center;
    width:34px;height:34px;border-radius:10px;
    background:rgba(255,255,255,.15);color:#fff;
    text-decoration:none;flex-shrink:0;transition:background .15s;
  }
  .mob-back-btn:active { background:rgba(255,255,255,.28); }
  .mob-back-btn .material-symbols-rounded { font-size:20px; }
  .mob-ai-badge {
    width:34px;height:34px;border-radius:10px;
    background:rgba(255,255,255,.15);
    display:flex;align-items:center;justify-content:center;flex-shrink:0;
  }
  .mob-ai-badge .material-symbols-outlined { font-size:22px;color:#fff; }
  .mob-greeting     { font-size:15px;font-weight:700;color:#fff;line-height:1.2; }
  .mob-greeting-sub { font-size:11px;color:rgba(255,255,255,.6);margin-top:2px; }
  .mob-hdr-actions { display:flex;align-items:center;gap:8px; }
  .mob-clear-btn {
    background:rgba(255,255,255,.15);border:none;border-radius:10px;
    padding:7px 10px;color:#fff;display:flex;align-items:center;gap:5px;
    font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;
  }
  .mob-clear-btn .material-symbols-rounded { font-size:16px; }

  /* ---------- SUGGESTION CHIPS ---------- */
  .mob-suggestions {
    display:flex;flex-wrap:wrap;gap:7px;padding:9px 14px;
    flex-shrink:0;background:#EDE5D0;
  }
  .mob-suggestion-chip {
    padding:6px 12px;border-radius:99px;font-size:11.5px;
    font-weight:500;border:1.5px solid #D4C9B8;background:#FDFAF5;
    color:#5C4030;cursor:pointer;white-space:nowrap;
  }
  .mob-suggestion-chip:active { background:#EDE8DF; }

  /* ---------- CHAT AREA ---------- */
  .mob-chat-body {
    flex:1;overflow-y:auto;padding:12px 14px 90px;
    display:flex;flex-direction:column;gap:12px;scroll-behavior:smooth;
    background-color:#EDE5D0;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='60' height='60'%3E%3Cdefs%3E%3Cpattern id='p' width='60' height='60' patternUnits='userSpaceOnUse'%3E%3Crect width='60' height='60' fill='%23EDE5D0'/%3E%3Ccircle cx='30' cy='30' r='1.2' fill='%23C4B49A' opacity='.55'/%3E%3Ccircle cx='0' cy='0' r='1.2' fill='%23C4B49A' opacity='.55'/%3E%3Ccircle cx='60' cy='0' r='1.2' fill='%23C4B49A' opacity='.55'/%3E%3Ccircle cx='0' cy='60' r='1.2' fill='%23C4B49A' opacity='.55'/%3E%3Ccircle cx='60' cy='60' r='1.2' fill='%23C4B49A' opacity='.55'/%3E%3Cpath d='M15 0 L30 15 L45 0' stroke='%23C4B49A' stroke-width='.5' fill='none' opacity='.3'/%3E%3Cpath d='M0 15 L15 30 L0 45' stroke='%23C4B49A' stroke-width='.5' fill='none' opacity='.3'/%3E%3Cpath d='M60 15 L45 30 L60 45' stroke='%23C4B49A' stroke-width='.5' fill='none' opacity='.3'/%3E%3Cpath d='M15 60 L30 45 L45 60' stroke='%23C4B49A' stroke-width='.5' fill='none' opacity='.3'/%3E%3Cpath d='M30 15 L45 30 L30 45 L15 30 Z' stroke='%23C4B49A' stroke-width='.4' fill='none' opacity='.25'/%3E%3C/pattern%3E%3C/defs%3E%3Crect width='60' height='60' fill='url(%23p)'/%3E%3C/svg%3E");
    background-size:60px 60px;
  }

  .mob-bubble { display:flex;gap:8px;max-width:88%;animation:fadeUp .22s ease both; }
  .mob-bubble.user { align-self:flex-end;flex-direction:row-reverse; }
  .mob-bubble.ai   { align-self:flex-start; }

  .mob-bubble-avatar {
    width:30px;height:30px;border-radius:50%;flex-shrink:0;margin-top:2px;
    display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;
  }
  .mob-bubble.user .mob-bubble-avatar { background:#8B5E30;color:#fff; }
  .mob-bubble.ai   .mob-bubble-avatar { background:#3D2510;color:#fff; }
  .mob-bubble.ai   .mob-bubble-avatar .material-symbols-outlined { font-size:16px; }

  .mob-bubble-content {
    padding:10px 13px;font-size:13.5px;line-height:1.6;border-radius:4px 16px 16px 16px;
    background:#FDFAF5;border:1px solid #EAE4D8;color:#2C1E0F;
  }
  .mob-bubble.user .mob-bubble-content {
    background:#8B5E30;color:#fff;border:none;border-radius:16px 4px 16px 16px;
  }
  .mob-bubble-content p { margin:0 0 6px; }
  .mob-bubble-content p:last-child { margin-bottom:0; }
  .mob-bubble-content strong { font-weight:600; }
  .mob-bubble-content ul, .mob-bubble-content ol { padding-left:16px;margin:4px 0; }
  .mob-bubble-content li { margin-bottom:3px; }
  .mob-bubble-time { font-size:10px;color:#B0A090;margin-top:3px; }
  .mob-bubble.user .mob-bubble-time { text-align:right; }

  .mob-typing {
    display:flex;align-items:center;gap:4px;
    padding:10px 13px;background:#FDFAF5;border:1px solid #EAE4D8;
    border-radius:4px 16px 16px 16px;width:fit-content;
  }
  .mob-typing-dot {
    width:6px;height:6px;background:#B0A090;border-radius:50%;
    animation:typingBounce .9s infinite ease-in-out;
  }
  .mob-typing-dot:nth-child(2) { animation-delay:.15s; }
  .mob-typing-dot:nth-child(3) { animation-delay:.30s; }

  /* ---------- EMPTY STATE ---------- */
  .mob-chat-empty {
    flex:1;display:flex;flex-direction:column;align-items:center;
    justify-content:center;gap:8px;text-align:center;padding:24px;
  }
  .mob-chat-empty .material-symbols-rounded { font-size:44px;color:#D4C9B8;font-variation-settings:'FILL' 1; }
  .mob-chat-empty-title { font-size:14.5px;font-weight:600;color:#5C4030; }
  .mob-chat-empty-sub   { font-size:12.5px;color:#9C8070;max-width:240px;line-height:1.6; }

  /* ---------- INPUT BAR (fixed bottom) ---------- */
  .mob-input-bar {
    position:fixed;
    bottom:0;left:0;right:0;
    padding:10px 14px;
    padding-bottom:calc(10px + env(safe-area-inset-bottom));
    background:#FDFAF5;
    border-top:1px solid #EAE4D8;
    z-index:200;
  }
  .mob-input-row {
    display:flex;gap:8px;align-items:flex-end;
    background:#F5F0E8;border:1.5px solid #D4C9B8;
    border-radius:14px;padding:9px 10px;transition:border-color .18s;
  }
  .mob-input-row:focus-within { border-color:#8B5E30; }
  #mob-chat-input {
    flex:1;border:none;outline:none;background:transparent;
    font-family:inherit;font-size:15px;color:#2C1E0F;
    resize:none;max-height:100px;line-height:1.5;
  }
  #mob-chat-input::placeholder { color:#B0A090; }
  #mob-send-btn {
    width:32px;height:32px;border-radius:10px;background:#8B5E30;
    border:none;color:#fff;display:flex;align-items:center;justify-content:center;
    flex-shrink:0;cursor:pointer;transition:background .15s,transform .1s;
  }
  #mob-send-btn:active:not(:disabled) { transform:scale(.93);background:#6E4420; }
  #mob-send-btn:disabled { background:#C4B8A8;cursor:not-allowed; }
  #mob-send-btn .material-symbols-rounded { font-size:19px; }

  /* ---------- INPUT BAR FIXED ---------- */
  /* Navbar dihilangkan — input bar langsung fixed di bawah */

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
      <div class="mob-hdr-left">
        <a href="dashboard.php" class="mob-back-btn" aria-label="Kembali">
          <span class="material-symbols-rounded">arrow_back</span>
        </a>
        <div class="mob-ai-badge">
          <span class="material-symbols-rounded" style="font-variation-settings:\'FILL\' 1,\'wght\' 600;">assistant</span>
        </div>
        <div>
          <div class="mob-greeting">DamGPT</div>
          <div class="mob-greeting-sub">Asisten keuangan pribadimu</div>
        </div>
      </div>
      <div class="mob-hdr-actions">
        <button class="mob-clear-btn" id="mob-clear-btn">
          <span class="material-symbols-rounded">refresh</span> Reset
        </button>
      </div>
    </div>
  </div>

  <!-- SUGGESTION CHIPS -->
  <div class="mob-suggestions" id="mob-suggestions">
    <button class="mob-suggestion-chip" onclick="mobSendSuggestion(this)">📊 Ringkasan keuangan</button>
    <button class="mob-suggestion-chip" onclick="mobSendSuggestion(this)">💡 Tips hemat</button>
    <button class="mob-suggestion-chip" onclick="mobSendSuggestion(this)">📈 Pengeluaran terbesar</button>
    <button class="mob-suggestion-chip" onclick="mobSendSuggestion(this)">🎯 Target tabungan</button>
  </div>

  <!-- CHAT BODY -->
  <div class="mob-chat-body" id="mob-chat-body">
    <div class="mob-chat-empty" id="mob-empty-state">
      <span class="material-symbols-rounded">finance</span>
      <div class="mob-chat-empty-title">Halo, <?= htmlspecialchars($displayUsername) ?>! 👋</div>
      <div class="mob-chat-empty-sub">Tanyakan kondisi keuangan, minta saran hemat, atau diskusi strategi tabungan.</div>
    </div>
  </div>

  <!-- INPUT BAR -->
  <div class="mob-input-bar">
    <div class="mob-input-row">
      <textarea id="mob-chat-input" rows="1" placeholder="Tanya DamGPT..." maxlength="800"></textarea>
      <button id="mob-send-btn" onclick="mobSendMessage()">
        <span class="material-symbols-rounded">send</span>
      </button>
    </div>
  </div>

</div>
<!-- end mob-shell -->

<!-- Navbar mobile dihapus di halaman DamGPT — digantikan input bar fixed -->

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
      <a href="damgpt.php"     class="nav-item active"><span class="material-symbols-rounded nav-icon-google" style="font-variation-settings:'FILL' 1,'wght' 600;">assistant</span>DamGPT<span class="badge-hot">HOT</span></a>
      <a href="pengaturan.php" class="nav-item"><span class="material-symbols-rounded nav-icon-google">settings</span>Pengaturan</a>
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

  <div class="main-content" style="display:flex;flex-direction:column;overflow:hidden;">
    <header class="topbar">
      <button class="hamburger" style="display:none;background:none;border:none;padding:6px;cursor:pointer">
        <span class="material-symbols-rounded" style="font-size:22px;vertical-align:middle">menu</span>
      </button>
      <h1 class="topbar-title">DamGPT</h1>
      <div class="topbar-actions">
        <button id="clear-btn" title="Bersihkan percakapan"
          style="background:none;border:none;display:flex;align-items:center;gap:5px;color:var(--text-muted);font-size:13px;cursor:pointer;padding:4px 8px;border-radius:8px;transition:background .2s;"
          onmouseenter="this.style.background='var(--surface-alt)'" onmouseleave="this.style.background='none'">
          <span class="material-symbols-rounded" style="font-size:18px;">refresh</span>
          <span>Reset</span>
        </button>
      </div>
    </header>

    <div class="chat-wrap">
      <div class="damgpt-hero">
        <div class="damgpt-hero-icon">
          <span class="material-symbols-rounded" style="font-variation-settings:\'FILL\' 1,\'wght\' 600;">assistant</span>
        </div>
        <div>
          <div class="damgpt-hero-title">DamGPT — AI Agent Pribadimu</div>
          <div class="damgpt-hero-sub">Tanyakan apa saja seputar keuangan kamu. DamGPT menganalisis data transaksimu secara real-time.</div>
        </div>
      </div>

      <div class="suggestions" id="suggestions">
        <button class="suggestion-chip" onclick="sendSuggestion(this)">📊 Ringkasan keuangan bulan ini</button>
        <button class="suggestion-chip" onclick="sendSuggestion(this)">💡 Tips hemat berdasarkan pengeluaranku</button>
        <button class="suggestion-chip" onclick="sendSuggestion(this)">📈 Kategori terbesar pengeluaranku</button>
        <button class="suggestion-chip" onclick="sendSuggestion(this)">🎯 Saran mencapai target tabungan</button>
      </div>

      <div class="chat-messages" id="chat-messages">
        <div class="chat-empty" id="empty-state">
          <span class="material-symbols-rounded">finance</span>
          <div class="chat-empty-title">Halo, <?= htmlspecialchars($displayUsername) ?>! 👋</div>
          <div class="chat-empty-sub">Mulai percakapan dengan DamGPT. Tanyakan kondisi keuangan, minta saran hemat, atau diskusi strategi tabungan.</div>
        </div>
      </div>

      <div class="chat-input-wrap">
        <div class="chat-input-row">
          <textarea id="chat-input" rows="1" placeholder="Tanya DamGPT tentang keuanganmu..." maxlength="600"></textarea>
          <button id="send-btn" onclick="sendMessage()">
            <span class="material-symbols-rounded">send</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="../assets/js/app.js"></script>
<script>
const IS_MOBILE = window.innerWidth <= 600;
const INITIALS  = <?= json_encode($initials) ?>;

if (IS_MOBILE) {
  document.querySelector('.mob-shell').style.display  = 'flex';
  document.querySelector('.app-layout').style.display = 'none';
} else {
  document.querySelector('.mob-shell').style.display  = 'none';
  document.querySelector('.app-layout').style.display = '';
}

// ── Hamburger ───────────────────────────────────────────────
document.querySelector('.hamburger')?.addEventListener('click', () => {
  document.querySelector('.sidebar')?.classList.toggle('open');
  document.getElementById('sidebar-overlay').style.display = 'block';
});

// ══════════════════════════════════════════════════
// DESKTOP CHAT
// ══════════════════════════════════════════════════
let history    = [];
let isLoading  = false;

const messagesEl = document.getElementById('chat-messages');
const inputEl    = document.getElementById('chat-input');
const sendBtn    = document.getElementById('send-btn');
const emptyState = document.getElementById('empty-state');
const suggestEl  = document.getElementById('suggestions');

if (inputEl) {
  inputEl.addEventListener('input', () => {
    inputEl.style.height = 'auto';
    inputEl.style.height = Math.min(inputEl.scrollHeight, 120) + 'px';
  });
  inputEl.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
  });
}

function sendSuggestion(btn) {
  const text = btn.textContent.replace(/^[^\w\u00C0-\u024F\u4E00-\u9FFF]+/u, '').trim();
  inputEl.value = text;
  sendMessage();
}

async function fetchWithRetry(url, options, retries = 3, delay = 2000) {
  try {
    const res = await fetch(url, options);
    if (res.status === 429 && retries > 0) {
      await new Promise(r => setTimeout(r, delay));
      return fetchWithRetry(url, options, retries - 1, delay * 2);
    }
    return res;
  } catch (err) {
    if (retries > 0) {
      await new Promise(r => setTimeout(r, delay));
      return fetchWithRetry(url, options, retries - 1, delay * 2);
    }
    throw err;
  }
}

async function sendMessage() {
  if (!inputEl) return;
  const text = inputEl.value.trim();
  if (!text || isLoading) return;

  if (emptyState) emptyState.style.display = 'none';
  if (suggestEl)  suggestEl.style.display  = 'none';

  appendBubble('user', text);
  history.push({ role: 'user', text });
  inputEl.value = '';
  inputEl.style.height = 'auto';

  const typingId = showTyping();
  setLoading(true);

  try {
    const res  = await fetchWithRetry('damgpt.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ message: text, history: history.slice(0, -1) })
    });
    const data = await res.json();
    removeTyping(typingId);
    if (!res.ok || data.error) {
      let err = data.error || 'Terjadi kesalahan.';
      if (res.status === 429) err = 'DamGPT sedang dalam tahap pengembangan, belum dapat digunakan saat ini.';
      appendBubble('ai', '⚠️ ' + err, true);
    } else {
      appendBubble('ai', data.reply);
      history.push({ role: 'assistant', text: data.reply });
    }
  } catch (err) {
    removeTyping(typingId);
    appendBubble('ai', '⚠️ Gagal terhubung ke DamGPT. Periksa koneksi lokal Anda.', true);
  }
  setLoading(false);
}

function setLoading(val) {
  isLoading = val;
  if (sendBtn) sendBtn.disabled = val;
}

function appendBubble(role, text, isError = false) {
  const wrap        = document.createElement('div');
  wrap.className    = `bubble ${role}`;
  const avatar      = document.createElement('div');
  avatar.className  = 'bubble-avatar';
  avatar.textContent = role === 'user' ? INITIALS : '';
  if (role === 'ai') avatar.innerHTML = '<span class="material-symbols-rounded" style="font-variation-settings:\'FILL\' 1,\'wght\' 600;">assistant</span>';
  const cWrap = document.createElement('div');
  cWrap.style.cssText = 'display:flex;flex-direction:column;max-width:100%';
  const content     = document.createElement('div');
  content.className = 'bubble-content';
  if (isError) content.style.background = 'var(--terra-bg)';
  content.innerHTML = markdownToHtml(text);
  const time        = document.createElement('div');
  time.className    = 'bubble-time';
  time.textContent  = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
  cWrap.append(content, time);
  wrap.append(avatar, cWrap);
  messagesEl.appendChild(wrap);
  messagesEl.scrollTop = messagesEl.scrollHeight;
}

function showTyping() {
  const id   = 'typing-' + Date.now();
  const wrap = document.createElement('div');
  wrap.className = 'bubble ai'; wrap.id = id;
  const avatar   = document.createElement('div');
  avatar.className = 'bubble-avatar';
  avatar.innerHTML = '<span class="material-symbols-rounded" style="font-variation-settings:\'FILL\' 1,\'wght\' 600;">assistant</span>';
  const ind = document.createElement('div');
  ind.className = 'typing-indicator';
  for (let i = 0; i < 3; i++) { const d = document.createElement('div'); d.className = 'typing-dot'; ind.appendChild(d); }
  wrap.append(avatar, ind);
  messagesEl.appendChild(wrap);
  messagesEl.scrollTop = messagesEl.scrollHeight;
  return id;
}

function removeTyping(id) { document.getElementById(id)?.remove(); }

document.getElementById('clear-btn')?.addEventListener('click', () => {
  history = [];
  messagesEl?.querySelectorAll('.bubble').forEach(b => b.remove());
  if (emptyState) emptyState.style.display = '';
  if (suggestEl)  suggestEl.style.display  = '';
});

// ══════════════════════════════════════════════════
// MOBILE CHAT
// ══════════════════════════════════════════════════
let mobHistory   = [];
let mobLoading   = false;

const mobBody    = document.getElementById('mob-chat-body');
const mobInput   = document.getElementById('mob-chat-input');
const mobSendBtn = document.getElementById('mob-send-btn');
const mobEmpty   = document.getElementById('mob-empty-state');
const mobSuggest = document.getElementById('mob-suggestions');

if (mobInput) {
  mobInput.addEventListener('input', () => {
    mobInput.style.height = 'auto';
    mobInput.style.height = Math.min(mobInput.scrollHeight, 100) + 'px';
  });
  mobInput.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); mobSendMessage(); }
  });
}

function mobSendSuggestion(btn) {
  const text = btn.textContent.replace(/^[^\w\u00C0-\u024F\u4E00-\u9FFF]+/u, '').trim();
  mobInput.value = text;
  mobSendMessage();
}

async function mobSendMessage() {
  if (!mobInput) return;
  const text = mobInput.value.trim();
  if (!text || mobLoading) return;

  if (mobEmpty)   mobEmpty.style.display   = 'none';
  if (mobSuggest) mobSuggest.style.display = 'none';

  mobAppendBubble('user', text);
  mobHistory.push({ role: 'user', text });
  mobInput.value = '';
  mobInput.style.height = 'auto';

  const typingEl = mobShowTyping();
  mobSetLoading(true);

  try {
    const res  = await fetchWithRetry('damgpt.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ message: text, history: mobHistory.slice(0, -1) })
    });
    const data = await res.json();
    typingEl.remove();
    if (!res.ok || data.error) {
      let err = data.error || 'Terjadi kesalahan.';
      if (res.status === 429) err = 'DamGPT sedang dalam tahap pengembangan, belum dapat digunakan saat ini.';
      mobAppendBubble('ai', '⚠️ ' + err, true);
    } else {
      mobAppendBubble('ai', data.reply);
      mobHistory.push({ role: 'assistant', text: data.reply });
    }
  } catch (err) {
    typingEl.remove();
    mobAppendBubble('ai', '⚠️ Gagal terhubung ke DamGPT. Periksa koneksi Anda.', true);
  }
  mobSetLoading(false);
}

function mobSetLoading(val) {
  mobLoading = val;
  if (mobSendBtn) mobSendBtn.disabled = val;
}

function mobAppendBubble(role, text, isError = false) {
  const wrap       = document.createElement('div');
  wrap.className   = `mob-bubble ${role}`;
  const avatar     = document.createElement('div');
  avatar.className = 'mob-bubble-avatar';
  if (role === 'user') avatar.textContent = INITIALS;
  else avatar.innerHTML = '<span class="material-symbols-rounded" style="font-variation-settings:\'FILL\' 1,\'wght\' 600;">assistant</span>';
  const cWrap = document.createElement('div');
  cWrap.style.cssText = 'display:flex;flex-direction:column;max-width:100%';
  const content     = document.createElement('div');
  content.className = 'mob-bubble-content';
  if (isError) content.style.background = '#FEF0E6';
  content.innerHTML = markdownToHtml(text);
  const time        = document.createElement('div');
  time.className    = 'mob-bubble-time';
  time.textContent  = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
  cWrap.append(content, time);
  wrap.append(avatar, cWrap);
  mobBody.appendChild(wrap);
  mobBody.scrollTop = mobBody.scrollHeight;
}

function mobShowTyping() {
  const wrap = document.createElement('div');
  wrap.className = 'mob-bubble ai';
  const avatar   = document.createElement('div');
  avatar.className = 'mob-bubble-avatar';
  avatar.innerHTML = '<span class="material-symbols-rounded" style="font-variation-settings:\'FILL\' 1,\'wght\' 600;">assistant</span>';
  const ind = document.createElement('div');
  ind.className = 'mob-typing';
  for (let i = 0; i < 3; i++) { const d = document.createElement('div'); d.className = 'mob-typing-dot'; ind.appendChild(d); }
  wrap.append(avatar, ind);
  mobBody.appendChild(wrap);
  mobBody.scrollTop = mobBody.scrollHeight;
  return wrap;
}

document.getElementById('mob-clear-btn')?.addEventListener('click', () => {
  mobHistory = [];
  mobBody?.querySelectorAll('.mob-bubble').forEach(b => b.remove());
  if (mobEmpty)   mobEmpty.style.display   = '';
  if (mobSuggest) mobSuggest.style.display = '';
});

// ── Shared markdown parser ───────────────────────────────────
function markdownToHtml(text) {
  return text
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.*?)\*/g, '<em>$1</em>')
    .replace(/^[•\-\*] (.+)$/gm, '<li>$1</li>')
    .replace(/(<li>.*<\/li>)/gs, '<ul>$1</ul>')
    .replace(/^\d+\. (.+)$/gm, '<li>$1</li>')
    .split(/\n\n+/)
    .map(p => p.trim() ? (p.startsWith('<ul>') || p.startsWith('<li>') ? p : `<p>${p.replace(/\n/g, '<br>')}</p>`) : '')
    .filter(Boolean)
    .join('');
}
</script>
</body>
</html>