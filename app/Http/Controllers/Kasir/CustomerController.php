<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kasir\StoreCustomerRequest;
use App\Models\Customer;
use App\Services\Kasir\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    /**
     * @var CustomerService
     */
    protected $customerService;

    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }

    /**
     * Menampilkan daftar pelanggan.
     * [cite_start]Kasir memiliki akses lihat dan tambah pelanggan. [cite: 88]
     *
     * @return View
     */
    public function index(): View
    {
        $customers = $this->customerService->getAllCustomersWithPagination();
        return view('kasir.customers.index', compact('customers'));
    }

    /**
     * Menampilkan form untuk membuat pelanggan baru.
     *
     * @return View
     */
    public function create(): View
    {
        return view('kasir.customers.create');
    }

    /**
     * Menyimpan data pelanggan baru.
     *
     * @param StoreCustomerRequest $request
     * @return RedirectResponse
     */
    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        try {
            $this->customerService->createCustomer($request->validated());
            return redirect()->route('kasir.customers.index')->with('success', 'Pelanggan baru berhasil ditambahkan.');
        } catch (\Exception $e) {
            logger()->error('Error creating customer from kasir: ' . $e->getMessage());
            return back()->with('error', 'Gagal menambahkan pelanggan.')->withInput();
        }
    }

    /**
     * [cite_start]Menampilkan detail dan riwayat pembelian pelanggan. [cite: 88]
     *
     * @param Customer $customer
     * @return View
     */
    public function show(Customer $customer): View
    {
        $customerData = $this->customerService->getCustomerDetails($customer);
        return view('kasir.customers.show', $customerData);
    }
}   