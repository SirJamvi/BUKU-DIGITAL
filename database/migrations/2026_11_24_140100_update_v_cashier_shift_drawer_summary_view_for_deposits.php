<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Memperbarui view v_cashier_shift_drawer_summary untuk memperhitungkan Customer Deposit:
     * - Menambahkan subquery total_cash_deposits (type = 'income', cash, flow_type = 'customer_deposit', affects_drawer = 1)
     * - Menambahkan deposit_refund ke subquery pengeluaran laci (affects_drawer = 1)
     * - Rumus expected_drawer_cash = opening_cash + total_cash_sales + total_cash_debt_collection + total_cash_deposits - total_cash_expenses
     */
    public function up(): void
    {
        DB::statement("DROP VIEW IF EXISTS v_cashier_shift_drawer_summary");

        $sql = <<<SQL
CREATE VIEW v_cashier_shift_drawer_summary AS
SELECT 
    ad.date,
    b.id AS branch_id,
    b.code AS branch_code,
    b.name AS branch_name,
    b.is_main,
    COALESCE(oc.opening_cash, 0.00) AS opening_cash,
    COALESCE(cs.total_cash_sales, 0.00) AS total_cash_sales,
    COALESCE(dc.total_cash_debt_collection, 0.00) AS total_cash_debt_collection,
    COALESCE(cd.total_cash_deposits, 0.00) AS total_cash_deposits,
    COALESCE(ex.total_cash_expenses, 0.00) AS total_cash_expenses,
    (
        COALESCE(oc.opening_cash, 0.00) + 
        COALESCE(cs.total_cash_sales, 0.00) + 
        COALESCE(dc.total_cash_debt_collection, 0.00) +
        COALESCE(cd.total_cash_deposits, 0.00) - 
        COALESCE(ex.total_cash_expenses, 0.00)
    ) AS expected_drawer_cash,
    COALESCE(cs.cash_sales_count, 0) AS cash_sales_count,
    COALESCE(dc.debt_collection_count, 0) AS debt_collection_count,
    COALESCE(cd.cash_deposits_count, 0) AS cash_deposits_count,
    COALESCE(ex.expenses_count, 0) AS expenses_count
FROM (
    SELECT branch_id, `date` FROM cashier_opening_balances WHERE branch_id IS NOT NULL
    UNION
    SELECT branch_id, DATE(`date`) AS `date` FROM cash_flow WHERE branch_id IS NOT NULL AND `date` IS NOT NULL
) ad
JOIN branches b ON b.id = ad.branch_id
LEFT JOIN (
    SELECT branch_id, `date`, SUM(amount) AS opening_cash
    FROM cashier_opening_balances
    GROUP BY branch_id, `date`
) oc ON oc.branch_id = ad.branch_id AND oc.date = ad.date
LEFT JOIN (
    SELECT 
        cf.branch_id, 
        DATE(cf.date) AS `date`,
        SUM(cf.amount) AS total_cash_sales,
        COUNT(cf.id) AS cash_sales_count
    FROM cash_flow cf
    WHERE cf.type = 'income'
      AND LOWER(cf.payment_method) = 'cash'
      AND cf.flow_type = 'sale_direct'
      AND cf.affects_drawer = 1
    GROUP BY cf.branch_id, DATE(cf.date)
) cs ON cs.branch_id = ad.branch_id AND cs.date = ad.date
LEFT JOIN (
    SELECT 
        cf.branch_id,
        DATE(cf.date) AS `date`,
        SUM(cf.amount) AS total_cash_debt_collection,
        COUNT(cf.id) AS debt_collection_count
    FROM cash_flow cf
    WHERE cf.type = 'income'
      AND LOWER(cf.payment_method) = 'cash'
      AND cf.flow_type = 'debt_settlement'
      AND cf.affects_drawer = 1
    GROUP BY cf.branch_id, DATE(cf.date)
) dc ON dc.branch_id = ad.branch_id AND dc.date = ad.date
LEFT JOIN (
    SELECT 
        cf.branch_id,
        DATE(cf.date) AS `date`,
        SUM(cf.amount) AS total_cash_deposits,
        COUNT(cf.id) AS cash_deposits_count
    FROM cash_flow cf
    WHERE cf.type = 'income'
      AND LOWER(cf.payment_method) = 'cash'
      AND cf.flow_type = 'customer_deposit'
      AND cf.affects_drawer = 1
    GROUP BY cf.branch_id, DATE(cf.date)
) cd ON cd.branch_id = ad.branch_id AND cd.date = ad.date
LEFT JOIN (
    SELECT 
        cf.branch_id,
        DATE(cf.date) AS `date`,
        SUM(cf.amount) AS total_cash_expenses,
        COUNT(cf.id) AS expenses_count
    FROM cash_flow cf
    WHERE cf.type = 'expense'
      AND LOWER(cf.payment_method) = 'cash'
      AND cf.affects_drawer = 1
      AND cf.flow_type IN ('expense', 'stock_purchase', 'cash_handover', 'cash_refund', 'deposit_refund')
    GROUP BY cf.branch_id, DATE(cf.date)
) ex ON ex.branch_id = ad.branch_id AND ex.date = ad.date
WHERE b.is_active = 1
SQL;

        DB::statement($sql);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS v_cashier_shift_drawer_summary");

        // Rollback ke versi sebelumnya tanpa kolom deposit
        $sql = <<<SQL
CREATE VIEW v_cashier_shift_drawer_summary AS
SELECT 
    ad.date,
    b.id AS branch_id,
    b.code AS branch_code,
    b.name AS branch_name,
    b.is_main,
    COALESCE(oc.opening_cash, 0.00) AS opening_cash,
    COALESCE(cs.total_cash_sales, 0.00) AS total_cash_sales,
    COALESCE(dc.total_cash_debt_collection, 0.00) AS total_cash_debt_collection,
    COALESCE(ex.total_cash_expenses, 0.00) AS total_cash_expenses,
    (
        COALESCE(oc.opening_cash, 0.00) + 
        COALESCE(cs.total_cash_sales, 0.00) + 
        COALESCE(dc.total_cash_debt_collection, 0.00) - 
        COALESCE(ex.total_cash_expenses, 0.00)
    ) AS expected_drawer_cash,
    COALESCE(cs.cash_sales_count, 0) AS cash_sales_count,
    COALESCE(dc.debt_collection_count, 0) AS debt_collection_count,
    COALESCE(ex.expenses_count, 0) AS expenses_count
FROM (
    SELECT branch_id, `date` FROM cashier_opening_balances WHERE branch_id IS NOT NULL
    UNION
    SELECT branch_id, DATE(`date`) AS `date` FROM cash_flow WHERE branch_id IS NOT NULL AND `date` IS NOT NULL
) ad
JOIN branches b ON b.id = ad.branch_id
LEFT JOIN (
    SELECT branch_id, `date`, SUM(amount) AS opening_cash
    FROM cashier_opening_balances
    GROUP BY branch_id, `date`
) oc ON oc.branch_id = ad.branch_id AND oc.date = ad.date
LEFT JOIN (
    SELECT 
        cf.branch_id, 
        DATE(cf.date) AS `date`,
        SUM(cf.amount) AS total_cash_sales,
        COUNT(cf.id) AS cash_sales_count
    FROM cash_flow cf
    WHERE cf.type = 'income'
      AND LOWER(cf.payment_method) = 'cash'
      AND cf.flow_type = 'sale_direct'
      AND cf.affects_drawer = 1
    GROUP BY cf.branch_id, DATE(cf.date)
) cs ON cs.branch_id = ad.branch_id AND cs.date = ad.date
LEFT JOIN (
    SELECT 
        cf.branch_id,
        DATE(cf.date) AS `date`,
        SUM(cf.amount) AS total_cash_debt_collection,
        COUNT(cf.id) AS debt_collection_count
    FROM cash_flow cf
    WHERE cf.type = 'income'
      AND LOWER(cf.payment_method) = 'cash'
      AND cf.flow_type = 'debt_settlement'
      AND cf.affects_drawer = 1
    GROUP BY cf.branch_id, DATE(cf.date)
) dc ON dc.branch_id = ad.branch_id AND dc.date = ad.date
LEFT JOIN (
    SELECT 
        cf.branch_id,
        DATE(cf.date) AS `date`,
        SUM(cf.amount) AS total_cash_expenses,
        COUNT(cf.id) AS expenses_count
    FROM cash_flow cf
    WHERE cf.type = 'expense'
      AND LOWER(cf.payment_method) = 'cash'
      AND cf.affects_drawer = 1
      AND cf.flow_type IN ('expense', 'stock_purchase', 'cash_handover', 'cash_refund')
    GROUP BY cf.branch_id, DATE(cf.date)
) ex ON ex.branch_id = ad.branch_id AND ex.date = ad.date
WHERE b.is_active = 1
SQL;

        DB::statement($sql);
    }
};
