# 🚀 High-Performance Product Importer API (Laravel 12 + FrankenPHP + Redis Queue)

Aplikasi REST API ini dirancang khusus untuk menangani unggahan data produk berskala besar (puluhan ribu baris) secara **asynchronous (non-blocking)**. Dengan menggabungkan efisiensi *streaming* PHP dan ketangguhan arsitektur *event-driven* Redis Queue, sistem mampu merespons unduhan file dalam waktu kurang dari 1 detik, sementara pemrosesan database berjalan aman di background.

---

## 📊 Alur Arsitektur Sistem

```text
[Postman / Client]
       │
       ▼ (Request POST /api/import/products membawa file CSV)
[FrankenPHP (App Container)] ───► [PostgreSQL] (Catat Job: Status 'pending')
       │
       ▼ (Dispatch Job & Lepas Request via HTTP 202)
[Redis Queue] ◄─────── Hub Antrian
       │
       ▼ (Diambil secara otomatis oleh Worker)
[Laravel Artisan Worker (Queue Container)]
       │
       ├─► Mode Streaming (fopen): Baca baris demi baris (RAM < 5MB)
       ├─► Update Progress ke PostgreSQL setiap kelipatan 500 baris
       └─► Status Akhir: 'completed' atau 'failed'
```

---

## 🛠️ Spesifikasi & Teknologi Utama

* **Core Framework:** PHP 8.3+ / Laravel 12
* **Application Server:** **FrankenPHP** (Server modern berbasis Go dengan fitur *Worker Mode* persisten untuk kecepatan eksekusi maksimal).
* **In-Memory Broker:** **Redis** (Sebagai pengelola antrian/queue driver berkecepatan tinggi).
* **Relational Database:** **PostgreSQL** (Penyimpanan data produk dan logging status transaksional).
* **Security Layer:** **Laravel Sanctum** (Autentikasi berbasis token dengan perlindungan global Exception JSON `401 Unauthorized`).

---

## ✨ Fitur Unggulan

1.  **Response Instan (HTTP 202 Accepted):** Klien tidak perlu menunggu data selesai dimasukkan ke database. Begitu file CSV tervalidasi dan aman disimpan di storage private, server langsung mengembalikan `job_id`.
2.  **Streaming & Memory Safe Processing:** Alih-alih memuat seluruh isi file CSV berukuran megabyte ke memori (`file_get_contents` atau library berat), Job menggunakan metode low-level `fopen()` dan `fgetcsv()`. Konsumsi RAM container terbukti stabil di **bawah 5MB** selama proses impor.
3.  **Chunk Reporting & Flat JSON Status:** Progress pekerjaan di-update ke database setiap kelipatan 500 baris. Endpoint pengecekan status menyajikan data secara *flat* (rata) lengkap dengan otomatisasi penyesuaian zona waktu lokal **Asia/Jakarta (WIB)**.
4.  **Resilient Fault Tolerance:** Logika pembacaan dilengkapi dengan *Try-Catch block* per baris. Jika ada satu baris data yang cacat (misal SKU duplikat/kosong), sistem akan menaikkan metrik `failed`, mencatatnya ke log aplikasi, dan **tetap melanjutkan** sisa baris data lainnya tanpa menghentikan *worker*.

---

## 📦 Panduan Instalasi Menggunakan Docker

Aplikasi ini sepenuhnya terisolasi di dalam Docker Container. Anda tidak perlu menginstal PHP, Composer, Redis, atau PostgreSQL di komputer Anda.

### 1. Kloning & Jalankan Infrastruktur Container
Pastikan Docker Desktop Anda sudah aktif, buka terminal pada root direktori proyek, lalu jalankan:

```bash
docker compose up -d --build
```
*Perintah ini akan menyalakan 4 container utama: `app`, `queue`, `postgres`, dan `redis`.*

### 2. Inisialisasi Environment & Install Dependensi
Salin file environment, unduh library vendor via Composer di dalam container, dan generate kunci enkripsi aplikasi:
```bash
# Copy file environment
cp .env.example .env

# MANDATORY: Install semua library Laravel di dalam container (Wajib setelah git clone)
docker compose exec app composer install

# Generate kunci enkripsi aplikasi
docker compose exec app php artisan key:generate

### 3. Migrasi Database & Otomatisasi Seeding
Eksekusi migrasi untuk membentuk skema tabel beserta seeder data:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

> 💡 **Efek Samping Seeder:** Proses ini membuat akun Admin untuk pengujian dan men-generate file CSV berisi data produk siap uji di dalam jalur aman `storage/app/private/products_sample.csv`.

---

## 📑 Dokumentasi Kontrak API

Seluruh endpoint API (kecuali Auth) dilindungi oleh middleware Sanctum dan wajib mengembalikan format JSON.

| Fitur | Method | Endpoint | Tingkat Proteksi | Payload / Request Body (form-data) |
| :--- | :--- | :--- | :--- | :--- |
| **Registrasi User** | `POST` | `/api/register` | Public | `name`, `email`, `password`, `password_confirmation` |
| **Login Akun** | `POST` | `/api/login` | Public | `email`, `password` |
| **Upload CSV (Async)** | `POST` | `/api/import/products` | Bearer Token | `file` (Wajib bertipe File/CSV, maks 20MB) |
| **Monitor Progress** | `GET` | `/api/import/status/{id}` | Bearer Token | *None* (Parameter ID dimasukkan pada URL) |

---

## 🎯 Skenario Pengujian via Postman

Gunakan Postman Collection yang telah disediakan di root direktori dengan nama: `.postman_collection/dtk_import_queue_postman_collection.json`.

1. **Impor Koleksi:** Buka Postman, klik tombol **Import**, lalu pilih file json di atas.
2. **Otentikasi Awal:** Jalankan request **Login** menggunakan kredensial default hasil seeder:
   * Email: `admin@internal.com`
   * Password: `password123`
3. **Atur Variabel Token:** Copy string `access_token` dari respons sukses login. Buka tab **Variables** pada tingkat Collection Postman Anda, lalu paste ke kolom `Current Value` pada variabel bernama `token`.
4. **Eksekusi Impor Massal:** Buka request **Upload CSV (Async)**. Masuk ke tab **Body** -> radio button **form-data**. Pastikan pada key bernama `file`, tipe datanya diubah dari Text menjadi **File**. Klik **Select Files** dan pilih file `products_real_sample.csv` (bisa diambil dari folder lokal proyek di `storage/app/private/`). Klik **Send**.
5. **Monitor Real-Time:** Ambil nilai `job_id` dari response instan (misal: `"job_id": 1`), lalu buka request **Check Job Status**. Ganti angka ID di ujung URL menjadi ID job Anda, klik **Send** berulang kali untuk melihat pergerakan naik angka `success` dan `total` secara *live*.

---

## 🔍 Sinkronisasi Log & Pemantauan di Background

Untuk memverifikasi secara langsung bahwa Redis dan Worker sedang bekerja memproses antrian di *background* Docker, Anda dapat memantau log melalui perintah berikut:

```bash
# Memantau aktivitas siklus hidup Job (Running -> Done) secara live
docker compose logs -f queue

# Mengintip log error handling per baris jika ada data CSV yang sengaja dirusak
docker compose exec app tail -n 50 storage/logs/laravel.log
```

---

## 📋 Struktur Skema Basis Data

### 1. Tabel `products` (Penyimpanan Utama)
Menyimpan data produk hasil ekstraksi CSV dengan aturan SKU harus unik.
* `id` (Bigint, Primary Key)
* `name` (Varchar, Nama Produk)
* `sku` (Varchar, Unique Index, Kode SKU Produk)
* `price` (Decimal 10,2, Harga)
* `stock` (Integer, Jumlah Stok)
* `timestamps` (created_at, updated_at)

### 2. Tabel `import_jobs` (Pelacak Antrian)
Digunakan sebagai *stateful tracker* untuk mencatat performa antrian background worker.
* `id` (Bigint, Primary Key)
* `filename` (Varchar, Nama file unik di private storage)
* `status` (Enum: pending, in_progress, completed, failed)
* `total` (Integer, Jumlah total baris terproses saat ini)
* `success` (Integer, Jumlah baris sukses di-upsert)
* `failed` (Integer, Jumlah baris gagal karena validasi bisnis)
* `timestamps` (created_at, updated_at)

---

## 🧪 Eksekusi Unit & Feature Testing

Aplikasi ini dibangun menggunakan pendekatan yang terukur dan dilengkapi dengan pengujian otomatis menggunakan PHPUnit guna memastikan keamanan endpoint dan kestabilan sistem antrian:

* **AuthTest:** Menguji fungsionalitas registrasi, login, logout, dan proteksi token.
* **ProductImportTest:** Menguji pembatasan validasi file, simulasi mocking Redis Queue, memastikan Job berhasil di-dispatch, serta akurasi format JSON respons status.

Jalankan seluruh pengujian di dalam lingkungan container dengan perintah:

```bash
docker compose exec app php artisan test
```

---

## 🛠️ Panduan Troubleshooting

**Masalah Caching / Respon JSON Tidak Berubah:**
Karena FrankenPHP memuat kode ke memori Opcache secara persisten, jika Anda melakukan modifikasi pada file PHP, jalankan perintah ini untuk membersihkan memori:

```bash
docker compose restart app queue
docker compose exec app php artisan config:clear
```

**Error Permission Denied pada Log:**
Jika container gagal menulis file log atau memproses file, jalankan perintah perbaikan hak akses folder berikut di root proyek:

```bash
chmod -R 777 storage bootstrap/cache
```
