@extends('layouts.app', ['title' => 'Financial Report | Cris Barber Shop'])

@section('content')

<main class="report-paper" id="main-content" tabindex="-1">
    <header class="report-header">
        <div>
            <div class="brand brand-with-logo">
                <img src="{{ asset('images/logo.jpg') }}" alt="Cris Barber Shop logo" class="w-12 h-12 rounded-full object-cover shadow-sm">
                <span>Cris Barber Shop</span>
            </div>
            <h1>Financial Report</h1>
            <p class="report-period">{{ $period['label'] }} · {{ ucfirst($period['type']) }} report</p>
        </div>
        <div class="report-actions">
            <button type="button" id="print-report">Print / Save as PDF</button>
            <a class="button" href="{{ route('dashboard') }}">Back to dashboard</a>
        </div>
    </header>

    <section class="report-summary" aria-label="Financial summary">
        <article><span>Total gross sales</span><strong>&#8369;{{ number_format($grossSales, 2) }}</strong></article>
        <article><span>Shop share (50%)</span><strong>&#8369;{{ number_format($shopShare, 2) }}</strong></article>
    </section>

    <section class="report-section">
        <h2>Barber earnings breakdown</h2>
        <table>
            <thead><tr><th>Barber</th><th>Completed services</th><th>Barber earnings</th><th>Unclaimed</th><th>Claimed</th></tr></thead>
            <tbody>
                @forelse ($barbers as $barber)
                    <tr><td>{{ $barber->barber_name }}</td><td>{{ $barber->completed_services }}</td><td>&#8369;{{ number_format($barber->barber_earnings, 2) }}</td><td>₱{{ number_format($barber->unclaimed_earnings, 2) }}</td><td>₱{{ number_format($barber->claimed_earnings, 2) }}</td></tr>
                @empty
                    <tr><td colspan="5">No services were recorded in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="report-section">
        <h2>Payment summary</h2>
        <table>
            <thead><tr><th>Payment method</th><th>Transactions</th><th>Amount</th></tr></thead>
            <tbody>
                <tr><td>Cash</td><td>{{ $payments['cash']['count'] }}</td><td>&#8369;{{ number_format($payments['cash']['amount'], 2) }}</td></tr>
                <tr><td>GCash</td><td>{{ $payments['gcash']['count'] }}</td><td>&#8369;{{ number_format($payments['gcash']['amount'], 2) }}</td></tr>
            </tbody>
        </table>
    </section>

    <section class="report-section">
        <h2>Completed verified transactions · earnings status</h2>
        <p class="muted field-hint">The period filters service dates, not payout dates. Claim status is current at report generation. Archived customers remain linked.</p>
        <div class="table-scroll"><table><thead><tr><th>Service date / ID</th><th>Customer</th><th>Barber</th><th>Barber share</th><th>Status / claim date</th><th>Confirmed by</th></tr></thead><tbody>
        @forelse($transactions as $transaction)<tr><td>{{ $transaction->transaction_date?->format('M j, Y') }}<br><span class="report-id" title="{{ $transaction->id }}">{{ $transaction->id }}</span></td><td>{{ $transaction->customer?->display_name ?? 'Walk-in' }}</td><td>{{ $transaction->barber->name }}</td><td>₱{{ number_format($transaction->barber_share,2) }}</td><td>{{ $transaction->claim ? 'Claimed · '.$transaction->claim->confirmed_at->format('M j, Y g:i A') : 'Unclaimed' }}</td><td>{{ $transaction->claim?->confirmedBy->name ?? '—' }}</td></tr>@empty<tr><td colspan="6">No eligible transactions.</td></tr>@endforelse
        </tbody></table></div>
    </section>
    <p class="report-generated">Generated {{ $generatedAt->format('M j, Y g:i A') }}</p>
</main>
<script>
    document.getElementById('print-report')?.addEventListener('click', () => window.print());
</script>
@if(request()->boolean('print'))
<script>
    window.addEventListener('load', () => window.print());
</script>
@endif
@endsection
