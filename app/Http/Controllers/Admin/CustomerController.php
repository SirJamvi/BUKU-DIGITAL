<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Admin\CustomerService;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
        $transactions = $this->customerService->getCustomerTransactions($customer);
        return view('admin.customers.show', compact('customer', 'transactions'));
    }
}