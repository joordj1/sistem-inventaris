<?php
include '../koneksi/koneksi.php';

require_auth_roles(['admin', 'petugas'], [
    'response' => 'page',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=mutasi_barang',
]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?page=mutasi_barang');
    exit;
}

$mutasiId = intval($_POST['id'] ?? 0);
$statusBaru = trim((string) ($_POST['status'] ?? ''));
$catatan = trim((string) ($_POST['catatan_status'] ?? ''));
$allowedStatus = ['draft', 'disetujui', 'selesai', 'dibatalkan'];

if ($mutasiId < 1 || !in_array($statusBaru, $allowedStatus, true)) {
    header('Location: ../index.php?page=mutasi_barang&error=Permintaan update status tidak valid.');
    exit;
}

$stmt = $koneksi->prepare("SELECT * FROM mutasi_barang WHERE id = ? LIMIT 1");
if (!$stmt) {
    header('Location: ../index.php?page=mutasi_barang&error=Gagal memuat data mutasi.');
    exit;
}
$stmt->bind_param('i', $mutasiId);
$stmt->execute();
$result = $stmt->get_result();
$mutasi = $result ? $result->fetch_assoc() : null;

if (!$mutasi) {
    header('Location: ../index.php?page=mutasi_barang&error=Data mutasi tidak ditemukan.');
    exit;
}

if (($mutasi['status'] ?? '') === 'selesai' && $statusBaru !== 'selesai') {
    header('Location: ../index.php?page=mutasi_barang&view=detail&id=' . $mutasiId . '&error=Mutasi yang sudah selesai tidak boleh diubah kembali.');
    exit;
}

$operatorId = current_user_id();
$operatorName = get_current_user_name($koneksi) ?? 'System';
$approvedBy = in_array($statusBaru, ['disetujui', 'selesai'], true) ? $operatorId : null;
$approvedByName = in_array($statusBaru, ['disetujui', 'selesai'], true) ? $operatorName : null;

$updateStmt = $koneksi->prepare(
    "UPDATE mutasi_barang
     SET status = ?, approved_by = ?, approved_by_name = ?, updated_at = NOW()
     WHERE id = ?"
);

if (!$updateStmt) {
    header('Location: ../index.php?page=mutasi_barang&error=Gagal menyiapkan update status mutasi.');
    exit;
}

$updateStmt->bind_param('sisi', $statusBaru, $approvedBy, $approvedByName, $mutasiId);
if (!$updateStmt->execute()) {
    header('Location: ../index.php?page=mutasi_barang&view=detail&id=' . $mutasiId . '&error=Status mutasi gagal diperbarui.');
    exit;
}

save_histori_log_entry($koneksi, [
    'ref_type' => 'mutasi',
    'ref_id' => $mutasiId,
    'event_type' => 'status_' . $statusBaru,
    'gudang_id' => $mutasi['gudang_tujuan_id'] ?? null,
    'user_id' => $operatorId,
    'user_name_snapshot' => $operatorName,
    'deskripsi' => 'Status mutasi ' . ($mutasi['kode_mutasi'] ?? ('#' . $mutasiId)) . ' diubah menjadi ' . $statusBaru,
    'meta_json' => [
        'status_sebelum' => $mutasi['status'] ?? null,
        'status_sesudah' => $statusBaru,
        'catatan_status' => $catatan,
    ],
]);

log_activity($koneksi, [
    'id_user' => $operatorId,
    'role_user' => get_current_user_role(),
    'action_name' => 'mutasi_status_update',
    'entity_type' => 'mutasi',
    'entity_id' => $mutasiId,
    'entity_label' => $mutasi['kode_mutasi'] ?? ('Mutasi #' . $mutasiId),
    'description' => 'Memperbarui status mutasi menjadi ' . $statusBaru,
    'id_gudang' => $mutasi['gudang_tujuan_id'] ?? null,
    'actor_name_snapshot' => $operatorName,
    'metadata_json' => [
        'status_sebelum' => $mutasi['status'] ?? null,
        'status_sesudah' => $statusBaru,
        'catatan_status' => $catatan,
    ],
]);

header('Location: ../index.php?page=mutasi_barang&view=detail&id=' . $mutasiId . '&success=Status mutasi berhasil diperbarui.');
exit;
