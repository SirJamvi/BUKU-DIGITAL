-- ==============================================================================
-- DOKUMEN PRODUKSI #02: SQL Query Audit & Verifikasi Baris NULL cost_price
-- 
-- PRASYARAT:
-- - Dijalankan untuk memeriksa kondisi data riil sebelum melakukan backfill dan migrasi NOT NULL.
-- - Bersifat READ-ONLY (SELECT murni, tidak mengubah data).
-- ==============================================================================

-- 1. Hitung total baris transaction_details yang memiliki cost_price NULL
SELECT 
    COUNT(*) AS total_null_rows,
    SUM(td.quantity) AS total_null_quantity,
    SUM(td.total_price) AS total_null_revenue
FROM transaction_details td
WHERE td.cost_price IS NULL;

-- 2. Breakdown baris NULL per produk fisik vs jasa
SELECT 
    p.id AS product_id,
    p.name AS product_name,
    p.unit,
    p.cost_price AS master_cost_price,
    CASE 
        WHEN LOWER(p.name) LIKE '%ongkir%' OR LOWER(p.name) LIKE '%ongkos kirim%' OR LOWER(p.unit) = 'jalan' THEN 1 
        ELSE 0 
    END AS is_service_detected,
    COUNT(td.id) AS count_null_details,
    SUM(td.quantity) AS sum_qty,
    SUM(td.total_price) AS sum_revenue
FROM transaction_details td
LEFT JOIN products p ON td.product_id = p.id
WHERE td.cost_price IS NULL
GROUP BY p.id, p.name, p.unit, p.cost_price
ORDER BY count_null_details DESC;

-- 3. Cek apakah ada transaksi penjualan yang bulan bukunya sudah ditutup (owner_profits completed)
SELECT 
    op.period_year,
    op.period_month,
    op.status AS closing_status,
    COUNT(td.id) AS null_details_in_closed_months
FROM transaction_details td
JOIN transactions t ON td.transaction_id = t.id
JOIN owner_profits op ON op.business_id = t.business_id 
    AND op.period_year = YEAR(t.transaction_date)
    AND op.period_month = MONTH(t.transaction_date)
WHERE td.cost_price IS NULL AND op.status = 'completed'
GROUP BY op.period_year, op.period_month, op.status;
