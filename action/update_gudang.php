<?php
include '../koneksi/koneksi.php';
require_auth_roles(['admin', 'petugas'], [
    'response' => 'page',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=data_gudang',
]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Location: ../index.php?page=data_gudang');
    exit;
}

// Mendapatkan data dari form
$id_gudang = intval($_POST['id_gudang'] ?? 0);
$nama_gudang = trim((string) ($_POST['nama_gudang'] ?? ''));
$lokasi = trim((string) ($_POST['lokasi'] ?? ''));

if ($id_gudang < 1 || $nama_gudang === '') {
    header('Location: ../index.php?page=data_gudang&status=error');
    exit;
}

$beforeResult = $koneksi->query('SELECT nama_gudang, lokasi FROM Gudang WHERE id_gudang = ' . $id_gudang);
$before = $beforeResult ? $beforeResult->fetch_assoc() : null;
if (!$before) {
    header('Location: ../index.php?page=data_gudang&status=error');
    exit;
}

$stmt = $koneksi->prepare("UPDATE gudang SET nama_gudang = ?, lokasi = ? WHERE id_gudang = ?");
$stmt->bind_param('ssi', $nama_gudang, $lokasi, $id_gudang);

if ($stmt->execute()) {
    log_activity($koneksi, [
        'id_user' => $_SESSION['id_user'] ?? null,
        'role_user' => get_current_user_role(),
        'action_name' => 'gudang_edit',
        'entity_type' => 'gudang',
        'entity_id' => $id_gudang,
        'entity_label' => $nama_gudang,
        'description' => 'Memperbarui detail gudang',
        'id_gudang' => $id_gudang,
        'metadata_json' => [
            'before' => $before,
            'after' => [
                'nama_gudang' => $nama_gudang,
                'lokasi' => $lokasi,
            ],
        ],
    ]);
    header("Location: ../index.php?page=data_gudang"); // Redirect ke halaman data gudang setelah update
    exit;
} else {
    log_event('ERROR', 'GUDANG', 'update_gudang gagal id=' . $id_gudang . ' - ' . $stmt->error);
    header('Location: ../index.php?page=data_gudang&status=error');
    exit;
}
?>
