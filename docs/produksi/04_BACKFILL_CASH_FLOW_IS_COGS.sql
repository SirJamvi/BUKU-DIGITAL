-- ==============================================================================
-- DOKUMEN PRODUKSI #04: SQL Backfill Kolom cash_flow.is_cogs
-- 
-- PRASYARAT:
-- 1. Database telah di-backup penuh sebelum menjalankan script ini.
-- 2. Migrasi 03_MIGRASI_CASH_FLOW_IS_COGS.php telah berhasil dijalankan (kolom cash_flow.is_cogs sudah ada).
-- 3. Script ini dijalankan secara mandiri oleh Database Administrator / Owner.
-- ==============================================================================

-- 1. Verifikasi baris yang akan diperbarui (Dry Run)
SELECT 
    cf.id,
    cf.business_id,
    cf.type,
    cf.category_id,
    ec.name AS category_name,
    ec.is_cogs AS category_is_cogs,
    cf.amount,
    cf.date
FROM cash_flow cf
JOIN expense_categories ec ON cf.category_id = ec.id
WHERE cf.type = 'expense' AND ec.is_cogs = 1;

-- 2. Eksekusi Backfill: Update cash_flow.is_cogs dari expense_categories
UPDATE cash_flow cf
JOIN expense_categories ec ON cf.category_id = ec.id
SET cf.is_cogs = ec.is_cogs
WHERE cf.type = 'expense';

-- 3. Pastikan record income / non-expense bernilai is_cogs = 0
UPDATE cash_flow
SET is_cogs = 0
WHERE type != 'expense' OR category_id IS NULL;

-- 4. Verifikasi Hasil Akhir
SELECT 
    cf.type,
    cf.is_cogs,
    COUNT(*) AS total_rows,
    SUM(cf.amount) AS total_amount
FROM cash_flow cf
GROUP BY cf.type, cf.is_cogs;
