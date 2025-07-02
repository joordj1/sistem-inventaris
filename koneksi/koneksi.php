<?php
$host = "localhost"; // Nama host server
$user = "root";      // Nama pengguna database
$pass = "";          // Kata sandi pengguna database
$db   = "inventaris_barang"; // Nama database yang digunakan

// Membuat koneksi
$koneksi = new mysqli($host, $user, $pass, $db);

// Mengecek koneksi
if ($koneksi->connect_error) {
    die("Koneksi gagal: " . $koneksi->connect_error);
}

?>
