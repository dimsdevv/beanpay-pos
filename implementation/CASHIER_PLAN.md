# 📋 IMPLEMENTATION PLAN: Modul Kasir (Modern UI/UX)

Dokumen ini menjelaskan rancangan spesifik untuk **Modul Kasir** agar memiliki estetika premium (Olive/Sage Green), *glassmorphism*, dan interaktivitas tingkat tinggi yang selaras dengan halaman Dashboard Admin.

---

## 1️⃣ ALUR KERJA (WORKFLOW) KASIR

1. **Pengecekan Sesi (Shift)**
   - Saat kasir login dan membuka halaman, sistem mengecek apakah ada sesi shift yang sedang "buka".
   - Jika belum ada, muncul **Modal Buka Shift** (wajib mengisi Modal Awal / saldo laci kasir).
   - Layar utama tidak bisa diakses sebelum shift dibuka.

2. **Layar Utama (Split Layout)**
   - **Kiri (Daftar Antrian)**: Menampilkan pesanan yang siap dibayar (status `selesai` dari dapur, atau `pending` jika langsung bayar).
   - **Kanan (Panel Pembayaran)**: Menampilkan rincian pesanan yang dipilih, subtotal, pajak, dan pilihan metode pembayaran.

3. **Proses Pembayaran**
   - Kasir memilih pesanan di sebelah kiri. Rincian muncul di kanan.
   - Pilih Metode Pembayaran: **Cash**, **QRIS**, atau **Debit** (berupa tombol kapsul/pill interaktif).
   - Jika Cash: Muncul input nominal uang bayar. Sistem menghitung kembalian (*change*) secara *real-time* via JavaScript.
   - Klik tombol utama **"Proses Pembayaran"**.

4. **Penyelesaian & Cetak Struk**
   - Data masuk ke tabel `pembayaran`, status pesanan berubah menjadi `dibayar`, dan status meja menjadi `kosong`.
   - Muncul pop-up SweetAlert sukses, dilanjutkan dengan membuka *jendela cetak (Print)* struk bergaya printer thermal.

---

## 2️⃣ DESAIN UI/UX & ESTETIKA MODERN

Sama seperti Dashboard Admin, Modul Kasir akan dilengkapi sentuhan modern:

### A. Layout & Struktur
*   **Grid Asimetris**: Menggunakan rasio grid `lg:grid-cols-3`. 2 kolom (66%) untuk *Daftar Pesanan*, dan 1 kolom (33%) untuk *Panel Checkout* yang posisinya menempel/fixed di kanan (`sticky top-0`).
*   **Warna**: Menggunakan palet `theme-bg` (latar hijau super terang) dipadukan dengan `theme-sage` dan `theme-evergreen`.

### B. Komponen Visual
1.  **Order Cards (Kartu Pesanan)**:
    *   Tampil seperti *pod* dengan bayangan lembut (*soft shadow*).
    *   Efek *hover* melayang.
    *   Menampilkan Nomor Meja dengan tulisan besar, waktu tunggu pesanan, dan nominal total.
    *   Status "Ready to Pay" dengan *badge* berwarna mencolok (hijau terang).
2.  **Panel Checkout (Sebelah Kanan)**:
    *   *Glassmorphism*: Menggunakan *background white/80* dengan efek *backdrop-blur*.
    *   *List* pesanan (*items*) yang rapi dengan *divider* putus-putus (*dashed*).
    *   **Payment Selectors**: Tombol besar dengan icon (Uang kertas untuk Cash, Barcode untuk QRIS, Kartu untuk Debit). Ketika diklik, tombol berubah warna (aktif) menggunakan transisi halus.
    *   **Tombol Bayar Utama**: Berwarna hijau tua (`theme-evergreen`) dengan animasi pulsa atau bayangan bercahaya (*glow shadow*).

### C. Interaktivitas (Alpine.js & Vanilla JS)
*   **Real-time Kalkulasi**: Ketika kasir mengetik uang masuk (Cash), nominal kembalian (*Change*) dihitung langsung tanpa *reload*.
*   **Validasi Animatif**: Tombol "Proses Pembayaran" tidak bisa diklik (*disabled* / abu-abu) jika metode pembayaran belum dipilih, atau uang *cash* kurang dari total tagihan.
*   **Animasi Transisi**: Penggantian antar pesanan yang dipilih akan diberi efek *fade* halus.

---

## 3️⃣ STRUKTUR FILE YANG AKAN DIBUAT/DIUBAH

1.  `modules/kasir/index.php` (Halaman Utama Kasir)
2.  `modules/kasir/proses_buka_sesi.php` (Logic Backend)
3.  `modules/kasir/proses_bayar.php` (Logic Backend)
4.  `modules/kasir/struk.php` (Halaman khusus tampilan cetak thermal)

---
*Jika rencana desain Modul Kasir ini sudah sesuai keinginan Anda, saya akan langsung memulai penulisan kodenya.*
