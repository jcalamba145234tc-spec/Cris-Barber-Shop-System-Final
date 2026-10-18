@extends('layouts.app', ['title' => 'Financial Report | Cris Barber Shop'])

@section('content')
<style>
    .report-paper { width: min(100%, 980px); margin: 28px auto; padding: 38px; border: 1px solid #e8e7e1; border-radius: 18px; background: #fff; box-shadow: var(--shadow); }
    .report-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; padding-bottom: 22px; border-bottom: 1px solid var(--line); }
    .report-header h1 { margin-top: 14px; }
    .report-period { margin: 6px 0 0; color: var(--muted); }
    .report-actions { display: flex; gap: 10px; }
    .report-summary { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin: 22px 0 28px; }
    .report-summary article { padding: 18px; border: 1px solid var(--line); border-radius: 14px; }
    .report-summary span { color: var(--muted); font-size: .84rem; }
    .report-summary strong { display: block; margin-top: 8px; font-size: 1.5rem; }
    .report-section { margin-top: 26px; }
    .report-section h2 { margin: 0 0 12px; }
    .report-paper table { min-width: 0; }
    .report-paper td:last-child, .report-paper th:last-child { text-align: right; }
    .report-generated { margin-top: 30px; color: var(--muted); font-size: .82rem; }
    @media print {
        @page { size: auto; margin: 16mm; }
        body { background: #fff !important; color: #111; }
        .report-paper { width: 100%; max-width: none; margin: 0; padding: 0; border: 0; border-radius: 0; box-shadow: none; }
        .report-actions { display: none !important; }
        .report-summary article { break-inside: avoid; }
        .report-section { break-inside: avoid; }
        .report-paper table { width: 100%; }
    }
    @media (max-width: 640px) {
        .report-paper { margin: 12px; padding: 20px; }
        .report-header { flex-direction: column; }
        .report-summary { grid-template-columns: 1fr; }
    }
</style>
<main class="report-paper">
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
            <button type="button" onclick="window.print()">Print / Save as PDF</button>
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
            <thead><tr><th>Barber</th><th>Completed services</th><th>Barber earnings</th></tr></thead>
            <tbody>
                @forelse ($barbers as $barber)
                    <tr><td>{{ $barber->barber_name }}</td><td>{{ $barber->completed_services }}</td><td>&#8369;{{ number_format($barber->barber_earnings, 2) }}</td></tr>
                @empty
                    <tr><td colspan="3">No services were recorded in this period.</td></tr>
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

    <p class="report-generated">Generated {{ $generatedAt->format('M j, Y g:i A') }}</p>
</main>
<script>
    window.addEventListener('load', () => window.print());
</script>
@endsection
