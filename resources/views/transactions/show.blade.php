@extends('layouts.shop', ['heading' => $transaction->trashed() ? 'Archived transaction' : 'Transaction details'])
@section('module')
@php
    $archived = $transaction->trashed();
    $isAdmin = auth()->user()->role === 'admin';
    $isOpen = !in_array($transaction->status, ['completed', 'cancelled']);
    $earned = $transaction->status === 'completed' && $transaction->payment?->verification_status === 'verified';
    $statusLabel = ucfirst(str_replace('_', ' ', $transaction->status));
@endphp
<div class="transaction-list-heading">
    <a class="quiet-button" href="{{ $archived ? route('transactions.recycle-bin') : route('transactions.index') }}"><x-icon name="back" />{{ $archived ? 'Back to recycle bin' : 'Back to transactions' }}</a>
    <div class="transaction-badges">@if($archived)<span class="badge badge-warning">Archived</span>@endif<span class="badge badge-{{ $transaction->status }}">{{ $statusLabel }}</span></div>
</div>
@if($archived)
<section class="panel transaction-archive-banner" aria-labelledby="archive-heading">
    <div><h2 id="archive-heading">This record is archived</h2><p class="muted">Archived {{ $transaction->deleted_at->format('M j, Y g:i A') }}. Details are read-only until restored. Restoring keeps the previous status; active services rejoin the queue. Payment and stock snapshots do not change.</p></div>
    <form method="POST" action="{{ route('transactions.restore', $transaction->id) }}" data-confirm="Restore this transaction with its previous status? Active services will rejoin the queue. Payment and stock snapshots will not change.">@csrf<button type="submit">Restore transaction</button></form>
</section>
@endif
<section class="panel transaction-overview" aria-labelledby="transaction-overview-heading">
    <div class="transaction-overview-head"><div><p class="eyebrow">Transaction overview</p><h2 id="transaction-overview-heading">{{ $transaction->customer?->display_name ?? 'Walk-in' }}</h2><p class="muted">{{ $transaction->transaction_date?->format('M j, Y g:i A') ?? 'Date not recorded' }} · Asia/Manila</p></div><div class="transaction-total"><span>Total</span><strong>₱{{ number_format($transaction->total_amount, 2) }}</strong><small>{{ $transaction->quantity }} service {{ (int)$transaction->quantity === 1 ? 'unit' : 'units' }}</small></div></div>
    <dl class="transaction-meta"><div><dt>Transaction ID</dt><dd class="transaction-id">{{ $transaction->id }}</dd></div><div><dt>Barber</dt><dd>{{ $transaction->barber?->name ?? 'Not recorded' }}</dd></div><div><dt>Transaction status</dt><dd>{{ $statusLabel }}</dd></div></dl>
</section>
<div class="transaction-detail-grid">
    <div class="transaction-main">
        <section class="panel" aria-labelledby="selected-services-heading">
            <h2 id="selected-services-heading">Selected services</h2><p class="muted">Recorded service names and prices are preserved for this transaction.</p>
            @if($transaction->services->isNotEmpty())
            <table><thead><tr><th>Service</th><th class="numeric">Quantity</th><th class="numeric">Unit price</th><th class="numeric">Subtotal</th></tr></thead><tbody>@foreach($transaction->services as $service)<tr><td>{{ $service->pivot->service_name }}</td><td class="numeric">{{ $service->pivot->quantity }}</td><td class="money">₱{{ number_format($service->pivot->unit_price,2) }}</td><td class="money">₱{{ number_format($service->pivot->unit_price * $service->pivot->quantity,2) }}</td></tr>@endforeach</tbody></table>
            @else
            <dl class="transaction-fields"><div><dt>Services</dt><dd>{{ $transaction->service_name ?? 'Legacy service (not recorded)' }}</dd></div><div><dt>Total service units</dt><dd>{{ $transaction->quantity }}</dd></div><div><dt>Unit price / Total</dt><dd>{{ $transaction->unit_price !== null ? '₱'.number_format($transaction->unit_price,2) : 'Legacy' }} / ₱{{ number_format($transaction->total_amount,2) }}</dd></div></dl>
            @endif
        </section>
        @if(!$archived)
        <section class="panel" aria-labelledby="service-actions-heading">
            <h2 id="service-actions-heading">Service actions</h2>
            @if($isOpen)
            <p class="muted">{{ $isAdmin ? 'Update service progress, then complete checkout when service and payment are ready. Completion applies the existing stock and payment checks.' : 'Update progress for your assigned service. Payment verification and completion are handled by the administrator.' }}</p>
            <form class="actions service-progress-actions" method="POST" action="{{ route('transactions.status',$transaction) }}">@csrf @method('PATCH')
                @if($transaction->status === 'queued')<button name="status" value="in_progress">Start service</button>@endif
                @if($transaction->status === 'in_progress')<button name="status" value="ready">Mark service ready for checkout</button>@endif
                @if($isAdmin)<button name="status" value="completed" class="{{ $transaction->status !== 'ready' ? 'secondary-button' : '' }}">Complete &amp; deduct stock</button><button class="danger-button" name="status" value="cancelled" data-confirm="Cancel this transaction? It will not count as earnings and cannot be completed afterward.">Cancel transaction</button>@endif
            </form>
            @if(!$isAdmin && $transaction->status === 'ready')<p class="transaction-notice">Service is ready. Waiting for administrator checkout.</p>@endif
            @else
            <p class="muted">{{ $transaction->status === 'completed' ? 'This service is completed. Financial and inventory snapshots remain protected.' : 'This transaction is cancelled and cannot be completed or reopened.' }}</p>
            @endif
            @if($isAdmin)
            <div class="transaction-record-actions"><div><h3>Record management</h3><p class="muted">Correct customer details or an eligible date. Archiving hides the record without returning stock.</p></div><div class="row-actions"><a class="button secondary-button" href="{{ route('transactions.edit',$transaction) }}">Edit customer / date</a>@if($transaction->status !== 'completed')<form method="POST" action="{{ route('transactions.destroy',$transaction) }}" data-confirm="Move this transaction to the recycle bin? Audit records are retained; consumed stock will not be returned. You can view or restore it from the Transaction Recycle Bin.">@csrf @method('DELETE')<button class="danger-button">Archive record</button></form>@endif</div></div>
            @endif
        </section>
        @endif
    </div>
    <aside class="transaction-payment">
        <section class="panel" aria-labelledby="payment-details-heading">
            <h2 id="payment-details-heading">Payment details</h2>
            <div class="transaction-badges"><span class="badge">{{ strtoupper($transaction->payment?->payment_method ?? 'missing') }}</span><span class="badge badge-{{ $transaction->payment?->verification_status ?? 'missing' }}">{{ ucfirst($transaction->payment?->verification_status ?? 'missing') }}</span></div>
            <dl class="transaction-fields"><div><dt>Transaction total</dt><dd class="money">₱{{ number_format($transaction->total_amount, 2) }}</dd></div>
            @if($isAdmin && $transaction->payment?->payment_method === 'cash')
                <div><dt>Customer's money / Cash received</dt><dd data-saved-cash>{{ $transaction->payment->cash_received !== null ? '₱'.number_format($transaction->payment->cash_received, 2) : 'Not recorded (existing payment)' }}</dd></div>
                <div><dt>Customer's change</dt><dd data-saved-change>{{ $transaction->payment->change_amount !== null ? '₱'.number_format($transaction->payment->change_amount, 2) : 'Not recorded (existing payment)' }}</dd></div>
            @endif
            @if($isAdmin)<div><dt>GCash reference</dt><dd class="transaction-id">{{ $transaction->payment?->gcash_reference_no ?? 'Not applicable' }}</dd></div>@endif
            </dl>
            @if($isAdmin && !$archived && $transaction->payment?->payment_method === 'gcash' && $isOpen)
            <p class="field-hint">Check the actual GCash payment before verifying. Verification does not complete the service.</p>
            <form class="actions" method="POST" action="{{ route('payments.verify',$transaction->payment) }}">@csrf<button name="status" value="verified">Verify GCash</button><button name="status" value="flagged" class="secondary-button">Flag payment</button></form>
            @endif
        </section>
        <section class="panel" aria-labelledby="earnings-heading"><h2 id="earnings-heading">Earnings payout status</h2><dl class="transaction-fields"><div><dt>Earnings (read-only)</dt><dd><strong>₱{{ number_format($transaction->barber_share,2) }}</strong><span class="cell-meta">{{ $earned ? '(earned)' : '(not yet valid earnings)' }}</span></dd></div></dl><p>@include('transactions.claim-status')</p>
            @if($transaction->claim)<p class="field-hint transaction-id">Confirmed {{ $transaction->claim->confirmed_at->format('M j, Y g:i A') }} by {{ $transaction->claim->confirmedBy?->name ?? 'Not recorded' }}. Payout ID: {{ $transaction->claim->id }}</p>@endif
        </section>
    </aside>
</div>
<section class="panel transaction-inventory" aria-labelledby="inventory-snapshot-heading">
    <h2 id="inventory-snapshot-heading">Inventory requirements snapshot</h2><p class="muted">{{ $transaction->inventory_deducted_at ? 'Deducted at '.$transaction->inventory_deducted_at->format('M j, Y g:i A') : 'No inventory deduction yet (legacy transactions are exempt).' }}</p>
    @if($transaction->products->isNotEmpty())
    <table><thead><tr><th>Product</th><th class="numeric">Required quantity</th></tr></thead><tbody>@foreach($transaction->products as $product)<tr><td>{{ $product->name }}</td><td class="numeric">{{ \App\Support\WholeQuantity::display($product->pivot->quantity) }} {{ $product->unit }}</td></tr>@endforeach</tbody></table>
    @else<p class="transaction-notice">No product requirements recorded.</p>@endif
</section>
@endsection
