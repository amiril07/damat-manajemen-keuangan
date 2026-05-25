<div align="center">

# 💰 Damat
### Manajemen Keuangan Mikro

**Versi 1.0.1** · PHP · MariaDB · PWA

[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![MariaDB](https://img.shields.io/badge/MariaDB-10.6+-003545?style=flat-square&logo=mariadb&logoColor=white)](https://mariadb.org)
[![License](https://img.shields.io/badge/Lisensi-MIT-green?style=flat-square)](LICENSE)
[![PWA](https://img.shields.io/badge/PWA-Ready-5A0FC8?style=flat-square&logo=pwa&logoColor=white)](https://web.dev/progressive-web-apps)

Aplikasi manajemen keuangan pribadi berbasis web yang ringan, cerdas, dan dapat diinstall sebagai Progressive Web App (PWA). Dilengkapi AI assistant, OCR scan struk, dan laporan ekspor otomatis.

---

![Dashboard Damat](assets/img/dompet.png)

</div>

---

## ✨ Fitur Utama

### 📊 Dashboard Interaktif
- **Ringkasan keuangan bulanan** — saldo bersih, total pemasukan, dan total pengeluaran dalam satu tampilan
- **Grafik arus keuangan 7 hari** menggunakan Chart.js (line chart pemasukan vs pengeluaran)
- **Grafik donut pengeluaran per kategori** untuk memvisualisasikan pola belanja
- **Transaksi terbaru** langsung tampil di dashboard tanpa perlu pindah halaman
- **Aksi cepat** — shortcut catat pemasukan, pengeluaran, dan tanya DamGPT

### 💳 Manajemen Transaksi
- **Catat pemasukan & pengeluaran** dengan 14 kategori tersedia
- **9 kategori pengeluaran:** Makanan, Transportasi, Belanja, Tagihan, Kesehatan, Hiburan, Pendidikan, Pakaian, Lainnya
- **6 kategori pemasukan:** Gaji, Freelance, Bonus, Investasi, Hadiah, Lainnya
- **Catatan & tanggal** bisa diisi manual untuk setiap transaksi
- **Hapus transaksi** dengan konfirmasi
- **Pagination** untuk riwayat transaksi yang panjang
- **Filter transaksi** berdasarkan tipe (Semua / Pemasukan / Pengeluaran)

### 🧾 Scan Struk / Nota (OCR + AI)
- **Upload foto struk** langsung dari kamera atau galeri
- **Tesseract OCR** membaca teks dari gambar secara lokal (bahasa Indonesia + Inggris)
- **Groq AI (LLaMA 3.1)** menganalisis teks OCR dan mengekstrak:
  - Nama merchant / toko
  - Total nominal yang dibayar
  - Kategori yang sesuai
  - Tanggal transaksi
  - Daftar item yang dibeli (maks 5)
- **Confidence score** ditampilkan agar user tahu seberapa akurat hasil scan
- **Auto-fill form** — hasil scan langsung mengisi form transaksi, user tinggal konfirmasi & simpan
- Mendukung format: **JPG, PNG, WEBP, BMP, TIFF** (maks 5MB)
- File gambar sementara dibersihkan otomatis setelah 30 menit

### 🤖 DamGPT — AI Financial Assistant
- Chatbot keuangan pribadi berbasis **Groq API (LLaMA 3.1-8b-instant)**
- Memiliki **konteks keuangan user** secara real-time (pemasukan, pengeluaran, saldo, budget)
- Dapat menjawab pertanyaan seperti:
  - *"Ringkasan keuanganku bulan ini"*
  - *"Tips hemat berdasarkan pengeluaranku"*
  - *"Kategori pengeluaran terbesar?"*
  - *"Saran target tabungan"*
- **Suggestion chips** untuk pertanyaan populer
- Mendukung percakapan multi-turn dengan history
- Typing indicator animasi saat AI sedang memproses
- Tombol reset percakapan
- Render **markdown** (bold, list, paragraf) di dalam bubble chat

### 📅 Anggaran Bulanan & Peringatan Impulsif
- User dapat menetapkan **anggaran belanja bulanan**
- **Progress bar anggaran** dengan indikator warna:
  - 🟢 Hijau: < 60% terpakai (aman)
  - 🟡 Kuning: 60–79% terpakai (waspada)
  - 🔴 Merah: ≥ 80% terpakai (kritis)
- **Modal peringatan impulsif** otomatis muncul saat user akan melewati batas anggaran
- Modal menampilkan persentase anggaran dan pertanyaan reflektif sebelum melanjutkan transaksi

### 📤 Ekspor Laporan
- **Ekspor ke Excel (.xlsx)** — tabel transaksi lengkap dengan header, filter periode, nama pengguna, dan tanggal ekspor (powered by PhpSpreadsheet)
- **Ekspor ke PDF** — laporan bergaya profesional dengan ringkasan keuangan, tabel transaksi, dan footer otomatis (powered by Dompdf)
- Filter ekspor berdasarkan **tipe transaksi** (Semua / Pemasukan / Pengeluaran)

### ⚙️ Pengaturan Akun
- **Ubah username** tampilan
- **Ganti password** dengan verifikasi password lama
- **Update anggaran bulanan**
- **Logout** dengan konfirmasi

### 📱 Progressive Web App (PWA)
- **Installable** — dapat diinstall di Android/iOS/Desktop seperti aplikasi native
- **Service Worker** terdaftar untuk pengalaman offline-ready
- **Web App Manifest** dengan ikon 192×192 dan 512×512
- **Standalone display mode** (tanpa address bar saat diinstall)
- **Theme color** dan **background color** sesuai identitas Damat

### 🎨 UI Desain Adaptif
- **Dual layout** — tampilan berbeda untuk mobile (≤600px) dan desktop (>600px)
- **Mobile:** Hero header bergradient cokelat, bottom navigation, FAB button, category pills, scan struk tab, balance display besar
- **Desktop:** Sidebar navigasi, topbar, grid 2-3 kolom, charts side-by-side dengan transaksi terbaru
- **Tablet:** Layout adaptif dengan sidebar collapsible
- Seluruh warna menggunakan **CSS variables** (tema cokelat/sage/terracotta yang konsisten)
- Material Symbols Rounded & Outlined untuk ikonografi

---

## 🗂️ Struktur Folder

```
damat/
├── assets/
│   ├── css/
│   │   └── style.css          # Stylesheet utama + CSS variables
│   ├── js/
│   │   └── app.js             # Logic frontend (form, chart, impulse warning, dll)
│   ├── img/
│   │   └── dompet.png         # Ilustrasi dompet (mobile hero)
│   └── favicon/               # Ikon PWA (32px, 192px, 512px)
├── includes/
│   ├── auth.php               # Autentikasi, sesi, register/login/logout
│   ├── config.php             # Konfigurasi database & konstanta app
│   ├── db.php                 # Koneksi PDO ke MariaDB
│   └── transactions.php       # Semua fungsi transaksi & data keuangan
├── pages/
│   ├── dashboard.php          # Halaman utama (ringkasan, chart, transaksi terbaru)
│   ├── tambah.php             # Form tambah transaksi + upload struk OCR
│   ├── transaksi.php          # Riwayat transaksi + filter + ekspor Excel/PDF
│   ├── damgpt.php             # Chat AI DamGPT
│   ├── pengaturan.php         # Pengaturan profil, password, anggaran
│   └── ocr_struk.php          # Endpoint OCR: Tesseract + Groq AI parser
├── tmp/
│   └── ocr/                   # Folder temp gambar OCR (auto-cleanup 30 menit)
├── vendor/                    # Composer dependencies
├── index.php                  # Entry point + halaman login/register
├── logout.php                 # Handler logout
├── manifest.json              # PWA manifest
├── sw.js                      # Service Worker
├── composer.json              # Dependency PHP
└── README.md                  # Dokumentasi ini
```

---

## 🛠️ Teknologi

| Layer | Teknologi |
|---|---|
| Backend | PHP 8.1+ |
| Database | MariaDB 10.6+ (via PDO) |
| AI Chatbot | Groq API — LLaMA 3.1-8b-instant |
| OCR | Tesseract OCR 5.x (bahasa `ind+eng`) |
| Chart | Chart.js 4.4 |
| Excel Export | PhpSpreadsheet ^5.7 |
| PDF Export | Dompdf ^3.1 |
| OCR Library | thiagoalessio/tesseract_ocr ^2.13 |
| Font/Icons | Google Material Symbols Rounded & Outlined |
| PWA | Web App Manifest + Service Worker |
| Server (Dev) | Laragon (Windows) / Apache / Nginx |

---

## 🚀 Instalasi

### Prasyarat
- PHP 8.1+
- MariaDB / MySQL
- Composer
- Tesseract OCR 5.x
- Laragon (Windows) atau XAMPP / server Apache/Nginx

### 1. Clone / Extract Project

```bash
git clone https://github.com/username/damat.git
# atau ekstrak ZIP ke folder htdocs / www Laragon
```

### 2. Install Dependency PHP

```bash
cd damat
composer install
```

### 3. Install Tesseract OCR (Windows / Laragon)

Download installer dari:
> https://github.com/UB-Mannheim/tesseract/wiki

Setelah install:
1. Download `ind.traineddata` dari https://github.com/tesseract-ocr/tessdata
2. Taruh di `C:\Program Files\Tesseract-OCR\tessdata\`
3. Tambahkan `C:\Program Files\Tesseract-OCR` ke variabel **PATH** Windows

Verifikasi:
```bash
tesseract --version
tesseract --list-langs   # pastikan 'ind' muncul
```

### 4. Konfigurasi Database

Edit `includes/config.php`:

```php
define('DB_HOST', 'localhost');       // atau nama container
define('DB_NAME', 'nama_database');
define('DB_USER', 'username_db');
define('DB_PASS', 'password_db');
define('DB_PORT', '3306');
```

Buat database dan tabel:

```sql
CREATE DATABASE damat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE damat;

CREATE TABLE users (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    username       VARCHAR(100) NOT NULL UNIQUE,
    password       VARCHAR(255) NOT NULL,
    monthly_budget DECIMAL(15,2) DEFAULT 0,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE transactions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    type        ENUM('income','expense') NOT NULL,
    category    VARCHAR(100) NOT NULL,
    amount      DECIMAL(15,2) NOT NULL,
    note        TEXT,
    trans_date  DATE NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### 5. Buat Folder Temp OCR

```bash
mkdir -p tmp/ocr
chmod 755 tmp/ocr
```

### 6. Konfigurasi Groq API Key

Edit `pages/damgpt.php` dan `pages/ocr_struk.php`, ganti nilai:

```php
$groqApiKey = 'gsk_YOUR_API_KEY_HERE';
```

Daftar & dapatkan API key gratis di: https://console.groq.com

### 7. Pastikan `exec()` Tidak Diblokir PHP

Cek `php.ini`:
```ini
; Pastikan 'exec' tidak ada di sini:
disable_functions =
```

### 8. Akses Aplikasi

```
http://localhost/damat/
```

---

## 📋 Changelog

### v1.0.1 *(terkini)*
- ✅ Fitur **Upload Struk / Nota** dengan Tesseract OCR + Groq AI parser
- ✅ **Auto-fill form** dari hasil scan struk
- ✅ **Confidence score** pada hasil scan AI
- ✅ Perbaikan bug **kalkulasi saldo bersih** (balance = income − expense)
- ✅ Redesign **mobile dashboard** bergaya hero (pola seperti aplikasi fintech)
- ✅ **Bottom navigation** mobile dengan FAB button
- ✅ **Mobile-friendly** untuk semua halaman: tambah, transaksi, pengaturan, DamGPT
- ✅ **Tab Mode Scan** di halaman Tambah untuk switch Manual / Scan Struk
- ✅ Seluruh warna mobile seragam menggunakan CSS variables (cokelat/sage/terra)
- ✅ **Navbar DamGPT** menggantikan menu Laporan di semua halaman
- ✅ Perbaikan tampilan desktop: dashboard grid 2-kolom dengan transaksi terbaru + aksi cepat
- ✅ Update versi app ke `1.0.1` di `config.php`

### v1.0.0
- 🎉 Rilis pertama Damat
- ✅ Dashboard dengan chart Chart.js
- ✅ Manajemen transaksi (tambah, lihat, hapus)
- ✅ Anggaran bulanan & peringatan impulsif
- ✅ DamGPT AI chatbot (Groq API)
- ✅ Ekspor Excel (.xlsx) dan PDF
- ✅ Autentikasi (login, register, logout)
- ✅ Pengaturan profil dan password
- ✅ PWA (installable, manifest, service worker)

---

## 🔐 Keamanan

- Password di-hash menggunakan `password_hash()` dengan algoritma **BCRYPT cost 12**
- Sesi PHP diamankan dengan `session_regenerate_id()` saat login
- Durasi sesi: **2 jam** (dapat diubah di `config.php`)
- Semua input user di-sanitasi dengan `htmlspecialchars()` dan prepared statements PDO
- File upload OCR divalidasi MIME type via `finfo`, dibatasi 5MB, dan dihapus otomatis
- Semua endpoint memerlukan autentikasi via `requireLogin()`

---

## 👨‍💻 Developer

Dibuat oleh **Kak Amiril** — lulusan SMKN 2 Pangkalpinang, mahasiswa Politeknik Manufaktur Negeri Bangka Belitung.

Instagram: [@amiril.ma_](https://instagram.com/amiril.ma_)

---

## 📄 Lisensi

Proyek ini dilisensikan di bawah **MIT License** — bebas digunakan, dimodifikasi, dan didistribusikan dengan menyertakan atribusi.

---

<div align="center">

**Damat v1.0.1** · Dibuat dengan ☕ di Bangka Belitung

</div>
#   d a m a t - m a n a j e m e n - k e u a n g a n - p r i b a d i  
 #   d a m a t - m a n a j e m e n - k e u a n g a n  
 