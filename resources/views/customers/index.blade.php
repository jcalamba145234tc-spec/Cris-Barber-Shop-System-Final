@extends('layouts.shop', ['heading' => 'Customers'])
@section('module')
<section class="panel">
<div class="panel-heading"><div><p class="eyebrow">Your community</p><h2>Customer records</h2></div><div class="row-actions"><a class="button secondary-button" href="{{ route('customers.recycle-bin') }}">Recycle Bin</a>@if($editing)<a class="button" href="{{ route('customers.index',['add'=>1]) }}">Add Customer</a>@else<button type="button" x-data @click="$dispatch('open-modal', 'customer-form')">Add Customer</button>@endif</div></div>
<form class="customer-search-toolbar" method="GET" action="{{ route('customers.index') }}" role="search" aria-label="Customer search">
    <x-search-autocomplete label="Search customers by name, email or phone" :endpoint="route('customers.index')" placeholder="Search name, email or phone…" />
    <div class="filter-toolbar-actions"><button type="submit">Search</button><a class="filter-reset" href="{{ route('customers.index') }}">Reset</a></div>
</form>
<table><thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Actions</th></tr></thead><tbody>
@forelse($customers as $customer)<tr><td><strong class="cell-title" title="{{ $customer->name }}">{{ $customer->name }}</strong></td><td>{{ $customer->phone ?? '—' }}</td><td>{{ $customer->email ?? '—' }}</td><td><div class="row-actions"><a href="{{ route('customers.show',$customer) }}">View</a><a href="{{ route('customers.index',['edit'=>$customer->id]) }}">Edit</a><form method="POST" action="{{ route('customers.destroy',$customer) }}" data-confirm="Archive {{ $customer->name }}? Their transactions and earnings will stay intact.">@csrf @method('DELETE')<button class="danger-button">Archive</button></form></div></td></tr>
@empty<tr><td colspan="4" class="empty-state">No matching customers. Add a customer to start building your client list.</td></tr>@endforelse
</tbody></table>{{ $customers->links() }}</section>
<x-modal name="customer-form" :title="$editing ? 'Edit Customer' : 'Add Customer'" :open="request()->boolean('add') || (bool)$editing">
<form method="POST" action="{{ $editing ? route('customers.update',$editing) : route('customers.store') }}">
@csrf @if($editing) @method('PUT') @endif<input type="hidden" name="_modal" value="customer-form">
<label for="customer-name">Customer name</label><input id="customer-name" name="name" maxlength="255" value="{{ old('name',$editing?->name) }}" autocomplete="name" required><x-field-error name="name" />
<label for="customer-phone">Phone <span class="muted">(optional)</span></label><input id="customer-phone" name="phone" type="tel" maxlength="30" value="{{ old('phone',$editing?->phone) }}"><x-field-error name="phone" />
<label for="customer-email">Email <span class="muted">(optional)</span></label><input id="customer-email" name="email" type="email" maxlength="255" value="{{ old('email',$editing?->email) }}"><x-field-error name="email" />
<div class="actions justify-end"><button type="button" class="secondary-button" x-data @click="$el.closest('dialog').close()">Cancel</button><button>Save customer</button></div></form>
</x-modal>
@endsection
