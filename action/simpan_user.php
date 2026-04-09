<?php
include '../koneksi/koneksi.php';
require_auth_roles(['admin'], [
    'response' => 'page',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=dashboard',
]);

$nama = trim((string) ($_POST['nama'] ?? ''));
$username = trim((string) ($_POST['username'] ?? ''));
$passwordRaw = (string) ($_POST['password'] ?? '');
$role = normalize_user_role($_POST['role'] ?? null);
$status = normalize_user_status($_POST['status'] ?? 'aktif');
$kategoriUser = normalize_user_category($_POST['kategori_user'] ?? 'umum');
$bidangId = nullable_int_id($_POST['bidang_id'] ?? null);

if ($nama === '' || $username === '' || $passwordRaw === '') {
    echo "Data user wajib diisi lengkap.";
    exit;
}

if ($kategoriUser === 'staff' && $bidangId === null) {
    echo "Bidang wajib dipilih untuk kategori staff.";
    exit;
}

if ($kategoriUser !== 'staff') {
    $bidangId = null;
}

if ($bidangId !== null && !inventory_bidang_exists($koneksi, $bidangId)) {
    echo "Bidang yang dipilih tidak valid.";
    exit;
}

$password = hash_inventory_password($passwordRaw);

$duplicateSql = "SELECT id_user FROM user WHERE username = ?";
$duplicateSql .= " LIMIT 1";

$stmtCheck = $koneksi->prepare($duplicateSql);
$stmtCheck->bind_param('s', $username);
$stmtCheck->execute();
$resultCheckUsername = $stmtCheck->get_result();

// Cek apakah username sudah ada
if ($resultCheckUsername->num_rows > 0) {
    // Jika username sudah ada, tampilkan SweetAlert dan berhenti
    echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Username sudah terdaftar!',
                text: 'Silakan gunakan username lain.',
                showConfirmButton: true
            }).then(() => {
                window.history.back(); // Kembali ke form sebelumnya
            });
          </script>";
    exit;
}

// Query untuk menyimpan data user ke database
$stmtInsert = $koneksi->prepare("INSERT INTO user (nama, username, password, role, status, kategori_user, bidang_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
$stmtInsert->bind_param('ssssssi', $nama, $username, $password, $role, $status, $kategoriUser, $bidangId);

if ($stmtInsert->execute()) {
    $newUserId = intval($koneksi->insert_id);
    log_activity($koneksi, [
        'id_user' => current_user_id(),
        'role_user' => get_current_user_role(),
        'action_name' => 'user_create',
        'entity_type' => 'user',
        'entity_id' => $newUserId,
        'entity_label' => $nama . ' (' . $username . ')',
        'description' => 'Menambahkan user internal baru.',
        'metadata_json' => [
            'role' => $role,
            'status' => $status,
            'kategori_user' => $kategoriUser,
            'bidang_id' => $bidangId,
            'bidang_nama' => $bidangId !== null ? get_bidang_name_by_id($koneksi, $bidangId) : null,
        ],
    ]);
    header('Location: ../index.php?page=user');
    exit;
} else {
    echo "Error: " . $koneksi->error;
}
?>
