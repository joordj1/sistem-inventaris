<?php
include '../koneksi/koneksi.php';
require_once __DIR__ . '/simpan_histori_log.php';

require_auth_roles(['admin', 'petugas'], [
    'response' => 'page',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=mutasi_barang',
]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?page=mutasi_barang');
    exit;
}

if (!schema_table_exists_now($koneksi, 'mutasi_barang') || !schema_table_exists_now($koneksi, 'mutasi_barang_detail')) {
    header('Location: ../index.php?page=mutasi_barang&error=Skema mutasi belum tersedia. Jalankan migration priority 2 terlebih dahulu.');
    exit;
}

$gudangAsalId = intval($_POST['gudang_asal_id'] ?? 0);
$gudangTujuanId = intval($_POST['gudang_tujuan_id'] ?? 0);
$tanggalMutasi = str_replace('T', ' ', trim((string) ($_POST['tanggal_mutasi'] ?? date('Y-m-d H:i:s'))));
$catatan = trim((string) ($_POST['catatan'] ?? ''));
$createdBy = current_user_id();
$createdByName = get_current_user_name($koneksi) ?? 'System';

if ($gudangAsalId < 1 || $gudangTujuanId < 1) {
    header('Location: ../index.php?page=mutasi_barang&action=form&error=Gudang asal dan gudang tujuan wajib dipilih.');
    exit;
}

if ($gudangAsalId === $gudangTujuanId) {
    header('Location: ../index.php?page=mutasi_barang&action=form&error=Gudang asal dan gudang tujuan tidak boleh sama.');
    exit;
}

if (!asset_unit_gudang_exists($koneksi, $gudangAsalId) || !asset_unit_gudang_exists($koneksi, $gudangTujuanId)) {
    header('Location: ../index.php?page=mutasi_barang&action=form&error=Gudang asal/tujuan tidak ditemukan.');
    exit;
}

$consumableItems = [];
$produkIds = $_POST['consumable_produk_id'] ?? [];
$quantities = $_POST['consumable_qty'] ?? [];
$detailNotes = $_POST['consumable_catatan_detail'] ?? [];

if (is_array($produkIds)) {
    foreach ($produkIds as $index => $produkIdRaw) {
        $produkId = intval($produkIdRaw);
        $qty = intval($quantities[$index] ?? 0);
        $detailNote = trim((string) ($detailNotes[$index] ?? ''));

        if ($produkId < 1 || $qty <= 0) {
            continue;
        }

        $consumableItems[] = [
            'produk_id' => $produkId,
            'qty' => $qty,
            'catatan_detail' => $detailNote,
        ];
    }
}

$assetItems = [];
$selectedUnits = $_POST['asset_unit_barang_id'] ?? [];
$assetConditions = $_POST['asset_kondisi_sesudah'] ?? [];
$assetNotes = $_POST['asset_catatan_detail'] ?? [];

if (is_array($selectedUnits)) {
    foreach ($selectedUnits as $unitIdRaw) {
        $unitId = intval($unitIdRaw);
        if ($unitId < 1) {
            continue;
        }

        $assetItems[] = [
            'unit_barang_id' => $unitId,
            'kondisi_sesudah' => trim((string) ($assetConditions[$unitId] ?? '')),
            'catatan_detail' => trim((string) ($assetNotes[$unitId] ?? '')),
        ];
    }
}

if (empty($consumableItems) && empty($assetItems)) {
    header('Location: ../index.php?page=mutasi_barang&action=form&error=Pilih minimal satu barang atau unit untuk dimutasi.');
    exit;
}

$jenisBarang = 'campuran';
if (!empty($consumableItems) && empty($assetItems)) {
    $jenisBarang = 'consumable';
} elseif (empty($consumableItems) && !empty($assetItems)) {
    $jenisBarang = 'asset';
}

try {
    $dokumenFile = store_uploaded_inventory_document('dokumen_mutasi', 'uploads/dokumen_mutasi', 'MUTASI');
} catch (Exception $e) {
    header('Location: ../index.php?page=mutasi_barang&action=form&error=' . urlencode($e->getMessage()));
    exit;
}

$kodeMutasi = generate_reference_code($koneksi, 'mutasi_barang', 'kode_mutasi', 'MTS');
if ($kodeMutasi === null) {
    header('Location: ../index.php?page=mutasi_barang&error=Kode mutasi gagal dibuat.');
    exit;
}

ensure_histori_log_table($koneksi);
$koneksi->begin_transaction();

try {
    $headerStmt = $koneksi->prepare(
        "INSERT INTO mutasi_barang (
            kode_mutasi, tanggal_mutasi, gudang_asal_id, gudang_tujuan_id, jenis_barang, catatan, dokumen_file,
            status, created_by, created_by_name, approved_by, approved_by_name, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'selesai', ?, ?, ?, ?, NOW(), NOW())"
    );

    if (!$headerStmt) {
        throw new Exception('Gagal menyiapkan penyimpanan header mutasi.');
    }

    $approvedBy = $createdBy;
    $approvedByName = $createdByName;
    $headerStmt->bind_param(
        'ssiisssisis',
        $kodeMutasi,
        $tanggalMutasi,
        $gudangAsalId,
        $gudangTujuanId,
        $jenisBarang,
        $catatan,
        $dokumenFile,
        $createdBy,
        $createdByName,
        $approvedBy,
        $approvedByName
    );

    if (!$headerStmt->execute()) {
        throw new Exception('Header mutasi gagal disimpan.');
    }

    $mutasiId = intval($koneksi->insert_id);

    $detailStmt = $koneksi->prepare(
        "INSERT INTO mutasi_barang_detail (
            mutasi_id, produk_id, unit_barang_id, qty, satuan_snapshot, kondisi_sebelum, kondisi_sesudah, catatan_detail
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$detailStmt) {
        throw new Exception('Gagal menyiapkan detail mutasi.');
    }

    foreach ($consumableItems as $item) {
        $produk = get_produk_by_id($koneksi, $item['produk_id']);
        if (!$produk || ($produk['tipe_barang'] ?? 'consumable') !== 'consumable') {
            throw new Exception('Produk consumable tidak valid untuk mutasi.');
        }

        $stokAsal = get_stok_gudang_qty($koneksi, $gudangAsalId, $item['produk_id']);
        if ($stokAsal < $item['qty']) {
            throw new Exception('Stok gudang asal tidak cukup untuk produk ' . ($produk['nama_produk'] ?? ('#' . $item['produk_id'])));
        }

        if (!upsert_stok_gudang_quantity($koneksi, $gudangAsalId, $item['produk_id'], -$item['qty'])) {
            throw new Exception('Gagal mengurangi stok gudang asal.');
        }
        if (!upsert_stok_gudang_quantity($koneksi, $gudangTujuanId, $item['produk_id'], $item['qty'])) {
            throw new Exception('Gagal menambah stok gudang tujuan.');
        }

        $unitBarangId = null;
        $satuanSnapshot = $produk['satuan'] ?? null;
        $kondisiSebelum = $produk['kondisi'] ?? null;
        $kondisiSesudah = $produk['kondisi'] ?? null;
        $detailStmt->bind_param(
            'iiiissss',
            $mutasiId,
            $item['produk_id'],
            $unitBarangId,
            $item['qty'],
            $satuanSnapshot,
            $kondisiSebelum,
            $kondisiSesudah,
            $item['catatan_detail']
        );
        if (!$detailStmt->execute()) {
            throw new Exception('Detail mutasi consumable gagal disimpan.');
        }

        log_tracking_history($koneksi, [
            'id_produk' => $item['produk_id'],
            'kode_produk' => $produk['kode_produk'] ?? null,
            'status_sebelum' => $produk['status'] ?? 'tersedia',
            'status_sesudah' => $produk['status'] ?? 'tersedia',
            'kondisi_sebelum' => $produk['kondisi'] ?? null,
            'kondisi_sesudah' => $produk['kondisi'] ?? null,
            'lokasi_sebelum' => get_gudang_name_by_id($koneksi, $gudangAsalId),
            'lokasi_sesudah' => get_gudang_name_by_id($koneksi, $gudangTujuanId),
            'id_user_sebelum' => null,
            'id_user_sesudah' => null,
            'id_user_terkait' => null,
            'activity_type' => 'pindah',
            'note' => 'Mutasi ' . $kodeMutasi . ': ' . $item['qty'] . ' ' . ($produk['satuan'] ?? '') . ' dipindahkan antar gudang.',
            'id_user_changed' => $createdBy,
        ]);

        save_official_histori_log_entry($koneksi, [
            'ref_type' => 'mutasi',
            'ref_id' => $mutasiId,
            'event_type' => 'detail_consumable_dipindahkan',
            'produk_id' => $item['produk_id'],
            'gudang_id' => $gudangTujuanId,
            'user_id' => $createdBy,
            'user_name_snapshot' => $createdByName,
            'deskripsi' => 'Mutasi consumable ' . ($produk['nama_produk'] ?? ('Produk #' . $item['produk_id'])) . ' qty ' . intval($item['qty']),
            'meta_json' => [
                'gudang_asal_id' => $gudangAsalId,
                'gudang_tujuan_id' => $gudangTujuanId,
                'satuan' => $produk['satuan'] ?? null,
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

        $unitLabel = trim((string) ($unit['kode_unit'] ?? ''));
        $unitLabel = $unitLabel !== '' ? $unitLabel : ('Unit #' . $item['unit_barang_id']);

        if (intval($unit['id_gudang'] ?? 0) !== $gudangAsalId) {
            throw new Exception('Unit asset ' . $unitLabel . ' tidak berada di gudang asal.');
        }

        if (!empty($unit['id_user'])) {
            throw new Exception('Unit asset ' . $unitLabel . ' sedang terhubung ke user. Gunakan serah terima formal atau release terlebih dahulu.');
        }

        $unitStatus = normalize_asset_unit_status($unit['status'] ?? null);
        if ($unitStatus !== 'tersedia') {
            throw new Exception('Unit asset ' . $unitLabel . ' tidak dalam status tersedia.');
        }

        $kondisiSesudah = $item['kondisi_sesudah'] !== '' ? $item['kondisi_sesudah'] : ($unit['kondisi'] ?? 'baik');
        $updateStmt = $koneksi->prepare("UPDATE unit_barang SET id_gudang = ?, kondisi = ?, updated_at = NOW() WHERE id_unit_barang = ?");
        if (!$updateStmt) {
            throw new Exception('Gagal menyiapkan update unit asset.');
        }
        $updateStmt->bind_param('isi', $gudangTujuanId, $kondisiSesudah, $item['unit_barang_id']);
        if (!$updateStmt->execute()) {
            throw new Exception('Gagal memindahkan unit asset ke gudang tujuan.');
        }

        $qtyUnit = 1;
        $satuanSnapshot = 'unit';
        $kondisiSebelum = $unit['kondisi'] ?? null;
        $detailStmt->bind_param(
            'iiiissss',
            $mutasiId,
            $unit['id_produk'],
            $item['unit_barang_id'],
            $qtyUnit,
            $satuanSnapshot,
            $kondisiSebelum,
            $kondisiSesudah,
            $item['catatan_detail']
        );
        if (!$detailStmt->execute()) {
            throw new Exception('Detail mutasi asset gagal disimpan.');
        }

        log_riwayat_unit_barang($koneksi, [
            'id_unit_barang' => $item['unit_barang_id'],
            'id_produk' => $unit['id_produk'],
            'activity_type' => 'pindah',
            'status_sebelum' => $unit['status'],
            'status_sesudah' => $unit['status'],
            'kondisi_sebelum' => $unit['kondisi'],
            'kondisi_sesudah' => $kondisiSesudah,
            'lokasi_sebelum' => get_gudang_name_by_id($koneksi, $gudangAsalId),
            'lokasi_sesudah' => get_gudang_name_by_id($koneksi, $gudangTujuanId),
            'id_user_sebelum' => $unit['id_user'] ?? null,
            'id_user_sesudah' => $unit['id_user'] ?? null,
            'id_user_terkait' => $unit['id_user'] ?? null,
            'note' => 'Mutasi resmi ' . $kodeMutasi,
            'id_user_changed' => $createdBy,
        ]);

        save_official_histori_log_entry($koneksi, [
            'ref_type' => 'mutasi',
            'ref_id' => $mutasiId,
            'event_type' => 'detail_asset_dipindahkan',
            'produk_id' => $unit['id_produk'],
            'unit_barang_id' => $item['unit_barang_id'],
            'gudang_id' => $gudangTujuanId,
            'user_id' => $createdBy,
            'user_name_snapshot' => $createdByName,
            'deskripsi' => 'Mutasi asset ' . ($produk['nama_produk'] ?? 'Asset') . ' unit ' . $unitLabel,
            'meta_json' => [
                'gudang_asal_id' => $gudangAsalId,
                'gudang_tujuan_id' => $gudangTujuanId,
                'kondisi_sebelum' => $unit['kondisi'] ?? null,
                'kondisi_sesudah' => $kondisiSesudah,
                'catatan_detail' => $item['catatan_detail'],
            ],
        ]);
    }

    save_official_histori_log_entry($koneksi, [
        'ref_type' => 'mutasi',
        'ref_id' => $mutasiId,
        'event_type' => 'mutasi_selesai',
        'gudang_id' => $gudangTujuanId,
        'user_id' => $createdBy,
        'user_name_snapshot' => $createdByName,
        'deskripsi' => 'Mutasi resmi ' . $kodeMutasi . ' selesai diproses.',
        'meta_json' => [
            'kode_mutasi' => $kodeMutasi,
            'gudang_asal_id' => $gudangAsalId,
            'gudang_tujuan_id' => $gudangTujuanId,
            'jenis_barang' => $jenisBarang,
            'dokumen_file' => $dokumenFile,
        ],
    ]);

    if ($dokumenFile && schema_table_exists_now($koneksi, 'dokumen_transaksi')) {
        $dokumenStmt = $koneksi->prepare(
            "INSERT INTO dokumen_transaksi (ref_type, ref_id, jenis_dokumen, file_path, uploaded_by, uploaded_by_name, created_at)
             VALUES ('mutasi', ?, 'dokumen_mutasi', ?, ?, ?, NOW())"
        );
        if ($dokumenStmt) {
            $dokumenStmt->bind_param('isis', $mutasiId, $dokumenFile, $createdBy, $createdByName);
            $dokumenStmt->execute();
        }
    }

    log_activity($koneksi, [
        'id_user' => $createdBy,
        'role_user' => get_current_user_role(),
        'action_name' => 'mutasi_barang',
        'entity_type' => 'mutasi',
        'entity_id' => $mutasiId,
        'entity_label' => $kodeMutasi,
        'description' => 'Mencatat mutasi barang antar gudang',
        'id_gudang' => $gudangTujuanId,
        'actor_name_snapshot' => $createdByName,
        'metadata_json' => [
            'gudang_asal_id' => $gudangAsalId,
            'gudang_tujuan_id' => $gudangTujuanId,
            'jenis_barang' => $jenisBarang,
            'dokumen_file' => $dokumenFile,
        ],
    ]);

    $koneksi->commit();
    header('Location: ../index.php?page=mutasi_barang&view=detail&id=' . $mutasiId . '&success=Mutasi barang berhasil disimpan.');
    exit;
} catch (Exception $e) {
    $koneksi->rollback();
    header('Location: ../index.php?page=mutasi_barang&action=form&error=' . urlencode($e->getMessage()));
    exit;
}
