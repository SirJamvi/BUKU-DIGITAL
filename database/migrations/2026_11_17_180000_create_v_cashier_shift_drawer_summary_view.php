<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Membuat Database View v_cashier_shift_drawer_summary untuk integrasi checksheet ACS.
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
    SELECT branch_id, DATE(transaction_date) AS `date` FROM transactions WHERE branch_id IS NOT NULL AND transaction_date IS NOT NULL
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
        t.branch_id, 
        DATE(t.transaction_date) AS `date`,
        SUM(t.total_amount) AS total_cash_sales,
        COUNT(t.id) AS cash_sales_count
    FROM transactions t
    WHERE t.type = 'sale'
      AND t.payment_status = 'paid'
      AND LOWER(t.payment_method) = 'cash'
      AND NOT EXISTS (
          SELECT 1 FROM cash_flow cf 
          WHERE cf.reference_id = CAST(t.id AS CHAR) 
            AND cf.description LIKE '%Pelunasan%'
      )
    GROUP BY t.branch_id, DATE(t.transaction_date)
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
      AND cf.description LIKE '%Pelunasan%'
      AND (cf.affects_drawer = 1 OR cf.affects_drawer IS NULL)
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
      AND (LOWER(cf.payment_method) = 'cash' OR cf.payment_method IS NULL)
      AND (
          cf.funding_source = 'drawer' 
          OR cf.funding_source IS NULL 
          OR cf.affects_drawer = 1 
          OR cf.affects_drawer IS NULL
      )
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
    }
};
