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
        return view('admin.customers.index', compact('customers'));
    }

    public function show(Customer $customer): View
    {
        // Global scope sudah memastikan admin hanya bisa melihat pelanggan bisnisnya
        $transactions = $this->customerService->getCustomerTransactions($customer);
        return view('admin.customers.show', compact('customer', 'transactions'));
    }

    /**
     * [BARU] Menampilkan form untuk mengedit data pelanggan.
     */
    public function edit(Customer $customer): View
    {
        return view('admin.customers.edit', compact('customer'));
    }

    /**
     * [BARU] Memproses pembaruan data pelanggan.
     */
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
}