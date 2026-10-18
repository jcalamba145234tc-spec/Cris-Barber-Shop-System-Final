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
    <div class="actions"><a class="button" href="{{ route('pos') }}">Open POS</a><a href="{{ route('transactions.index') }}">Open queue ({{ $queueCount }})</a></div>
    <form class="report-filters" method="GET" action="{{ route('reports.csv') }}">
        <div><label for="report-period">Export period</label><select id="report-period" name="period"><option value="month">Month</option><option value="date">Date</option></select></div>
        <input type="hidden" name="date" value="{{ $selectedDate }}"><input type="hidden" name="month" value="{{ $selectedMonth }}">
        <button>Export CSV</button><button formaction="{{ route('reports.pdf') }}" formtarget="_blank">Print / Save as PDF</button>
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
