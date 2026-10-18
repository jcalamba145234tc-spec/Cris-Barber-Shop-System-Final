<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'active' => ['nullable', 'in:0,1']]);
        $query = Service::with('products');
        if (! empty($data['q'])) {
            $query->where('name', 'like', '%'.$data['q'].'%');
        }
        if (isset($data['active'])) {
            $query->where('active', $data['active']);
        }

        return view('services.index', ['services' => $query->orderBy('name')->paginate(20)->withQueryString()]);
    }

    public function create()
    {
        return $this->form(new Service);
    }

    public function edit(Service $service)
    {
        return $this->form($service);
    }

    public function show(Service $service)
    {
        return view('services.show', ['service' => $service->load('products')]);
    }

    public function store(Request $request)
    {
        $service = $this->save($request, new Service);

        return redirect()->route('services.show', $service)->with('status', 'Service created.');
    }

    public function update(Request $request, Service $service)
    {
        $this->save($request, $service);

        return redirect()->route('services.show', $service)->with('status', 'Service updated. Existing transaction snapshots are unchanged.');
    }

    public function destroy(Service $service)
    {
        DB::transaction(fn () => Service::whereKey($service->id)->lockForUpdate()->firstOrFail()->update(['active' => false]));

        return redirect()->route('services.index')->with('status', 'Service deactivated; historical records retained.');
    }

    private function form(Service $service)
    {
        return view('services.form', ['service' => $service->load('products'), 'products' => Product::orderBy('name')->get()]);
    }

    private function save(Request $request, Service $service): Service
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:600'], 'active' => ['required', 'boolean'],
            'requirements' => ['nullable', 'array', 'max:100'],
            'requirements.*.product_id' => ['required', 'distinct', 'exists:products,id'],
            'requirements.*.quantity' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ]);
        $requirements = collect($data['requirements'] ?? [])->filter(fn ($r) => (float) ($r['quantity'] ?? 0) > 0)
            ->mapWithKeys(fn ($r) => [$r['product_id'] => ['quantity' => $r['quantity']]])->all();
        unset($data['requirements']);

        return DB::transaction(function () use ($service, $data, $requirements) {
            if ($service->exists) {
                $service = Service::whereKey($service->id)->lockForUpdate()->firstOrFail();
            }
            $service->fill($data)->save();
            $service->products()->sync($requirements);

            return $service;
        });
    }
}
