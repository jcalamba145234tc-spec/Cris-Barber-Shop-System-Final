<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $query = Customer::query();
        if (! empty($data['q'])) {
            $term = '%'.$data['q'].'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('phone', 'like', $term)->orWhere('email', 'like', $term));
        }

        return view('customers.index', ['customers' => $query->orderBy('name')->paginate(20)->withQueryString()]);
    }

    public function create()
    {
        return view('customers.form', ['customer' => new Customer]);
    }

    public function edit(Customer $customer)
    {
        return view('customers.form', compact('customer'));
    }

    public function show(Customer $customer)
    {
        return view('customers.show', ['customer' => $customer, 'transactions' => $customer->transactions()->with('payment')->latest('transaction_date')->paginate(20)]);
    }

    public function store(Request $request)
    {
        $customer = Customer::create($this->validated($request));

        return redirect()->route('customers.show', $customer)->with('status', 'Customer added.');
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($this->validated($request));

        return redirect()->route('customers.show', $customer)->with('status', 'Customer updated.');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return redirect()->route('customers.index')->with('status', 'Customer removed; transactions retained without personal link.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
    }
}
