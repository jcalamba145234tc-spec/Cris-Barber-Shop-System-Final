@extends('layouts.shop', ['heading' => $product->exists ? 'Edit product' : 'Add product'])
@section('module')
<section class="panel"><form method="POST" action="{{ $product->exists ? route('inventory.update',$product) : route('inventory.store') }}">@csrf @if($product->exists) @method('PUT') @endif
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
<div><label for="name">Product name</label><input id="name" name="name" maxlength="100" value="{{ old('name',$product->name) }}" required></div>
<div><label for="category">Category</label><input id="category" name="category" maxlength="100" value="{{ old('category',$product->category) }}"></div>
<div><label for="unit">Unit</label><input id="unit" name="unit" maxlength="30" value="{{ old('unit',$product->unit ?? 'piece') }}" required></div>
@if(!$product->exists)<div><label for="stock">Opening stock</label><input id="stock" name="stock" type="number" min="0" step="1" value="{{ old('stock',0) }}" required></div>@else<div><label>Current stock (read-only)</label><p>{{ \App\Support\WholeQuantity::display($product->stock) }} {{ $product->unit }} — use restock to add stock.</p></div>@endif
<div><label for="low_stock_threshold">Low-stock threshold</label><input id="low_stock_threshold" name="low_stock_threshold" type="number" min="0" step="1" value="{{ old('low_stock_threshold',\App\Support\WholeQuantity::text($product->low_stock_threshold ?? 5)) }}" required></div>
<div><label for="cost">Unit cost (optional)</label><input id="cost" name="cost" type="number" min="0" step="0.01" value="{{ old('cost',$product->cost) }}"></div>
<div><label for="active">Status</label><select id="active" name="active"><option value="1" @selected((string)old('active',$product->active ?? 1) === '1')>Active</option><option value="0" @selected((string)old('active',$product->active ?? 1) === '0')>Inactive</option></select></div>
</div><label for="description">Description</label><textarea id="description" name="description" maxlength="2000">{{ old('description',$product->description) }}</textarea>
<div class="actions"><button>Save product</button><a href="{{ route('inventory.index') }}">Cancel</a></div></form></section>
@endsection
