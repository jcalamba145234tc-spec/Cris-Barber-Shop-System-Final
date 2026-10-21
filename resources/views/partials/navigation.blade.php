<button class="nav-backdrop" type="button" data-nav-close aria-label="Close navigation" hidden></button>
<nav class="shop-nav" id="shop-navigation" aria-label="{{ auth()->user()->role === 'admin' ? 'Admin' : 'Barber' }} navigation">
    <div class="sidebar-brand-row">
        <a class="brand brand-with-logo" href="{{ route('dashboard') }}"><img src="{{ asset('images/logo.jpg') }}" alt="" width="36" height="36"><span>Cris Barber Shop</span></a>
        <button class="nav-collapse" type="button" data-sidebar-collapse aria-label="Minimize sidebar" title="Minimize sidebar">&lsaquo;</button>
        <button class="nav-close" type="button" data-nav-close aria-label="Close navigation">&times;</button>
    </div>
    <div class="nav-links" id="shop-navigation-links">
        <p class="nav-section-label">Workspace</p>
        <a href="{{ route('dashboard') }}" @class(['active' => (request()->routeIs('dashboard') || request()->routeIs('earnings'))]) @if((request()->routeIs('dashboard') || request()->routeIs('earnings'))) aria-current="page" @endif><x-icon name="dashboard"/><span class="sidebar-label">Dashboard</span></a>
        @if(auth()->user()->role === 'admin')
            <a href="{{ route('pos') }}" @class(['active' => request()->routeIs('pos')]) @if(request()->routeIs('pos')) aria-current="page" @endif><x-icon name="pos"/><span class="sidebar-label">Point of Sale</span></a>
            <a href="{{ route('transactions.index') }}" @class(['active' => request()->routeIs('transactions.*')]) @if(request()->routeIs('transactions.*')) aria-current="page" @endif><x-icon name="queue"/><span class="sidebar-label">Queue / Transactions</span></a>
            <p class="nav-section-label">Shop management</p>
            @foreach(['customers' => 'Customers', 'services' => 'Services', 'barbers' => 'Barbers', 'inventory' => 'Inventory'] as $module => $label)
                <a href="{{ route($module.'.index') }}" @class(['active' => request()->routeIs($module.'.*')]) @if(request()->routeIs($module.'.*')) aria-current="page" @endif><x-icon :name="$module"/><span class="sidebar-label">{{ $label }}</span></a>
            @endforeach
        @else
            <a href="{{ route('transactions.index') }}" @class(['active' => request()->routeIs('transactions.*')]) @if(request()->routeIs('transactions.*')) aria-current="page" @endif><x-icon name="queue"/><span class="sidebar-label">My Queue</span></a>
        @endif
        <a href="{{ route('attendance.index') }}" @class(['active' => request()->routeIs('attendance.*')]) @if(request()->routeIs('attendance.*')) aria-current="page" @endif><x-icon name="attendance"/><span class="sidebar-label">Attendance</span></a>
        @if(auth()->user()->role === 'admin')<a href="{{ route('earning-claims.index') }}" @class(['active' => request()->routeIs('earning-claims.*')]) @if(request()->routeIs('earning-claims.*')) aria-current="page" @endif><x-icon name="earnings"/><span class="sidebar-label">Earnings payouts</span></a>@endif
        <p class="nav-section-label">Account</p>
        <a href="{{ route('profile.edit') }}" @class(['active' => request()->routeIs('profile.*')]) @if(request()->routeIs('profile.*')) aria-current="page" @endif><x-icon name="settings"/><span class="sidebar-label">Account Settings</span></a>
        <div class="sidebar-caption">SHOP MANAGEMENT<br><span>Good service. Better business.</span></div>
    </div>
</nav>
<button class="sidebar-restore" type="button" data-sidebar-restore aria-controls="shop-navigation" aria-expanded="true" aria-label="Open sidebar" title="Open sidebar">&#9776;</button>
<header class="mobile-nav-bar">
    <button class="nav-open" type="button" data-nav-open aria-controls="shop-navigation" aria-expanded="false" aria-label="Open navigation"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg></button>
    <a class="brand brand-with-logo" href="{{ route('dashboard') }}"><img src="{{ asset('images/logo.jpg') }}" alt="" width="36" height="36"><span>Cris Barber Shop</span></a>
</header>
