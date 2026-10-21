@extends('layouts.shop', ['heading' => 'Customer Recycle Bin'])
@section('module')
<section class="panel">
<div class="panel-heading"><div><p class="eyebrow">Safe to restore</p><h2>Archived customers</h2></div><a class="button secondary-button" href="{{ route('customers.index') }}">Back to customers</a></div>
<p class="muted">Archiving never removes transactions, payments, or earnings. Permanent deletion is available only for customers who have never had a transaction, including archived transactions.</p>
<table><thead><tr><th>Customer</th><th>Archived on</th><th>Transactions</th><th>Actions</th></tr></thead><tbody>
@forelse($customers as $customer)
<tr><td>{{ $customer->name }}</td><td>{{ $customer->deleted_at->format('M j, Y g:i A') }}</td><td>{{ $customer->transactions_count }}</td><td><div class="row-actions">
<form method="POST" action="{{ route('customers.restore',$customer->id) }}" data-confirm="Restore {{ $customer->name }} and their original transaction links?">@csrf<button>Restore</button></form>
@if($customer->transactions_count === 0)<form method="POST" action="{{ route('customers.permanent',$customer->id) }}" data-confirm="Permanently delete {{ $customer->name }}? This cannot be undone.">@csrf @method('DELETE')<button class="danger-button">Delete permanently</button></form>@else<span class="muted">Protected: linked transactions</span>@endif
</div></td></tr>
@empty<tr><td colspan="4" class="empty-state">The recycle bin is empty. Archived customers will appear here.</td></tr>@endforelse
</tbody></table>{{ $customers->links() }}
</section>
@endsection
