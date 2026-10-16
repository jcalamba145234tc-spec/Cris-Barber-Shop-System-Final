@extends('layouts.shop', ['heading' => request()->routeIs('history') ? 'History' : (auth()->user()->role === 'admin' ? 'Queue / Transactions' : 'My Queue')])
@section('module')
<section class="panel">
<form class="report-filters" method="GET">
    <div><label for="q">Search ID, customer, service or reference</label><input id="q" name="q" value="{{ request('q') }}"></div>
    <div><label for="from">From date</label><input id="from" name="from" type="date" value="{{ request('from') }}"></div>
    <div><label for="to">To date</label><input id="to" name="to" type="date" value="{{ request('to') }}"></div>
    <div><label for="barber_id">Barber</label><select id="barber_id" name="barber_id"><option value="">All permitted barbers</option>@foreach($barbers as $barber)<option value="{{ $barber->id }}" @selected(request('barber_id') === $barber->id)>{{ $barber->name }}</option>@endforeach</select></div>
    <div><label for="service_id">Service</label><select id="service_id" name="service_id"><option value="">All services</option>@foreach($services as $service)<option value="{{ $service->id }}" @selected(request('service_id') === $service->id)>{{ $service->name }}</option>@endforeach</select></div>
    <div><label for="payment_method">Payment method</label><select id="payment_method" name="payment_method"><option value="">All methods</option>@foreach(['cash','gcash'] as $method)<option @selected(request('payment_method') === $method) value="{{ $method }}">{{ strtoupper($method) }}</option>@endforeach</select></div>
    <div><label for="status">Status</label><select id="status" name="status"><option value="">{{ request()->routeIs('history') ? 'Completed / cancelled' : 'All statuses' }}</option>@foreach(['queued','in_progress','ready','completed','cancelled'] as $status)<option @selected(request('status') === $status) value="{{ $status }}">{{ ucfirst(str_replace('_',' ',$status)) }}</option>@endforeach</select></div>
    <button>Filter</button><a href="{{ url()->current() }}">Reset</a>
</form>
<table><thead><tr><th>Date</th><th>Customer</th><th>Barber</th><th>Service / Qty</th><th>Total</th><th>{{ auth()->user()->role === 'admin' ? 'Barber share' : 'Your earnings' }}</th><th>Payment</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@forelse($transactions as $transaction)
<tr><td>{{ $transaction->transaction_date?->format('M d, Y g:i A') ?? $transaction->created_at?->format('M d, Y') }}</td><td>{{ $transaction->customer?->name ?? 'Walk-in' }}</td><td>{{ $transaction->barber->name }}</td><td>{{ $transaction->service_name ?? 'Legacy service (not recorded)' }} × {{ $transaction->quantity }}</td><td>₱{{ number_format($transaction->total_amount,2) }}</td><td>₱{{ number_format($transaction->status === 'completed' && $transaction->payment?->verification_status === 'verified' ? $transaction->barber_share : 0,2) }}</td><td>{{ strtoupper($transaction->payment?->payment_method ?? '—') }} / {{ $transaction->payment?->verification_status ?? 'missing' }}</td><td>{{ ucfirst(str_replace('_',' ',$transaction->status)) }}</td><td><div class="row-actions"><a href="{{ route('transactions.show',$transaction) }}">View</a>@if(auth()->user()->role === 'admin')<a href="{{ route('transactions.edit',$transaction) }}">Edit</a>@if($transaction->status !== 'completed')<form method="POST" action="{{ route('transactions.destroy',$transaction) }}" data-confirm="Remove this record from history? Audit data remains; consumed stock will not be restored.">@csrf @method('DELETE')<button>Remove</button></form>@endif @endif</div></td></tr>
@empty<tr><td colspan="9">No matching transactions.</td></tr>@endforelse
</tbody></table>{{ $transactions->links() }}
</section>
@endsection
