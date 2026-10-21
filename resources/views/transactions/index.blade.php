@extends('layouts.shop', ['heading' => auth()->user()->role === 'admin' ? 'Queue / Transactions' : 'My Queue'])
@section('module')
<div class="stats compact-stats grid grid-cols-1 sm:grid-cols-3">@foreach(['active'=>'Active queue','completed'=>'Completed transactions','pending'=>'Pending payment verification'] as $key=>$label)<article class="stat"><span>{{ $label }}</span><strong>{{ $queueSummary[$key] }}</strong></article>@endforeach</div>
@if(auth()->user()->role === 'admin')
<div class="transaction-list-heading"><div><h2>Transaction records</h2><p class="muted">Manage services, payments and the active queue.</p></div><a class="button secondary-button" href="{{ route('transactions.recycle-bin') }}">Recycle Bin</a></div>
@endif
<section class="panel">
@php
    $advancedFilterKeys = ['from', 'to', 'service_id', 'payment_method', 'claim_status'];
    if (auth()->user()->role === 'admin') $advancedFilterKeys[] = 'barber_id';
    $advancedFilterCount = count(array_filter($advancedFilterKeys, fn ($key) => request()->filled($key)));
@endphp
<form class="queue-filter-toolbar" method="GET" action="{{ route('transactions.index') }}" aria-label="Transaction filters">
    <div class="queue-filter-primary">
        <div class="queue-search-field"><label for="q">Search transactions</label><input type="search" id="q" name="q" maxlength="100" placeholder="Customer, service, ID or reference…" value="{{ request('q') }}"></div>
        <div><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option>@foreach(['queued','in_progress','ready','completed','cancelled'] as $status)<option @selected(request('status') === $status) value="{{ $status }}">{{ ucfirst(str_replace('_',' ',$status)) }}</option>@endforeach</select></div>
        <div class="filter-toolbar-actions"><button type="submit">Apply filters</button><a class="filter-reset" href="{{ route('transactions.index') }}">Reset</a></div>
    </div>
    <details class="queue-advanced-filters" @if($advancedFilterCount > 0) open @endif>
        <summary>More filters @if($advancedFilterCount > 0)<span class="filter-count">{{ $advancedFilterCount }} active</span>@endif</summary>
        <div class="queue-filter-grid">
            <div><label for="from">From date</label><input id="from" name="from" type="date" value="{{ request('from') }}"></div>
            <div><label for="to">To date</label><input id="to" name="to" type="date" value="{{ request('to') }}"></div>
            @if(auth()->user()->role === 'admin')<div><label for="barber_id">Barber</label><select id="barber_id" name="barber_id"><option value="">All barbers</option>@foreach($barbers as $barber)<option value="{{ $barber->id }}" @selected(request('barber_id') === $barber->id)>{{ $barber->name }}</option>@endforeach</select></div>@endif
            <div><label for="service_id">Service</label><select id="service_id" name="service_id"><option value="">All services</option>@foreach($services as $service)<option value="{{ $service->id }}" @selected(request('service_id') === $service->id)>{{ $service->name }}</option>@endforeach</select></div>
            <div><label for="payment_method">Payment method</label><select id="payment_method" name="payment_method"><option value="">All methods</option>@foreach(['cash','gcash'] as $method)<option @selected(request('payment_method') === $method) value="{{ $method }}">{{ strtoupper($method) }}</option>@endforeach</select></div>
            <div><label for="claim_status">Earnings status</label><select id="claim_status" name="claim_status"><option value="">All earnings</option><option value="unclaimed" @selected(request('claim_status') === 'unclaimed')>Unclaimed</option><option value="claimed" @selected(request('claim_status') === 'claimed')>Claimed</option></select></div>
        </div>
    </details>
</form>
<table class="queue-table"><thead><tr><th>Date</th><th>Customer / Barber</th><th>Service / Qty</th><th class="numeric">Total</th><th class="numeric">{{ auth()->user()->role === 'admin' ? 'Barber share' : 'Your earnings' }}</th><th>Payment / Status</th><th>Earnings status</th><th>Actions</th></tr></thead><tbody>
@forelse($transactions as $transaction)
@php($serviceLabel = $transaction->services->isNotEmpty() ? $transaction->services->map(fn ($service) => $service->pivot->service_name.' × '.$service->pivot->quantity)->implode(', ') : ($transaction->service_name ?? 'Legacy service (not recorded)').' × '.$transaction->quantity)
<tr>
    <td class="cell-date">{{ $transaction->transaction_date?->format('M j, Y') ?? $transaction->created_at?->format('M j, Y') }}<span class="cell-meta">{{ $transaction->transaction_date?->format('g:i A') }}</span><span class="sr-only">{{ $transaction->id }}</span></td>
    <td><span class="cell-title" title="{{ $transaction->customer?->display_name ?? 'Walk-in' }}">{{ $transaction->customer?->display_name ?? 'Walk-in' }}</span><span class="cell-meta">{{ $transaction->barber->name }}</span></td>
    <td><span class="cell-title" title="{{ $serviceLabel }}">{{ $serviceLabel }}</span></td>
    <td class="money">₱{{ number_format($transaction->total_amount,2) }}</td>
    <td class="money">₱{{ number_format($transaction->status === 'completed' && $transaction->payment?->verification_status === 'verified' ? $transaction->barber_share : 0,2) }}</td>
    <td><span class="badge badge-{{ $transaction->status }}">{{ ucfirst(str_replace('_',' ',$transaction->status)) }}</span><span class="cell-meta">{{ strtoupper($transaction->payment?->payment_method ?? '—') }} · {{ ucfirst($transaction->payment?->verification_status ?? 'missing') }}</span></td>
    <td>@include('transactions.claim-status')</td>
    <td><div class="row-actions"><a href="{{ route('transactions.show',$transaction) }}">View</a>@if(auth()->user()->role === 'admin')<a href="{{ route('transactions.edit',$transaction) }}">Edit</a>@if($transaction->status !== 'completed')<form method="POST" action="{{ route('transactions.destroy',$transaction) }}" data-confirm="Move this transaction to the recycle bin? You can view or restore it there. Consumed stock will not be returned.">@csrf @method('DELETE')<button class="danger-button">Archive</button></form>@endif @endif</div></td>
</tr>
@empty<tr><td colspan="8" class="empty-state">No matching transactions. Try adjusting the filters. <a href="{{ route('transactions.index') }}">Reset filters</a></td></tr>@endforelse
</tbody></table>{{ $transactions->links() }}
</section>
@endsection
