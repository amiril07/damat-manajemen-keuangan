<?php
// ============================================================
// pages/dashboard.php
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
        }
    }
    if ($action === 'update_budget') {
        $budget = abs((float)($_POST['budget'] ?? 0));
        updateBudget($_SESSION['user_id'], $budget);
    }
    header('Location: dashboard.php');
    exit;
}

$user      = currentUser();
$summary   = getMonthlySummary($user['id']);
$chart7    = getLast7DaysChart($user['id']);
$catData   = getCategoryBreakdown($user['id']);

$budget    = (float)($user['monthly_budget'] ?? 0);
$expense   = (float)$summary['expense'];
$income    = (float)$summary['income'];
$balance   = (float)$summary['balance'];
$pct       = $budget > 0 ? min(($expense / $budget) * 100, 100) : 0;
$isDanger  = $pct >= 80;
$isWarning = $pct >= 60 && !$isDanger;
$barClass  = $isDanger ? 'danger' : ($isWarning ? 'warning' : '');
$barColor  = $isDanger ? '#C4600A' : ($isWarning ? '#D97706' : '#5F8B5A');

$chartLabels = $chartIncome = $chartExpense = [];
foreach ($chart7 as $row) {
    $chartLabels[]  = $row['label'];
    $chartIncome[]  = (float)$row['income'];
    $chartExpense[] = (float)$row['expense'];
}
$catLabels = array_column($catData, 'category');
$catTotals = array_map('floatval', array_column($catData, 'total'));

$catIcons = [
    'Makanan'=>'restaurant','Transportasi'=>'commute','Belanja'=>'shopping_bag',
    'Tagihan'=>'payments','Kesehatan'=>'medical_services','Hiburan'=>'sports_esports',
    'Pendidikan'=>'school','Pakaian'=>'checkroom','Gaji'=>'savings',
    'Freelance'=>'laptop_mac','Bonus'=>'redeem','Investasi'=>'monitoring',
    'Hadiah'=>'card_giftcard','Lainnya'=>'category',
];

function fmtRp(float $n): string { return 'Rp '.number_format($n,0,',','.'); }
function fmtDate(string $d): string {
    $m = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'];
    $p = explode('-',$d); if(count($p)<3) return $d;
    return $p[2].' '.$m[(int)$p[1]].' '.$p[0];
}

$displayUsername = $user['username'] ?? 'User';
$initials        = strtoupper(substr($displayUsername, 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Damat</title>
  <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon/favicon-32x32.png">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block"/>
  <link rel="stylesheet" href="../assets/css/dashboard.css">
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
        <div class="mob-greeting">👋 Hei, <?= htmlspecialchars(explode('@',$displayUsername)[0]) ?>!</div>
        <div class="mob-greeting-sub">Pantau keuanganmu hari ini</div>
      </div>
      <a href="pengaturan.php" class="mob-avatar"><?= $initials ?></a>
    </div>

    <div class="mob-period">
      <span class="material-symbols-rounded">calendar_month</span>
      <?= date('d, M Y') ?> &nbsp;·&nbsp; Saldo Bersih
    </div>

    <div class="mob-bal-section">
      <div class="mob-bal-lbl">
        <span class="material-symbols-rounded">account_balance_wallet</span>
        DANA BERSIH
        <button class="mob-bal-eye" id="mob-bal-eye" onclick="toggleBalVisibility()" aria-label="Sembunyikan nominal">
          <span class="material-symbols-rounded" id="mob-eye-icon">visibility</span>
        </button>
      </div>
      <div class="mob-bal-amt">
        <span class="bal-real"><?= fmtRp(max(0,$budget-$expense+$income)) ?></span>
        <span class="bal-sensor lg"></span>
      </div>

      <div class="mob-minicards">
        <div class="mob-minicard">
          <div class="mob-mc-icon inc"><span class="material-symbols-rounded"><strong>arrow_downward</strong></span></div>
          <div>
            <div class="mob-mc-lbl">Pemasukan</div>
            <div class="mob-mc-val">
              <span class="bal-real"><?= fmtRp($income) ?></span>
              <span class="bal-sensor sm"></span>
            </div>
          </div>
        </div>
        <img src="../assets/img/dompet.png" alt="dompet" class="mob-wallet-img">
        <div class="mob-minicard">
          <div class="mob-mc-icon exp"><span class="material-symbols-rounded"><strong>arrow_upward</strong></span></div>
          <div>
            <div class="mob-mc-lbl">Pengeluaran</div>
            <div class="mob-mc-val">
              <span class="bal-real"><?= fmtRp($expense) ?></span>
              <span class="bal-sensor sm"></span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- BUDGET BAR -->
  <?php if ($budget > 0): ?>
  <div class="mob-budget">
    <div class="mob-budget-hdr">
      <span class="mob-budget-lbl">Anggaran Bulanan</span>
      <span class="mob-budget-pct" style="color:<?= $barColor ?>"><?= round($pct) ?>%</span>
    </div>
    <div class="mob-btrack">
      <div class="mob-bfill <?= $barClass ?>" style="width:<?= $pct ?>%"></div>
    </div>
    <div class="mob-budget-meta">
      <span><?= fmtRp($expense) ?> terpakai</span>
      <span>Sisa <?= fmtRp(max(0,$budget-$expense)) ?></span>
    </div>
  </div>
  <?php else: ?>
  <div style="height:14px"></div>
  <?php endif; ?>

  <!-- SHORTCUT MENU -->
  <div class="mob-shortcuts">
    <div class="mob-sc-grid">
      <a href="tambah.php" class="mob-sc-btn">
        <div class="mob-sc-icon brown"><span class="material-symbols-rounded">add_circle</span></div>
        <span class="mob-sc-lbl">Catat Transaksi</span>
      </a>
      <a href="transaksi.php" class="mob-sc-btn">
        <div class="mob-sc-icon sage"><span class="material-symbols-rounded">history</span></div>
        <span class="mob-sc-lbl">Riwayat</span>
      </a>
      <a href="damgpt.php" class="mob-sc-btn">
        <div class="mob-sc-icon terra"><span class="material-symbols-rounded" style="font-size:26px;font-variation-settings:'FILL' 1,'wght' 600;">assistant</span></div>
        <div class="mob-sc-lbl-wrap">
          <span class="mob-sc-lbl">DamGPT</span>
          <span class="mob-sc-hot">HOT</span>
        </div>
      </a>
      <a href="pengaturan.php" class="mob-sc-btn">
        <div class="mob-sc-icon gray"><span class="material-symbols-rounded">settings</span></div>
        <span class="mob-sc-lbl">Pengaturan</span>
      </a>
    </div>
  </div>

  <!-- CHART 7 HARI -->
  <div class="mob-section">
    <div class="mob-sec-hdr">
      <span class="mob-sec-ttl">Arus Keuangan 7 Hari</span>
    </div>
    <div class="mob-chart"><canvas id="mob-line"></canvas></div>
  </div>

  <!-- KATEGORI -->
  <?php if (!empty($catData)): ?>
  <div class="mob-section">
    <div class="mob-sec-hdr">
      <span class="mob-sec-ttl">Pengeluaran per Kategori</span>
      <a href="transaksi.php" class="mob-sec-link">Lihat semua →</a>
    </div>
    <div class="mob-chart" style="height:160px"><canvas id="mob-donut"></canvas></div>
  </div>
  <?php else: ?>
  <div class="mob-section">
    <div class="mob-empty">Belum ada data pengeluaran bulan ini</div>
  </div>
  <?php endif; ?>

  <div class="mob-foot"></div>
</div>
<!-- end mob-shell -->

<!-- BOTTOM NAV (mobile) -->
<nav class="mob-nav" style="display:none">
  <a href="dashboard.php" class="mob-nav-i on">
    <span class="material-symbols-rounded" style="font-variation-settings:'FILL' 1">home</span>
    <span class="lbl">Beranda</span>
  </a>
  <a href="transaksi.php" class="mob-nav-i">
    <span class="material-symbols-rounded">receipt_long</span>
    <span class="lbl">Transaksi</span>
  </a>
  <div class="mob-nav-fab">
    <a href="tambah.php" class="mob-fab">
      <span class="material-symbols-rounded"><strong>add</strong></span>
    </a>
  </div>
  <a href="damgpt.php" class="mob-nav-i mob-nav-damgpt">
    <span style="position:relative;display:inline-block;">
      <span class="material-symbols-rounded" style="font-size:22px;font-variation-settings:'FILL' 1,'wght' 600;display:block;">assistant</span>
      <span class="mob-nav-hot">HOT</span>
    </span>
    <span class="lbl">DamGPT</span>
  </a>
  <a href="pengaturan.php" class="mob-nav-i">
    <span class="material-symbols-rounded">person</span>
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
      <a href="dashboard.php"  class="nav-item active"><span class="material-symbols-rounded nav-icon-google">grid_view</span>Dashboard</a>
      <a href="transaksi.php"  class="nav-item"><span class="material-symbols-rounded nav-icon-google">history</span>Riwayat</a>
      <a href="tambah.php"     class="nav-item"><span class="material-symbols-rounded nav-icon-google">add_circle</span>Tambah</a>
      <a href="damgpt.php"     class="nav-item"><span class="material-symbols-rounded nav-icon-google" style="font-variation-settings:'FILL' 1,'wght' 600;">assistant</span>DamGPT<span class="badge-hot">HOT</span></a>
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

  <div class="main-content">
    <header class="topbar">
      <button class="hamburger" style="display:none;background:none;border:none;padding:6px;cursor:pointer">
        <span class="material-symbols-rounded" style="font-size:22px;vertical-align:middle">menu</span>
      </button>
      <div style="display:flex;align-items:center;gap:12px;">
        <div>
          <div class="topbar-greeting-name">👋 Hai, <?= htmlspecialchars($displayUsername) ?>!</div>
          <div class="topbar-greeting-sub"><?= date('l, d F Y') ?></div>
        </div>
      </div>
      <div class="topbar-actions" style="display:flex;align-items:center;gap:10px">
        <span style="font-size:12px;color:var(--text-muted);background:var(--surface);border:1px solid var(--border-light);padding:5px 12px;border-radius:99px;display:flex;align-items:center;gap:5px">
          <span class="material-symbols-rounded" style="font-size:13px">calendar_today</span>
          <?= date('d M Y') ?>
        </span>
        <a href="tambah.php" class="btn btn-primary btn-sm" style="display:flex;align-items:center;gap:5px">
          <span class="material-symbols-rounded" style="font-size:15px">add</span> Tambah Transaksi
        </a>
      </div>
    </header>

    <main class="page-body">

      <!-- Stat Cards -->
      <div class="stats-grid">
        <div class="stat-card balance">
          <div class="stat-icon"><span class="material-symbols-rounded">account_balance_wallet</span></div>
          <div class="stat-label">Saldo Bersih</div>
          <div class="stat-value" style="color:<?= $balance>=0 ? 'var(--income-clr)' : 'var(--expense-clr)' ?>"><?= fmtRp($balance) ?></div>
          <div class="stat-sub">Bulan <?= date('M Y') ?></div>
        </div>
        <div class="stat-card income">
          <div class="stat-icon"><span class="material-symbols-rounded">trending_up</span></div>
          <div class="stat-label">Total Pemasukan</div>
          <div class="stat-value"><?= fmtRp($income) ?></div>
          <div class="stat-sub">Bulan <?= date('M Y') ?></div>
        </div>
        <div class="stat-card expense">
          <div class="stat-icon"><span class="material-symbols-rounded">trending_down</span></div>
          <div class="stat-label">Total Pengeluaran</div>
          <div class="stat-value"><?= fmtRp($expense) ?></div>
          <div class="stat-sub">Bulan <?= date('M Y') ?></div>
        </div>
      </div>

      <!-- Budget Bar -->
      <?php if ($budget > 0): ?>
      <div class="budget-bar-wrap">
        <div class="budget-header">
          <div>
            <div class="budget-title">Anggaran Bulanan</div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:3px"><?= fmtRp($expense) ?> dari <?= fmtRp($budget) ?></div>
          </div>
          <div class="budget-pct" style="color:<?= $barColor ?>"><?= round($pct) ?>%</div>
        </div>
        <div class="budget-bar">
          <div class="budget-bar-fill <?= $barClass ?>" style="width:<?= $pct ?>%"></div>
        </div>
        <div class="budget-meta">
          <span style="display:flex;align-items:center;gap:5px;color:<?= $barColor ?>">
            <span class="material-symbols-rounded" style="font-size:14px"><?= $isDanger?'warning':($isWarning?'info':'check_circle') ?></span>
            <?= $isDanger?'Anggaran hampis habis!':($isWarning?'Perhatikan pengeluaran':'Anggaran masih aman') ?>
          </span>
          <span>Sisa: <strong><?= fmtRp(max(0,$budget-$expense)) ?></strong></span>
        </div>
      </div>
      <?php endif; ?>

      <!-- Dashboard Grid: Charts (kiri) + Transaksi Terbaru (kanan) -->
      <div class="dashboard-grid">

        <!-- KIRI: Charts -->
        <div class="dashboard-left">
          <div class="chart-card">
            <div class="chart-title" style="display:flex;align-items:center;gap:7px;">
              <span class="material-symbols-rounded" style="font-size:17px;color:var(--brown-400)">show_chart</span>
              Arus Keuangan 7 Hari
            </div>
            <div class="chart-container"><canvas id="line-chart"></canvas></div>
          </div>
          <div class="chart-card">
            <div class="chart-title" style="display:flex;align-items:center;gap:7px;">
              <span class="material-symbols-rounded" style="font-size:17px;color:var(--terra)">donut_large</span>
              Pengeluaran per Kategori
            </div>
            <div class="chart-container" style="height:220px!important;">
              <?php if (!empty($catData)): ?>
              <canvas id="donut-chart"></canvas>
              <?php else: ?>
              <div class="empty-state" style="padding:36px 0;text-align:center">
                <span class="material-symbols-rounded" style="font-size:42px;color:var(--brown-200)">pie_chart</span>
                <div style="margin-top:9px;color:var(--text-muted);font-size:13px">Belum ada data kategori</div>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- KANAN: Transaksi Terbaru + Aksi Cepat -->
        <div class="dashboard-right">
          <!-- Transaksi Terbaru -->
          <div class="recent-tx-card">
            <div class="recent-tx-header">
              <div class="recent-tx-title">
                <span class="material-symbols-rounded" style="font-size:17px;color:var(--brown-400)">receipt_long</span>
                Transaksi Terbaru
              </div>
              <a href="transaksi.php" class="recent-tx-link">Lihat Semua →</a>
            </div>
            <?php
              $catIconsLocal = [
                'Makanan'=>'restaurant','Transportasi'=>'commute','Belanja'=>'shopping_bag',
                'Tagihan'=>'payments','Kesehatan'=>'medical_services','Hiburan'=>'sports_esports',
                'Pendidikan'=>'school','Pakaian'=>'checkroom','Gaji'=>'savings',
                'Freelance'=>'laptop_mac','Bonus'=>'redeem','Investasi'=>'monitoring',
                'Hadiah'=>'card_giftcard','Lainnya'=>'category',
              ];
              $recentTx = function_exists('getRecentTransactions')
                ? getRecentTransactions($user['id'] ?? 0, 10)
                : [];
              if (empty($recentTx)):
            ?>
            <div class="empty-state" style="padding:24px 0;text-align:center;">
              <span class="material-symbols-rounded" style="font-size:40px;color:var(--brown-200)">receipt_long</span>
              <div style="margin-top:8px;font-weight:600;font-size:13.5px;color:var(--text-secondary)">Belum Ada Transaksi</div>
              <div style="font-size:12px;color:var(--text-muted);margin-top:3px">Mulai catat keuanganmu!</div>
            </div>
            <?php else: ?>
            <div class="tx-list">
              <?php foreach ($recentTx as $tx):
                $icon = $catIconsLocal[$tx['category']] ?? 'label';
              ?>
              <div class="tx-card" data-type="<?= $tx['type'] ?>" style="padding:10px 12px;">
                <div class="tx-icon <?= $tx['type'] ?>">
                  <span class="material-symbols-rounded"><?= $icon ?></span>
                </div>
                <div class="tx-info">
                  <div class="tx-category"><?= htmlspecialchars($tx['category']) ?></div>
                  <div class="tx-note"><?= $tx['note'] ? htmlspecialchars($tx['note']) : '&nbsp;' ?></div>
                </div>
                <div class="tx-right">
                  <div class="tx-amount <?= $tx['type'] ?>">
                    <?= $tx['type']==='income' ? '+' : '-' ?><?= fmtRp((float)$tx['amount']) ?>
                  </div>
                  <div class="tx-date"><?= fmtDate($tx['date']) ?></div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>

          <!-- Aksi Cepat -->
          <div class="recent-tx-card" style="padding:18px 20px;">
            <div class="recent-tx-title" style="margin-bottom:13px;">
              <span class="material-symbols-rounded" style="font-size:17px;color:var(--sage)">bolt</span>
              Aksi Cepat
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;">
              <a href="tambah.php?type=income" class="btn btn-sage btn-block" style="justify-content:flex-start;gap:7px;">
                <span class="material-symbols-rounded" style="font-size:17px;">add_circle</span> Catat Pemasukan
              </a>
              <a href="tambah.php?type=expense" class="btn btn-danger btn-block" style="justify-content:flex-start;gap:7px;">
                <span class="material-symbols-rounded" style="font-size:17px;">remove_circle</span> Catat Pengeluaran
              </a>
              <a href="damgpt.php" class="btn btn-primary btn-block" style="justify-content:flex-start;gap:7px;">
                <span class="material-symbols-rounded" style="font-size:17px;font-variation-settings:'FILL' 1,'wght' 600;vertical-align:middle;">assistant</span> Tanya DamGPT
              </a>
            </div>
          </div>
        </div>

      </div><!-- /dashboard-grid -->

    </main>
  </div>
</div>
<!-- end app-layout -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="../assets/js/app.js"></script>
<script>
const chartLabels  = <?= json_encode($chartLabels) ?>;
const chartIncome  = <?= json_encode($chartIncome) ?>;
const chartExpense = <?= json_encode($chartExpense) ?>;
const catLabels    = <?= json_encode($catLabels) ?>;
const catTotals    = <?= json_encode($catTotals) ?>;

Chart.defaults.maintainAspectRatio = false;
Chart.defaults.responsive = true;

const IS_MOBILE = window.innerWidth <= 600;

if (IS_MOBILE) {
  // Tampilkan elemen mobile, sembunyikan desktop
  document.querySelector('.mob-shell').style.display = 'flex';
  document.querySelector('.mob-nav').style.display   = 'flex';
  document.querySelector('.app-layout').style.display = 'none';

  // Chart line mobile
  const mobLine = document.getElementById('mob-line');
  if (mobLine) {
    new Chart(mobLine, {
      type: 'line',
      data: {
        labels: chartLabels,
        datasets: [
          { label:'Pemasukan', data:chartIncome,  borderColor:'#5F8B5A', backgroundColor:'rgba(95,139,90,.12)', tension:.4, fill:true, pointRadius:3, pointBackgroundColor:'#5F8B5A' },
          { label:'Pengeluaran', data:chartExpense, borderColor:'#C4600A', backgroundColor:'rgba(196,96,10,.10)', tension:.4, fill:true, pointRadius:3, pointBackgroundColor:'#C4600A' }
        ]
      },
      options: {
        responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ labels:{ font:{size:10}, boxWidth:10, padding:10 } } },
        scales:{
          x:{ ticks:{font:{size:10}}, grid:{display:false} },
          y:{ ticks:{font:{size:10}, callback:v=>'Rp'+Number(v).toLocaleString('id')}, grid:{color:'#EAE4D8'} }
        }
      }
    });
  }

  // Chart donut mobile
  const mobDonut = document.getElementById('mob-donut');
  if (mobDonut && catLabels.length) {
    new Chart(mobDonut, {
      type:'doughnut',
      data:{
        labels:catLabels,
        datasets:[{ data:catTotals, backgroundColor:['#8B5E30','#5F8B5A','#C4600A','#A07850','#4A7C42','#B84A22','#9C8070'], borderWidth:0, hoverOffset:5 }]
      },
      options:{
        responsive:true, maintainAspectRatio:false, cutout:'60%',
        plugins:{ legend:{ position:'right', labels:{ font:{size:10}, boxWidth:10, padding:8 } } }
      }
    });
  }

} else {
  // Desktop
  document.querySelector('.mob-shell').style.display  = 'none';
  document.querySelector('.mob-nav').style.display    = 'none';
  document.querySelector('.app-layout').style.display = '';

  if (typeof initLineChart    === 'function') initLineChart(chartLabels, chartIncome, chartExpense);
  if (typeof initDoughnutChart=== 'function') initDoughnutChart(catLabels, catTotals);
}

// Hamburger (tablet)
document.querySelector('.hamburger')?.addEventListener('click', () => {
  document.querySelector('.sidebar')?.classList.toggle('open');
  document.getElementById('sidebar-overlay').style.display = 'block';
});
</script>
  <script>
  // ── Toggle sembunyikan / tampilkan nominal ──────────────────
  (function() {
    const KEY = 'damat_bal_hidden';

    function applyState(hidden) {
      if (hidden) {
        document.body.classList.add('bal-hidden');
        document.getElementById('mob-eye-icon').textContent = 'visibility_off';
      } else {
        document.body.classList.remove('bal-hidden');
        document.getElementById('mob-eye-icon').textContent = 'visibility';
      }
    }

    // Restore state dari localStorage saat halaman load
    window.addEventListener('DOMContentLoaded', function() {
      const saved = localStorage.getItem(KEY) === '1';
      applyState(saved);
    });

    // Toggle saat tombol ditekan
    window.toggleBalVisibility = function() {
      const isNowHidden = !document.body.classList.contains('bal-hidden');
      localStorage.setItem(KEY, isNowHidden ? '1' : '0');
      applyState(isNowHidden);
    };
  })();
  </script>
</body>
</html>