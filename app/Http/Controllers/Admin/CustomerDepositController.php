<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PeriodClosedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CorrectDepositRequest;
use App\Http\Requests\Admin\ReverseDepositRequest;
use App\Http\Requests\Admin\StoreAdminDepositRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerDepositLog;
use App\Models\User;
use App\Services\Accounting\CustomerDepositService;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CustomerDepositController extends Controller
{
    public function index(Request $request): View
    {
        $businessId = (int) Auth::user()->business_id;
        $contextBranchId = app(BranchContext::class)->getBranchId();
        $branchId = $request->filled('branch_id')
            ? (int) $request->input('branch_id')
            : ($contextBranchId ? (int) $contextBranchId : null);

        $search = trim((string) $request->input('search', ''));
        $typeFilter = (string) $request->input('type', 'all');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $activeTab = $request->input('tab', 'logs');

        $baseQuery = CustomerDepositLog::withoutGlobalScopes()
            ->where('business_id', $businessId);

        if ($branchId) {
            $baseQuery->where('branch_id', $branchId);
        }
        if ($startDate) {
            $baseQuery->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $baseQuery->whereDate('created_at', '<=', $endDate);
        }
        if ($search !== '') {
            $numericId = preg_replace('/[^0-9]/', '', $search);
            $baseQuery->where(function ($q) use ($search, $numericId) {
                $q->where('notes', 'LIKE', "%{$search}%")
                  ->orWhere('reversal_reason', 'LIKE', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'LIKE', "%{$search}%")
                         ->orWhere('phone', 'LIKE', "%{$search}%");
                  });
                if ($numericId !== '' && (str_starts_with($search, '#') || ctype_digit($search))) {
                    $q->orWhere('id', (int) $numericId)
                      ->orWhere('transaction_id', (int) $numericId);
                }
            });
        }

        // KPI Ringkasan
        $totalActiveDepositBalance = (float) Customer::where('business_id', $businessId)->sum('deposit_balance');
        $customersWithDepositCount = Customer::where('business_id', $businessId)->where('deposit_balance', '>', 0)->count();
        $periodCreditTotal = (float) (clone $baseQuery)->where('type', 'credit')->whereNull('reversed_at')->sum('amount');
        $periodDebitTotal = (float) (clone $baseQuery)->where('type', 'debit')->sum('amount');
        $periodReversedTotal = (float) (clone $baseQuery)->where('type', 'refund')->sum('amount');
        $periodReversedCount = (int) (clone $baseQuery)->where('type', 'refund')->count();

        // Filter Jenis Mutasi
        $logsQuery = clone $baseQuery;
        if ($typeFilter === 'active_credit') {
            $logsQuery->where('type', 'credit')->whereNull('reversed_at');
        } elseif ($typeFilter === 'reversed') {
            $logsQuery->where('type', 'credit')->whereNotNull('reversed_at');
        } elseif (in_array($typeFilter, ['credit', 'debit', 'refund', 'void_restore'], true)) {
            $logsQuery->where('type', $typeFilter);
        }

        $depositLogs = $logsQuery
            ->with(['customer', 'branch', 'createdBy', 'reversedBy', 'transaction', 'cashFlow.createdBy'])
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'logs_page')
            ->withQueryString();

        $custQuery = Customer::where('business_id', $businessId)
            ->where('deposit_balance', '>', 0);
        if ($search !== '') {
            $custQuery->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }
        $customersWithDeposit = $custQuery
            ->orderByDesc('deposit_balance')
            ->paginate(15, ['*'], 'cust_page')
            ->withQueryString();

        $allCustomers = Customer::where('business_id', $businessId)
            ->select('id', 'name', 'phone', 'deposit_balance')
            ->orderBy('name')
            ->get();

        $branches = Branch::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get();

        $cashiers = User::where('business_id', $businessId)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('role', 'kasir')
                  ->orWhereJsonContains('secondary_roles', 'kasir');
            })
            ->orderBy('name')
            ->get();

        Log::info('[DEPOSIT_DEBUG] ADMIN_INDEX_VIEW', [
            'user_id'          => Auth::id(),
            'business_id'      => $businessId,
            'branch_id'        => $branchId,
            'tab'              => $activeTab,
            'type_filter'      => $typeFilter,
            'search'           => $search,
            'logs_total'       => $depositLogs->total(),
            'active_dep_total' => $totalActiveDepositBalance,
        ]);

        return view('admin.deposits.index', compact(
            'depositLogs',
            'customersWithDeposit',
            'allCustomers',
            'branches',
            'cashiers',
            'branchId',
            'activeTab',
            'totalActiveDepositBalance',
            'customersWithDepositCount',
            'periodCreditTotal',
            'periodDebitTotal',
            'periodReversedTotal',
            'periodReversedCount'
        ));
    }

    public function store(StoreAdminDepositRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $businessId = (int) Auth::user()->business_id;
        $branchId = !empty($validated['branch_id'])
            ? (int) $validated['branch_id']
            : (app(BranchContext::class)->getBranchId() ?: (Auth::user()->branch_id ? (int) Auth::user()->branch_id : null));

        $mode = $validated['deposit_mode'];
        $amount = (float) $validated['amount'];
        $targetDate = !empty($validated['date']) ? Carbon::parse($validated['date']) : now();
        $notes = $validated['notes'] ?? null;

        Log::info('[DEPOSIT_DEBUG] ADMIN_STORE_REQUEST', [
            'admin_id'    => Auth::id(),
            'business_id' => $businessId,
            'branch_id'   => $branchId,
            'customer_id' => (int) $validated['customer_id'],
            'mode'        => $mode,
            'cashier_id'  => $validated['cashier_id'] ?? null,
            'amount'      => $amount,
            'target_date' => $targetDate->toDateString(),
            'notes'       => $notes,
        ]);

        try {
            if ($mode === 'opening_balance') {
                $log = CustomerDepositService::addOpeningDeposit(
                    $businessId,
                    $branchId,
                    (int) $validated['customer_id'],
                    $amount,
                    $notes,
                    (int) Auth::id(),
                    $targetDate
                );

                Log::info('[DEPOSIT_DEBUG] ADMIN_STORE_SUCCESS', [
                    'mode'           => 'opening_balance',
                    'deposit_log_id' => $log->id,
                    'customer_id'    => $log->customer_id,
                    'amount'         => $amount,
                    'balance_after'  => (float) $log->balance_after,
                ]);

                return back()->with(
                    'success',
                    'Saldo Awal / Titipan Lama #' . $log->id . ' sebesar Rp ' . number_format($amount, 0, ',', '.')
                    . ' berhasil ditambahkan tanpa mengubah kas laci hari ini.'
                );
            }

            $paymentMethod = in_array($mode, ['cash_owner', 'cash_drawer'], true) ? 'cash' : $mode;
            $affectsDrawer = ($mode === 'cash_drawer');
            $cashFlowCreator = ($mode === 'cash_drawer' && !empty($validated['cashier_id']))
                ? (int) $validated['cashier_id']
                : (int) Auth::id();

            $log = CustomerDepositService::addDeposit(
                $businessId,
                $branchId,
                (int) $validated['customer_id'],
                $amount,
                $paymentMethod,
                null,
                $notes ?: '[Input Admin]',
                (int) Auth::id(),
                $targetDate,
                $cashFlowCreator,
                $affectsDrawer
            );

            Log::info('[DEPOSIT_DEBUG] ADMIN_STORE_SUCCESS', [
                'mode'              => $mode,
                'deposit_log_id'    => $log->id,
                'cash_flow_id'      => $log->cash_flow_id,
                'customer_id'       => $log->customer_id,
                'amount'            => $amount,
                'affects_drawer'    => $affectsDrawer,
                'cash_flow_creator' => $cashFlowCreator,
                'balance_after'     => (float) $log->balance_after,
            ]);

            return back()->with(
                'success',
                'Setoran deposit #' . $log->id . ' sebesar Rp ' . number_format($amount, 0, ',', '.')
                . ' berhasil disimpan.'
            );
        } catch (PeriodClosedException $e) {
            Log::warning('[DEPOSIT_DEBUG] ADMIN_STORE_BLOCKED_PERIOD_CLOSED', [
                'admin_id' => Auth::id(),
                'message'  => $e->getMessage(),
            ]);
            return back()->with('error', $e->getMessage())->withInput();
        } catch (\Throwable $e) {
            Log::error('[DEPOSIT_DEBUG] ADMIN_STORE_ERROR', [
                'admin_id'  => Auth::id(),
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
                'location'  => $e->getFile() . ':' . $e->getLine(),
                'trace'     => array_slice(explode("\n", $e->getTraceAsString()), 0, 8),
            ]);
            return back()->with('error', 'Gagal menyimpan deposit: ' . $e->getMessage())->withInput();
        }
    }

    public function reverse(ReverseDepositRequest $request, int $depositLog): RedirectResponse
    {
        $validated = $request->validated();
        $businessId = (int) Auth::user()->business_id;

        Log::info('[DEPOSIT_DEBUG] ADMIN_REVERSE_REQUEST', [
            'admin_id'       => Auth::id(),
            'business_id'    => $businessId,
            'deposit_log_id' => $depositLog,
            'reason'         => $validated['reason'],
        ]);

        try {
            $contraLog = CustomerDepositService::reverseDeposit(
                $businessId,
                $depositLog,
                $validated['reason'],
                (int) Auth::id()
            );

            Log::info('[DEPOSIT_DEBUG] ADMIN_REVERSE_SUCCESS', [
                'original_log_id' => $depositLog,
                'contra_log_id'   => $contraLog->id,
                'cash_flow_id'    => $contraLog->cash_flow_id,
                'amount'          => (float) $contraLog->amount,
                'balance_after'   => (float) $contraLog->balance_after,
            ]);

            return back()->with(
                'success',
                "Setoran deposit #{$depositLog} berhasil dibatalkan (Void) dengan jurnal pembalik #{$contraLog->id}."
            );
        } catch (PeriodClosedException $e) {
            Log::warning('[DEPOSIT_DEBUG] ADMIN_REVERSE_BLOCKED_PERIOD_CLOSED', [
                'deposit_log_id' => $depositLog,
                'message'        => $e->getMessage(),
            ]);
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('[DEPOSIT_DEBUG] ADMIN_REVERSE_ERROR', [
                'admin_id'       => Auth::id(),
                'deposit_log_id' => $depositLog,
                'exception'      => get_class($e),
                'message'        => $e->getMessage(),
                'location'       => $e->getFile() . ':' . $e->getLine(),
                'trace'          => array_slice(explode("\n", $e->getTraceAsString()), 0, 8),
            ]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function correct(CorrectDepositRequest $request, int $depositLog): RedirectResponse
    {
        $validated = $request->validated();
        $businessId = (int) Auth::user()->business_id;

        Log::info('[DEPOSIT_DEBUG] ADMIN_CORRECT_REQUEST', [
            'admin_id'           => Auth::id(),
            'business_id'        => $businessId,
            'deposit_log_id'     => $depositLog,
            'new_amount'         => (float) $validated['new_amount'],
            'new_payment_method' => $validated['new_payment_method'],
            'reason'             => $validated['reason'],
        ]);

        try {
            $result = CustomerDepositService::correctDeposit(
                $businessId,
                $depositLog,
                (float) $validated['new_amount'],
                $validated['new_payment_method'],
                $validated['reason'],
                (int) Auth::id()
            );

            Log::info('[DEPOSIT_DEBUG] ADMIN_CORRECT_SUCCESS', [
                'original_log_id' => $depositLog,
                'reversal_log_id' => $result['reversal_log']->id,
                'new_log_id'      => $result['new_log']->id,
                'new_amount'      => (float) $result['new_log']->amount,
                'balance_after'   => (float) $result['new_log']->balance_after,
            ]);

            return back()->with(
                'success',
                "Setoran deposit #{$depositLog} dikoreksi menjadi Rp "
                . number_format((float) $validated['new_amount'], 0, ',', '.')
                . " (Log Pembatalan #{$result['reversal_log']->id} & Log Baru #{$result['new_log']->id})."
            );
        } catch (PeriodClosedException $e) {
            Log::warning('[DEPOSIT_DEBUG] ADMIN_CORRECT_BLOCKED_PERIOD_CLOSED', [
                'deposit_log_id' => $depositLog,
                'message'        => $e->getMessage(),
            ]);
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('[DEPOSIT_DEBUG] ADMIN_CORRECT_ERROR', [
                'admin_id'       => Auth::id(),
                'deposit_log_id' => $depositLog,
                'exception'      => get_class($e),
                'message'        => $e->getMessage(),
                'location'       => $e->getFile() . ':' . $e->getLine(),
                'trace'          => array_slice(explode("\n", $e->getTraceAsString()), 0, 8),
            ]);
            return back()->with('error', $e->getMessage());
        }
    }
}
