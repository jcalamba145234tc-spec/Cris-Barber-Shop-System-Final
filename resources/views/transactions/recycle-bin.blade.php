@extends('layouts.shop', ['heading' => 'Transaction Recycle Bin'])
@section('module')
<div class="transaction-list-heading">
    <div><p class="eyebrow">Archived records</p><h2>Review and restore transactions</h2><p class="muted">Records stay here until restored. No permanent deletion is available.</p></div>
    <a class="button secondary-button" href="{{ route('transactions.index') }}"><x-icon name="back" />Back to transactions</a>
</div>
<section class="panel" aria-labelledby="archived-records-heading">
    <div class="panel-heading"><h2 id="archived-records-heading">Archived transactions</h2><span class="badge">{{ $transactions->total() }} matching</span></div>
    <p class="transaction-notice">Restoring returns a record to the transaction list with its previous status. It does not complete a service, verify payment, deduct stock or return consumed stock. Queued, in-progress and ready records rejoin the active queue.</p>
    <form class="report-filters" method="GET" action="{{ route('transactions.recycle-bin') }}" aria-label="Archived transaction filters">
        <div class="archive-search"><label for="archive-q">Search archived transactions</label><input id="archive-q" type="search" name="q" maxlength="100" value="{{ request('q') }}" placeholder="Customer, service, ID or reference…"></div>
        <button type="submit">Search</button><a class="filter-reset" href="{{ route('transactions.recycle-bin') }}">Reset</a>
    </form>
    @if($transactions->isNotEmpty())
    <table class="archive-table"><thead><tr><th>Archived on</th><th>Customer / Barber</th><th>Service / Qty</th><th class="numeric">Total</th><th>Status / Payment</th><th>Actions</th></tr></thead><tbody>
    @foreach($transactions as $transaction)
        @php($serviceLabel = $transaction->services->isNotEmpty() ? $transaction->services->map(fn ($service) => $service->pivot->service_name.' × '.$service->pivot->quantity)->implode(', ') : ($transaction->service_name ?? 'Legacy service (not recorded)').' × '.$transaction->quantity)
        <tr>
            <td class="cell-date">{{ $transaction->deleted_at->format('M j, Y') }}<span class="cell-meta">{{ $transaction->deleted_at->format('g:i A') }}</span></td>
            <td><span class="cell-title" title="{{ $transaction->customer?->display_name ?? 'Walk-in' }}">{{ $transaction->customer?->display_name ?? 'Walk-in' }}</span><span class="cell-meta">{{ $transaction->barber?->name ?? 'Not recorded' }}</span></td>
            <td><span class="cell-title" title="{{ $serviceLabel }}">{{ $serviceLabel }}</span><span class="cell-meta archive-record-id" title="{{ $transaction->id }}">{{ $transaction->id }}</span></td>
            <td class="money">₱{{ number_format($transaction->total_amount, 2) }}</td>
            <td><span class="badge badge-{{ $transaction->status }}">{{ ucfirst(str_replace('_', ' ', $transaction->status)) }}</span><span class="cell-meta">{{ strtoupper($transaction->payment?->payment_method ?? 'missing') }} · {{ ucfirst($transaction->payment?->verification_status ?? 'missing') }}</span></td>
            <td><div class="row-actions"><a href="{{ route('transactions.archived-show', $transaction->id) }}">View record</a><form method="POST" action="{{ route('transactions.restore', $transaction->id) }}" data-confirm="Restore this transaction with its previous status? Active services will rejoin the queue. Payment and stock snapshots will not change.">@csrf<button type="submit" class="secondary-button" aria-label="Restore transaction {{ $transaction->id }}">Restore</button></form></div></td>
        </tr>
    @endforeach
    </tbody></table>
    @else
    <div class="empty-state">@if(request()->filled('q'))No archived transactions match your search. <a href="{{ route('transactions.recycle-bin') }}">Reset search</a>@else
The recycle bin is empty. Archived transactions will appear here. <a href="{{ route('transactions.index') }}">Back to transactions</a>@endif</div>
    @endif
    {{ $transactions->links() }}
</section>
@endsection
