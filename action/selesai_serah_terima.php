<?php
include '../koneksi/koneksi.php';
require_once __DIR__ . '/simpan_histori_log.php';

require_auth_roles(['admin', 'petugas'], [
    'response' => 'page',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=serah_terima',
]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?page=serah_terima');
    exit;
}

$serahTerimaId = intval($_POST['id'] ?? 0);
$catatanKembali = trim((string) ($_POST['catatan_kembali'] ?? ''));
$operatorId = current_user_id();
$operatorName = get_current_user_name($koneksi) ?? 'System';

if ($serahTerimaId < 1) {
    header('Location: ../index.php?page=serah_terima&error=ID serah terima tidak valid.');
    exit;
}

$headerStmt = $koneksi->prepare("SELECT * FROM serah_terima_barang WHERE id = ? LIMIT 1");
if (!$headerStmt) {
    header('Location: ../index.php?page=serah_terima&error=Gagal memuat data serah terima.');
    exit;
}
$headerStmt->bind_param('i', $serahTerimaId);
$headerStmt->execute();
$headerResult = $headerStmt->get_result();
$header = $headerResult ? $headerResult->fetch_assoc() : null;

if (!$header) {
    header('Location: ../index.php?page=serah_terima&error=Data serah terima tidak ditemukan.');
    exit;
}

if (($header['status'] ?? '') !== 'aktif') {
    header('Location: ../index.php?page=serah_terima&view=detail&id=' . $serahTerimaId . '&error=Hanya serah terima aktif yang dapat diselesaikan.');
    exit;
}

$details = fetch_serah_terima_detail_rows($koneksi, $serahTerimaId);
if (empty($details)) {
    header('Location: ../index.php?page=serah_terima&view=detail&id=' . $serahTerimaId . '&error=Detail serah terima tidak ditemukan.');
    exit;
}

$gudangAsalId = isset($header['gudang_asal_id']) ? intval($header['gudang_asal_id']) : null;
ensure_histori_log_table($koneksi);
$koneksi->begin_transaction();

try {
    foreach ($details as $detail) {
        $kondisiKembali = trim((string) ($_POST['kondisi_kembali'][$detail['id']] ?? $detail['kondisi_serah'] ?? 'baik'));
        $detailId = intval($detail['id']);
        $produkId = intval($detail['produk_id']);
        $unitBarangId = isset($detail['unit_barang_id']) ? intval($detail['unit_barang_id']) : null;
        $qty = intval($detail['qty'] ?? 0);

        $updateDetailStmt = $koneksi->prepare(
            "UPDATE serah_terima_detail
             SET kondisi_kembali = ?, tanggal_kembali = NOW()
             WHERE id = ?"
        );
        if (!$updateDetailStmt) {
            throw new Exception('Gagal menyiapkan update detail pengembalian.');
        }
        $updateDetailStmt->bind_param('si', $kondisiKembali, $detailId);
        if (!$updateDetailStmt->execute()) {
            throw new Exception('Detail pengembalian gagal diperbarui.');
        }

        if ($unitBarangId !== null && $unitBarangId > 0) {
            $unit = get_unit_barang_by_id($koneksi, $unitBarangId);
            if ($unit) {
                $updateUnitStmt = $koneksi->prepare(
                    "UPDATE unit_barang
                     SET id_gudang = ?, id_user = NULL, lokasi_custom = NULL, status = ?, tersedia = 1, kondisi = ?, updated_at = NOW()
                     WHERE id_unit_barang = ?"
                );
                if (!$updateUnitStmt) {
                    throw new Exception('Gagal menyiapkan update unit kembali.');
                }
                $statusTersedia = map_asset_unit_status_for_storage('tersedia');
                $updateUnitStmt->bind_param('issi', $gudangAsalId, $statusTersedia, $kondisiKembali, $unitBarangId);
                if (!$updateUnitStmt->execute()) {
                    throw new Exception('Unit asset gagal dikembalikan ke gudang.');
                }

                log_tracking_unit_barang($koneksi, [
                    'id_unit' => $unitBarangId,
                    'id_unit_barang' => $unitBarangId,
                    'id_produk' => $produkId,
                    'activity_type' => 'kembali',
                    'status_sebelum' => $unit['status'] ?? null,
                    'status_sesudah' => $statusTersedia,
                    'kondisi_sebelum' => $unit['kondisi'] ?? null,
                    'kondisi_sesudah' => $kondisiKembali,
                    'lokasi_sebelum' => $header['lokasi_tujuan'] ?? ($header['pihak_penerima_nama'] ?? null),
                    'lokasi_sesudah' => $gudangAsalId ? get_gudang_name_by_id($koneksi, $gudangAsalId) : null,
                    'id_user_sebelum' => $unit['id_user'] ?? null,
                    'id_user_sesudah' => null,
                    'id_user_terkait' => $header['pihak_penerima_user_id'] ?? null,
                    'note' => 'Pengembalian dari serah terima ' . ($header['kode_serah_terima'] ?? ('#' . $serahTerimaId)),
                    'id_user_changed' => $operatorId,
                ]);

                sync_foundation_barang_from_units($koneksi, $produkId, [
                    'activity_type' => 'kembali',
                    'actor_user_id' => $operatorId,
                    'note' => 'Pengembalian dari serah terima ' . ($header['kode_serah_terima'] ?? ('#' . $serahTerimaId)),
                ]);
            }
        } else {
            $updateProdukStmt = $koneksi->prepare(
                "UPDATE produk SET jumlah_stok = jumlah_stok + ?, last_tracked_at = NOW() WHERE id_produk = ?"
            );
            if ($updateProdukStmt) {
                $updateProdukStmt->bind_param('ii', $qty, $produkId);
                $updateProdukStmt->execute();
            }

            if ($gudangAsalId && !upsert_stok_gudang_quantity($koneksi, $gudangAsalId, $produkId, $qty)) {
                throw new Exception('Stok gudang asal gagal dikembalikan untuk consumable.');
            }
        }

        save_official_histori_log_entry($koneksi, [
            'ref_type' => 'handover',
            'ref_id' => $serahTerimaId,
            'event_type' => 'handover_detail_dikembalikan',
            'produk_id' => $produkId,
            'unit_barang_id' => $unitBarangId,
            'gudang_id' => $gudangAsalId,
            'user_id' => $operatorId,
            'user_name_snapshot' => $operatorName,
            'target_user_id' => $header['pihak_penerima_user_id'] ?? null,
            'target_user_name_snapshot' => $header['pihak_penerima_nama'] ?? null,
            'deskripsi' => 'Barang pada serah terima ' . ($header['kode_serah_terima'] ?? ('#' . $serahTerimaId)) . ' dikembalikan.',
            'meta_json' => [
                'detail_id' => $detailId,
                'kondisi_kembali' => $kondisiKembali,
                'catatan_kembali' => $catatanKembali,
            ],
        ]);
    }

    $updateHeaderStmt = $koneksi->prepare(
        "UPDATE serah_terima_barang
         SET status = 'dikembalikan', catatan = CONCAT(COALESCE(catatan, ''), ?)
         WHERE id = ?"
    );
    if (!$updateHeaderStmt) {
        throw new Exception('Gagal menyiapkan update header serah terima.');
    }

    $catatanAppend = $catatanKembali !== '' ? "\n[Pengembalian] " . $catatanKembali : '';
    $updateHeaderStmt->bind_param('si', $catatanAppend, $serahTerimaId);
    if (!$updateHeaderStmt->execute()) {
        throw new Exception('Status serah terima gagal diperbarui.');
    }

    save_official_histori_log_entry($koneksi, [
        'ref_type' => 'handover',
        'ref_id' => $serahTerimaId,
        'event_type' => 'handover_dikembalikan',
        'gudang_id' => $gudangAsalId,
        'user_id' => $operatorId,
        'user_name_snapshot' => $operatorName,
        'target_user_id' => $header['pihak_penerima_user_id'] ?? null,
        'target_user_name_snapshot' => $header['pihak_penerima_nama'] ?? null,
        'deskripsi' => 'Serah terima ' . ($header['kode_serah_terima'] ?? ('#' . $serahTerimaId)) . ' telah dikembalikan.',
        'meta_json' => [
            'catatan_kembali' => $catatanKembali,
        ],
    ]);

    log_activity($koneksi, [
        'id_user' => $operatorId,
        'role_user' => get_current_user_role(),
        'action_name' => 'serah_terima_selesai',
        'entity_type' => 'handover',
        'entity_id' => $serahTerimaId,
        'entity_label' => $header['kode_serah_terima'] ?? ('Serah Terima #' . $serahTerimaId),
        'description' => 'Menyelesaikan dan mengembalikan serah terima barang',
        'id_gudang' => $gudangAsalId,
        'actor_name_snapshot' => $operatorName,
        'metadata_json' => [
            'catatan_kembali' => $catatanKembali,
        ],
    ]);

    $koneksi->commit();
    header('Location: ../index.php?page=serah_terima&view=detail&id=' . $serahTerimaId . '&success=Serah terima berhasil diselesaikan.');
    exit;
} catch (Exception $e) {
    $koneksi->rollback();
    header('Location: ../index.php?page=serah_terima&view=detail&id=' . $serahTerimaId . '&error=' . urlencode($e->getMessage()));
    exit;
}
