<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @group Customers
 */
class CustomerController extends Controller
{
    use ApiResponse;

    public function __construct(private CustomerService $service) {}

    public function index()
    {
        return view('customers.index');
    }

    public function all(Request $request): JsonResponse
    {
        $customers = Customer::query()
            ->when($request->search, function ($q, $s) {
                $safe = addcslashes($s, '\%_');
                $q->where(function ($q) use ($safe) {
                    $q->where('name', 'like', "%{$safe}%")
                        ->orWhere('phone', 'like', "%{$safe}%")
                        ->orWhere('code', 'like', "%{$safe}%");
                });
            })
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when(! $request->boolean('with_inactive'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->paginate($request->per_page ?? 20);

        return $this->success($customers->toArray());
    }

    public function search(Request $request): JsonResponse
    {
        $q = $request->get('q', '');

        // addcslashes(, '\%_') is not available in all Laravel 11.x builds — escape manually
        $safe = addcslashes($q, '%_\\');
        $customers = Customer::where('is_active', true)
            ->where(function ($query) use ($safe) {
                $query->where('name', 'like', "%{$safe}%")
                    ->orWhere('phone', 'like', "%{$safe}%")
                    ->orWhere('code', 'like', "%{$safe}%");
            })
            ->select('id', 'code', 'name', 'phone', 'type', 'balance', 'loyalty_points')
            ->orderBy('name')
            ->limit(10)
            ->get();

        return $this->success(['customers' => $customers]);
    }

    /**
     * Quick-create a customer from the POS (name + phone only).
     * Accessible to view_pos users without full CRM permissions.
     */
    public function quickCreate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'  => 'required|string|max:150',
            'phone' => 'nullable|string|max:20',
        ]);

        $data['code'] = $this->service->nextCode();
        $data['type'] = 'individual';
        $data['is_active'] = true;

        $customer = Customer::create($data);

        return $this->success([
            'customer' => [
                'id'    => $customer->id,
                'name'  => $customer->name,
                'phone' => $customer->phone,
                'code'  => $customer->code,
            ],
        ], '', 201);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['code'] = $this->service->nextCode();
        $data['type'] = $data['type'] ?? 'individual';

        $customer = Customer::create($data);

        return $this->success(['customer' => new CustomerResource($customer)], '', 201);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer->update($request->validated());

        return $this->success(['customer' => new CustomerResource($customer->fresh())]);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        if ($customer->invoices()->exists()) {
            return $this->error(__('pos.customer_has_invoices'), 422);
        }

        $customer->delete();

        return $this->success([], __('pos.customer_deleted'));
    }

    public function show(Customer $customer): JsonResponse
    {
        $customer->load('accountEntries');

        return $this->success(['customer' => new CustomerResource($customer)]);
    }
}
