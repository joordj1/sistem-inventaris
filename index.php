<?php
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['id_user'])) {
    // Jika belum login, arahkan ke login.php
    header("Location: login.php");
    exit;
}

// Cek role user (hanya admin dapat mengakses modul manajemen user)
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['role'] === 'leader') {
    $_SESSION['role'] = 'user';
}

$userMenuVisible = ($_SESSION['role'] === 'admin');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Informasi Manajemen Inventaris Barang</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Sweet Alert -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.4.4/dist/sweetalert2.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="app-shell">
<?php include 'koneksi/koneksi.php'; ?>
<?php include 'components/sidebar.php'; ?>

<div class="app-content">
    <div class="page-header">
        <h1 class="page-title">Inventaris Dashboard</h1>
    </div>

    <main class="main-page p-4">
    <?php
        if (isset($_GET['page'])) {
            $page = $_GET['page'];
            switch ($page) {
                // Menu untuk halaman leader
                case 'user':
                    if ($userMenuVisible) {
                        include "pages/user.php";
                    } else {
                        echo "<center><h3>Anda tidak memiliki akses ke halaman ini.</h3></center>";
                    }
                    break;
                case "tambah_user":
                    if ($userMenuVisible) {
                        include "form/tambah_user.php";
                    } else {
                        echo "<center><h3>Anda tidak memiliki akses ke halaman ini.</h3></center>";
                    }
                    break;
                case "edit_user":
                    if ($userMenuVisible) {
                        include "form/edit_user.php";
                    } else {
                        echo "<center><h3>Anda tidak memiliki akses ke halaman ini.</h3></center>";
                    }
                    break;


                // Halaman lainnya tetap bisa diakses admin
                case 'dashboard':
                    include "pages/dashboard.php";
                    break;
                case 'laporan':
                    include "pages/laporan.php";
                    break;
                case 'logout':
                    include "pages/logout.php";
                    break;
                
                // menuju ke views
                case 'data_produk':
                    include "views/data_produk.php";
                    break;
                case 'barang_masuk':
                    include "views/barang_masuk.php";
                    break;
                case 'barang_keluar':
                    include "views/barang_keluar.php";
                    break;
                case 'data_gudang':
                    include "views/data_gudang.php";
                    break;
                case 'kategori_barang':
                    include "views/kategori_barang.php";
                    break;
                case 'transaksi_barang':
                    include "views/transaksi_barang.php";
                    break;
                case 'gudang_info':
                    include "views/gudang_info.php";
                    break;
                case 'produk_info':
                    include "views/produk_info.php";
                    break;
                case 'unit_barang_info':
                    include "views/unit_barang_info.php";
                    break;
                case 'print_unit_qr':
                    include "views/print_unit_qr.php";
                    break;
                    
                // menuju ke form
                case 'tambah_produk':
                    include "form/tambah_produk.php";
                    break;
                case 'tambah_kategori':
                    include "form/tambah_kategori.php";
                    break;
                case 'edit_produk':
                    include "form/edit_produk.php";
                    break;
                case 'edit_kategori':
                    include "form/edit_kategori.php";
                    break;
                case 'tambah_gudang':
                    include "form/tambah_gudang.php";
                    break;
                case 'edit_gudang':
                    include "form/edit_gudang.php";
                    break;
                case 'tambah_barang_masuk':
                    include "form/tambah_barang_masuk.php";
                    break;
                case 'tambah_barang_keluar':
                    include "form/tambah_barang_keluar.php";
                    break;
                    
                // menuju ke laporan
                case 'laporan_persediaan':
                    include "laporan/laporan_persediaan.php";
                    break;
                case 'laporan_persediaan_kategori':
                    include "laporan/laporan_persediaan_kategori.php";
                    break;
                case 'laporan_persediaan_gudang':
                    include "laporan/laporan_persediaan_gudang.php";
                    break;
                
                default:
                    echo "<center><h3>Maaf. Halaman tidak di temukan !</h3></center>";
                    break;
            }
        } else {
            include "pages/dashboard.php";
        }
    ?>
</div>

    </main>
</div>

<?php include 'components/footer.php'; ?>

<!-- Bootstrap JS and Dependencies -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Tambahkan SweetAlert2 dari CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- js -->
<script src="assets/js/delete.js"></script>
<script src="assets/js/delete_kategori.js"></script>
<script src="assets/js/delete_gudang.js"></script>
<script src="assets/js/delete_user.js"></script>
</body>
</html>
