<?php
include __DIR__ . '/../koneksi/koneksi.php';

require_auth_roles(['admin'], [
    'response' => 'page',
    'login_redirect' => '../login.php',
    'forbidden_redirect' => '../index.php?page=dashboard',
]);

function run_sql_file_statements($koneksi, $filePath, &$messages) {
    if (!file_exists($filePath)) {
        $messages[] = 'File migrasi tidak ditemukan: ' . basename($filePath);
        return false;
    }

    $content = file_get_contents($filePath);
    if ($content === false) {
        $messages[] = 'Gagal membaca file migrasi: ' . basename($filePath);
        return false;
    }

    $statements = preg_split('/;\s*(\r?\n|$)/', $content);
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement === '' || strpos($statement, '--') === 0) {
            continue;
        }

        if (!$koneksi->query($statement)) {
            $messages[] = 'Skip/Warning [' . basename($filePath) . ']: ' . $koneksi->error;
        }
    }

    return true;
}

function adjust_safe_history_foreign_keys($koneksi, &$messages) {
    if (!schema_table_exists_now($koneksi, 'peminjaman')) {
        $messages[] = 'Tabel peminjaman tidak ada, penyesuaian FK user dilewati.';
        return;
    }

    $koneksi->query("ALTER TABLE peminjaman MODIFY COLUMN id_user INT NULL");

    $constraintSql = "SELECT CONSTRAINT_NAME
                      FROM information_schema.KEY_COLUMN_USAGE
                      WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = 'peminjaman'
                        AND COLUMN_NAME = 'id_user'
                        AND REFERENCED_TABLE_NAME = 'user'
                      LIMIT 1";
    $constraintResult = $koneksi->query($constraintSql);
    $constraintRow = $constraintResult ? $constraintResult->fetch_assoc() : null;
    $constraintName = $constraintRow['CONSTRAINT_NAME'] ?? null;

    if ($constraintName) {
        $dropSql = "ALTER TABLE peminjaman DROP FOREIGN KEY `$constraintName`";
        if (!$koneksi->query($dropSql)) {
            $messages[] = 'Warning drop FK peminjaman.id_user: ' . $koneksi->error;
        }
    }

    $addSql = "ALTER TABLE peminjaman
               ADD CONSTRAINT fk_peminjaman_user_safe_history
               FOREIGN KEY (id_user) REFERENCES user(id_user) ON DELETE SET NULL";
    if (!$koneksi->query($addSql)) {
        $messages[] = 'Warning add FK aman peminjaman.id_user: ' . $koneksi->error;
    } else {
        $messages[] = 'FK peminjaman.id_user berhasil diubah ke ON DELETE SET NULL.';
    }
}

function backfill_snapshot_columns($koneksi, &$messages) {
    if (schema_table_exists_now($koneksi, 'activity_log') && schema_has_column_now($koneksi, 'activity_log', 'actor_name_snapshot')) {
        $sql = "UPDATE activity_log al
                LEFT JOIN user u ON al.id_user = u.id_user
                SET al.actor_name_snapshot = COALESCE(al.actor_name_snapshot, u.nama, 'System')
                WHERE al.actor_name_snapshot IS NULL OR TRIM(al.actor_name_snapshot) = ''";
        $koneksi->query($sql);
    }

    if (schema_table_exists_now($koneksi, 'catatan_inventaris') && schema_has_column_now($koneksi, 'catatan_inventaris', 'created_by_name_snapshot')) {
        $sql = "UPDATE catatan_inventaris ci
                LEFT JOIN user u ON ci.created_by = u.id_user
                SET ci.created_by_name_snapshot = COALESCE(ci.created_by_name_snapshot, u.nama, 'System')
                WHERE ci.created_by_name_snapshot IS NULL OR TRIM(ci.created_by_name_snapshot) = ''";
        $koneksi->query($sql);
    }

    $messages[] = 'Backfill snapshot nama untuk activity_log dan catatan_inventaris selesai.';
}

$messages = [];

if (!schema_table_exists_now($koneksi, 'unit_barang')) {
    run_sql_file_statements($koneksi, __DIR__ . '/../database/migration_hybrid_inventory.sql', $messages);
}

run_sql_file_statements($koneksi, __DIR__ . '/../database/migration_priority_2.sql', $messages);
run_sql_file_statements($koneksi, __DIR__ . '/../database/migration_priority_2_safe_history.sql', $messages);
adjust_safe_history_foreign_keys($koneksi, $messages);
backfill_snapshot_columns($koneksi, $messages);

echo '<h3>Migration Priority 2 selesai dijalankan.</h3>';
echo '<ul>';
foreach ($messages as $message) {
    echo '<li>' . htmlspecialchars($message) . '</li>';
}
echo '</ul>';
echo '<p><a href="../index.php?page=dashboard">Kembali ke dashboard</a></p>';
