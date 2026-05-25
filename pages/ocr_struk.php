<?php
// ============================================================
// pages/ocr_struk.php  —  Endpoint OCR struk via Groq Vision AI
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ── 1. Validasi file upload ──────────────────────────────────
if (empty($_FILES['struk']) || $_FILES['struk']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['struk']['error'] ?? -1;
    $errMsg  = match($errCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran file terlalu besar (maks 5MB).',
        UPLOAD_ERR_NO_FILE  => 'Tidak ada file yang diupload.',
        default             => 'Gagal mengupload file. Kode error: ' . $errCode,
    };
    echo json_encode(['error' => $errMsg]);
    exit;
}

$file        = $_FILES['struk'];
$mimeAllowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/bmp', 'image/tiff'];
$finfo       = new finfo(FILEINFO_MIME_TYPE);
$mimeType    = $finfo->file($file['tmp_name']);

if (!in_array($mimeType, $mimeAllowed)) {
    echo json_encode(['error' => 'Format file tidak didukung. Gunakan JPG, PNG, WEBP, BMP, atau TIFF.']);
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['error' => 'Ukuran file maks 5MB.']);
    exit;
}

// ── 2. Konversi gambar ke Base64 + Kompres Otomatis ─────────────────
// Kita gunakan library GD PHP untuk memperkecil resolusi dan kualitas gambar 
// agar ukuran Base64 sangat kecil, sehingga cURL tidak akan Timeout.

if (!extension_loaded('gd')) {
    echo json_encode(['error' => 'Ekstensi GD tidak aktif di hosting Anda. Silakan aktifkan via cPanel.']);
    exit;
}

// Buat resource gambar berdasarkan mime type asli
$srcImage = match($mimeType) {
    'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($file['tmp_name']),
    'image/png'               => @imagecreatefrompng($file['tmp_name']),
    'image/webp'              => @imagecreatefromwebp($file['tmp_name']),
    'image/bmp'               => @imagecreatefromwbmp($file['tmp_name']),
    default                   => false,
};

if (!$srcImage) {
    echo json_encode(['error' => 'Gagal membaca file gambar yang diupload.']);
    exit;
}

// AMBIL UKURAN ASLI
$oldWidth  = imagesx($srcImage);
$oldHeight = imagesy($srcImage);

// ATUR MAKSIMAL RESOLUSI (Resize jika terlalu besar, misal kamera HP 4K)
$maxWidth  = 1000; 
if ($oldWidth > $maxWidth) {
    $newWidth  = $maxWidth;
    $newHeight = floor($oldHeight * ($maxWidth / $oldWidth));
    
    // Proses Resize
    $tmpCanvas = imagecreatetruecolor($newWidth, $newHeight);
    
    // Jaga transparansi jika format PNG/WEBP
    imagealphablending($tmpCanvas, false);
    imagesavealpha($tmpCanvas, true);
    
    imagecopyresampled($tmpCanvas, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $oldWidth, $oldHeight);
    imagedestroy($srcImage);
    $srcImage = $tmpCanvas;
}

// PROSES KOMPRES KUALITAS KE JPEG (Kualitas 60% agar sizenya di bawah 200KB tapi teks tetap terbaca jelas)
ob_start();
imagejpeg($srcImage, null, 60); 
$rawBytes = ob_get_clean();
imagedestroy($srcImage);

$imageData = base64_encode($rawBytes);
$mediaType = 'image/jpeg'; // Payload diubah menjadi jpeg karena hasil kompresi

if (!$imageData) {
    echo json_encode(['error' => 'Gagal mengkompres dan mengonversi gambar ke Base64.']);
    exit;
}

// ── 3. Kirim ke Groq Vision API ──────────────────────────────

$apiUrl     = 'https://api.groq.com/openai/v1/chat/completions';

$tanggalHariIni = date('Y-m-d');

$systemPrompt = <<<PROMPT
Kamu adalah parser struk belanja / nota keuangan. Tugasmu mengekstrak informasi transaksi dari gambar struk yang dikirimkan.

Kembalikan HANYA JSON valid dengan format berikut (tanpa backtick, tanpa komentar, tanpa teks lain apapun):
{
  "type": "expense",
  "amount": 75000,
  "category": "Makanan",
  "note": "Makan siang di Warung Padang",
  "date": "2026-05-24",
  "items": ["Nasi Padang", "Es Teh"],
  "merchant": "Warung Padang Jaya",
  "confidence": 0.9
}

Aturan wajib:
- type: selalu "expense" untuk pembelian / belanja. "income" hanya jika jelas ini penerimaan uang.
- amount: total akhir yang dibayar (angka bulat saja, tanpa Rp/titik/koma/simbol). Prioritaskan field TOTAL, GRAND TOTAL, atau JUMLAH. Jika ada kembalian, gunakan total sebelum kembalian.
- category: pilih SATU dari daftar berikut: Makanan, Transportasi, Belanja, Tagihan, Kesehatan, Hiburan, Pendidikan, Pakaian, Lainnya
- note: deskripsi singkat berisi nama merchant dan ringkasan belanja, maksimal 80 karakter.
- date: format YYYY-MM-DD. Jika tidak ada tanggal pada struk, gunakan tanggal hari ini: $tanggalHariIni
- items: array nama-nama item yang dibeli, maksimal 5 item, tiap item string pendek.
- merchant: nama toko / restoran / merchant yang tertera.
- confidence: nilai 0.0 hingga 1.0 seberapa yakin kamu mengekstrak data ini. Gunakan nilai di bawah 0.5 jika data tidak jelas.

Panduan kategori:
- Makanan: restoran, warung, cafe, kopi, minuman, snack, supermarket makanan
- Belanja: supermarket umum, minimarket (Indomaret/Alfamart), fashion, elektronik
- Transportasi: bensin/BBM, parkir, tol, ojek, taksi, tiket
- Tagihan: listrik, air, internet, telepon, pulsa
- Kesehatan: apotek, klinik, rumah sakit, vitamin
- Hiburan: bioskop, game, streaming, rekreasi
- Pendidikan: buku, kursus, ATK, fotokopi
- Pakaian: baju, sepatu, tas, aksesoris fashion

Jika gambar struk tidak jelas atau buram, tetap kembalikan JSON terbaik yang bisa kamu hasilkan dengan nilai confidence yang rendah. Jangan kembalikan apapun selain JSON.
PROMPT;

$payload = json_encode([
    'model'           => 'meta-llama/llama-4-scout-17b-16e-instruct',
    'messages'        => [
        [
            'role'    => 'system',
            'content' => $systemPrompt,
        ],
        [
            'role'    => 'user',
            'content' => [
                [
                    'type'      => 'image_url',
                    'image_url' => [
                        'url' => 'data:' . $mediaType . ';base64,' . $imageData,
                    ],
                ],
                [
                    'type' => 'text',
                    'text' => 'Baca struk pada gambar ini dan kembalikan JSON sesuai instruksi.',
                ],
            ],
        ],
    ],
    'temperature'     => 0.1,
    'max_tokens'      => 400,
    'response_format' => ['type' => 'json_object'],
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
    CURLOPT_TIMEOUT        => 0,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

// ── 4. Tangani respons API ────────────────────────────────────
if ($curlErr) {
    echo json_encode(['error' => 'Gagal terhubung ke Groq Vision API: ' . $curlErr]);
    exit;
}

$aiData = json_decode($response, true);

if ($httpCode !== 200 || empty($aiData['choices'][0]['message']['content'])) {
    echo json_encode([
        'error'  => 'Groq Vision API gagal memproses gambar struk.',
        'detail' => $aiData['error']['message'] ?? ('HTTP ' . $httpCode),
    ]);
    exit;
}

$aiContent = $aiData['choices'][0]['message']['content'];

// Bersihkan jika model membungkus dengan backtick (jaga-jaga)
$aiContent = preg_replace('/^```json\s*/i', '', trim($aiContent));
$aiContent = preg_replace('/\s*```$/', '', $aiContent);

$parsed = json_decode(trim($aiContent), true);

if (!$parsed || !isset($parsed['amount'])) {
    echo json_encode([
        'error'  => 'AI tidak dapat mengekstrak data dari struk ini. Pastikan gambar struk jelas dan terbaca.',
        'raw_ai' => $aiContent,
    ]);
    exit;
}

// ── 5. Sanitasi dan kirim output ─────────────────────────────
$result = [
    'success'    => true,
    'type'       => in_array($parsed['type'] ?? '', ['income', 'expense']) ? $parsed['type'] : 'expense',
    'amount'     => max(0, (int)($parsed['amount'] ?? 0)),
    'category'   => htmlspecialchars($parsed['category'] ?? 'Lainnya'),
    'note'       => htmlspecialchars(mb_substr($parsed['note'] ?? '', 0, 100)),
    'date'       => preg_match('/^\d{4}-\d{2}-\d{2}$/', $parsed['date'] ?? '') ? $parsed['date'] : date('Y-m-d'),
    'items'      => array_slice(array_map('htmlspecialchars', (array)($parsed['items'] ?? [])), 0, 5),
    'merchant'   => htmlspecialchars(mb_substr($parsed['merchant'] ?? '', 0, 80)),
    'confidence' => min(1.0, max(0.0, (float)($parsed['confidence'] ?? 0.5))),
];

echo json_encode($result);