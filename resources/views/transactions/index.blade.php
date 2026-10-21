@extends('layouts.shop', ['heading' => auth()->user()->role === 'admin' ? 'Queue / Transactions' : 'My Queue'])
@section('module')
<div class="stats compact-stats grid grid-cols-1 sm:grid-cols-3">@foreach(['active'=>'Active queue','completed'=>'Completed transactions','pending'=>'Pending payment verification'] as $key=>$label)<article class="stat"><span>{{ $label }}</span><strong>{{ $queueSummary[$key] }}</strong></article>@endforeach</div>
<section class="panel">
<details class="filter-disclosure" open><summary>Search &amp; filters</summary>
<form class="report-filters" method="GET">
    <div><label for="q">Search ID, customer, service or reference</label><input id="q" name="q" value="{{ request('q') }}"></div>
    <div><label for="from">From date</label><input id="from" name="from" type="date" value="{{ request('from') }}"></div>
    <div><label for="to">To date</label><input id="to" name="to" type="date" value="{{ request('to') }}"></div>
    <div><label for="barber_id">Barber</label><select id="barber_id" name="barber_id"><option value="">All permitted barbers</option>@foreach($barbers as $barber)<option value="{{ $barber->id }}" @selected(request('barber_id') === $barber->id)>{{ $barber->name }}</option>@endforeach</select></div>
    <div><label for="service_id">Service</label><select id="service_id" name="service_id"><option value="">All services</option>@foreach($services as $service)<option value="{{ $service->id }}" @selected(request('service_id') === $service->id)>{{ $service->name }}</option>@endforeach</select></div>
    <div><label for="payment_method">Payment method</label><select id="payment_method" name="payment_method"><option value="">All methods</option>@foreach(['cash','gcash'] as $method)<option @selected(request('payment_method') === $method) value="{{ $method }}">{{ strtoupper($method) }}</option>@endforeach</select></div>
    <div><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option>@foreach(['queued','in_progress','ready','completed','cancelled'] as $status)<option @selected(request('status') === $status) value="{{ $status }}">{{ ucfirst(str_replace('_',' ',$status)) }}</option>@endforeach</select></div>
    <div><label for="claim_status">Earnings status</label><select id="claim_status" name="claim_status"><option value="">All earnings</option><option value="unclaimed" @selected(request('claim_status') === 'unclaimed')>Unclaimed</option><option value="claimed" @selected(request('claim_status') === 'claimed')>Claimed</option></select></div>
    <button>Filter</button><a href="{{ url()->current() }}">Reset</a>
</form>
</details>
<table><thead><tr><th>Date</th><th>Customer</th><th>Barber</th><th>Service / Qty</th><th>Total</th><th>{{ auth()->user()->role === 'admin' ? 'Barber share' : 'Your earnings' }}</th><th>Payment</th><th>Status</th><th>Earnings status</th><th>Actions</th></tr></thead><tbody>
@forelse($transactions as $transaction)
<tr><td>{{ $transaction->transaction_date?->format('M d, Y g:i A') ?? $transaction->created_at?->format('M d, Y') }}</td><td>{{ $transaction->customer?->display_name ?? 'Walk-in' }}</td><td>{{ $transaction->barber->name }}</td><td>{{ $transaction->services->isNotEmpty() ? $transaction->services->map(fn ($service) => $service->pivot->service_name.' × '.$service->pivot->quantity)->implode(', ') : ($transaction->service_name ?? 'Legacy service (not recorded)').' × '.$transaction->quantity }}</td><td>₱{{ number_format($transaction->total_amount,2) }}</td><td>₱{{ number_format($transaction->status === 'completed' && $transaction->payment?->verification_status === 'verified' ? $transaction->barber_share : 0,2) }}</td><td>{{ strtoupper($transaction->payment?->payment_method ?? '—') }} / {{ $transaction->payment?->verification_status ?? 'missing' }}</td><td><span class="badge">{{ ucfirst(str_replace('_',' ',$transaction->status)) }}</span></td><td>@include('transactions.claim-status')</td><td><div class="row-actions"><a href="{{ route('transactions.show',$transaction) }}">View</a>@if(auth()->user()->role === 'admin')<a href="{{ route('transactions.edit',$transaction) }}">Edit</a>@if($transaction->status !== 'completed')<form method="POST" action="{{ route('transactions.destroy',$transaction) }}" data-confirm="Archive this transaction? Audit data remains; consumed stock will not be restored.">@csrf @method('DELETE')<button>Remove</button></form>@endif @endif</div></td></tr>
@empty<tr><td colspan="10" class="empty-state">No matching transactions. Try adjusting the filters.</td></tr>@endforelse
</tbody></table>{{ $transactions->links() }}
</section>
@endsection
