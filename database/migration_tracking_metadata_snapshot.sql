-- Safe migration for tracking metadata snapshots and actor separation.

ALTER TABLE tracking_barang
    ADD COLUMN IF NOT EXISTS actor_name_snapshot VARCHAR(255) NULL AFTER id_user_changed;

UPDATE tracking_barang tb
LEFT JOIN user u ON tb.id_user_changed = u.id_user
SET tb.actor_name_snapshot = COALESCE(NULLIF(tb.actor_name_snapshot, ''), u.nama)
WHERE (tb.actor_name_snapshot IS NULL OR tb.actor_name_snapshot = '')
  AND tb.id_user_changed IS NOT NULL;

ALTER TABLE riwayat_unit_barang
    ADD COLUMN IF NOT EXISTS id_user_changed INT NULL AFTER id_user;

ALTER TABLE riwayat_unit_barang
    ADD COLUMN IF NOT EXISTS id_user_terkait INT NULL AFTER id_user_changed;

ALTER TABLE riwayat_unit_barang
    ADD COLUMN IF NOT EXISTS actor_name_snapshot VARCHAR(255) NULL AFTER id_user_terkait;

UPDATE riwayat_unit_barang
SET id_user_terkait = id_user
WHERE id_user_terkait IS NULL
  AND id_user IS NOT NULL;

UPDATE riwayat_unit_barang hr
LEFT JOIN user u ON hr.id_user_changed = u.id_user
SET hr.actor_name_snapshot = COALESCE(NULLIF(hr.actor_name_snapshot, ''), u.nama)
WHERE (hr.actor_name_snapshot IS NULL OR hr.actor_name_snapshot = '')
  AND hr.id_user_changed IS NOT NULL;
