# Implementation Plan: Manajemen Meja & Pengaturan Pajak / Service Charge

Dokumen ini merangkum alur kerja dan logika cerdas (*smart logic*) yang akan diimplementasikan untuk menambahkan fitur Manajemen Meja (CRUD) dan integrasi sistem Pajak & *Service Charge* yang dinamis.

## 🎯 Objektif Utama
1. **Manajemen Meja**: Admin dapat menambah, mengedit, dan menghapus nomor meja tanpa menyentuh *database*. Dilengkapi validasi agar meja yang sedang terisi tidak bisa dihapus.
2. **Tax & Service Charge**: Memberikan kemampuan bagi Admin untuk mengatur besaran persentase pajak (PB1) dan *Service Charge*, serta status aktif/nonaktifnya.
3. **Kalkulasi Akurat & Aman**: Perhitungan akhir harga dilakukan secara ketat di sisi *server* (PHP) saat pemesanan, sehingga terhindar dari manipulasi sisi *client*. Rekaman pajak juga disimpan abadi (snapshot) di setiap transaksi.

---

## 🏗️ Phase 1: Modifikasi Database
Sistem membutuhkan tempat penyimpanan pengaturan dan riwayat nominal pajak pada setiap pesanan:

1. **Membuat Tabel `pengaturan`**:
   - `id` (INT, PK)
   - `kunci` (VARCHAR) - contoh: `pajak_persen`, `pajak_status`, `service_persen`, `service_status`.
   - `nilai` (VARCHAR)
   - *Default seed*: Pajak 10% (Aktif), Service 5% (Aktif).
2. **Modifikasi Tabel `pesanan`**:
   - Menambahkan kolom: `subtotal` (DECIMAL), `service_persen` (DECIMAL), `service_nominal` (DECIMAL), `pajak_persen` (DECIMAL), `pajak_nominal` (DECIMAL).
   - *Smart Logic*: Pajak dan Service harus direkam nilainya per pesanan. Jika besok Admin mengubah Pajak menjadi 12%, transaksi hari ini tetap tercatat menggunakan tarif 10%.

---

## 🪑 Phase 2: Manajemen Meja (Admin UI)
Membuat halaman `modules/admin/meja.php` untuk pengelolaan meja.

*   **Fitur**: *Grid layout* dengan tampilan estetik untuk list meja.
*   **Logika Cerdas (Validasi)**: 
    - Saat Admin menghapus meja, sistem akan mengecek statusnya. Jika `status == 'terisi'`, tolak aksi penghapusan dengan peringatan "Meja sedang digunakan".
*   **Navigasi**: Menambahkan tautan "Manajemen Meja" di `sidebar.php`.

---

## ⚙️ Phase 3: Pengaturan Pajak & Service (Admin UI)
Membuat halaman `modules/admin/pengaturan.php`.

*   **UI/UX**: Desain premium bergaya *card* dengan *toggle switches* (Alpine.js) untuk menyalakan/mematikan Pajak & Service. Terdapat *input number* untuk persentase.
*   **Penyimpanan**: Saat disimpan, sistem memperbarui nilai tabel `pengaturan` di *database*.

---

## 🧮 Phase 4: Integrasi Kalkulasi Cerdas (Waiter & Backend)
Kalkulasi akan ditampilkan di UI secara transparan dan dihitung ulang di *backend* demi keamanan.

1.  **Frontend (`modules/waiter/order.php`)**:
    - Saat halaman di-load, PHP akan melempar konfigurasi Pajak & Service aktif ke dalam sistem Alpine.js.
    - Keranjang akan otomatis menghitung: `Subtotal`, `Service` (Subtotal × Service%), `Pajak` ((Subtotal + Service) × Pajak%), dan `Grand Total`.
2.  **Backend (Keamanan Transaksi)**:
    - Saat `submit_order` dikirim, PHP **TIDAK** mempercayai total yang dikirim HP Waiter.
    - PHP menghitung ulang: `Subtotal` murni dari sum harga item × qty. Kemudian menerapkan formula Pajak & Service berdasarkan *setting* database saat ini.
    - PHP menyimpan rincian nominal pajak & service tersebut ke dalam tabel `pesanan` yang sudah di-alter di *Phase 1*.

---

## 🧾 Phase 5: Integrasi Kasir & Struk (Cashier)
*   **Struk Thermal (`struk.php`)**: Dimodifikasi untuk tidak hanya menampilkan total, tapi juga merinci:
    *   Subtotal
    *   Service Charge (...%)
    *   Tax PB1 (...%)
    *   Grand Total
*   **Laporan Admin (`dashboard.php` & `laporan.php`)**: Nominal total pendapatan yang tercatat akan tetap akurat berdasarkan `jumlah_bayar` yang meliputi pajak. (Pilihan: Bisa diekstrak laporan khusus pajak di masa depan).

---
*Silakan setujui rencana ini agar saya dapat mengeksekusinya.*
