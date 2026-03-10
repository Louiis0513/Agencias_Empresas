<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Models\Store;
use App\Models\Customer;
use App\Services\CustomerService;
use App\Services\StorePermissionService;
use Illuminate\Http\Request;

class StoreCustomerController extends Controller
{
    public function index(Store $store, Request $request, CustomerService $customerService, StorePermissionService $permission)
    {
        $permission->authorize($store, 'customers.view');

        $filtros = [
            'search' => $request->get('search'),
        ];

        $customers = $customerService->getStoreCustomers($store, $filtros);

        return view('stores.customer.clientes', compact('store', 'customers'));
    }

    public function store(Store $store, StoreCustomerRequest $request, CustomerService $customerService, StorePermissionService $permission)
    {
        $permission->authorize($store, 'customers.create');

        $data = $request->validated();
        $data['phone'] = $this->buildFullPhone($data['phone_country_code'] ?? '', $data['phone'] ?? '');
        unset($data['phone_country_code']);

        try {
            $customerService->createCustomer($store, $data);
            return redirect()->route('stores.customers', $store)
                ->with('success', 'Cliente creado correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.customers', $store)
                ->with('error', $e->getMessage());
        }
    }

    public function update(Store $store, Customer $customer, StoreCustomerRequest $request, CustomerService $customerService, StorePermissionService $permission)
    {
        $permission->authorize($store, 'customers.edit');

        if ($customer->store_id !== $store->id) {
            abort(404);
        }

        $data = $request->validated();
        $data['phone'] = $this->buildFullPhone($data['phone_country_code'] ?? '', $data['phone'] ?? '');
        unset($data['phone_country_code']);

        try {
            $customerService->updateCustomer($store, $customer->id, $data);
            return redirect()->route('stores.customers', $store)
                ->with('success', 'Cliente actualizado correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.customers', $store)
                ->with('error', $e->getMessage());
        }
    }

    public function destroy(Store $store, Customer $customer, CustomerService $customerService, StorePermissionService $permission)
    {
        $permission->authorize($store, 'customers.destroy');

        if ($customer->store_id !== $store->id) {
            abort(404);
        }

        try {
            $customerService->deleteCustomer($store, $customer->id);
            return redirect()->route('stores.customers', $store)
                ->with('success', 'Cliente eliminado correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.customers', $store)
                ->with('error', $e->getMessage());
        }
    }

    private function buildFullPhone(string $code, string $number): ?string
    {
        $digits = preg_replace('/\D/', '', $number);
        if ($digits === '') {
            return null;
        }
        $code = preg_replace('/\D/', '', $code);

        return $code !== '' ? '+'.$code.$digits : '+'.$digits;
    }
}
