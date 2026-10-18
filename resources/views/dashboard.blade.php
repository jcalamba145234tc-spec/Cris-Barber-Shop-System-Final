@extends('layouts.shop', ['heading' => ucfirst($user->role).' dashboard'])
@section('module')
@if($user->role === 'admin')
<div class="stats admin-dashboard-stats grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4">
    <article class="stat"><span>Total Customers</span><strong>{{ $totalCustomers }}</strong></article>
    <article class="stat"><span>Total Barbers</span><strong>{{ $totalBarbers }}</strong></article>
    <article class="stat"><span>Total Services</span><strong>{{ $totalServices }}</strong><p class="muted">Active and inactive catalogue records.</p></article>
    <article class="stat stat-payment-transactions"><span>Payment Transactions</span><strong>{{ $totalPayments }}</strong><p class="muted">Payment statuses for active transactions.</p></article>
    <article class="stat"><span>Available Barbers</span><strong>{{ $availableBarbers }}</strong></article>
    <article class="stat"><span>Total Sales / Revenue</span><strong>₱{{ number_format($totalRevenue, 2) }}</strong></article>
    <article class="stat"><span>Low Stock Items</span><strong>{{ $lowStock }}</strong><p class="muted">Active products, including out-of-stock.</p></article>
</div>
<section class="panel">
    <h2>Sales summary</h2><p class="muted">Only completed transactions with verified payments are included.</p>
    <form class="report-filters" method="GET"><div><label for="date">Date</label><input id="date" type="date" name="date" value="{{ $selectedDate }}"></div><div><label for="month">Month</label><input id="month" type="month" name="month" value="{{ $selectedMonth }}"></div><button>Apply</button></form>
    <div class="stats grid grid-cols-1 sm:grid-cols-3">
        <article class="stat"><span>Selected date sales</span><strong>₱{{ number_format($todayRevenue,2) }}</strong></article>
        <article class="stat"><span>Selected month sales</span><strong>₱{{ number_format($monthRevenue,2) }}</strong></article>
        <article class="stat"><span>Shop share this month</span><strong>₱{{ number_format($monthShopShare,2) }}</strong></article>
    </div>
    <form class="export-panel" method="GET" action="{{ route('reports.pdf') }}"
          x-data="{ range: 'last_month', thisMonth: '{{ now()->format('Y-m') }}', lastMonth: '{{ now()->startOfMonth()->subMonth()->format('Y-m') }}', month: '{{ now()->startOfMonth()->subMonth()->format('Y-m') }}', date: '{{ $selectedDate }}',
            get period() { return this.range === 'date' ? 'date' : 'month'; },
            get chosenMonth() { return this.range === 'this_month' ? this.thisMonth : (this.range === 'last_month' ? this.lastMonth : this.month); },
            get label() {
                if (this.range === 'date') { return this.date ? new Date(this.date + 'T00:00:00').toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : 'Select a date'; }
                return this.chosenMonth ? new Date(this.chosenMonth + '-01T00:00:00').toLocaleDateString('en-PH', { year: 'numeric', month: 'long' }) : 'Select a month';
            } }">
        <div class="export-head">
            <h3>Reports &amp; exports</h3>
            <p class="muted">Review a report on screen, or download it as a CSV or PDF.</p>
        </div>
        <input type="hidden" name="period" value="month" :value="period">
        <input type="hidden" name="month" value="{{ now()->startOfMonth()->subMonth()->format('Y-m') }}" :value="chosenMonth">
        <input type="hidden" name="date" value="{{ $selectedDate }}" :value="date">
        <fieldset>
            <legend>Report period</legend>
            <div class="range-options">
                <label class="range-pill"><input type="radio" value="this_month" x-model="range"><span>This month</span></label>
                <label class="range-pill"><input type="radio" value="last_month" x-model="range" checked><span>Last month</span></label>
                <label class="range-pill"><input type="radio" value="month" x-model="range"><span>Choose month</span></label>
                <label class="range-pill"><input type="radio" value="date" x-model="range"><span>Single day</span></label>
            </div>
        </fieldset>
        <div class="export-field" x-show="range === 'month'" x-cloak>
            <label for="export-month">Month</label>
            <input id="export-month" type="month" x-model="month" max="{{ now()->format('Y-m') }}">
        </div>
        <div class="export-field" x-show="range === 'date'" x-cloak>
            <label for="export-date">Date</label>
            <input id="export-date" type="date" x-model="date" max="{{ today()->format('Y-m-d') }}">
        </div>
        <p class="export-summary">Selected report: <strong x-text="label">Last month</strong></p>
        <div class="export-actions">
            <button type="submit">View report</button>
            <button type="submit" class="export-secondary" formaction="{{ route('reports.csv') }}" formtarget="_self">Download CSV</button>
            <button type="submit" class="export-secondary" name="print" value="1">Print / Save as PDF</button>
        </div>
    </form>
</section>
@else
<div class="stats grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4">
    <article class="stat"><span>Today's Earnings</span><strong>₱{{ number_format($todayEarnings, 2) }}</strong></article>
    <article class="stat"><span>Total Earnings</span><strong>₱{{ number_format($totalEarnings, 2) }}</strong></article>
    <article class="stat"><span>Assigned / active services</span><strong>{{ $assignedCount }}</strong></article>
    <article class="stat"><span>Today's Attendance</span><strong>{{ ucfirst($todayAttendance?->status ?? 'Not clocked in') }}</strong></article>
</div>
<section class="panel">
    <h2>Daily &amp; Monthly Sales Breakdown</h2>
    <p class="muted">Read-only earnings from your own completed, verified transactions. Existing 50/50 split retained.</p>
    <form class="report-filters" method="GET"><div><label for="date">Earnings date (optional)</label><input id="date" type="date" name="date" value="{{ request('date') }}"></div><div><label for="month">Month</label><input id="month" type="month" name="month" value="{{ $selectedMonth }}"></div><button>Apply</button><a href="{{ route('dashboard') }}">Reset</a></form>
    <div class="stats grid grid-cols-1 sm:grid-cols-3">
        <article class="stat"><span>Selected Period Gross Revenue</span><strong>₱{{ number_format($periodGross,2) }}</strong></article>
        <article class="stat"><span>Selected Period Net Barber Share</span><strong>₱{{ number_format($selectedPeriodBarberShare,2) }}</strong></article>
        <article class="stat"><span>Selected month earnings</span><strong>₱{{ number_format($monthEarnings,2) }}</strong></article>
    </div>
    <table><thead><tr><th>Date</th><th>Completed services</th><th>Your earnings</th></tr></thead><tbody>
    @forelse($salesBreakdown as $day)<tr><td>{{ $day->date }}</td><td>{{ $day->total_services }}</td><td>₱{{ number_format($day->barber_share,2) }}</td></tr>@empty<tr><td colspan="3">No valid completed services in this period.</td></tr>@endforelse
    </tbody></table>
</section>
<section class="panel"><h2>Service menu</h2><p class="muted">Currently available services. Payment entry is restricted to Admin POS.</p><table><thead><tr><th>Service</th><th>Price</th></tr></thead><tbody>@forelse($services as $service)<tr><td>{{ $service->name }}</td><td>₱{{ number_format($service->price,2) }}</td></tr>@empty<tr><td colspan="2">No services currently available.</td></tr>@endforelse</tbody></table></section>
@endif
@endsection
