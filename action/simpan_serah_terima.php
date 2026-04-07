<?php
include '../koneksi/koneksi.php';

require_auth_roles(['admin', 'petugas'], [
    'response' => 'page',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=serah_terima',
]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?page=serah_terima');
    exit;
}

if (!schema_table_exists_now($koneksi, 'serah_terima_barang') || !schema_table_exists_now($koneksi, 'serah_terima_detail')) {
    header('Location: ../index.php?page=serah_terima&error=Skema serah terima belum tersedia. Jalankan migration priority 2 terlebih dahulu.');
    exit;
}

$tanggalSerahTerima = str_replace('T', ' ', trim((string) ($_POST['tanggal_serah_terima'] ?? date('Y-m-d H:i:s'))));
$jenisTujuan = trim((string) ($_POST['jenis_tujuan'] ?? 'user'));
$gudangAsalId = isset($_POST['gudang_asal_id']) && $_POST['gudang_asal_id'] !== '' ? intval($_POST['gudang_asal_id']) : null;
$pihakPenyerahUserId = isset($_POST['pihak_penyerah_user_id']) && $_POST['pihak_penyerah_user_id'] !== '' ? intval($_POST['pihak_penyerah_user_id']) : null;
$pihakPenerimaUserId = isset($_POST['pihak_penerima_user_id']) && $_POST['pihak_penerima_user_id'] !== '' ? intval($_POST['pihak_penerima_user_id']) : null;
$pihakPenyerahNama = trim((string) ($_POST['pihak_penyerah_nama'] ?? ''));
$pihakPenerimaNama = trim((string) ($_POST['pihak_penerima_nama'] ?? ''));
$lokasiTujuan = trim((string) ($_POST['lokasi_tujuan'] ?? ''));
$catatan = trim((string) ($_POST['catatan'] ?? ''));
$createdBy = current_user_id();
$createdByName = get_current_user_name($koneksi) ?? 'System';

if (!in_array($jenisTujuan, ['user', 'lokasi', 'departemen'], true)) {
    header('Location: ../index.php?page=serah_terima&action=form&error=Jenis tujuan serah terima tidak valid.');
    exit;
}

if ($gudangAsalId === null || !asset_unit_gudang_exists($koneksi, $gudangAsalId)) {
    header('Location: ../index.php?page=serah_terima&action=form&error=Gudang asal wajib dipilih.');
    exit;
}

if ($pihakPenyerahNama === '' && $pihakPenyerahUserId) {
    $pihakPenyerahNama = get_user_name_by_id($koneksi, $pihakPenyerahUserId) ?? '';
}

if ($pihakPenerimaNama === '' && $pihakPenerimaUserId) {
    $pihakPenerimaNama = get_user_name_by_id($koneksi, $pihakPenerimaUserId) ?? '';
}

if ($pihakPenyerahNama === '' || $pihakPenerimaNama === '') {
    header('Location: ../index.php?page=serah_terima&action=form&error=Nama penyerah dan penerima wajib diisi.');
    exit;
}

try {
    $dokumenFile = store_uploaded_inventory_document('dokumen_serah_terima', 'uploads/dokumen_serah_terima', 'STB');
} catch (Exception $e) {
    header('Location: ../index.php?page=serah_terima&action=form&error=' . urlencode($e->getMessage()));
    exit;
}

$consumableItems = [];
$produkIds = $_POST['consumable_produk_id'] ?? [];
$qtyRows = $_POST['consumable_qty'] ?? [];
$conditions = $_POST['consumable_kondisi_serah'] ?? [];
$detailNotes = $_POST['consumable_catatan_detail'] ?? [];

if (is_array($produkIds)) {
    foreach ($produkIds as $index => $produkIdRaw) {
        $produkId = intval($produkIdRaw);
        $qty = intval($qtyRows[$index] ?? 0);
        if ($produkId < 1 || $qty <= 0) {
            continue;
        }

        $consumableItems[] = [
            'produk_id' => $produkId,
            'qty' => $qty,
            'kondisi_serah' => trim((string) ($conditions[$index] ?? 'baik')),
            'catatan_detail' => trim((string) ($detailNotes[$index] ?? '')),
        ];
    }
}

$assetItems = [];
$selectedUnits = $_POST['asset_unit_barang_id'] ?? [];
$assetConditions = $_POST['asset_kondisi_serah'] ?? [];
$assetNotes = $_POST['asset_catatan_detail'] ?? [];

if (is_array($selectedUnits)) {
    foreach ($selectedUnits as $unitIdRaw) {
        $unitId = intval($unitIdRaw);
        if ($unitId < 1) {
            continue;
        }

        $assetItems[] = [
            'unit_barang_id' => $unitId,
            'kondisi_serah' => trim((string) ($assetConditions[$unitId] ?? 'baik')),
            'catatan_detail' => trim((string) ($assetNotes[$unitId] ?? '')),
        ];
    }
}

if (empty($consumableItems) && empty($assetItems)) {
    header('Location: ../index.php?page=serah_terima&action=form&error=Pilih minimal satu barang atau unit untuk diserahterimakan.');
    exit;
}

$kodeSerahTerima = generate_reference_code($koneksi, 'serah_terima_barang', 'kode_serah_terima', 'BAST');
if ($kodeSerahTerima === null) {
    header('Location: ../index.php?page=serah_terima&error=Kode serah terima gagal dibuat.');
    exit;
}

$koneksi->begin_transaction();

try {
    $headerStmt = $koneksi->prepare(
        "INSERT INTO serah_terima_barang (
            kode_serah_terima, tanggal_serah_terima, jenis_tujuan, pihak_penyerah_user_id, pihak_penyerah_nama,
            pihak_penerima_user_id, pihak_penerima_nama, gudang_asal_id, lokasi_tujuan, catatan, dokumen_file,
            status, created_by, created_by_name, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'aktif', ?, ?, NOW())"
    );

    if (!$headerStmt) {
        throw new Exception('Gagal menyiapkan header serah terima.');
    }

    $lokasiTujuanValue = $lokasiTujuan !== '' ? $lokasiTujuan : null;
    $headerStmt->bind_param(
        'sssisiissssis',
        $kodeSerahTerima,
        $tanggalSerahTerima,
        $jenisTujuan,
        $pihakPenyerahUserId,
        $pihakPenyerahNama,
        $pihakPenerimaUserId,
        $pihakPenerimaNama,
        $gudangAsalId,
        $lokasiTujuanValue,
        $catatan,
        $dokumenFile,
        $createdBy,
        $createdByName
    );

    if (!$headerStmt->execute()) {
        throw new Exception('Header serah terima gagal disimpan.');
    }

    $serahTerimaId = intval($koneksi->insert_id);

    $detailStmt = $koneksi->prepare(
        "INSERT INTO serah_terima_detail (
            serah_terima_id, produk_id, unit_barang_id, qty, kondisi_serah, kondisi_kembali, tanggal_kembali, catatan_detail
        ) VALUES (?, ?, ?, ?, ?, NULL, NULL, ?)"
    );

    if (!$detailStmt) {
        throw new Exception('Gagal menyiapkan detail serah terima.');
    }

    foreach ($consumableItems as $item) {
        $produk = get_produk_by_id($koneksi, $item['produk_id']);
        if (!$produk || ($produk['tipe_barang'] ?? 'consumable') !== 'consumable') {
            throw new Exception('Produk consumable tidak valid untuk serah terima.');
        }

        $stokAsal = get_stok_gudang_qty($koneksi, $gudangAsalId, $item['produk_id']);
        if ($stokAsal < $item['qty']) {
            throw new Exception('Stok gudang asal tidak cukup untuk produk ' . ($produk['nama_produk'] ?? ('#' . $item['produk_id'])));
        }

        $updateProduk = $koneksi->prepare("UPDATE produk SET jumlah_stok = GREATEST(jumlah_stok - ?, 0), last_tracked_at = NOW() WHERE id_produk = ?");
        if ($updateProduk) {
            $updateProduk->bind_param('ii', $item['qty'], $item['produk_id']);
            $updateProduk->execute();
        }

        if (!upsert_stok_gudang_quantity($koneksi, $gudangAsalId, $item['produk_id'], -$item['qty'])) {
            throw new Exception('Gagal mengurangi stok gudang asal untuk handover consumable.');
        }

        $unitBarangId = null;
        $detailStmt->bind_param(
            'iiiiss',
            $serahTerimaId,
            $item['produk_id'],
            $unitBarangId,
            $item['qty'],
            $item['kondisi_serah'],
            $item['catatan_detail']
        );
        if (!$detailStmt->execute()) {
            throw new Exception('Detail consumable serah terima gagal disimpan.');
        }

        save_histori_log_entry($koneksi, [
            'ref_type' => 'handover',
            'ref_id' => $serahTerimaId,
            'event_type' => 'handover_consumable_aktif',
            'produk_id' => $item['produk_id'],
            'gudang_id' => $gudangAsalId,
            'user_id' => $createdBy,
            'user_name_snapshot' => $createdByName,
            'target_user_id' => $pihakPenerimaUserId,
            'target_user_name_snapshot' => $pihakPenerimaNama,
            'deskripsi' => 'Serah terima consumable ' . ($produk['nama_produk'] ?? ('Produk #' . $item['produk_id'])) . ' qty ' . intval($item['qty']),
            'meta_json' => [
                'kode_serah_terima' => $kodeSerahTerima,
                'jenis_tujuan' => $jenisTujuan,
                'lokasi_tujuan' => $lokasiTujuanValue,
                'catatan_detail' => $item['catatan_detail'],
            ],
        ]);
    }

    foreach ($assetItems as $item) {
        $unit = get_unit_barang_by_id($koneksi, $item['unit_barang_id']);
        if (!$unit) {
            throw new Exception('Unit asset tidak ditemukan.');
        }

        $produk = get_produk_by_id($koneksi, $unit['id_produk']);
        if (!$produk || ($produk['tipe_barang'] ?? 'consumable') !== 'asset') {
            throw new Exception('Unit yang dipilih bukan asset valid.');
        }

        if (intval($unit['id_gudang'] ?? 0) !== $gudangAsalId) {
            throw new Exception('Unit asset ' . ($unit['serial_number'] ?? ('#' . $item['unit_barang_id'])) . ' tidak berada di gudang asal.');
        }

        if (!empty($unit['id_user'])) {
            throw new Exception('Unit asset ' . ($unit['serial_number'] ?? ('#' . $item['unit_barang_id'])) . ' masih terhubung ke user lain.');
        }

        $statusSaatIni = normalize_asset_unit_status($unit['status'] ?? null);
        if ($statusSaatIni !== 'tersedia') {
            throw new Exception('Unit asset ' . ($unit['serial_number'] ?? ('#' . $item['unit_barang_id'])) . ' tidak dalam status tersedia.');
        }

        $updateStmt = $koneksi->prepare(
            "UPDATE unit_barang
             SET id_gudang = NULL,
                 id_user = ?,
                 lokasi_custom = ?,
                 status = ?,
                 tersedia = 0,
                 kondisi = ?,
                 updated_at = NOW()
             WHERE id_unit_barang = ?"
        );
        if (!$updateStmt) {
            throw new Exception('Gagal menyiapkan update unit untuk serah terima.');
        }

        $lokasiCustom = $jenisTujuan === 'user' ? null : $lokasiTujuanValue;
        $statusPinjam = map_asset_unit_status_for_storage('dipakai');
        $updateStmt->bind_param(
            'isssi',
            $pihakPenerimaUserId,
            $lokasiCustom,
            $statusPinjam,
            $item['kondisi_serah'],
            $item['unit_barang_id']
        );
        if (!$updateStmt->execute()) {
            throw new Exception('Unit asset gagal diproses ke serah terima.');
        }

        $qtyUnit = 1;
        $detailStmt->bind_param(
            'iiiiss',
            $serahTerimaId,
            $unit['id_produk'],
            $item['unit_barang_id'],
            $qtyUnit,
            $item['kondisi_serah'],
            $item['catatan_detail']
        );
        if (!$detailStmt->execute()) {
            throw new Exception('Detail asset serah terima gagal disimpan.');
        }

        log_riwayat_unit_barang($koneksi, [
            'id_unit_barang' => $item['unit_barang_id'],
            'id_produk' => $unit['id_produk'],
            'activity_type' => 'pinjam',
            'status_sebelum' => $unit['status'],
            'status_sesudah' => $statusPinjam,
            'kondisi_sebelum' => $unit['kondisi'],
            'kondisi_sesudah' => $item['kondisi_serah'],
            'lokasi_sebelum' => get_gudang_name_by_id($koneksi, $gudangAsalId),
            'lokasi_sesudah' => $lokasiCustom ?? $pihakPenerimaNama,
            'id_user_sebelum' => $unit['id_user'] ?? null,
            'id_user_sesudah' => $pihakPenerimaUserId,
            'id_user_terkait' => $pihakPenerimaUserId,
            'note' => 'Serah terima resmi ' . $kodeSerahTerima,
            'id_user_changed' => $createdBy,
        ]);

        save_histori_log_entry($koneksi, [
            'ref_type' => 'handover',
            'ref_id' => $serahTerimaId,
            'event_type' => 'handover_asset_aktif',
            'produk_id' => $unit['id_produk'],
            'unit_barang_id' => $item['unit_barang_id'],
            'user_id' => $createdBy,
            'user_name_snapshot' => $createdByName,
            'target_user_id' => $pihakPenerimaUserId,
            'target_user_name_snapshot' => $pihakPenerimaNama,
            'deskripsi' => 'Serah terima asset ' . ($produk['nama_produk'] ?? 'Asset') . ' unit ' . ($unit['serial_number'] ?? ('#' . $item['unit_barang_id'])),
            'meta_json' => [
                'kode_serah_terima' => $kodeSerahTerima,
                'jenis_tujuan' => $jenisTujuan,
                'lokasi_tujuan' => $lokasiTujuanValue,
                'catatan_detail' => $item['catatan_detail'],
            ],
        ]);
    }

    save_histori_log_entry($koneksi, [
        'ref_type' => 'handover',
        'ref_id' => $serahTerimaId,
        'event_type' => 'handover_dibuat',
        'gudang_id' => $gudangAsalId,
        'user_id' => $createdBy,
        'user_name_snapshot' => $createdByName,
        'target_user_id' => $pihakPenerimaUserId,
        'target_user_name_snapshot' => $pihakPenerimaNama,
        'deskripsi' => 'Serah terima resmi ' . $kodeSerahTerima . ' dibuat.',
        'meta_json' => [
            'jenis_tujuan' => $jenisTujuan,
            'lokasi_tujuan' => $lokasiTujuanValue,
            'dokumen_file' => $dokumenFile,
        ],
    ]);

    if ($dokumenFile && schema_table_exists_now($koneksi, 'dokumen_transaksi')) {
        $dokumenStmt = $koneksi->prepare(
            "INSERT INTO dokumen_transaksi (ref_type, ref_id, jenis_dokumen, file_path, uploaded_by, uploaded_by_name, created_at)
             VALUES ('handover', ?, 'dokumen_serah_terima', ?, ?, ?, NOW())"
        );
        if ($dokumenStmt) {
            $dokumenStmt->bind_param('isis', $serahTerimaId, $dokumenFile, $createdBy, $createdByName);
            $dokumenStmt->execute();
        }
    }

    log_activity($koneksi, [
        'id_user' => $createdBy,
        'role_user' => get_current_user_role(),
        'action_name' => 'serah_terima_barang',
        'entity_type' => 'handover',
        'entity_id' => $serahTerimaId,
        'entity_label' => $kodeSerahTerima,
        'description' => 'Mencatat serah terima barang formal',
        'id_gudang' => $gudangAsalId,
        'actor_name_snapshot' => $createdByName,
        'metadata_json' => [
            'jenis_tujuan' => $jenisTujuan,
            'pihak_penyerah_nama' => $pihakPenyerahNama,
            'pihak_penerima_nama' => $pihakPenerimaNama,
            'lokasi_tujuan' => $lokasiTujuanValue,
            'dokumen_file' => $dokumenFile,
        ],
    ]);

    $koneksi->commit();
    header('Location: ../index.php?page=serah_terima&view=detail&id=' . $serahTerimaId . '&success=Serah terima berhasil disimpan.');
    exit;
} catch (Exception $e) {
    $koneksi->rollback();
    header('Location: ../index.php?page=serah_terima&action=form&error=' . urlencode($e->getMessage()));
    exit;
}
