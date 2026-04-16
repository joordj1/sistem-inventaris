# Sistem Inventaris Aset

Sistem Inventaris Aset adalah aplikasi berbasis web yang digunakan untuk mengelola data barang, tracking penggunaan, serah terima, serta monitoring kondisi aset secara terstruktur dan terdokumentasi.

---

## 🚀 Fitur Utama

### 1. Manajemen Produk

* Tambah, edit, dan hapus data barang
* Identifikasi barang menggunakan kode unik / unit
* Pengelompokan berdasarkan kategori dan gudang

### 2. Tracking Aset

* Mencatat setiap aktivitas barang (dipinjam, dikembalikan, perbaikan, dll)
* Riwayat tracking tersimpan secara kronologis
* Tracking dapat dilakukan per unit

### 3. Serah Terima Barang

* Pencatatan pemberi dan penerima barang
* Dokumentasi serah terima (foto)
* Riwayat serah terima tersimpan

### 4. Manajemen Status Barang

* Status utama:

  * tersedia
  * dipinjam
  * perbaikan
* Perubahan status otomatis berdasarkan aksi

### 5. Vendor Perbaikan

* Pencatatan vendor untuk perbaikan barang
* Estimasi waktu perbaikan
* Integrasi dengan tracking

### 6. QR Code

* Scan QR untuk melihat:

  * Detail barang
  * Riwayat penggunaan
  * Kondisi terbaru (berdasarkan dokumentasi)

### 7. Laporan

* Laporan berdasarkan:

  * Barang
  * Pengguna
* Siap untuk kebutuhan print

---

## 🧱 Struktur Data (Simplified)

### Produk

* id
* kode_unit
* nama
* status
* lokasi

### Tracking

* id
* produk_id
* aksi
* status
* user_id
* vendor_id (opsional)
* tanggal
* keterangan

### Serah Terima

* id
* produk_id
* pemberi_id
* penerima_id
* dokumentasi

---

## ⚠️ Aturan Penting Sistem

### 1. Konsistensi Status

Nilai status HARUS sesuai dengan database:

```
tersedia
dipinjam
perbaikan
```

Tidak diperbolehkan menggunakan variasi lain seperti:

* "sedang perbaikan"
* "repair"
* "fix"

---

### 2. Relasi Data

* penerima_id harus terhubung ke tabel user
* produk_id harus valid
* vendor hanya digunakan saat status = perbaikan

---

### 3. Validasi Tracking

* Aksi perbaikan WAJIB:

  * vendor_id
  * estimasi
* Status otomatis berubah sesuai aksi

---

## 🐛 Bug yang Telah Diperbaiki (Patch)

* Perbaikan tampilan penerima barang (tidak lagi bernilai 0)
* Perbaikan filter tabel agar nomor tidak acak
* Perubahan tampilan tracking dari card ke tabel
* Otomatisasi status saat aksi perbaikan
* Perbaikan kolom unit pada riwayat tracking

---

## 📌 Catatan Pengembangan

Beberapa fitur yang sedang atau akan dikembangkan:

* Report berdasarkan user dan barang
* Tracking per user
* Dokumentasi mutasi barang
* Integrasi foto kondisi terbaru ke QR
* User manual / SOP sistem

---

## 🛠️ Prinsip Pengembangan

* Perubahan harus minimal dan terarah
* Tidak mengubah struktur database tanpa alasan kuat
* Validasi dilakukan di backend, bukan hanya frontend
* Konsistensi data adalah prioritas utama

---

## 📷 Dokumentasi

Dokumentasi penggunaan sistem akan ditambahkan dalam bentuk:

* Screenshot
* Panduan penggunaan (SOP)
* Studi kasus penggunaan

---

## 📄 Lisensi

Project ini digunakan untuk kebutuhan internal dan pengembangan sistem inventaris.

---

## 👨‍💻 Developer Note

Sistem ini masih dalam tahap pengembangan aktif.

Jika terjadi error:

* Periksa validasi input
* Pastikan relasi database benar
* Hindari perubahan di luar scope bug

---
