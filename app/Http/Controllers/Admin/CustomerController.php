<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerDepositLog;
use App\Models\User;
use App\Services\Admin\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class CustomerController extends Controller
{
    protected CustomerService $customerService;

    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }

    public function index(): View
    {
        $customers = $this->customerService->getAllCustomersWithPagination();
        $monthlyStats = $this->customerService->getMonthlyCustomerStats();
        
        // [BARU] Ambil data statistik status
        $statusCounts = $this->customerService->getCustomerStatusCounts();
        
        return view('admin.customers.index', compact('customers', 'monthlyStats', 'statusCounts'));
    }

    public function show(Customer $customer): View
    {
        $businessId = (int) Auth::user()->business_id;
        if ((int) $customer->business_id !== $businessId) {
            abort(403, 'Akses ditolak.');
        }

        $transactions = $this->customerService->getCustomerTransactions($customer);

        $depositLogs = CustomerDepositLog::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('customer_id', $customer->id)
            ->with(['branch', 'createdBy', 'reversedBy', 'transaction', 'cashFlow.createdBy'])
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'deposit_page')
            ->withQueryString();

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

        $allCustomers = collect([$customer]);

        return view('admin.customers.show', compact(
            'customer',
            'transactions',
            'depositLogs',
            'branches',
            'cashiers',
            'allCustomers'
        ));
    }

    public function edit(Customer $customer): View
    {
        return view('admin.customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:191',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:191',
            'address' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        $this->customerService->updateCustomer($customer, $validatedData);

        return redirect()->route('admin.customers.index')->with('success', 'Data pelanggan berhasil diperbarui.');
    }

    /**
     * [BARU] Method untuk mengubah status dari halaman index
     */
    public function toggleStatus(Customer $customer): RedirectResponse
    {
        $this->customerService->toggleStatus($customer);
        
        $statusMsg = $customer->fresh()->status == 'active' ? 'diaktifkan' : 'dinonaktifkan';
        
        return redirect()->back()->with('success', "Pelanggan berhasil $statusMsg.");
    }
}