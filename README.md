<div align="center">

# 🧾 POS Backend API

**Backend REST API untuk sistem kasir (POS) internal toko**

Dibangun dengan Laravel 13 · PHP 8.3 · MySQL 8 · Laravel Sanctum

[![Tests](https://img.shields.io/badge/tests-35%20passed-brightgreen)](tests/)
[![Code Style](https://img.shields.io/badge/code%20style-Pint-blueviolet)](https://laravel.com/docs/pint)
[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![MySQL](https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)

</div>

---

## 📋 Daftar Isi

- [Tentang Proyek](#-tentang-proyek)
- [Fitur](#-fitur)
- [Tech Stack](#-tech-stack)
- [Skema Database](#-skema-database)
- [Instalasi](#-instalasi)
- [Menjalankan Aplikasi](#-menjalankan-aplikasi)
- [Dokumentasi Endpoint](#-dokumentasi-endpoint)
- [Role & Permission](#-role--permission)
- [Aturan Bisnis](#-aturan-bisnis)
- [Pengujian](#-pengujian)
- [Struktur Proyek](#-struktur-proyek)
- [Dokumentasi Tambahan](#-dokumentasi-tambahan)

---

## 🎯 Tentang Proyek

Backend API untuk aplikasi Point of Sale (POS) yang dipakai untuk operasional internal toko
bangunan. Sistem menangani **transaksi penjualan di kasir**, **manajemen stok terpusat**, dan
**otomatisasi pengurangan stok** setiap kali transaksi selesai.

Skema database mengikuti dokumen rancangan resmi proyek (`docs/Rancangan Database MPTI.pdf`)
yang terdiri dari **4 tabel**: `pengguna`, `stok`, `transaksi`, dan `detail_transaksi`.

---

## ✨ Fitur

| Fitur | Status | Keterangan |
|---|:---:|---|
| Autentikasi token | ✅ | Laravel Sanctum, token per device |
| Manajemen pengguna & role | ✅ | Role ENUM: `KASIR`, `GUDANG`, `MANAGER` |
| Otorisasi berbasis permission | ✅ | spatie/laravel-permission di server |
| CRUD stok | ✅ | SKU otomatis, pencarian, filter ketersediaan |
| Scan produk by SKU | ✅ | Endpoint khusus untuk input kasir |
| Transaksi kasir (checkout) | ✅ | Multi-item, atomik, rollback saat gagal |
| Pengurangan stok otomatis | ✅ | Dalam satu DB transaction + `lockForUpdate` |
| Perhitungan kembalian | ✅ | Hanya untuk pembayaran `TUNAI` |
| Nomor struk otomatis | ✅ | Format `INV/YYYY/MM/NNNN`, tidak pernah berulang |
| Data struk | ✅ | Endpoint siap cetak untuk frontend |
| Pembatalan transaksi | ✅ | Stok dikembalikan, histori tetap tersimpan |
| Ganti password | ✅ | Mencabut token perangkat lain |
| Laporan harian/bulanan | ❌ | Belum diimplementasikan |

---

## 🛠 Tech Stack

| Komponen | Versi / Keterangan |
|---|---|
| **Framework** | Laravel 13.x (API-only) |
| **Bahasa** | PHP 8.3 |
| **Database** | MySQL 8 / MariaDB (InnoDB, `utf8mb4_unicode_ci`) |
| **Autentikasi** | Laravel Sanctum |
| **Otorisasi** | spatie/laravel-permission |
| **Code Style** | Laravel Pint |
| **Testing** | PHPUnit 12 |
| **Timezone** | `Asia/Jakarta` |

---

## 🗄 Skema Database

Mengikuti `docs/Rancangan Database MPTI.pdf` — **wajib**, tidak boleh menambah tabel/kolom di luar rancangan.

### `users` (Pengguna)

| Kolom | Tipe | Key | Keterangan |
|---|---|---|---|
| `id` | BIGINT | PK | Auto Increment |
| `name` | VARCHAR(255) | UNIQUE | Nama pengguna (kolom `nama`) |
| `email` | VARCHAR(150) | | Login |
| `password` | VARCHAR(255) | | Hash |
| `role` | ENUM | | `KASIR` / `GUDANG` / `MANAGER` |

### `stok`

| Kolom | Tipe | Key | Keterangan |
|---|---|---|---|
| `id_produk` | BIGINT | PK | Auto Increment |
| `kode_sku` | VARCHAR(50) | UNIQUE | Kode unik produk |
| `nama_produk` | VARCHAR(150) | | Nama produk |
| `harga` | INT | | Harga satuan (rupiah) |
| `kuantitas` | INT | | Saldo stok |

### `transaksi`

| Kolom | Tipe | Key | Keterangan |
|---|---|---|---|
| `id_transaksi` | BIGINT | PK | Auto Increment |
| `nomor_struk` | VARCHAR(30) | UNIQUE | `INV/2026/10/0001` |
| `id_pengguna` | BIGINT | FK → `users.id` | Kasir |
| `tanggal_transaksi` | TIMESTAMP | | Waktu transaksi |
| `total_pembayaran` | DECIMAL(15,2) | | Total belanja |
| `jenis_pembayaran` | ENUM | | `TUNAI` / `QRIS` / `TRANSFER` |
| `kembalian` | DECIMAL(15,2) | | Hanya untuk `TUNAI` |

### `detail_transaksi`

| Kolom | Tipe | Key | Keterangan |
|---|---|---|---|
| `id_detail` | BIGINT | PK | Auto Increment |
| `id_transaksi` | BIGINT | FK → `transaksi` | |
| `id_produk` | BIGINT | FK → `stok` | |
| `jumlah` | INT | | Qty |
| `sub_total` | DECIMAL(15,2) | | `jumlah × harga` |

---

## 🚀 Instalasi

### Prasyarat

- PHP **8.3+** dengan ekstensi `pdo_mysql`, `mbstring`, `openssl`, `intl`, `bcmath`
- Composer **2.x**
- MySQL **8** / MariaDB (berjalan di `127.0.0.1:3306`)

### Langkah

**1. Clone & install dependensi**

```bash
git clone <repo-url> backend
cd backend
composer install
```

**2. Siapkan database**

```sql
CREATE DATABASE pos_backend      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE pos_backend_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**3. Konfigurasi environment**

```bash
cp .env.example .env
php artisan key:generate
```

Sesuaikan bagian database pada `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pos_backend
DB_USERNAME=root
DB_PASSWORD=
```

**4. Migrasi & isi data awal**

```bash
php artisan migrate --seed --force
```

Seeder membuat 3 akun, 3 role, 9 permission, dan **104 produk** katalog toko bangunan.

---

## ▶ Menjalankan Aplikasi

```bash
php artisan serve
```

API tersedia di **`http://localhost:8000/api/v1`**

### Akun Bawaan

| Nama | Email | Password | Role |
|---|---|---|---|
| Owner Toko | `owner@pos.local` | `password` | MANAGER |
| Kasir Toko | `kasir@pos.local` | `password` | KASIR |
| Petugas Gudang | `gudang@pos.local` | `password` | GUDANG |

> ⚠️ **Ganti semua password ini sebelum digunakan di produksi.** Nilai awal diambil dari
> `POS_OWNER_NAME`, `POS_OWNER_EMAIL`, dan `POS_OWNER_PASSWORD` di `.env`.

---

## 📡 Dokumentasi Endpoint

**Base URL:** `http://localhost:8000/api/v1`
**Format:** JSON · **Auth:** `Authorization: Bearer <token>`

### Ringkasan

| Method | Endpoint | Akses | Fungsi |
|---|---|---|---|
| `GET` | `/api` | publik | Info API |
| `GET` | `/health` | publik | Status aplikasi & database |
| `POST` | `/auth/login` | publik | Login, menerima token |
| `POST` | `/auth/logout` | auth | Cabut token aktif |
| `GET` | `/auth/me` | auth | Profil + role + permission |
| `PATCH` | `/auth/password` | auth | Ganti password |
| `GET` | `/stok` | `stok.view` | Daftar stok |
| `POST` | `/stok` | `stok.create` | Tambah produk |
| `GET` | `/stok/scan` | `stok.view` | Cari produk via SKU |
| `GET` | `/stok/{id_produk}` | `stok.view` | Detail produk |
| `PUT` | `/stok/{id_produk}` | `stok.update` | Ubah produk |
| `DELETE` | `/stok/{id_produk}` | `stok.delete` | Hapus produk |
| `GET` | `/transaksi` | `transaksi.view` | Daftar transaksi |
| `POST` | `/transaksi` | `transaksi.create` | Checkout |
| `GET` | `/transaksi/{id}` | `transaksi.view` | Detail transaksi |
| `GET` | `/transaksi/{id}/struk` | `transaksi.view` | Data struk |
| `DELETE` | `/transaksi/{id}` | `transaksi.void` | Batalkan transaksi |

### Format Response

**Sukses**

```json
{
  "success": true,
  "message": "Transaksi berhasil.",
  "data": { }
}
```

**Error**

```json
{
  "success": false,
  "message": "Stok Semen tidak mencukupi (tersedia 8, diminta 99).",
  "error_code": "STOK_TIDAK_CUKUP",
  "errors": { "jumlah": ["Stok tidak mencukupi."] }
}
```

### Contoh Penggunaan

**1. Login**

```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"kasir@pos.local","password":"password"}'
```

```json
{
  "success": true,
  "message": "Login berhasil.",
  "data": {
    "token": "2|N52mxT9Hqb6NoHILo6pz59H83P0Lh5lw...",
    "token_type": "Bearer",
    "user": {
      "id": 2,
      "nama": "Kasir Toko",
      "role": "KASIR",
      "permissions": ["stok.view", "transaksi.view", "transaksi.create"]
    }
  }
}
```

**2. Scan produk**

```bash
curl "http://localhost:8000/api/v1/stok/scan?kode_sku=SEMEN" \
  -H "Authorization: Bearer <token>"
```

**3. Checkout**

```bash
curl -X POST http://localhost:8000/api/v1/transaksi \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "detail": [{"id_produk": 1, "jumlah": 2}],
    "jenis_pembayaran": "TUNAI",
    "jumlah_bayar": 200000
  }'
```

```json
{
  "success": true,
  "message": "Transaksi berhasil.",
  "data": {
    "id_transaksi": 1,
    "nomor_struk": "INV/2026/10/0001",
    "total_pembayaran": 130000,
    "jenis_pembayaran": "TUNAI",
    "kembalian": 70000,
    "detail": [
      { "produk": { "kode_sku": "SEMEN", "harga": 65000 }, "jumlah": 2, "sub_total": 130000 }
    ]
  }
}
```

### Kode Error

| HTTP | `error_code` | Arti |
|---|---|---|
| `401` | `UNAUTHENTICATED` | Token tidak ada / kedaluwarsa |
| `403` | `FORBIDDEN` | Role tidak punya permission |
| `403` | `ACCOUNT_INACTIVE` | Akun dinonaktifkan |
| `404` | `NOT_FOUND` | Data tidak ditemukan |
| `409` | `RESOURCE_IN_USE` | Data masih dipakai record lain |
| `422` | `VALIDATION_ERROR` | Validasi field gagal |
| `422` | `INVALID_CREDENTIALS` | Email/password salah |
| `422` | `STOK_TIDAK_CUKUP` | Kuantitas stok tidak cukup |
| `422` | `PEMBAYARAN_KURANG` | Uang tunai kurang dari total |
| `500` | `INTERNAL_ERROR` | Kesalahan server |

> 📖 Dokumentasi lengkap (payload & contoh respons per endpoint): **[docs/API.md](docs/API.md)**

---

## 🔐 Role & Permission

| Permission | MANAGER | KASIR | GUDANG |
|---|:---:|:---:|:---:|
| `stok.view` | ✅ | ✅ | ✅ |
| `stok.create` / `update` / `delete` | ✅ | — | ✅ |
| `transaksi.view` | ✅ | ✅ | — |
| `transaksi.create` | ✅ | ✅ | — |
| `transaksi.void` | ✅ | — | — |
| `user.*` | ✅ | — | — |
| `report.view` | ✅ | — | ✅ |

---

## 📏 Aturan Bisnis

Sistem menegakkan aturan berikut di sisi server:

1. **Harga selalu dari database** — payload client tidak pernah menentukan harga.
2. **Stok dipotong atomik** — dalam DB transaction yang sama dengan pembuatan transaksi,
   memakai `lockForUpdate()` dengan urutan ID agar bebas deadlock.
3. **Stok minus ditolak** — `422 STOK_TIDAK_CUKUP`, seluruh transaksi di-rollback.
4. **Kembalian hanya untuk TUNAI** — `QRIS` dan `TRANSFER` selalu `0`.
5. **Nomor struk unik selamanya** — pembatalan memakai soft delete sehingga nomor tidak didaur ulang.
6. **Otorisasi di server** — via middleware `permission:`, bukan hanya disembunyikan di frontend.

---

## 🧪 Pengujian

### Test Otomatis

```bash
php artisan test
```

```
Tests:    35 passed (148 assertions)
```

Cakupan: autentikasi, otorisasi per role, CRUD stok, checkout + pengurangan stok, rollback stok
kurang, kembalian, validasi, struk, pembatalan, dan nomor struk yang tidak berulang.

### Uji Coba API (Smoke Test)

Setelah server berjalan:

```bash
powershell -NoProfile -ExecutionPolicy Bypass -File tests/ApiSmokeTest.ps1
```

Menjalankan 61 request ke seluruh endpoint dan menulis hasil ke `storage/hasil.txt`.
Hasil terakhir: **61 PASS / 0 FAIL** — lihat [docs/ujicoba.md](docs/ujicoba.md).

### Code Style

```bash
vendor/bin/pint --test    # cek
vendor/bin/pint           # perbaiki otomatis
```

---

## 📁 Struktur Proyek

```
backend/
├── app/
│   ├── Enums/                  # UserRole, JenisPembayaran
│   ├── Exceptions/             # ApiException (error code + status)
│   ├── Http/
│   │   ├── Controllers/Api/V1/ # Auth, Stok, Transaksi, Health
│   │   ├── Middleware/         # ForceJsonResponse, EnsureAccountIsActive
│   │   ├── Requests/           # Validasi form
│   │   └── Resources/          # Bentuk response JSON
│   ├── Models/                 # User, Stok, Transaksi, DetailTransaksi
│   ├── Services/               # NomorStrukService
│   └── Support/                # ApiResponse (envelope)
├── database/
│   ├── data/                   # CSV katalog 104 produk
│   ├── migrations/             # 11 migrasi
│   ├── seeders/                # Role, pengguna, katalog
│   └── factories/
├── routes/
│   ├── api.php
│   └── api/v1/{auth,master}.php
├── tests/
│   ├── Feature/                # Auth, Stok, Transaksi
│   └── ApiSmokeTest.ps1        # Uji coba manual
└── docs/
```

---

## 📚 Dokumentasi Tambahan

| Dokumen | Isi |
|---|---|
| [docs/API.md](docs/API.md) | Kontrak endpoint lengkap: payload, respons, error code |
| [docs/ujicoba.md](docs/ujicoba.md) | Hasil uji coba 61 request |
| [docs/IMPLEMENTATION_LOG.md](docs/IMPLEMENTATION_LOG.md) | Status implementasi & catatan perbaikan bug |
| [docs/Rancangan Database MPTI.pdf](docs/Rancangan%20Database%20MPTI.pdf) | Acuan skema (sumber kebenaran) |

---

<div align="center">

**POS Backend API** · Laravel 13 · PHP 8.3 · MySQL 8

</div>
