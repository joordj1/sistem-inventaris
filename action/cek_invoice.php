<?php
require '../koneksi/koneksi.php';
require_auth_roles(['admin', 'petugas', 'user'], [
    'response' => 'json',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=dashboard',
]);

$no_invoice = trim((string) ($_GET['no_invoice'] ?? ''));
$stmt = $koneksi->prepare("SELECT id_transaksi FROM stoktransaksi WHERE no_invoice = ? LIMIT 1");
$stmt->bind_param('s', $no_invoice);
$stmt->execute();
$result = $stmt->get_result();
echo json_encode(['exists' => $result && $result->num_rows > 0]);
?>
