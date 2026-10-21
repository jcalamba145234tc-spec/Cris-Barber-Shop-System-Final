@extends('layouts.shop', ['heading' => 'Earnings payouts'])
@section('module')
<section class="panel"><div class="panel-heading"><div><p class="eyebrow">Owner-confirmed cash handover</p><h2>Unclaimed barber earnings</h2></div><span class="badge">50 / 50 split retained</span></div>
<p class="muted">Only completed sales with verified payments qualify. Balances carry forward until handed to the barber. Review the amount before confirming cash has been paid.</p>
<form method="GET" class="report-filters"><div><label for="from">Service date from</label><input id="from" type="date" name="from" value="{{ $from }}" max="{{ today()->toDateString() }}"></div><div><label for="to">Through</label><input id="to" type="date" name="to" value="{{ $to }}" max="{{ today()->toDateString() }}"></div><button>Filter</button><a href="{{ route('earning-claims.index') }}">All unclaimed</a></form>
<table><thead><tr><th>Barber</th><th>Eligible transactions</th><th>Service date range</th><th>Unclaimed</th><th>Payout</th></tr></thead><tbody>
@forelse($barbers as $barber)
@php($snapshot = $snapshots[$barber->id])
<tr><td><strong>{{ $barber->name }}</strong></td><td>{{ $snapshot['count'] }}</td><td>{{ $snapshot['from'] ?? '—' }} @if($snapshot['to']) – {{ $snapshot['to'] }} @endif</td><td class="money">₱{{ number_format($snapshot['amount'],2) }}</td><td><button type="button" class="secondary-button" x-data @click="$dispatch('open-modal', 'claim-{{ $barber->id }}')" @disabled($snapshot['count'] === 0)>Review payout</button></td></tr>
@empty<tr><td colspan="5" class="empty-state">No barbers found. Add a barber account first.</td></tr>@endforelse
</tbody></table>{{ $barbers->links() }}</section>
@foreach($barbers as $barber)
@php($snapshot = $snapshots[$barber->id])
<x-modal :name="'claim-'.$barber->id" title="Confirm earnings payout">
<form method="POST" action="{{ route('earning-claims.store',$barber) }}">@csrf
<input type="hidden" name="_modal" value="claim-{{ $barber->id }}"><input type="hidden" name="from" value="{{ $from }}"><input type="hidden" name="to" value="{{ $to }}"><input type="hidden" name="amount" value="{{ $snapshot['amount'] }}"><input type="hidden" name="fingerprint" value="{{ $snapshot['fingerprint'] }}"><input type="hidden" name="claim_token" value="{{ (string)Illuminate\Support\Str::uuid() }}">
<p class="eyebrow">Cash to hand over</p><p class="payout-amount">₱{{ number_format($snapshot['amount'],2) }}</p>
<dl class="details-grid"><dt>Barber</dt><dd>{{ $barber->name }}</dd><dt>Service dates</dt><dd>{{ $snapshot['from'] }} – {{ $snapshot['to'] }}</dd><dt>Transactions</dt><dd>{{ $snapshot['count'] }}</dd><dt>Confirmed by</dt><dd>{{ auth()->user()->name }}</dd></dl>
<label class="checkbox-label"><input type="checkbox" name="confirmed" value="1" required>I confirm this cash has been handed to the barber.</label><x-field-error name="confirmed" /><x-field-error name="claim" />
<p class="muted field-hint">This records an immutable payout, not a new expense or sale. These earnings cannot be claimed again. If the balance changes, confirmation is blocked for a fresh review.</p>
<div class="actions justify-end"><button type="button" class="secondary-button" x-data @click="$el.closest('dialog').close()">Cancel</button><button @disabled($snapshot['count'] === 0)>Confirm cash paid</button></div></form>
</x-modal>
@endforeach
<section class="panel"><h2>Payout ledger</h2><table><thead><tr><th>Confirmed</th><th>Barber</th><th>Service dates</th><th>Transactions</th><th>Amount</th><th>Confirmed by</th></tr></thead><tbody>
@forelse($claims as $claim)<tr><td>{{ $claim->confirmed_at->format('M j, Y g:i A') }}</td><td>{{ $claim->barber->name }}</td><td>{{ $claim->period_start->format('Y-m-d') }} – {{ $claim->period_end->format('Y-m-d') }}</td><td>{{ $claim->transaction_count }}</td><td class="money">₱{{ number_format($claim->amount,2) }}</td><td>{{ $claim->confirmedBy->name }}</td></tr>@empty<tr><td colspan="6" class="empty-state">No payouts recorded yet. Unclaimed balances continue accumulating.</td></tr>@endforelse
</tbody></table>{{ $claims->links() }}</section>
@endsection
