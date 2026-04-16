-- =============================================================
-- Migration: QR Hash Hardening
-- Purpose: add qr_hash token for public QR scan endpoint
-- Non-destructive: keeps legacy unit_id flow as fallback
-- =============================================================

ALTER TABLE `unit_barang`
  ADD COLUMN `qr_hash` VARCHAR(64) NULL AFTER `kode_qrcode`;

-- Backfill existing rows with deterministic unique hash
UPDATE `unit_barang`
SET `qr_hash` = LOWER(SHA2(CONCAT('unit:', `id_unit_barang`, ':', UUID()), 256))
WHERE (`qr_hash` IS NULL OR `qr_hash` = '');

ALTER TABLE `unit_barang`
  ADD UNIQUE KEY `uk_unit_barang_qr_hash` (`qr_hash`);
