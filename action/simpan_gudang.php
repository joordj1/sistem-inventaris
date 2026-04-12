<?php
include '../koneksi/koneksi.php'; 
require_auth_roles(['admin', 'petugas'], [
    'response' => 'page',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=data_gudang',
]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Location: ../index.php?page=tambah_gudang');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $namaGudang = trim((string) ($_POST['namaGudang'] ?? ''));
    $lokasiGudang = trim((string) ($_POST['lokasiGudang'] ?? ''));

    if ($namaGudang === '') {
        header('Location: ../index.php?page=tambah_gudang&error=Nama+gudang+wajib+diisi');
        exit;
    }

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
        log_event('ERROR', 'GUDANG', 'simpan_gudang gagal nama=' . $namaGudang . ' - ' . $stmt->error);
        header('Location: ../index.php?page=tambah_gudang&error=Gagal+menyimpan+gudang');
        exit;
    }
}
?>
