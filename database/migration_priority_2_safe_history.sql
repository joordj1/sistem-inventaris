-- Priority 2 safe history migration: soft delete user, snapshot nama, dan FK aman

ALTER TABLE user
  ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER created_at;

ALTER TABLE user
  ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER deleted_at;

ALTER TABLE catatan_inventaris
  ADD COLUMN IF NOT EXISTS created_by_name_snapshot VARCHAR(255) NULL AFTER created_by;

ALTER TABLE activity_log
  ADD COLUMN IF NOT EXISTS actor_name_snapshot VARCHAR(255) NULL AFTER id_user;

ALTER TABLE peminjaman
  MODIFY COLUMN id_user INT NULL;

-- Penyesuaian foreign key peminjaman -> user ke ON DELETE SET NULL
-- Drop/add constraint dijalankan secara aman oleh action/apply_migration_priority_2.php
