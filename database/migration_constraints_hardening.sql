-- =============================================================
-- Migration: Database Constraint Hardening
-- Target: inventaris_barang (MySQL 8.0.16+)
-- Purpose: Add FK constraints + CHECK constraints for integrity
-- Non-destructive: adds only, does not modify existing structure
-- Run once. Re-running will produce "Duplicate constraint" errors
--   which are safe to ignore if constraints already exist.
-- =============================================================

-- ---------------------------------------------------------------
-- PART 1: Foreign Key Constraints
-- ---------------------------------------------------------------

-- stokgudang.id_gudang → gudang.id_gudang
-- CASCADE: deleting a gudang also removes its stok rows
ALTER TABLE `stokgudang`
  ADD CONSTRAINT `fk_stokgudang_gudang`
    FOREIGN KEY (`id_gudang`) REFERENCES `gudang` (`id_gudang`)
    ON DELETE CASCADE ON UPDATE CASCADE;

-- stokgudang.id_produk → produk.id_produk
-- CASCADE: deleting a produk also removes its per-gudang stok rows
ALTER TABLE `stokgudang`
  ADD CONSTRAINT `fk_stokgudang_produk`
    FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id_produk`)
    ON DELETE CASCADE ON UPDATE CASCADE;

-- stoktransaksi.id_produk → produk.id_produk
-- RESTRICT: preserve audit trail — a produk with transactions
--   cannot be deleted until all its transaction records are removed.
ALTER TABLE `stoktransaksi`
  ADD CONSTRAINT `fk_stoktransaksi_produk`
    FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id_produk`)
    ON DELETE RESTRICT ON UPDATE CASCADE;

-- ---------------------------------------------------------------
-- PART 2: CHECK Constraints  (MySQL 8.0.16+)
-- ---------------------------------------------------------------

-- produk: stok fisik tidak boleh negatif
ALTER TABLE `produk`
  ADD CONSTRAINT `chk_produk_jumlah_stok_gte0`
    CHECK (`jumlah_stok` >= 0);

-- stokgudang: stok per-gudang tidak boleh negatif
ALTER TABLE `stokgudang`
  ADD CONSTRAINT `chk_stokgudang_jumlah_gte0`
    CHECK (`jumlah_stok` >= 0);

-- stoktransaksi: jumlah pada setiap transaksi harus > 0
ALTER TABLE `stoktransaksi`
  ADD CONSTRAINT `chk_stoktransaksi_jumlah_gt0`
    CHECK (`jumlah` > 0);
