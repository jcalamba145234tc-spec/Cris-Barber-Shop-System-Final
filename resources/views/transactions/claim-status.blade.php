@if($transaction->status === 'completed' && $transaction->payment?->verification_status === 'verified')
<span class="badge {{ $transaction->claim ? 'badge-present' : 'badge-late' }}">{{ $transaction->claim ? 'Claimed · '.$transaction->claim->confirmed_at->format('M j, Y') : 'Unclaimed' }}</span>
@else<span class="muted">Not yet earned</span>@endif
