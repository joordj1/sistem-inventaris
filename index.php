<?php
session_start();
include 'koneksi/koneksi.php';

// Legacy QR compatibility: allow old public scan URL pattern to redirect without login.
if ((string) ($_GET['page'] ?? '') === 'unit_barang_info' && empty($_SESSION['id_user'])) {
    $legacyUnitId = isset($_GET['id_unit_barang']) ? intval($_GET['id_unit_barang']) : 0;
    if ($legacyUnitId > 0 && schema_table_exists_now($koneksi, 'unit_barang')) {
        $stmtLegacyQr = $koneksi->prepare("SELECT id_unit_barang FROM unit_barang WHERE id_unit_barang = ? LIMIT 1");
        if ($stmtLegacyQr) {
            $stmtLegacyQr->bind_param('i', $legacyUnitId);
            $stmtLegacyQr->execute();
            $legacyResult = $stmtLegacyQr->get_result();
            if ($legacyResult && $legacyResult->num_rows > 0) {
                header('Location: scan_barang.php?unit_id=' . $legacyUnitId, true, 302);
                exit;
            }
        }
    }

    header('Location: scan_barang.php?unit_id=0', true, 302);
    exit;
}

require_auth_roles(['admin', 'petugas', 'user'], [
    'login_redirect' => 'login.php',
    'forbidden_redirect' => 'login.php',
]);

$currentUserRole = get_current_user_role();
$userMenuVisible = current_user_has_role('admin');
$inventoryManageVisible = current_user_has_role(['admin', 'petugas']);

$pageRoleMap = [
    'user' => ['admin'],
    'tambah_user' => ['admin'],
    'edit_user' => ['admin'],
    'tambah_produk' => ['admin', 'petugas'],
    'edit_produk' => ['admin', 'petugas'],
    'tambah_kategori' => ['admin', 'petugas'],
    'edit_kategori' => ['admin', 'petugas'],
    'tambah_gudang' => ['admin', 'petugas'],
    'edit_gudang' => ['admin', 'petugas'],
    'tambah_barang_masuk' => ['admin', 'petugas'],
    'tambah_barang_keluar' => ['admin', 'petugas'],
    'mutasi_barang' => ['admin', 'petugas', 'user'],
    'serah_terima' => ['admin', 'petugas', 'user'],
    'report_mutasi' => ['admin', 'petugas', 'user'],
    'report_persediaan_user' => ['admin', 'petugas', 'user'],
    'histori_log' => ['admin', 'petugas', 'user'],
];
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
    <meta name="csrf-token" content="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
</head>

<body class="app-shell">
<?php include 'components/sidebar.php'; ?>

<div class="app-content">
    <div class="page-header">
        <h1 class="page-title">Inventaris Dashboard</h1>
    </div>

    <main class="main-page p-4">
    <?php
        if (isset($_GET['page'])) {
            $page = $_GET['page'];
            $pageAccessDenied = isset($pageRoleMap[$page]) && !current_user_has_role($pageRoleMap[$page]);
            if ($pageAccessDenied) {
                echo "<center><h3>Anda tidak memiliki akses ke halaman ini.</h3></center>";
            } else {
            switch ($page) {
                case 'user':
                    include "pages/user.php";
                    break;
                case "tambah_user":
                    include "form/tambah_user.php";
                    break;
                case "edit_user":
                    include "form/edit_user.php";
                    break;
                case 'dashboard':
                    include "pages/dashboard.php";
                    break;
                case 'laporan':
                    include "pages/laporan.php";
                    break;
                case 'mutasi_barang':
                    include "pages/mutasi_barang.php";
                    break;
                case 'serah_terima':
                    include "pages/serah_terima.php";
                    break;
                case 'report_mutasi':
                    include "pages/report_mutasi.php";
                    break;
                case 'report_persediaan_user':
                    include "pages/report_persediaan_user.php";
                    break;
                case 'histori_log':
                    include "pages/histori_log.php";
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
                case 'print_mass_qr':
                    include "views/print_mass_qr.php";
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

<script>
(function () {
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (!meta) return;

    var token = meta.getAttribute('content') || '';
    if (!token) return;

    function ensureFormToken(form) {
        if (!form || !form.tagName || form.tagName.toLowerCase() !== 'form') return;
        var method = (form.getAttribute('method') || 'get').toLowerCase();
        if (method !== 'post') return;

        var input = form.querySelector('input[name="csrf_token"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'csrf_token';
            form.appendChild(input);
        }
        input.value = token;
    }

    document.querySelectorAll('form').forEach(ensureFormToken);
    document.addEventListener('submit', function (ev) {
        ensureFormToken(ev.target);
    }, true);

    if (window.fetch) {
        var originalFetch = window.fetch.bind(window);
        window.fetch = function (input, init) {
            init = init || {};
            var method = String(init.method || 'GET').toUpperCase();
            if (method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS') {
                var headers = new Headers(init.headers || {});
                if (!headers.get('X-CSRF-Token')) {
                    headers.set('X-CSRF-Token', token);
                }
                init.headers = headers;
            }
            return originalFetch(input, init);
        };
    }

    var originalOpen = XMLHttpRequest.prototype.open;
    var originalSend = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function (method) {
        this.__inventarisMethod = String(method || 'GET').toUpperCase();
        return originalOpen.apply(this, arguments);
    };
    XMLHttpRequest.prototype.send = function () {
        if (this.__inventarisMethod !== 'GET' && this.__inventarisMethod !== 'HEAD' && this.__inventarisMethod !== 'OPTIONS') {
            try {
                this.setRequestHeader('X-CSRF-Token', token);
            } catch (e) {}
        }
        return originalSend.apply(this, arguments);
    };
})();
</script>

<!-- js -->
<script src="assets/js/delete.js"></script>
<script src="assets/js/delete_kategori.js"></script>
<script src="assets/js/delete_gudang.js"></script>
<script src="assets/js/delete_user.js"></script>
</body>
</html>
