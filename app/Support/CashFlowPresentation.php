<?php

namespace App\Support;

use App\Models\CashFlow;

class CashFlowPresentation
{
    /**
     * Mengembalikan label Bahasa Indonesia untuk flow_type.
     */
    public static function flowTypeLabel(string|CashFlow|null $flowOrType): string
    {
        $flowType = self::resolveFlowType($flowOrType);
        $categoryType = self::resolveCategoryType($flowOrType);

        return match ($flowType) {
            'sale_direct'      => 'Penjualan Langsung',
            'debt_settlement'  => 'Pelunasan Kasbon',
            'customer_deposit' => 'Deposit Pelanggan',
            'deposit_refund'   => 'Pengembalian Deposit',
            'expense'          => ($categoryType && $categoryType !== 'Operasional') 
                                    ? "Pengeluaran ({$categoryType})" 
                                    : 'Beban Operasional',
            'stock_purchase'   => 'Pembelian Stok (HPP)',
            'cash_handover'    => 'Setoran ke Owner/Bank',
            'cash_refund'      => 'Refund / Void Nota',
            'salary_external'  => 'Gaji Karyawan (Uang Bos)',
            default            => ucfirst(str_replace('_', ' ', $flowType ?: 'Pengeluaran')),
        };
    }

    /**
     * Mengembalikan class badge CSS untuk flow_type.
     */
    public static function flowTypeBadgeClass(string|CashFlow|null $flowOrType): string
    {
        $flowType = self::resolveFlowType($flowOrType);
        $categoryType = self::resolveCategoryType($flowOrType);

        return match ($flowType) {
            'sale_direct'      => 'badge-soft-success bg-success bg-opacity-10 text-success border border-success border-opacity-25',
            'debt_settlement'  => 'badge-soft-primary bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25',
            'customer_deposit' => 'badge-soft-info bg-info bg-opacity-10 text-info border border-info border-opacity-25',
            'deposit_refund'   => 'badge-soft-secondary bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25',
            'expense'          => ($categoryType && $categoryType !== 'Operasional')
                                    ? 'badge-soft-secondary bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25'
                                    : 'badge-soft-danger bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25',
            'stock_purchase'   => 'badge-soft-info bg-info bg-opacity-10 text-info border border-info border-opacity-25',
            'cash_handover'    => 'badge-soft-warning bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25',
            'cash_refund'      => 'badge-soft-dark bg-dark bg-opacity-10 text-dark border border-dark border-opacity-25',
            'salary_external'  => 'badge-soft-purple bg-purple bg-opacity-10 text-primary border border-primary border-opacity-25',
            default            => 'badge-soft-secondary bg-secondary bg-opacity-10 text-secondary',
        };
    }

    /**
     * Resolusi internal tipe transaksi (income vs expense).
     */
    private static function resolveType(mixed $item): ?string
    {
        if ($item instanceof CashFlow) {
            return $item->type;
        }

        if (is_array($item)) {
            return $item['type'] ?? null;
        }

        return null;
    }

    /**
     * Mengembalikan label Bahasa Indonesia untuk funding_source & status laci.
     */
    public static function fundingSourceLabel(CashFlow|array|string|null $item, ?bool $affectsDrawer = null): string
    {
        $source = self::resolveFundingSource($item);
        $isDrawer = self::resolveAffectsDrawer($item, $affectsDrawer);
        $type = self::resolveType($item);

        if ($source === 'owner') {
            return 'Rekening Owner (Non-Laci)';
        }

        if ($source === 'external') {
            return 'Uang Bos / Eksternal (Non-Laci)';
        }

        if ($source === 'drawer' || $isDrawer) {
            if ($type === 'income') {
                return 'Masuk Laci Kasir';
            }
            if ($type === 'expense') {
                return 'Potong Laci Kasir';
            }
            return 'Laci Kasir';
        }

        return 'Non-Laci';
    }

    /**
     * Mengembalikan class badge CSS untuk funding_source.
     */
    public static function fundingSourceBadgeClass(CashFlow|array|string|null $item, ?bool $affectsDrawer = null): string
    {
        $source = self::resolveFundingSource($item);
        $isDrawer = self::resolveAffectsDrawer($item, $affectsDrawer);
        $type = self::resolveType($item);

        if ($source === 'drawer' || $isDrawer) {
            if ($type === 'expense') {
                return 'bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25';
            }
            return 'bg-success bg-opacity-10 text-success border border-success border-opacity-25';
        }

        if ($source === 'owner') {
            return 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25';
        }

        if ($source === 'external') {
            return 'bg-purple bg-opacity-10 text-primary border border-primary border-opacity-25';
        }

        return 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25';
    }

    /**
     * Menentukan apakah transaksi ini berdampak pada Laba Rugi (P&L).
     * Bernilai true HANYA jika:
     * - type = 'expense'
     * - flow_type IN ('expense', 'salary_external')
     * - is_cogs = 0 (bukan HPP)
     * - category_type = 'Operasional' (atau default Operasional jika kategori tidak diset khusus)
     */
    public static function affectsProfitLoss(CashFlow|array $item): bool
    {
        $type = self::resolveType($item);
        if ($type !== 'expense') {
            return false;
        }

        $flowType = self::resolveFlowType($item);
        if (!in_array($flowType, ['expense', 'salary_external'], true)) {
            return false;
        }

        $isCogs = ($item instanceof CashFlow) 
            ? (bool) ($item->is_cogs ?? optional($item->category)->is_cogs)
            : (bool) ($item['is_cogs'] ?? false);

        if ($isCogs) {
            return false;
        }

        $categoryType = self::resolveCategoryType($item) ?? 'Operasional';
        return $categoryType === 'Operasional';
    }

    /**
     * Render badge dampak P&L: Pendapatan P&L / Beban P&L vs Non-P&L.
     */
    public static function renderProfitLossBadge(CashFlow|array $item): string
    {
        $type = self::resolveType($item);

        if ($type === 'income') {
            $flowType = self::resolveFlowType($item);
            if ($flowType === 'sale_direct') {
                $isVoid = false;
                if ($item instanceof CashFlow) {
                    if (optional($item->transaction)->status === 'cancelled') {
                        $isVoid = true;
                    } elseif (stripos($item->description ?? '', 'void') !== false || stripos($item->description ?? '', 'batal') !== false) {
                        $isVoid = true;
                    }
                } elseif (is_array($item)) {
                    if (($item['transaction']['status'] ?? '') === 'cancelled'
                        || stripos($item['description'] ?? '', 'void') !== false 
                        || stripos($item['description'] ?? '', 'batal') !== false) {
                        $isVoid = true;
                    }
                }

                if ($isVoid) {
                    return '<span class="badge bg-dark bg-opacity-10 text-dark border border-dark border-opacity-25" title="Transaksi Penjualan Dibatalkan (Void)"><i class="fas fa-ban me-1"></i>Dibatalkan (Void)</span>';
                }

                return '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" title="Diakui sebagai pendapatan usaha (Omset)"><i class="fas fa-check-circle me-1"></i>Pendapatan P&L (Omset)</span>';
            }

            if ($flowType === 'debt_settlement') {
                return '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25" title="Pelunasan piutang pelanggan (Non-P&L karena omset diakui saat transaksi tempo dibuat)"><i class="fas fa-hand-holding-usd me-1"></i>Non-P&L (Pelunasan Piutang)</span>';
            }

            if ($flowType === 'customer_deposit') {
                return '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25" title="Kewajiban titipan pelanggan (Non-P&L)"><i class="fas fa-piggy-bank me-1"></i>Non-P&L (Titipan Pelanggan)</span>';
            }

            return '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25" title="Penerimaan kas non-omset"><i class="fas fa-info-circle me-1"></i>Non-P&L (Pendapatan Lain)</span>';
        }

        if (self::affectsProfitLoss($item)) {
            return '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25"><i class="fas fa-check-circle me-1"></i>Beban P&L</span>';
        }

        return '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" title="Tidak memotong laba operasional (HPP, kasbon karyawan, setoran, refund, atau non-usaha)"><i class="fas fa-shield-alt me-1"></i>Non-P&L / Tidak Memotong Laba</span>';
    }

    /**
     * Resolusi internal flow_type.
     */
    private static function resolveFlowType(mixed $item): string
    {
        if ($item instanceof CashFlow) {
            return $item->flow_type ?? ($item->type === 'income' ? 'sale_direct' : 'expense');
        }

        if (is_array($item)) {
            return $item['flow_type'] ?? ($item['type'] === 'income' ? 'sale_direct' : 'expense');
        }

        return is_string($item) ? $item : 'expense';
    }

    /**
     * Resolusi internal category_type.
     */
    private static function resolveCategoryType(mixed $item): ?string
    {
        if ($item instanceof CashFlow) {
            return $item->category->category_type ?? $item->category->type ?? null;
        }

        if (is_array($item)) {
            return $item['category']['category_type'] ?? $item['category']['type'] ?? ($item['category_type'] ?? null);
        }

        return null;
    }

    /**
     * Resolusi internal funding_source.
     */
    private static function resolveFundingSource(mixed $item): string
    {
        if ($item instanceof CashFlow) {
            return $item->funding_source ?? ($item->affects_drawer ? 'drawer' : 'owner');
        }

        if (is_array($item)) {
            return $item['funding_source'] ?? (!empty($item['affects_drawer']) ? 'drawer' : 'owner');
        }

        return is_string($item) ? $item : 'drawer';
    }

    /**
     * Resolusi internal affects_drawer.
     */
    private static function resolveAffectsDrawer(mixed $item, ?bool $explicit = null): bool
    {
        if ($explicit !== null) {
            return $explicit;
        }

        if ($item instanceof CashFlow) {
            return (bool) ($item->affects_drawer ?? ($item->funding_source === 'drawer'));
        }

        if (is_array($item)) {
            return (bool) ($item['affects_drawer'] ?? (($item['funding_source'] ?? '') === 'drawer'));
        }

        return true;
    }
}
