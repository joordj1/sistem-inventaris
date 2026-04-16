CREATE TABLE IF NOT EXISTS bidang (
  id INT NOT NULL AUTO_INCREMENT,
  nama_bidang VARCHAR(150) NOT NULL,
  kode_bidang VARCHAR(50) DEFAULT NULL,
  status ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_bidang_nama (nama_bidang),
  UNIQUE KEY uniq_bidang_kode (kode_bidang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE user
  MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'user';

UPDATE user
SET role = 'petugas'
WHERE LOWER(TRIM(COALESCE(role, ''))) IN ('leader', 'operator');

UPDATE user
SET role = 'user'
WHERE role IS NULL
   OR TRIM(COALESCE(role, '')) = ''
   OR LOWER(TRIM(COALESCE(role, ''))) IN ('viewer', 'pemohon');

ALTER TABLE user
  MODIFY COLUMN role ENUM('admin', 'petugas', 'user') NOT NULL DEFAULT 'user';

ALTER TABLE user
  MODIFY COLUMN email VARCHAR(255) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS status ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif' AFTER role,
  ADD COLUMN IF NOT EXISTS kategori_user ENUM('staff', 'dosen', 'mahasiswa', 'umum') NOT NULL DEFAULT 'umum' AFTER status,
  ADD COLUMN IF NOT EXISTS bidang_id INT NULL AFTER kategori_user,
  ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER updated_at;

UPDATE user
SET status = 'nonaktif'
WHERE deleted_at IS NOT NULL;

UPDATE user
SET bidang_id = NULL
WHERE kategori_user <> 'staff';

ALTER TABLE user
  ADD KEY IF NOT EXISTS idx_user_bidang_id (bidang_id);

ALTER TABLE user
  ADD CONSTRAINT fk_user_bidang
  FOREIGN KEY (bidang_id) REFERENCES bidang(id)
  ON DELETE SET NULL
  ON UPDATE CASCADE;