<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'string', 'max:100'],
            'stock_status' => ['nullable', 'in:in,low,out'], 'active' => ['nullable', 'in:0,1'],
        ]);
        $query = Product::query();
        if (! empty($filters['q'])) {
            $query->where('name', 'like', '%'.$filters['q'].'%');
        }
        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (isset($filters['active'])) {
            $query->where('active', $filters['active']);
        }
        match ($filters['stock_status'] ?? '') {
            'out' => $query->where('stock', '<=', 0),
            'low' => $query->where('stock', '>', 0)->whereColumn('stock', '<=', 'low_stock_threshold'),
            'in' => $query->whereColumn('stock', '>', 'low_stock_threshold'),
            default => null,
        };

        return view('inventory.index', [
            'products' => $query->orderBy('name')->paginate(20)->withQueryString(),
            'categories' => Product::whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function create()
    {
        return view('inventory.form', ['product' => new Product]);
    }

    public function store(Request $request)
    {
        $data = $this->validateProduct($request, true);
        $product = DB::transaction(function () use ($data, $request) {
            $product = Product::create($data);
            InventoryMovement::create([
                'product_id' => $product->id, 'user_id' => $request->user()->id,
                'type' => 'opening', 'quantity' => $product->stock, 'balance' => $product->stock,
                'notes' => 'Opening manual stock',
            ]);

            return $product;
        });

        return redirect()->route('inventory.show', $product)->with('status', 'Product created.');
    }

    public function show(Product $product)
    {
        return view('inventory.show', ['product' => $product, 'movements' => $product->movements()->latest('id')->paginate(20)]);
    }

    public function edit(Product $product)
    {
        return view('inventory.form', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validateProduct($request, false);
        DB::transaction(fn () => Product::whereKey($product->id)->lockForUpdate()->firstOrFail()->update($data));

        return redirect()->route('inventory.show', $product)->with('status', 'Product updated. Use restock for quantity increases.');
    }

    public function restock(Request $request, Product $product)
    {
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'decimal:0,3', 'min:0.001', 'max:999999'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        DB::transaction(function () use ($product, $data, $request) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            if (! $locked->active || (float) $locked->stock + (float) $data['quantity'] > 999999999.999) {
                throw ValidationException::withMessages(['quantity' => 'Activate this product first or reduce the restock quantity.']);
            }
            Product::whereKey($locked->id)->update(['stock' => DB::raw('ROUND(stock + '.sprintf('%.3F', (float) $data['quantity']).', 3)')]);
            $locked->refresh();
            InventoryMovement::create([
                'product_id' => $locked->id, 'user_id' => $request->user()->id, 'type' => 'restock',
                'quantity' => $data['quantity'], 'balance' => $locked->stock, 'notes' => $data['notes'] ?? null,
            ]);
        }, 3);

        return back()->with('status', 'Restock added to the existing quantity.');
    }

    public function destroy(Product $product)
    {
        DB::transaction(fn () => Product::whereKey($product->id)->lockForUpdate()->firstOrFail()->update(['active' => false]));

        return redirect()->route('inventory.index')->with('status', 'Product deactivated; stock and movement history retained.');
    }

    private function validateProduct(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:100'], 'unit' => ['required', 'string', 'max:30'],
            'stock' => $creating ? ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999.999'] : ['prohibited'],
            'low_stock_threshold' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999.999'],
            'cost' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'active' => ['required', 'boolean'],
        ]);
    }
}
