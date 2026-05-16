# 🧠 MASTER IMPLEMENTATION PLAN: Smart Workflow & Business Logic
## BeanPay — Sistem Kasir Cafe & Restaurant

---

## 1️⃣ ALUR KERJA MENYELURUH (END-TO-END FLOW)

Alur kerja seluruh sistem mengikuti pipeline linear berikut:

```
WAITER (Input Order) → DAPUR (Proses Masak) → KASIR (Checkout) → ADMIN (Laporan)
```

Setiap tahap memiliki **status gate** — modul selanjutnya tidak bisa bergerak sebelum status dari modul sebelumnya terpenuhi.

---

## 2️⃣ STATE MACHINE: STATUS PESANAN

Ini adalah "jantung" dari seluruh sistem. Status berpindah secara linear dan ada validasi agar tidak bisa melompat.

```
pending ──► diproses ──► selesai ──► dibayar
  (Waiter)   (Dapur)     (Dapur)    (Kasir)
                                       │
                              dibatalkan (kapan saja sebelum dibayar)
```

| Status | Aktor | Kondisi |
|---|---|---|
| `pending` | Waiter | Order baru dibuat, menunggu konfirmasi dapur |
| `diproses` | Dapur | Setidaknya 1 item berstatus `cooking` |
| `selesai` | Dapur | Semua item berstatus `ready` — **kasir baru bisa proses** |
| `dibayar` | Kasir | Transaksi berhasil — **meja otomatis kosong** |
| `dibatalkan` | Admin/Kasir | Dibatalkan sebelum pembayaran |

### State Machine: Status Item Detail
```
pending ──► cooking ──► ready
         (Dapur)    (Dapur)
```
> Pesanan dianggap `selesai` jika **semua** `detail_pesanan` berstatus `ready`.

---

## 3️⃣ MODUL WAITER — LOGIKA CERDAS

### A. Validasi Sebelum Input Order
- Jika `dine_in`: Cek apakah meja masih `kosong`. Jika meja `terisi`, tampilkan peringatan.
- Jika `take_away`: Hanya wajib mengisi `nama_pelanggan`.

### B. Generate Nomor Pesanan Otomatis
```
Format: ORD-{YYYYMMDD}-{NomorUrut 3 digit}
Contoh: ORD-20260505-007
```
> Query: `SELECT COUNT(*) FROM pesanan WHERE DATE(waktu_pesan) = CURDATE()` lalu increment.

### C. Submit Order (TRANSACTION)
Proses submit menggunakan `PDO Transaction` agar atomik:
1. `INSERT INTO pesanan` → dapatkan `last_insert_id`
2. `INSERT INTO detail_pesanan` (loop untuk setiap item di cart)
3. `UPDATE meja SET status = 'terisi'` (jika dine_in)
4. Jika salah satu gagal → `ROLLBACK` semua

### D. Cart di Frontend (Alpine.js)
- Data cart disimpan di `localStorage` sebagai backup jika halaman di-refresh.
- Qty item bisa di-increment/decrement secara langsung di UI.
- Kalkulasi grand total dilakukan real-time.

---

## 4️⃣ MODUL DAPUR — LOGIKA CERDAS

### A. Auto-Refresh Tanpa Reload (AJAX Polling)
- Menggunakan `setInterval` setiap **5 detik** untuk memanggil endpoint `api/dapur_orders.php`.
- Hanya data yang berubah yang di-re-render (bukan seluruh halaman).
- Jika ada order baru, muncul **notifikasi bunyi** (`Audio API`) + **badge merah** baru berkedip.

### B. Update Status Item (One-Click)
- Tombol `cooking` hanya muncul jika status item masih `pending`.
- Tombol `ready` hanya muncul jika status item sudah `cooking`.
- Setelah tombol diklik, sistem **otomatis cek**: Apakah semua item dalam pesanan ini sudah `ready`?
  - **Jika YA** → Update `pesanan.status_pesanan = 'selesai'` secara otomatis.
  - **Jika BELUM** → Update `pesanan.status_pesanan = 'diproses'`.

### C. Tampilan Kanban / Card
- Setiap pesanan ditampilkan sebagai **Order Card** dengan:
  - Header: Nomor meja / Take Away + Waktu tunggu (dihitung realtime dari `waktu_pesan`).
  - Body: List item dengan tombol status.
  - Highlight: Warna background berubah sesuai urgensi waktu tunggu (hijau → kuning → merah).

---

## 5️⃣ MODUL KASIR — LOGIKA CERDAS

### A. Sesi Kasir (Shift Guard)
- Kasir **wajib** buka shift sebelum bisa akses halaman kasir.
- Saat shift ditutup: Sistem hitung otomatis `total_pemasukan = SUM(pembayaran.jumlah_bayar)` di shift tersebut.
- **Laporan Ringkasan Shift**: Modal Awal + Total Pemasukan = Total di Laci.

### B. Filter Pesanan Pintar
Kasir hanya bisa melihat pesanan dengan status `selesai` (sudah siap dari dapur).

### C. Logika Pembayaran
| Metode | Validasi | Kembalian |
|---|---|---|
| Cash | `jumlah_bayar >= total_harga` | `jumlah_bayar - total_harga` |
| QRIS | `jumlah_bayar == total_harga` (fixed) | `0` |
| Debit | `jumlah_bayar == total_harga` (fixed) | `0` |

### D. Proses Checkout (TRANSACTION)
1. `INSERT INTO pembayaran`
2. `UPDATE pesanan SET status = 'dibayar'`
3. `UPDATE meja SET status = 'kosong'` (jika dine_in)
4. `UPDATE sesi_kasir SET total_pemasukan = total_pemasukan + total_harga`
5. Redirect ke `struk.php` + auto-print

### E. Tutup Shift
- Saat kasir klik "Tutup Shift": Muncul **ringkasan shift** lengkap.
- `UPDATE sesi_kasir SET waktu_tutup = NOW(), status = 'tutup'`.

---

## 6️⃣ MODUL ADMIN — LOGIKA CERDAS

### A. Dashboard KPI
Query agregasi data secara real-time:
- Total Sales Hari Ini
- Total Order Hari Ini
- Average Check
- Jumlah Meja Aktif

### B. CRUD Menu + Kategori
- Upload gambar menu → validasi tipe file (jpg, png, webp) & ukuran maksimal 2MB.
- Soft toggle status `tersedia/habis` tanpa hapus data.

### C. CRUD Meja
- Validasi: Meja tidak bisa dihapus jika sedang `terisi`.

### D. CRUD User (Manajemen Staf)
- Admin bisa reset password user.
- Non-aktifkan akun tanpa menghapus riwayat transaksi.

### E. Laporan Penjualan (Lengkap)
- Filter berdasarkan rentang tanggal.
- Export ke **PDF** (via `print CSS`) atau **Excel** (via manual CSV generation).
- Grafik Tren Penjualan 7 hari terakhir (Chart.js).
- Laporan **Best Seller** (item terlaris).

---

## 7️⃣ STRUKTUR FILE YANG AKAN DIBUAT

```
BeanPay/
├── api/
│   └── dapur_orders.php      ← Endpoint AJAX untuk auto-refresh dapur
├── modules/
│   ├── admin/
│   │   ├── dashboard.php     ✅ DONE
│   │   ├── menu.php          ← CRUD Menu & Kategori
│   │   ├── users.php         ← Manajemen User
│   │   └── laporan.php       ← Laporan Penjualan
│   ├── kasir/
│   │   ├── index.php         ✅ DONE
│   │   ├── proses_buka_sesi.php ✅ DONE
│   │   ├── proses_bayar.php  ✅ DONE
│   │   ├── struk.php         ✅ DONE
│   │   └── tutup_shift.php   ← Tutup shift & ringkasan
│   ├── waiter/
│   │   ├── order.php         ← Halaman POS Waiter (input order)
│   │   └── meja.php          ← Status semua meja
│   └── dapur/
│       └── index.php         ← Antrian dapur (auto-refresh)
```

---

## 8️⃣ URUTAN PENGERJAAN (PRIORITY ORDER)

| Prioritas | Modul | Alasan |
|---|---|---|
| **1** | Admin Menu & Kategori | Wajib ada data menu sebelum Waiter bisa order |
| **2** | Waiter (POS Order) | Sumber utama transaksi |
| **3** | Dapur (Kitchen Display) | Jembatan antara Waiter dan Kasir |
| **4** | Kasir (Tutup Shift) | Melengkapi siklus kasir |
| **5** | Admin Laporan | Ringkasan bisnis untuk owner |

> [!IMPORTANT]
> Semua modul sudah memiliki fondasi **database yang solid** dan **kode autentikasi** yang benar. Yang tersisa adalah membangun UI dan logika untuk masing-masing modul sesuai urutan di atas.

---
*Jika rencana ini sudah disetujui, kita mulai dari Prioritas 1: Halaman Admin CRUD Menu & Kategori.*
