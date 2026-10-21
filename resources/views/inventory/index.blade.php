@extends('layouts.shop', ['heading' => 'Inventory'])

@section('module')
<div class="stats compact-stats grid grid-cols-1 sm:grid-cols-3" id="inventory-summary">@foreach(['products'=>'Matching products','low'=>'Low Stock','out'=>'Out of Stock'] as $key=>$label)<article class="stat"><span>{{ $label }}</span><strong>{{ $stockSummary[$key] }}</strong></article>@endforeach</div>
<section class="panel" data-live-region="inventory-results">
    <div class="panel-heading">
        <h2>Manual product stock</h2>
        <a class="button" href="{{ route('inventory.create') }}">Add product</a>
    </div>
    <details class="filter-disclosure" open><summary>Search &amp; filters</summary>
<form class="report-filters" method="GET" data-live-search>
        <x-search-autocomplete label="Product search" :endpoint="route('inventory.index')" placeholder="Search products…" describedby="inventory-search-status" />
        <div><label for="category">Category</label><select id="category" name="category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>@endforeach</select></div>
        <div><label for="stock_status">Stock status</label><select id="stock_status" name="stock_status"><option value="">All stock</option>@foreach(['in'=>'In Stock','low'=>'Low Stock','out'=>'Out of Stock'] as $value=>$label)<option value="{{ $value }}" @selected(request('stock_status') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div><label for="active">Product status</label><select id="active" name="active"><option value="">All statuses</option><option value="1" @selected(request('active') === '1')>Active</option><option value="0" @selected(request('active') === '0')>Inactive</option></select></div>
        <button>Filter</button><a href="{{ route('inventory.index') }}">Reset</a>
    </form>
</details>
    <p id="inventory-search-status" class="muted field-hint" role="status" aria-live="polite">Partial keyword search · {{ $products->total() }} {{ Illuminate\Support\Str::plural('product', $products->total()) }}</p>
    <div id="inventory-results">
    <table>
        <thead><tr><th>Product</th><th>Category</th><th>Stock / Unit</th><th>Threshold</th><th>Stock status</th><th>Product status</th><th>Actions</th></tr></thead>
        <tbody>
            @forelse($products as $product)
                <tr>
                    <td><span class="cell-title" title="{{ $product->name }}">{{ $product->name }}</span></td>
                    <td>{{ $product->category ?? '—' }}</td>
                    <td>{{ \App\Support\WholeQuantity::display($product->stock) }} {{ $product->unit }}</td>
                    <td>{{ \App\Support\WholeQuantity::display($product->low_stock_threshold) }}</td>
                    <td><span @class(['badge', 'badge-danger' => $product->stock <= 0, 'badge-warning' => $product->stock > 0 && $product->stock <= $product->low_stock_threshold, 'badge-positive' => $product->stock > $product->low_stock_threshold])>{{ $product->stock_status }}</span></td>
                    <td>{{ $product->active ? 'Active' : 'Inactive' }}</td>
                    <td>
                        <div class="row-actions">
                            <a href="{{ route('inventory.show', $product) }}">View / Restock</a>
                            <a href="{{ route('inventory.edit', $product) }}">Edit</a>
                            <form method="POST" action="{{ route('inventory.status', $product) }}" data-confirm="{{ $product->active ? 'Deactivate this product? Linked services will no longer be available.' : 'Activate this product?' }}">
                                @csrf @method('PATCH')
                                <button class="{{ $product->active ? 'danger-button' : 'secondary-button activate-product-button' }}">{{ $product->active ? 'Deactivate' : 'Activate' }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">No matching products. <a href="{{ route('inventory.index') }}">Reset filters</a></td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $products->links() }}
    </div>
</section>
@endsection
