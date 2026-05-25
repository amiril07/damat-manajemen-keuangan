<?php
// ============================================================
// pages/transaksi.php  —  Riwayat semua transaksi
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/transactions.php';

require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

requireLogin();

// ── Export handler (GET, sebelum output HTML apapun) ─────────
$exportAction = $_GET['export'] ?? '';

if ($exportAction === 'excel') {
    $autoloaderPaths = [
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/../../vendor/autoload.php',
        dirname(__DIR__) . '/vendor/autoload.php',
    ];
    $autoloaded = false;
    foreach ($autoloaderPaths as $p) {
        if (file_exists($p)) { require_once $p; $autoloaded = true; break; }
    }
    if (!$autoloaded) die('PhpSpreadsheet tidak ditemukan. Jalankan: composer require phpoffice/phpspreadsheet');

    $allTx    = getTransactions($_SESSION['user_id'], 9999, 0);
    $filterEx = $_GET['filter'] ?? 'all';
    $userName = currentUser()['username'] ?? 'User';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Riwayat Transaksi');

    $exportDate = date('d/m/Y H:i');
    $sheet->mergeCells('A1:F1');
    $sheet->setCellValue('A1', 'Laporan Transaksi Keuangan — Damat');
    $sheet->getStyle('A1')->applyFromArray([
        'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
        'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '6B4226']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(28);

    $sheet->mergeCells('A2:F2');
    $sheet->setCellValue('A2', 'Diekspor oleh: ' . $userName . '   |   Tanggal: ' . $exportDate);
    $sheet->getStyle('A2')->applyFromArray([
        'font'      => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '5C4030']],
        'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F5EDE3']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
    ]);

    $headers   = ['No', 'Tanggal', 'Kategori', 'Keterangan', 'Jenis', 'Jumlah (Rp)'];
    $cols      = ['A', 'B', 'C', 'D', 'E', 'F'];
    $headerRow = 3;
    foreach ($headers as $i => $h) $sheet->setCellValue($cols[$i] . $headerRow, $h);
    $sheet->getStyle("A{$headerRow}:F{$headerRow}")->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '8B5E30']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'C4A882']]],
    ]);
    $sheet->getRowDimension($headerRow)->setRowHeight(20);

    $row = $headerRow + 1; $no = 1; $totalInc = 0; $totalExp = 0;
    $months = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

    foreach ($allTx as $tx) {
        if ($filterEx !== 'all' && $tx['type'] !== $filterEx) continue;
        $isIncome = $tx['type'] === 'income';
        $amount   = (float)$tx['amount'];
        if ($isIncome) $totalInc += $amount; else $totalExp += $amount;

        $p   = explode('-', $tx['trans_date']);
        $tgl = count($p) === 3 ? $p[2].' '.$months[(int)$p[1]].' '.$p[0] : $tx['trans_date'];

        $sheet->setCellValue("A{$row}", $no);
        $sheet->setCellValue("B{$row}", $tgl);
        $sheet->setCellValue("C{$row}", $tx['category']);
        $sheet->setCellValue("D{$row}", $tx['note'] ?: '-');
        $sheet->setCellValue("E{$row}", $isIncome ? 'Pemasukan' : 'Pengeluaran');
        $sheet->setCellValue("F{$row}", $isIncome ? $amount : -$amount);

        $fillColor = ($no % 2 === 0) ? 'FDF8F2' : 'FFFFFF';
        $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
            'fill'    => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => $fillColor]],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'EAE0D5']]],
        ]);
        $colorFQCN = $isIncome ? 'FF3A6B35' : 'FF8B3A10';
        $sheet->getStyle("E{$row}")->getFont()->setBold(true);
        $sheet->getStyle("E{$row}")->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($colorFQCN));
        $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("F{$row}")->getFont()->setBold(true);
        $sheet->getStyle("F{$row}")->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($colorFQCN));
        $sheet->getStyle("A{$row}:F{$row}")->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("F{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $row++; $no++;
    }

    if ($filterEx !== 'expense') {
        $sheet->setCellValue("E{$row}", 'Total Pemasukan');
        $sheet->setCellValue("F{$row}", $totalInc);
        $sheet->getStyle("E{$row}:F{$row}")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4A7A45']]]);
        $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0');
        $row++;
    }
    if ($filterEx !== 'income') {
        $sheet->setCellValue("E{$row}", 'Total Pengeluaran');
        $sheet->setCellValue("F{$row}", $totalExp);
        $sheet->getStyle("E{$row}:F{$row}")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B05020']]]);
        $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0');
        $row++;
    }
    if ($filterEx === 'all') {
        $net = $totalInc - $totalExp;
        $sheet->setCellValue("E{$row}", 'Saldo Bersih');
        $sheet->setCellValue("F{$row}", $net);
        $sheet->getStyle("E{$row}:F{$row}")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => $net >= 0 ? '2D6A4F' : 'A93226']]]);
        $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0');
    }

    $sheet->getColumnDimension('A')->setWidth(6);
    $sheet->getColumnDimension('B')->setWidth(22);
    $sheet->getColumnDimension('C')->setWidth(18);
    $sheet->getColumnDimension('D')->setWidth(32);
    $sheet->getColumnDimension('E')->setWidth(16);
    $sheet->getColumnDimension('F')->setWidth(20);
    $sheet->freezePane('A4');

    $filename = 'Transaksi_Damat_' . date('Ymd_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

if ($exportAction === 'pdf') {
    $autoloaderPaths = [
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/../../vendor/autoload.php',
        dirname(__DIR__) . '/vendor/autoload.php',
    ];
    $autoloaded = false;
    foreach ($autoloaderPaths as $p) {
        if (file_exists($p)) { require_once $p; $autoloaded = true; break; }
    }
    if (!$autoloaded) die('Dompdf tidak ditemukan. Jalankan: composer require dompdf/dompdf');

    $allTx    = getTransactions($_SESSION['user_id'], 9999, 0);
    $filterEx = $_GET['filter'] ?? 'all';
    $userName = currentUser()['username'] ?? 'User';
    $months   = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

    $totalInc = 0; $totalExp = 0; $rows = ''; $no = 1;
    foreach ($allTx as $tx) {
        if ($filterEx !== 'all' && $tx['type'] !== $filterEx) continue;
        $isIncome = $tx['type'] === 'income';
        $amount   = (float)$tx['amount'];
        if ($isIncome) $totalInc += $amount; else $totalExp += $amount;
        $p      = explode('-', $tx['trans_date']);
        $tgl    = count($p) === 3 ? $p[2].' '.$months[(int)$p[1]].' '.$p[0] : $tx['trans_date'];
        $cat    = htmlspecialchars($tx['category']);
        $note   = htmlspecialchars($tx['note'] ?: '-');
        $jenis  = $isIncome ? 'Pemasukan' : 'Pengeluaran';
        $amtFmt = ($isIncome ? '+' : '-') . 'Rp ' . number_format($amount, 0, ',', '.');
        $rowBg  = ($no % 2 === 0) ? '#FDF8F2' : '#FFFFFF';
        $cl     = $isIncome ? '#2D6A4F' : '#8B3A10';
        $rows  .= "<tr style='background:{$rowBg}'><td style='text-align:center'>{$no}</td><td>{$tgl}</td><td>{$cat}</td><td style='color:#5C4030'>{$note}</td><td style='text-align:center;font-weight:700;color:{$cl}'>{$jenis}</td><td style='text-align:right;font-weight:700;color:{$cl}'>{$amtFmt}</td></tr>";
        $no++;
    }

    $filterLabel = ['all' => 'Semua Transaksi', 'income' => 'Pemasukan', 'expense' => 'Pengeluaran'][$filterEx] ?? 'Semua';
    $exportDate  = date('d/m/Y H:i');
    $summaryRows = '';
    if ($filterEx !== 'expense') $summaryRows .= "<tr style='background:#EAF4EA'><td colspan='5' style='font-weight:700;text-align:right;color:#2D6A4F'>Total Pemasukan</td><td style='font-weight:700;text-align:right;color:#2D6A4F'>+Rp " . number_format($totalInc,0,',','.') . "</td></tr>";
    if ($filterEx !== 'income')  $summaryRows .= "<tr style='background:#FEF0E6'><td colspan='5' style='font-weight:700;text-align:right;color:#8B3A10'>Total Pengeluaran</td><td style='font-weight:700;text-align:right;color:#8B3A10'>-Rp " . number_format($totalExp,0,',','.') . "</td></tr>";
    if ($filterEx === 'all') {
        $net = $totalInc - $totalExp; $nc = $net >= 0 ? '#2D6A4F' : '#A93226';
        $summaryRows .= "<tr style='background:" . ($net >= 0 ? '#C8E6C9' : '#FFCDD2') . "'><td colspan='5' style='font-weight:700;text-align:right;color:{$nc}'>Saldo Bersih</td><td style='font-weight:700;text-align:right;color:{$nc}'>" . ($net >= 0 ? '+' : '-') . "Rp " . number_format(abs($net),0,',','.') . "</td></tr>";
    }

    $html = <<<HTML
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">
<style>
* { margin:0;padding:0;box-sizing:border-box; }
body { font-family:DejaVu Sans,Arial,sans-serif;font-size:11px;color:#2C1E0F;background:#fff; }
.header-bar { background:#6B4226;color:#fff;padding:18px 24px; }
.header-bar h1 { font-size:18px;font-weight:700; }
.header-bar p  { font-size:10px;opacity:.75;margin-top:4px; }
.meta-bar { background:#F5EDE3;padding:8px 24px;border-bottom:2px solid #C4A882;font-size:10px;color:#5C4030; }
.table-wrap { padding:16px 24px 24px; }
table { width:100%;border-collapse:collapse;font-size:10.5px; }
thead th { background:#8B5E30;color:#fff;font-weight:700;padding:8px 7px;text-align:left;border:1px solid #C4A882; }
tbody td { padding:7px;border:1px solid #E8E0D5;vertical-align:middle; }
tfoot td { padding:8px 7px;border:1px solid #D4C9B8;font-size:11px; }
.footer-note { text-align:center;font-size:9px;color:#9C8070;margin-top:16px;padding-top:10px;border-top:1px solid #EAE4D8; }
</style></head><body>
<div class="header-bar"><h1>Laporan Transaksi Keuangan</h1><p>Aplikasi Damat &mdash; Keuangan Pribadi</p></div>
<div class="meta-bar"><span>Pengguna: <strong>{$userName}</strong> &nbsp;|&nbsp; Filter: <strong>{$filterLabel}</strong> &nbsp;|&nbsp; Diekspor: {$exportDate}</span></div>
<div class="table-wrap">
<table>
<thead><tr><th style="width:28px;text-align:center">No</th><th style="width:90px">Tanggal</th><th style="width:80px">Kategori</th><th>Keterangan</th><th style="width:78px;text-align:center">Jenis</th><th style="width:100px;text-align:right">Jumlah</th></tr></thead>
<tbody>{$rows}</tbody>
<tfoot>{$summaryRows}</tfoot>
</table>
<div class="footer-note">Dokumen dibuat otomatis oleh sistem Damat. Dicetak pada {$exportDate}.</div>
</div></body></html>
HTML;

    $options = new \Dompdf\Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false);
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream('Transaksi_Damat_' . date('Ymd_His') . '.pdf', ['Attachment' => true]);
    exit;
}

// ── POST handler (delete) ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_tx') {
        $txId = (int)($_POST['tx_id'] ?? 0);
        if ($txId > 0) deleteTransaction($_SESSION['user_id'], $txId);
    }
    header('Location: transaksi.php');
    exit;
}

$user  = currentUser();
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$off   = ($page - 1) * $limit;

$all   = getTransactions($user['id'], $limit, $off);
$total = getTransactions($user['id'], 9999, 0);
$pages = ceil(count($total) / $limit);

$catIcons = [
    'Makanan'      => 'restaurant',
    'Transportasi' => 'commute',
    'Belanja'      => 'shopping_bag',
    'Tagihan'      => 'payments',
    'Kesehatan'    => 'medical_services',
    'Hiburan'      => 'sports_esports',
    'Pendidikan'   => 'school',
    'Pakaian'      => 'checkroom',
    'Gaji'         => 'savings',
    'Freelance'    => 'laptop_mac',
    'Bonus'        => 'redeem',
    'Investasi'    => 'monitoring',
    'Hadiah'       => 'card_giftcard',
    'Lainnya'      => 'category',
];

function txIcon(string $cat): string {
    global $catIcons;
    return $catIcons[$cat] ?? 'label';
}
function fmtRp(float $n): string {
    return 'Rp ' . number_format($n, 0, ',', '.');
}
function fmtDate(string $d): string {
    $months = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'];
    $parts  = explode('-', $d);
    if (count($parts) < 3) return $d;
    [$y, $m, $day] = $parts;
    return $day . ' ' . $months[(int)$m] . ' ' . $y;
}

$displayUsername = $user['username'] ?? 'User';
$initials        = strtoupper(substr($displayUsername, 0, 1));

$totalIncome  = 0;
$totalExpense = 0;
foreach ($total as $tx) {
    if ($tx['type'] === 'income')  $totalIncome  += (float)$tx['amount'];
    if ($tx['type'] === 'expense') $totalExpense += (float)$tx['amount'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Riwayat — Damat</title>
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

.btn-delete-tx {
  background:none;border:none;color:#9C8070;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  padding:4px;transition:color .2s;
}
.btn-delete-tx:hover { color:#ef4444; }

/* ── Desktop export bar ── */
.export-bar {
  display:flex;gap:8px;align-items:center;flex-wrap:wrap;
  padding:10px 0 4px;
}
.export-bar-label { font-size:12px;font-weight:600;color:#9C8070;white-space:nowrap; }
.btn-export-select {
  padding:6px 10px;border-radius:7px;border:1.5px solid #D4C9B8;
  background:#fff;color:#5C4030;font-size:12px;font-family:inherit;cursor:pointer;
}
.btn-export {
  display:inline-flex;align-items:center;gap:5px;
  padding:7px 14px;border-radius:8px;font-size:12.5px;font-weight:600;
  border:1.5px solid;cursor:pointer;text-decoration:none;
  transition:background .15s,color .15s;line-height:1;font-family:inherit;white-space:nowrap;
}
.btn-export .material-symbols-rounded { font-size:17px; }
.btn-export.excel { border-color:#217346;color:#217346;background:#F0FAF4; }
.btn-export.excel:hover { background:#217346;color:#fff; }
.btn-export.pdf   { border-color:#B03A2E;color:#B03A2E;background:#FEF0EE; }
.btn-export.pdf:hover   { background:#B03A2E;color:#fff; }

/* ===== DESKTOP: hide mobile shell ===== */
@media (min-width: 601px) {
  .mob-shell { display:none !important; }
  .mob-nav   { display:none !important; }
  .hamburger { display:flex; }
}

/* ===== TABLET ===== */
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
  .mob-header { background:#8B5E30;padding:32px 18px 24px;position:relative; }
  .mob-hdr-top { display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:18px; }
  .mob-greeting     { font-size:20px;font-weight:700;color:#fff;line-height:1.2; }
  .mob-greeting-sub { font-size:12px;color:rgba(255,255,255,.6);margin-top:3px; }
  .mob-avatar {
    width:38px;height:38px;border-radius:50%;
    background:rgba(255,255,255,.18);border:2px solid rgba(255,255,255,.3);
    display:flex;align-items:center;justify-content:center;
    color:#fff;font-weight:700;font-size:15px;text-decoration:none;flex-shrink:0;
  }
  .mob-minicards { display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:4px; }
  .mob-minicard { background:#3D2510;border-radius:13px;padding:10px 12px;display:flex;align-items:center;gap:10px; }
  .mob-mc-icon { width:32px;height:32px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center; }
  .mob-mc-icon.inc { background:#5F8B5A; }
  .mob-mc-icon.exp { background:#C4600A; }
  .mob-mc-icon .material-symbols-rounded { font-size:16px;color:#fff; }
  .mob-mc-lbl { font-size:10px;color:rgba(255,255,255,.6); }
  .mob-mc-val { font-size:13.5px;font-weight:700;color:#fff;letter-spacing:-.3px; }

  /* ---------- FILTER + EXPORT — satu scroll row ---------- */
  /*
   * Semua chip filter DAN tombol export disatukan dalam
   * satu container hscroll agar tidak ada yang kepotong.
   * Divider vertikal memisahkan filter dari export.
   */
  .mob-action-bar {
    display:flex;
    align-items:center;
    gap:8px;
    padding:12px 14px 10px;
    overflow-x:auto;
    overflow-y:hidden;
    -webkit-overflow-scrolling:touch;
    scrollbar-width:none;          /* Firefox */
    white-space:nowrap;
  }
  .mob-action-bar::-webkit-scrollbar { display:none; } /* Chrome/Safari */

  /* Filter chips */
  .mob-filter-chip {
    display:inline-flex;
    align-items:center;
    flex-shrink:0;
    padding:7px 18px;
    border-radius:99px;
    font-size:12.5px;
    font-weight:600;
    border:1.5px solid #D4C9B8;
    background:#fff;
    color:#5C4030;
    cursor:pointer;
    transition:all .15s;
    white-space:nowrap;
  }
  .mob-filter-chip.active { background:#8B5E30;border-color:#8B5E30;color:#fff; }

  /* Divider */
  .mob-action-divider {
    flex-shrink:0;
    width:1px;
    height:28px;
    background:#D4C9B8;
    margin:0 2px;
  }

  /* Export dropdown */
  .mob-export-select {
    flex-shrink:0;
    padding:7px 10px;
    border-radius:9px;
    border:1.5px solid #D4C9B8;
    background:#fff;
    color:#5C4030;
    font-size:12px;
    font-family:inherit;
    cursor:pointer;
    max-width:148px;
  }

  /* Export buttons */
  .mob-btn-export {
    flex-shrink:0;
    display:inline-flex;
    align-items:center;
    gap:5px;
    padding:7px 13px;
    border-radius:9px;
    font-size:12px;
    font-weight:700;
    border:1.5px solid;
    cursor:pointer;
    text-decoration:none;
    font-family:inherit;
    white-space:nowrap;
    line-height:1;
  }
  .mob-btn-export .material-symbols-rounded { font-size:15px; }
  .mob-btn-export.excel { border-color:#217346;color:#217346;background:#F0FAF4; }
  .mob-btn-export.pdf   { border-color:#B03A2E;color:#B03A2E;background:#FEF0EE; }

  /* ---------- TRANSAKSI LIST ---------- */
  .mob-tx-section { background:#FDFAF5;margin:0 14px 12px;border-radius:16px;border:1px solid #EAE4D8;overflow:hidden; }
  .mob-tx-item { display:flex;align-items:center;gap:12px;padding:13px 14px;border-bottom:1px solid #F0EAE0;transition:background .12s; }
  .mob-tx-item:last-child { border-bottom:none; }
  .mob-tx-item:active { background:#F5EDE3; }
  .mob-tx-icon { width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
  .mob-tx-icon.income  { background:#EDF5EB;color:#5F8B5A; }
  .mob-tx-icon.expense { background:#FEF0E6;color:#C4600A; }
  .mob-tx-icon .material-symbols-rounded { font-size:20px; }
  .mob-tx-info { flex:1;min-width:0; }
  .mob-tx-cat  { font-size:13.5px;font-weight:600;color:#2C1E0F;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
  .mob-tx-note { font-size:11.5px;color:#9C8070;margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
  .mob-tx-date { font-size:10.5px;color:#B0A090;margin-top:2px; }
  .mob-tx-right { display:flex;flex-direction:column;align-items:flex-end;gap:3px;flex-shrink:0; }
  .mob-tx-amt   { font-size:13.5px;font-weight:700;letter-spacing:-.3px; }
  .mob-tx-amt.income  { color:#5F8B5A; }
  .mob-tx-amt.expense { color:#C4600A; }
  .mob-tx-del { background:none;border:none;padding:3px;cursor:pointer;color:#C4B8A8;display:flex;align-items:center;justify-content:center;border-radius:6px;transition:color .15s; }
  .mob-tx-del:active { color:#ef4444; }
  .mob-tx-del .material-symbols-rounded { font-size:16px; }

  /* ---------- EMPTY STATE ---------- */
  .mob-empty { text-align:center;padding:48px 24px; }
  .mob-empty .material-symbols-rounded { font-size:52px;color:#D4C9B8;display:block;margin-bottom:12px; }
  .mob-empty-ttl { font-size:15px;font-weight:600;color:#5C4030;margin-bottom:6px; }
  .mob-empty-sub { font-size:13px;color:#9C8070;margin-bottom:20px; }

  /* ---------- PAGINATION ---------- */
  .mob-pagination { display:flex;gap:8px;justify-content:center;padding:8px 14px 14px;flex-wrap:wrap; }
  .mob-page-btn { padding:7px 15px;border-radius:8px;border:1.5px solid #D4C9B8;font-size:13px;font-weight:500;background:#fff;color:#5C4030;text-decoration:none; }
  .mob-page-btn.active { background:#8B5E30;color:#fff;border-color:#8B5E30; }

  /* ---------- BOTTOM NAV ---------- */
  .mob-nav {
    position:fixed;bottom:0;left:0;right:0;
    background:#FDFAF5;border-top:1px solid #EAE4D8;
    z-index:300;display:flex;align-items:center;
    height:60px;padding:0 6px;
    padding-bottom:env(safe-area-inset-bottom);
  }
  .mob-nav-i { flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;text-decoration:none;padding:4px;color:#9C8070;transition:color .15s; }
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
  .mob-nav-fab { flex:0 0 56px;display:flex;align-items:center;justify-content:center;position:relative;margin-top:-22px; }
  .mob-fab { width:54px;height:54px;border-radius:50%;background:#8B5E30;border:3px solid #FDFAF5;display:flex;align-items:center;justify-content:center;color:#fff;text-decoration:none; }
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
        <div class="mob-greeting">Riwayat Transaksi</div>
        <div class="mob-greeting-sub">Semua catatan keuanganmu</div>
      </div>
      <a href="pengaturan.php" class="mob-avatar"><?= $initials ?></a>
    </div>
    <div class="mob-minicards">
      <div class="mob-minicard">
        <div class="mob-mc-icon inc"><span class="material-symbols-rounded">trending_up</span></div>
        <div>
          <div class="mob-mc-lbl">Pemasukan</div>
          <div class="mob-mc-val"><?= fmtRp($totalIncome) ?></div>
        </div>
      </div>
      <div class="mob-minicard">
        <div class="mob-mc-icon exp"><span class="material-symbols-rounded">trending_down</span></div>
        <div>
          <div class="mob-mc-lbl">Pengeluaran</div>
          <div class="mob-mc-val"><?= fmtRp($totalExpense) ?></div>
        </div>
      </div>
    </div>
  </div>

  <!--
    ACTION BAR — filter chip + divider + export dalam satu baris scrollable.
    Geser ke kanan untuk melihat tombol XLS & PDF.
  -->
  <div class="mob-action-bar" id="mob-action-bar">
    <!-- Filter chips -->
    <button class="mob-filter-chip active" data-filter="all">Semua</button>
    <button class="mob-filter-chip" data-filter="income">Pemasukan</button>
    <button class="mob-filter-chip" data-filter="expense">Pengeluaran</button>

    <!-- Divider -->
    <div class="mob-action-divider"></div>

    <!-- Dropdown filter untuk export -->
    <select class="mob-export-select" id="mob-export-filter">
      <option value="all">Semua</option>
      <option value="income">Pemasukan</option>
      <option value="expense">Pengeluaran</option>
    </select>

    <!-- Tombol export -->
    <a href="#" class="mob-btn-export excel" id="mob-btn-excel">
      <span class="material-symbols-rounded">table_view</span> XLS
    </a>
    <a href="#" class="mob-btn-export pdf" id="mob-btn-pdf">
      <span class="material-symbols-rounded">picture_as_pdf</span> PDF
    </a>
  </div>

  <!-- LIST TRANSAKSI -->
  <?php if (empty($all)): ?>
  <div class="mob-empty">
    <span class="material-symbols-rounded">inventory_2</span>
    <div class="mob-empty-ttl">Belum ada transaksi</div>
    <div class="mob-empty-sub">Mulai catat pemasukan atau pengeluaranmu</div>
    <a href="tambah.php" class="mob-fab" style="width:auto;border-radius:12px;padding:10px 20px;gap:6px;font-size:13.5px;font-weight:600;display:inline-flex;margin:0 auto;border:none;">
      <span class="material-symbols-rounded" style="font-size:18px">add</span> Tambah Transaksi
    </a>
  </div>
  <?php else: ?>

  <form method="POST" id="mob-delete-form" style="display:none">
    <input type="hidden" name="action" value="delete_tx">
    <input type="hidden" name="tx_id"  id="mob-delete-tx-id">
  </form>

  <div class="mob-tx-section" id="mob-tx-list">
    <?php foreach ($all as $tx): ?>
    <div class="mob-tx-item" data-type="<?= $tx['type'] ?>">
      <div class="mob-tx-icon <?= $tx['type'] ?>">
        <span class="material-symbols-rounded"><?= txIcon($tx['category']) ?></span>
      </div>
      <div class="mob-tx-info">
        <div class="mob-tx-cat"><?= htmlspecialchars($tx['category']) ?></div>
        <div class="mob-tx-note"><?= htmlspecialchars($tx['note'] ?: '—') ?></div>
        <div class="mob-tx-date"><?= fmtDate($tx['trans_date']) ?></div>
      </div>
      <div class="mob-tx-right">
        <div class="mob-tx-amt <?= $tx['type'] ?>">
          <?= $tx['type'] === 'income' ? '+' : '-' ?><?= fmtRp($tx['amount']) ?>
        </div>
        <button class="mob-tx-del" onclick="mobDeleteTx(<?= $tx['id'] ?>)" title="Hapus">
          <span class="material-symbols-rounded">delete</span>
        </button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($pages > 1): ?>
  <div class="mob-pagination">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a href="?page=<?= $i ?>" class="mob-page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

  <?php endif; ?>

  <div class="mob-foot"></div>
</div>
<!-- end mob-shell -->

<!-- BOTTOM NAV (mobile) -->
<nav class="mob-nav" style="display:none">
  <a href="dashboard.php" class="mob-nav-i">
    <span class="material-symbols-rounded">home</span>
    <span class="lbl">Beranda</span>
  </a>
  <a href="transaksi.php" class="mob-nav-i on">
    <span class="material-symbols-rounded" style="font-variation-settings:'FILL' 1">receipt_long</span>
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
      <a href="dashboard.php"  class="nav-item"><span class="material-symbols-rounded nav-icon-google">grid_view</span>Dashboard</a>
      <a href="transaksi.php"  class="nav-item active"><span class="material-symbols-rounded nav-icon-google">history</span>Riwayat</a>
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
      <h1 class="topbar-title">Riwayat Transaksi</h1>
      <a href="tambah.php" class="btn btn-primary btn-sm">+ Tambah</a>
    </header>

    <main class="page-body">
      <!-- Filter chips -->
      <div class="filter-bar">
        <button class="filter-chip active" data-filter="all">Semua</button>
        <button class="filter-chip" data-filter="income">Pemasukan</button>
        <button class="filter-chip" data-filter="expense">Pengeluaran</button>
      </div>

      <!-- Desktop export bar -->
      <div class="export-bar">
        <span class="export-bar-label">Unduh laporan:</span>
        <select class="btn-export-select" id="export-filter">
          <option value="all">Semua Transaksi</option>
          <option value="income">Pemasukan saja</option>
          <option value="expense">Pengeluaran saja</option>
        </select>
        <a href="#" class="btn-export excel" id="btn-excel">
          <span class="material-symbols-rounded">table_view</span> Unduh Excel
        </a>
        <a href="#" class="btn-export pdf" id="btn-pdf">
          <span class="material-symbols-rounded">picture_as_pdf</span> Unduh PDF
        </a>
      </div>

      <?php if (empty($all)): ?>
      <div class="empty-state">
        <span class="material-symbols-rounded" style="font-size:56px;color:var(--brown-200);margin-bottom:12px">inventory_2</span>
        <div class="empty-title">Belum ada transaksi</div>
        <div class="empty-body">Mulai catat pemasukan atau pengeluaranmu</div>
        <a href="tambah.php" class="btn btn-primary" style="margin-top:18px;display:inline-flex;align-items:center;gap:8px">
          <span class="material-symbols-rounded" style="font-size:18px">add</span> Tambah Transaksi
        </a>
      </div>
      <?php else: ?>

      <form method="POST" id="delete-form" style="display:none">
        <input type="hidden" name="action" value="delete_tx">
        <input type="hidden" name="tx_id"  id="delete-tx-id">
      </form>

      <div class="tx-list">
        <?php foreach ($all as $tx): ?>
        <div class="tx-card" data-type="<?= $tx['type'] ?>">
          <div class="tx-icon <?= $tx['type'] ?>">
            <span class="material-symbols-rounded" style="font-size:20px"><?= txIcon($tx['category']) ?></span>
          </div>
          <div class="tx-info">
            <div class="tx-category"><?= htmlspecialchars($tx['category']) ?></div>
            <div class="tx-note"><?= htmlspecialchars($tx['note'] ?: '—') ?></div>
            <div class="tx-date"><?= fmtDate($tx['trans_date']) ?></div>
          </div>
          <div class="tx-right">
            <div class="tx-amount <?= $tx['type'] ?>">
              <?= $tx['type'] === 'income' ? '+' : '-' ?><?= fmtRp($tx['amount']) ?>
            </div>
            <span class="badge <?= $tx['type'] ?>" style="margin-top:4px">
              <?= $tx['type'] === 'income' ? 'Masuk' : 'Keluar' ?>
            </span>
            <br>
            <button class="btn-delete-tx" style="margin-top:6px"
                    onclick="confirmDelete(<?= $tx['id'] ?>)" title="Hapus">
              <span class="material-symbols-rounded" style="font-size:18px">delete</span>
            </button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if ($pages > 1): ?>
      <div style="display:flex;gap:8px;justify-content:center;margin-top:28px;flex-wrap:wrap">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a href="?page=<?= $i ?>"
           style="padding:8px 16px;border-radius:8px;border:1.5px solid var(--brown-100);font-size:13.5px;font-weight:500;text-decoration:none;
                  <?= $i === $page ? 'background:var(--brown-500);color:#fff;border-color:var(--brown-500)' : 'background:#fff;color:var(--text-secondary)' ?>">
          <?= $i ?>
        </a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>

      <?php endif; ?>
    </main>
  </div>
</div>

<script src="../assets/js/app.js"></script>
<script>
const IS_MOBILE = window.innerWidth <= 600;

if (IS_MOBILE) {
  document.querySelector('.mob-shell').style.display  = 'flex';
  document.querySelector('.mob-nav').style.display    = 'flex';
  document.querySelector('.app-layout').style.display = 'none';
} else {
  document.querySelector('.mob-shell').style.display  = 'none';
  document.querySelector('.mob-nav').style.display    = 'none';
  document.querySelector('.app-layout').style.display = '';
}

// ── Desktop filter ───────────────────────────────────────────
document.querySelectorAll('.filter-chip').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.filter-chip').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const f = btn.dataset.filter;
    document.querySelectorAll('.tx-card').forEach(card => {
      card.style.display = (f === 'all' || card.dataset.type === f) ? '' : 'none';
    });
  });
});

// ── Mobile filter ────────────────────────────────────────────
document.querySelectorAll('.mob-filter-chip').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.mob-filter-chip').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const f = btn.dataset.filter;
    document.querySelectorAll('#mob-tx-list .mob-tx-item').forEach(item => {
      item.style.display = (f === 'all' || item.dataset.type === f) ? '' : 'none';
    });
  });
});

// ── Delete ───────────────────────────────────────────────────
function confirmDelete(id) {
  if (confirm('Hapus transaksi ini?')) {
    document.getElementById('delete-tx-id').value = id;
    document.getElementById('delete-form').submit();
  }
}
function mobDeleteTx(id) {
  if (confirm('Hapus transaksi ini?')) {
    document.getElementById('mob-delete-tx-id').value = id;
    document.getElementById('mob-delete-form').submit();
  }
}

// ── Hamburger ────────────────────────────────────────────────
document.querySelector('.hamburger')?.addEventListener('click', () => {
  document.querySelector('.sidebar')?.classList.toggle('open');
  document.getElementById('sidebar-overlay').style.display = 'block';
});

// ── Export URL builder ───────────────────────────────────────
function buildExportUrl(type, filter) {
  return 'transaksi.php?export=' + type + '&filter=' + filter;
}

// Desktop
const exportFilterEl = document.getElementById('export-filter');
const btnExcel       = document.getElementById('btn-excel');
const btnPdf         = document.getElementById('btn-pdf');
function updateDesktopExport() {
  const f = exportFilterEl?.value ?? 'all';
  if (btnExcel) btnExcel.href = buildExportUrl('excel', f);
  if (btnPdf)   btnPdf.href   = buildExportUrl('pdf',   f);
}
exportFilterEl?.addEventListener('change', updateDesktopExport);
updateDesktopExport();

// Mobile
const mobExportFilter = document.getElementById('mob-export-filter');
const mobBtnExcel     = document.getElementById('mob-btn-excel');
const mobBtnPdf       = document.getElementById('mob-btn-pdf');
function updateMobileExport() {
  const f = mobExportFilter?.value ?? 'all';
  if (mobBtnExcel) mobBtnExcel.href = buildExportUrl('excel', f);
  if (mobBtnPdf)   mobBtnPdf.href   = buildExportUrl('pdf',   f);
}
mobExportFilter?.addEventListener('change', updateMobileExport);
updateMobileExport();
</script>
</body>
</html>