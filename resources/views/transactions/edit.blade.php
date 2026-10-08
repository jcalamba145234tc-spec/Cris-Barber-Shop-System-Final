@extends('layouts.shop', ['heading' => 'Edit history record'])
@section('module')
<section class="panel"><p class="muted">Correct customer/date, or a GCash reference before completion. A changed reference requires verification again. Prices, payment, selected service, barber and consumed supplies remain immutable for accounting safety. Cancel/remove an incorrect sale and record a replacement; manually account for any actual returned stock.</p>
<form method="POST" action="{{ route('transactions.update',$transaction) }}">@csrf @method('PUT')
<label for="customer_id">Customer</label><select id="customer_id" name="customer_id"><option value="">Walk-in</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string)old('customer_id',$transaction->customer_id) === (string)$customer->id)>{{ $customer->name }}</option>@endforeach</select>
<label for="transaction_date">Date</label><input id="transaction_date" name="transaction_date" type="datetime-local" value="{{ old('transaction_date',$transaction->transaction_date?->format('Y-m-d\TH:i') ?? $transaction->created_at->format('Y-m-d\TH:i')) }}" required>
@if($transaction->payment?->payment_method === 'gcash' && !in_array($transaction->status,['completed','cancelled']))
<label for="gcash_reference_no">GCash Reference Number</label><input id="gcash_reference_no" name="gcash_reference_no" value="{{ old('gcash_reference_no',$transaction->payment->gcash_reference_no) }}" inputmode="numeric" pattern="[0-9]{6,30}" required>
@endif
<div class="actions"><button>Save correction</button><a href="{{ route('transactions.show',$transaction) }}">Cancel</a></div></form></section>
@endsection
