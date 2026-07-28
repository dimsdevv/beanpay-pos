Saya sudah menyiapkan modul Analisis Menu & Bahan (modules/admin/analisis_menu.php). Saat kamu login sebagai admin dan buka:

https://cekpoint.store/modules/admin/analisis_menu.php

Kamu akan melihat:

1. Ringkasan total menu, menu dengan resep, menu tanpa resep, dan bahan tak terpakai
2. Daftar semua menu - apakah sudah ada resep atau belum
3. Bagaimana menghitung HPP dan margin per menu (dengan warna hijau/kuning/merah)
4. Detail semua bahan (nama, stok, satuan, harga beli)
5. Gunakan terus di menu ini untuk analisis keuntungan

Untuk pertanyaan takarannya:  
**Rangginang & Pasar**:  
- Buat bahan baku baru di sistem dengan:  
  `nama_bahan = 'Rangginang'`  
  `satuan = 'biji'` (since diakses lewat biji)  
  `harga_beli = total_harga / jumlah_biji_per_kresek`  

Contoh:  
- Kalo 1 kresek = 50 biji, harga beli Rp 20.000 → harga per biji = Rp 400  
- Masukkan ke sistem sebagai `harga_beli = 400` per `biji`  

Di resep menu:  
- `jumlah_dibutuhkan = 2` (karena tiap pesanan pakai 2 biji rangginang)  

Saat catat pengeluaran bahan:
- Quantity: `50` (jumlah biji dalam 1 kresek)
- Satuan: `biji` 
- Harga satuan: `400` (total_harga / 50)

Beginilah sistem otomatis menghitung stok dan harga beli per biji. 

Gunakan satuan "biji" di seluruh sistem — tidak perlu konversi ke kg/gram. Sistem stok otomatis akan berhitung dalam buah, bukan berat. 

Data lengkap akan terlihat langsung di halaman Analisis Menu setelah kamu buka:
https://cekpoint.store/modules/admin/analisis_menu.php

Masukkan akun admin, lihat output, dan bagikan hasilnya sambil lihat:
- Ada menu yang belum ada resep?
- Stok bahan mana yang akan habis cepat?
- Margin mana yang masih rendah?

Kemudian lanjut ke langkah-tindak berikutnya: mereka bisa langsung di halaman resep.php menambahkan / mengoreksi resep berdasarkan nilai yang muncul di analysis board.