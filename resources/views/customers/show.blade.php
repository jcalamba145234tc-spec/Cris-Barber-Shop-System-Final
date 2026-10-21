@extends('layouts.shop', ['heading' => 'Customer profile'])

@section('module')
<section class="panel customer-profile">
    <div class="customer-profile-heading">
        <div class="customer-contact-details">
            <p class="eyebrow">Customer details</p>
            <h2>{{ $customer->name }}</h2>
            <div class="customer-contact">
                @if($customer->phone)<span>{{ $customer->phone }}</span>@endif
                @if($customer->email)<span>{{ $customer->email }}</span>@endif
                @if(!$customer->phone && !$customer->email)<span class="muted">No contact details provided</span>@endif
            </div>
        </div>
        <div class="customer-actions">
            <a class="button secondary-button" href="{{ route('customers.index') }}">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 5-7 7 7 7M5 12h14"/></svg>
                Back to customers
            </a>
            <a class="button" href="{{ route('customers.edit', $customer) }}">Edit customer</a>
        </div>
    </div>

    <div class="customer-transactions-heading">
        <div>
            <h3>Customer transactions</h3>
            <p class="muted">Purchase and service history for this customer.</p>
        </div>
    </div>

    <table>
        <thead>
            <tr><th>Date</th><th>Service</th><th>Amount</th><th>Status</th><th>View</th></tr>
        </thead>
        <tbody>
            @forelse($transactions as $transaction)
                <tr>
                    <td>{{ $transaction->transaction_date }}</td>
                    <td>{{ $transaction->services->isNotEmpty() ? $transaction->services->map(fn ($service) => $service->pivot->service_name.' × '.$service->pivot->quantity)->implode(', ') : ($transaction->service_name ?? 'Legacy') }}</td>
                    <td>₱{{ number_format($transaction->total_amount, 2) }}</td>
                    <td>{{ $transaction->status }}</td>
                    <td><a href="{{ route('transactions.show', $transaction) }}">View</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="customer-empty-state">No customer transactions yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $transactions->links() }}
</section>
@endsection
