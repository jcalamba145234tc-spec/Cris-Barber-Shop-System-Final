<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $query = Customer::query();
        $term = trim((string) ($data['q'] ?? ''));
        if ($term !== '') {
            $pattern = '%'.mb_strtolower($term).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(phone) LIKE ?', [$pattern])->orWhereRaw('LOWER(email) LIKE ?', [$pattern]));
        }

        if ($request->wantsJson()) {
            return response()->json(['suggestions' => $term === '' ? [] : $query->orderBy('name')->orderBy('id')->limit(8)
                ->get(['id', 'name', 'phone', 'email'])->map(fn (Customer $customer) => [
                    'label' => $customer->name,
                    'value' => $customer->name,
                    'detail' => implode(' · ', array_filter([$customer->phone, $customer->email])),
                ])])->header('Cache-Control', 'no-store')->header('Vary', 'Accept');
        }

        return view('customers.index', ['customers' => $query->orderBy('name')->paginate(20)->withQueryString(), 'editing' => $request->filled('edit') ? Customer::findOrFail($request->integer('edit')) : null]);
    }

    public function create()
    {
        return redirect()->route('customers.index', ['add' => 1]);
    }

    public function edit(Customer $customer)
    {
        return redirect()->route('customers.index', ['edit' => $customer->id]);
    }

    public function show(Customer $customer)
    {
        return view('customers.show', ['customer' => $customer, 'transactions' => $customer->transactions()->with(['payment', 'services'])->latest('transaction_date')->paginate(20)]);
    }

    public function store(Request $request)
    {
        $customer = Customer::create($this->validated($request));

        return redirect()->route('customers.index')->with('status', 'Customer added.');
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($this->validated($request));

        return redirect()->route('customers.index')->with('status', 'Customer updated.');
    }

    public function destroy(Customer $customer)
    {
        DB::transaction(fn () => Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail()->delete());

        return redirect()->route('customers.index')->with('status', 'Customer archived; profile and historical transaction links retained.');
    }

    public function recycleBin()
    {
        return view('customers.recycle-bin', ['customers' => Customer::onlyTrashed()->withCount(['transactions' => fn ($query) => $query->withTrashed()])
            ->latest('deleted_at')->paginate(20)]);
    }

    public function restore(string $customer)
    {
        DB::transaction(fn () => Customer::onlyTrashed()->whereKey($customer)->lockForUpdate()->firstOrFail()->restore(), 3);

        return back()->with('status', 'Customer restored with all original transaction links.');
    }

    public function forceDelete(string $customer)
    {
        DB::transaction(function () use ($customer) {
            $record = Customer::onlyTrashed()->whereKey($customer)->lockForUpdate()->firstOrFail();
            if ($record->transactions()->withTrashed()->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['customer' => 'Customers with transactions cannot be permanently deleted, including archived transactions.']);
            }
            $record->forceDelete();
        }, 3);

        return back()->with('status', 'Customer permanently deleted. No transactions were linked.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
    }
}
