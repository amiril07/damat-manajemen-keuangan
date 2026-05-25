<?php
// ============================================================
// includes/transactions.php — CRUD + fungsi laporan & DamAI
// ============================================================

require_once __DIR__ . '/db.php';

// ---------------------------------------------------------------
// CRUD
// ---------------------------------------------------------------

function addTransaction(int $userId, string $type, string $category,
                        float $amount, string $note, string $date): bool {
    $db   = getDB();
    $stmt = $db->prepare(
        'INSERT INTO transactions (user_id, type, category, amount, note, trans_date)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    return $stmt->execute([$userId, $type, $category, $amount, $note ?: null, $date]);
}

function deleteTransaction(int $userId, int $txId): bool {
    $db   = getDB();
    $stmt = $db->prepare('DELETE FROM transactions WHERE id = ? AND user_id = ?');
    return $stmt->execute([$txId, $userId]);
}

// ---------------------------------------------------------------
// RINGKASAN BULANAN
// ---------------------------------------------------------------

function getMonthlySummary(int $userId, string $month = ''): array {
    if (!$month) $month = date('Y-m');
    [$year, $mon] = explode('-', $month);
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT
            SUM(CASE WHEN type='income'  THEN amount ELSE 0 END) AS total_income,
            SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS total_expense
         FROM transactions
         WHERE user_id = ?
           AND YEAR(trans_date)  = ?
           AND MONTH(trans_date) = ?"
    );
    $stmt->execute([$userId, $year, $mon]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $budget   = (float)($row['total_budget'] ?? 0);
    $income  = (float)($row['total_income']  ?? 0);
    $expense = (float)($row['total_expense'] ?? 0);

    return [
        'income'  => $income,
        'expense' => $expense,
        'balance' => $budget + $income - $expense,
    ];
}

// ---------------------------------------------------------------
// RIWAYAT TRANSAKSI
// ---------------------------------------------------------------

function getTransactions(int $userId, int $limit = 20, int $offset = 0,
                         string $type = ''): array {
    $db     = getDB();
    $where  = 'user_id = ?';
    $params = [$userId];
    if ($type) { $where .= ' AND type = ?'; $params[] = $type; }

    $sql  = "SELECT id, type, category, amount, note, trans_date
             FROM transactions
             WHERE $where
             ORDER BY trans_date DESC, id DESC
             LIMIT $limit OFFSET $offset";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------------------------------------------------------------
// CHART 7 HARI
// ---------------------------------------------------------------

function getLast7DaysChart(int $userId): array {
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT
            DATE_FORMAT(trans_date, '%d %b') AS label,
            SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS expense,
            SUM(CASE WHEN type='income'  THEN amount ELSE 0 END) AS income
         FROM transactions
         WHERE user_id = ?
           AND trans_date >= CURDATE() - INTERVAL 6 DAY
         GROUP BY trans_date
         ORDER BY trans_date ASC"
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------------------------------------------------------------
// BREAKDOWN KATEGORI
// ---------------------------------------------------------------

function getCategoryBreakdown(int $userId, string $month = ''): array {
    if (!$month) $month = date('Y-m');
    [$year, $mon] = explode('-', $month);
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT category, SUM(amount) AS total
         FROM transactions
         WHERE user_id = ?
           AND type = 'expense'
           AND YEAR(trans_date)  = ?
           AND MONTH(trans_date) = ?
         GROUP BY category
         ORDER BY total DESC"
    );
    $stmt->execute([$userId, $year, $mon]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getMonthlyExpense(int $userId): float {
    return getMonthlySummary($userId)['expense'];
}

// ---------------------------------------------------------------
// ★ FUNGSI LAPORAN — digunakan laporan.php & DamAI context
// ---------------------------------------------------------------

/**
 * Ambil ringkasan bulan ini + bulan lalu sekaligus
 */
function getMonthlyComparison(int $userId): array {
    $thisMonth = date('Y-m');
    $lastMonth = date('Y-m', strtotime('first day of last month'));

    $this_ = getMonthlySummary($userId, $thisMonth);
    $last_ = getMonthlySummary($userId, $lastMonth);

    // Persentase perubahan
    $incomeChange  = $last_['income']  > 0
        ? (($this_['income']  - $last_['income'])  / $last_['income'])  * 100 : null;
    $expenseChange = $last_['expense'] > 0
        ? (($this_['expense'] - $last_['expense']) / $last_['expense']) * 100 : null;

    return [
        'this_month'    => $thisMonth,
        'last_month'    => $lastMonth,
        'current'       => $this_,
        'previous'      => $last_,
        'income_change_pct'  => $incomeChange  !== null ? round($incomeChange,  1) : null,
        'expense_change_pct' => $expenseChange !== null ? round($expenseChange, 1) : null,
    ];
}

/**
 * Transaksi terbaru bulan ini (untuk konteks AI)
 */
function getRecentTransactionsForAI(int $userId, int $limit = 15): array {
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT type, category, amount, note, trans_date
         FROM transactions
         WHERE user_id = ?
           AND YEAR(trans_date)  = YEAR(CURDATE())
           AND MONTH(trans_date) = MONTH(CURDATE())
         ORDER BY trans_date DESC, id DESC
         LIMIT ?"
    );
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Bangun string konteks keuangan untuk system prompt DamAI
 */
function buildFinancialContext(int $userId, float $budget): string {
    $cmp      = getMonthlyComparison($userId);
    $cats     = getCategoryBreakdown($userId);
    $recent   = getRecentTransactionsForAI($userId);
    $cur      = $cmp['current'];
    $prev     = $cmp['previous'];
    $budgetPct = $budget > 0 ? round(($cur['expense'] / $budget) * 100, 1) : 0;

    $lines = [];
    $lines[] = "=== DATA KEUANGAN USER (BULAN " . strtoupper(date('F Y')) . ") ===";
    $lines[] = "Anggaran Bulanan  : Rp " . number_format($budget, 0, ',', '.');
    $lines[] = "Total Pemasukan   : Rp " . number_format($cur['income'],  0, ',', '.');
    $lines[] = "Total Pengeluaran : Rp " . number_format($cur['expense'], 0, ',', '.');
    $lines[] = "Saldo Bersih      : Rp " . number_format($cur['balance'], 0, ',', '.');
    $lines[] = "Pemakaian Anggaran: {$budgetPct}%";
    $lines[] = "";
    $lines[] = "--- Perbandingan Bulan Lalu ---";
    $lines[] = "Pemasukan  bulan lalu : Rp " . number_format($prev['income'],  0, ',', '.');
    $lines[] = "Pengeluaran bulan lalu: Rp " . number_format($prev['expense'], 0, ',', '.');

    if ($cmp['income_change_pct'] !== null) {
        $sign = $cmp['income_change_pct'] >= 0 ? '+' : '';
        $lines[] = "Perubahan Pemasukan   : {$sign}{$cmp['income_change_pct']}%";
    }
    if ($cmp['expense_change_pct'] !== null) {
        $sign = $cmp['expense_change_pct'] >= 0 ? '+' : '';
        $lines[] = "Perubahan Pengeluaran : {$sign}{$cmp['expense_change_pct']}%";
    }

    if (!empty($cats)) {
        $lines[] = "";
        $lines[] = "--- Pengeluaran per Kategori ---";
        foreach ($cats as $cat) {
            $lines[] = "• {$cat['category']}: Rp " . number_format($cat['total'], 0, ',', '.');
        }
    }

    if (!empty($recent)) {
        $lines[] = "";
        $lines[] = "--- 15 Transaksi Terbaru Bulan Ini ---";
        foreach ($recent as $tx) {
            $sign = $tx['type'] === 'income' ? '+' : '-';
            $note = $tx['note'] ? " ({$tx['note']})" : '';
            $lines[] = "[{$tx['trans_date']}] {$sign}Rp " .
                       number_format($tx['amount'], 0, ',', '.') .
                       " — {$tx['category']}{$note}";
        }
    }

    $lines[] = "=== AKHIR DATA KEUANGAN ===";
    return implode("\n", $lines);
}
