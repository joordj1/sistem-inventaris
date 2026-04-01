<?php
require '../koneksi/koneksi.php';

$no_invoice = $_GET['no_invoice'];
$result = $koneksi->query("SELECT * FROM stoktransaksi WHERE no_invoice = '$no_invoice'");
echo json_encode(['exists' => $result->num_rows > 0]);
?>
