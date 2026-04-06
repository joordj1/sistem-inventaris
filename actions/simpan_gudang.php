<?php
include '../koneksi/koneksi.php'; 
require_auth_roles(['admin', 'petugas'], [
    'response' => 'page',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=data_gudang',
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $namaGudang = trim((string) ($_POST['namaGudang'] ?? ''));
    $lokasiGudang = trim((string) ($_POST['lokasiGudang'] ?? ''));

    $stmt = $koneksi->prepare("INSERT INTO Gudang (nama_gudang, lokasi) VALUES (?, ?)");
    $stmt->bind_param('ss', $namaGudang, $lokasiGudang);

    if ($stmt->execute()) {
        $idGudang = $stmt->insert_id;
        log_activity($koneksi, [
            'id_user' => $_SESSION['id_user'] ?? null,
            'role_user' => get_current_user_role(),
            'action_name' => 'gudang_tambah',
            'entity_type' => 'gudang',
            'entity_id' => $idGudang,
            'entity_label' => $namaGudang,
            'description' => 'Menambahkan gudang baru',
            'id_gudang' => $idGudang,
            'metadata_json' => [
                'nama_gudang' => $namaGudang,
                'lokasi' => $lokasiGudang,
            ],
        ]);
        header('Location: ../index.php?page=data_gudang');
        exit;
    } else {
        echo "Error: " . $koneksi->error;
    }
}
?>
