<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Admin\CustomerService;
use Illuminate\Http\Request;
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
        $transactions = $this->customerService->getCustomerTransactions($customer);
        return view('admin.customers.show', compact('customer', 'transactions'));
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