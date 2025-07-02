# Sistem Informasi Inventaris Barang

Proyek ini adalah Sistem Informasi Inventaris Barang yang dikembangkan menggunakan bahasa pemrograman PHP. Sistem ini dirancang untuk membantu manajemen persediaan barang di gudang, termasuk fungsi untuk menambah, mengedit, menghapus, serta melihat laporan persediaan barang. Proyek ini juga mencakup manajemen kategori, user, dan transaksi terkait barang keluar dan masuk.

## Struktur Proyek

Berikut adalah struktur proyek beserta penjelasan dari masing-masing direktori dan file:

### 1. `actions`
Berisi berbagai file PHP yang menangani aksi CRUD (Create, Read, Update, Delete) pada berbagai data di sistem. Berikut adalah daftar file beserta fungsinya:
- `cek_invoice.php`: Mengecek nomor invoice yang dimasukkan.
- `cek_produk_in_gudang.php`: Mengecek ketersediaan produk di gudang.
- `simpan_barang_keluar.php`: Menyimpan data barang yang keluar dari gudang.
- `simpan_barang_masuk.php`: Menyimpan data barang yang masuk ke gudang.
- `simpan_gudang.php`: Menyimpan data gudang baru.
- `simpan_kategori.php`: Menyimpan data kategori baru.
- `simpan_produk.php`: Menyimpan data produk baru.
- `simpan_user.php`: Menyimpan data user baru.
- `update_barang_masuk.php`: Memperbarui data barang yang masuk.
- `update_gudang.php`: Memperbarui data gudang.

### 2. `assets`
Folder ini berisi aset-aset yang digunakan pada sistem.

- **css**: Berisi file-file stylesheet (CSS) untuk memperindah tampilan antarmuka.
  - `laporan.css`: Mengatur tampilan untuk halaman laporan.
  - `login.css`: Mengatur tampilan untuk halaman login.
  - `style.css`: Mengatur tampilan umum dari keseluruhan sistem.

- **js**: Berisi file JavaScript yang digunakan untuk menghapus data secara asinkron.
  - `delete_gudang.js`: Membuat alert saat menghapus data gudang.
  - `delete_kategori.js`: Membuat alert saat enghapus data kategori.
  - `delete_user.js`: Membuat alert saat menghapus data user.
  - `delete.js`: Membuat alert saat menghapus data produk.

- **images**: Folder kosong untuk menyimpan gambar-gambar yang dibutuhkan dalam sistem.

### 3. `components`
Berisi komponen-komponen yang digunakan secara berulang di berbagai halaman, seperti:
- `footer.php`: Bagian footer dari sistem.
- `header.php`: Bagian header dari sistem.

### 4. `delete`
Berisi file PHP yang menangani proses penghapusan data dari database.
- `hapus_gudang.php`: Menghapus data gudang.
- `hapus_kategori.php`: Menghapus data kategori.
- `hapus_produk.php`: Menghapus data produk.
- `hapus_user.php`: Menghapus data user.

### 5. `form`
Berisi file PHP yang menampilkan formulir untuk menginput atau mengedit data.
- `edit_gudang.php`: Formulir untuk mengedit data gudang.
- `edit_kategori.php`: Formulir untuk mengedit data kategori.
- `edit_produk.php`: Formulir untuk mengedit data produk.
- `edit_user.php`: Formulir untuk mengedit data user.
- `tambah_barang_keluar.php`: Formulir untuk menambahkan data barang keluar.
- `tambah_barang_masuk.php`: Formulir untuk menambahkan data barang masuk.
- `tambah_gudang.php`: Formulir untuk menambahkan gudang baru.
- `tambah_kategori.php`: Formulir untuk menambahkan kategori baru.
- `tambah_produk.php`: Formulir untuk menambahkan produk baru.
- `tambah_user.php`: Formulir untuk menambahkan user baru.

### 6. `koneksi`
Berisi file untuk mengatur koneksi ke database.
- `koneksi.php`: Mengatur koneksi ke database MySQL.

### 7. `laporan`
Berisi file PHP untuk menampilkan berbagai laporan terkait inventaris.
- `laporan_persediaan_gudang.php`: Menampilkan laporan persediaan per gudang.
- `laporan_persediaan_kategori.php`: Menampilkan laporan persediaan berdasarkan kategori.
- `laporan_persediaan.php`: Menampilkan laporan persediaan umum.
- `laporan_transaksi.php`: Menampilkan laporan transaksi barang masuk dan keluar.

### 8. `pages`
Berisi halaman-halaman utama dari sistem.
- `dashboard.php`: Halaman utama setelah login, menampilkan ringkasan informasi.
- `laporan.php`: Halaman untuk mengakses berbagai laporan.
- `logout.php`: Mengakhiri sesi user dan keluar dari sistem.
- `user.php`: Mengelola data user yang terdaftar di sistem.

### 9. `uploads`
Folder ini berisi file gambar produk yang diupload ke dalam sistem, misalnya:
- `kain kafan.jpeg`
- `kain katun.jpeg`
- `kain sutra.jpeg`
- `KAIN-POLYESTER.png`
- `kaos.jpeg`
- `kemeja pria.jpeg`

### 10. `views`
Berisi file PHP untuk menampilkan data dari database kepada user.
- `barang_keluar.php`: Menampilkan data barang keluar.
- `barang_masuk.php`: Menampilkan data barang masuk.
- `data_gudang.php`: Menampilkan data gudang.
- `data_produk.php`: Menampilkan data produk.
- `gudang_info.php`: Menampilkan informasi rinci tentang gudang.
- `Kategori_barang.php`: Menampilkan data kategori barang.
- `produk_info.php`: Menampilkan informasi rinci tentang produk.
- `transaksi_barang.php`: Menampilkan data transaksi barang.

### 11. `index.php`
Halaman utama (beranda) dari sistem yang menginclude halaman yang dipilih user

### 12. `login.php`
Halaman login untuk akses ke sistem.

## Cara Menggunakan

1. Clone repository ini ke direktori server lokal Anda.
2. Pastikan untuk mengonfigurasi database dan mengimpor file SQL (buatlah database dengan nama inventaris_barang lalu import file inventaris_barang.sql)
3. Atur file `koneksi/koneksi.php` sesuai dengan konfigurasi database Anda tetapi jika anda sudah membuat database dengan nama inventaris_barang dan sudah mengimport file inventaris_barang.sql maka tidak perlu mengubah apa-apa karena sudah sesuai dengan konfigurasi.
4. sebelum anda menjalankan situs pastikan anda terhubung dengan internet karena kami menggunakan beberapa CDN untuk memperindah tampilan.
5. Akses Situs melalui `localhost/sistem_inventaris` pada web browser anda dan login dengan akun yang sesuai, karena situs ini adalah situs inventaris perusahaan yang menyimpan data perushaan maka orang lain tidak boleh membuat akun dan memasuki situs ini, maka dari itu kami tidak menyediakan fitur sign up atau register akun, penambahan akun hanya dapat dilakukan oleh leader perusahaan di halaman user, maka login lah dengan akun yang sudah di sediakan:
- `username: leader1 password: leader1` (akun dengan role leader)
- `username: leader2 password: leader2` (akun dengan role leader)
- `username: admin1 password: admin1` (akun dengan role admin)
- `username: admin2 password: admin2` (akun dengan role admin)
6. Setelah login, Anda dapat mengelola inventaris barang melalui antarmuka yang tersedia.

## Teknologi yang Digunakan

- **PHP** - Bahasa pemrograman server-side untuk pengembangan fungsi sistem.
- **MySQL** - Database untuk menyimpan data inventaris.
- **JavaScript** - Digunakan untuk fitur asinkron dan manipulasi DOM.
- **CSS** - Untuk memperindah tampilan antarmuka.
- **Bootstrap** - Framwork Css Untuk memperindah dan membuat tampilan situs responsif.
- **Sweetalert** - Untuk memberi alert yang indah.

## developer 

Nama: Moammar Iqbal
NIM: 32231600
Prodi: Komputerisasi Akuntansi
Kelas: KA-2023-KIP-P1

## Kontak

Jika Anda memiliki pertanyaan atau masalah terkait proyek ini, silakan hubungi pengembang melalui [ikbal30042005@gmail.com].

---

Semoga file README ini membantu Anda dalam memahami dan menggunakan Sistem Informasi Inventaris Barang.
