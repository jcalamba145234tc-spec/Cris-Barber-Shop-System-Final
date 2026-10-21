@extends('layouts.shop', ['heading' => 'Transaction details'])
@section('module')
<section class="panel">
<dl class="details-grid"><dt>Transaction ID</dt><dd>{{ $transaction->id }}</dd><dt>Customer</dt><dd>{{ $transaction->customer?->display_name ?? 'Walk-in' }}</dd><dt>Barber</dt><dd>{{ $transaction->barber->name }}</dd><dt>Services</dt><dd>{{ $transaction->services->isNotEmpty() ? $transaction->services->map(fn ($service) => $service->pivot->service_name)->implode(', ') : ($transaction->service_name ?? 'Legacy service (not recorded)') }}</dd><dt>Total service units</dt><dd>{{ $transaction->quantity }}</dd>@if($transaction->services->isNotEmpty())<dt>Total</dt><dd>₱{{ number_format($transaction->total_amount,2) }}</dd>@else<dt>Unit price / Total</dt><dd>{{ $transaction->unit_price !== null ? '₱'.number_format($transaction->unit_price,2) : 'Legacy' }} / ₱{{ number_format($transaction->total_amount,2) }}</dd>@endif<dt>Date</dt><dd>{{ $transaction->transaction_date?->format('M j, Y g:i A') }}</dd><dt>Transaction status</dt><dd>{{ $transaction->status }}</dd><dt>Payment</dt><dd>{{ strtoupper($transaction->payment?->payment_method ?? 'missing') }} / {{ $transaction->payment?->verification_status ?? 'missing' }}</dd><dt>Earnings (read-only)</dt><dd>₱{{ number_format($transaction->barber_share,2) }} {{ $transaction->status === 'completed' && $transaction->payment?->verification_status === 'verified' ? '(earned)' : '(not yet valid earnings)' }}</dd></dl>
@if($transaction->services->isNotEmpty())
<h2>Selected services</h2><table><thead><tr><th>Service</th><th>Quantity</th><th>Unit price</th><th>Subtotal</th></tr></thead><tbody>@foreach($transaction->services as $service)<tr><td>{{ $service->pivot->service_name }}</td><td>{{ $service->pivot->quantity }}</td><td>₱{{ number_format($service->pivot->unit_price,2) }}</td><td>₱{{ number_format($service->pivot->unit_price * $service->pivot->quantity,2) }}</td></tr>@endforeach</tbody></table>
@endif
@if(auth()->user()->role === 'admin')
@if($transaction->payment?->payment_method === 'cash')
<dl class="details-grid"><dt>Customer's money / Cash received</dt><dd data-saved-cash>{{ $transaction->payment->cash_received !== null ? '₱'.number_format($transaction->payment->cash_received, 2) : 'Not recorded (existing payment)' }}</dd><dt>Customer's change</dt><dd data-saved-change>{{ $transaction->payment->change_amount !== null ? '₱'.number_format($transaction->payment->change_amount, 2) : 'Not recorded (existing payment)' }}</dd></dl>
@endif
<p>GCash reference: {{ $transaction->payment?->gcash_reference_no ?? 'Not applicable' }}</p>
@if($transaction->payment?->payment_method === 'gcash' && !in_array($transaction->status,['completed','cancelled']))
<form class="actions" method="POST" action="{{ route('payments.verify',$transaction->payment) }}">@csrf<button name="status" value="verified">Verify GCash</button><button name="status" value="flagged" class="secondary-button">Flag payment</button></form>
@endif
<div class="actions"><a href="{{ route('transactions.edit',$transaction) }}">Edit customer / date</a>@if($transaction->status !== 'completed')<form method="POST" action="{{ route('transactions.destroy',$transaction) }}" data-confirm="Remove this history record? Inventory consumed remains consumed; audit records are retained.">@csrf @method('DELETE')<button>Remove record</button></form>@endif</div>
@endif
@if(!in_array($transaction->status,['completed','cancelled']))
<form class="actions" method="POST" action="{{ route('transactions.status',$transaction) }}">@csrf @method('PATCH')
@if($transaction->status === 'queued')<button name="status" value="in_progress">Start service</button>@endif
@if($transaction->status === 'in_progress')<button name="status" value="ready">Mark service ready for checkout</button>@endif
@if(auth()->user()->role === 'admin')<button name="status" value="completed">Complete &amp; deduct stock</button><button class="secondary-button" name="status" value="cancelled" data-confirm="Cancel this transaction? It will not count as earnings and cannot be completed afterward.">Cancel transaction</button>@endif
</form>
@endif
<h2>Earnings payout status</h2><p>@include('transactions.claim-status')</p>
@if($transaction->claim)<p class="muted">Confirmed {{ $transaction->claim->confirmed_at->format('M j, Y g:i A') }} by {{ $transaction->claim->confirmedBy->name }}. Payout ID: {{ $transaction->claim->id }}</p>@endif
<h2>Inventory requirements snapshot</h2><p class="muted">{{ $transaction->inventory_deducted_at ? 'Deducted at '.$transaction->inventory_deducted_at->format('M j, Y g:i A') : 'No inventory deduction yet (legacy transactions are exempt).' }}</p>
<table><thead><tr><th>Product</th><th>Required quantity</th></tr></thead><tbody>@forelse($transaction->products as $product)<tr><td>{{ $product->name }}</td><td>{{ \App\Support\WholeQuantity::display($product->pivot->quantity) }} {{ $product->unit }}</td></tr>@empty<tr><td colspan="2">No product requirements recorded.</td></tr>@endforelse</tbody></table>
</section>
@endsection
