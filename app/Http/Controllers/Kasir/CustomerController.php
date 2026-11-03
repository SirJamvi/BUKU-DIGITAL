<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kasir\StoreCustomerRequest;
use App\Http\Requests\Kasir\UpdateCustomerRequest;
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
     * Mendukung pencarian via query string ?search=
     * Kasir memiliki akses lihat dan tambah pelanggan.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $search = $request->query('search');

        $customers = $this->customerService->getAllCustomersWithPagination(15, $search);

        return view('kasir.customers.index', compact('customers', 'search'));
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
     * Menampilkan detail dan riwayat pembelian pelanggan.
     *
     * @param Customer $customer
     * @return View
     */
    public function show(Customer $customer): View
    {
        $customerData = $this->customerService->getCustomerDetails($customer);
        return view('kasir.customers.show', $customerData);
    }

    /**
     * Menampilkan form edit pelanggan.
     *
     * @param Customer $customer
     * @return View
     */
    public function edit(Customer $customer): View
    {
        if ($customer->business_id !== auth()->user()->business_id) {
            abort(404);
        }

        return view('kasir.customers.edit', compact('customer'));
    }

    /**
     * Memperbarui data pelanggan.
     *
     * @param UpdateCustomerRequest $request
     * @param Customer $customer
     * @return RedirectResponse
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        try {
            $this->customerService->updateCustomer($customer, $request->validated());
            return redirect()->route('kasir.customers.index')->with('success', 'Data pelanggan berhasil diperbarui.');
        } catch (\Exception $e) {
            logger()->error('Error updating customer from kasir: ' . $e->getMessage());
            return back()->with('error', 'Gagal memperbarui data pelanggan.')->withInput();
        }
    }
}
