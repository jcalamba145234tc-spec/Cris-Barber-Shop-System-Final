<button class="nav-backdrop" type="button" data-nav-close aria-label="Close navigation" hidden></button>
<nav class="shop-nav" id="shop-navigation" aria-label="{{ auth()->user()->role === 'admin' ? 'Admin' : 'Barber' }} navigation">
    <div class="sidebar-brand-row">
        <a class="brand brand-with-logo" href="{{ route('dashboard') }}"><img src="{{ asset('images/logo.jpg') }}" alt="" width="36" height="36"><span>Cris Barber Shop</span></a>
        <button class="nav-collapse" type="button" data-sidebar-collapse aria-label="Minimize sidebar" title="Minimize sidebar">&lsaquo;</button>
        <button class="nav-close" type="button" data-nav-close aria-label="Close navigation">&times;</button>
    </div>
    <div class="nav-links" id="shop-navigation-links">
        <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>Dashboard</a>
        @if(auth()->user()->role === 'admin')
            <a href="{{ route('pos') }}" @class(['active' => request()->routeIs('pos')])>Point of Sale</a>
            <a href="{{ route('transactions.index') }}" @class(['active' => request()->routeIs('transactions.*')])>Queue / Transactions</a>
            @foreach(['customers' => 'Customers', 'services' => 'Services', 'barbers' => 'Barbers', 'inventory' => 'Inventory'] as $module => $label)
                <a href="{{ route($module.'.index') }}" @class(['active' => request()->routeIs($module.'.*')])>{{ $label }}</a>
            @endforeach
        @else
            <a href="{{ route('transactions.index') }}" @class(['active' => request()->routeIs('transactions.*')])>My Queue</a>
        @endif
        <a href="{{ route('attendance.index') }}" @class(['active' => request()->routeIs('attendance.*')])>Attendance</a>
        <a href="{{ route('history') }}" @class(['active' => request()->routeIs('history')])>History</a>
        <a href="{{ route('profile.edit') }}" @class(['active' => request()->routeIs('profile.*')])>Account Settings</a>
        <form method="POST" action="{{ route('logout') }}" data-confirm="Log out of your account?">@csrf<button type="submit" class="secondary-button">Logout</button></form>
    </div>
</nav>
<button class="sidebar-restore" type="button" data-sidebar-restore aria-controls="shop-navigation" aria-expanded="true" aria-label="Open sidebar" title="Open sidebar">&#9776;</button>
<header class="mobile-nav-bar">
    <button class="nav-open" type="button" data-nav-open aria-controls="shop-navigation" aria-expanded="false" aria-label="Open navigation"><span aria-hidden="true">&#9776;</span></button>
    <a class="brand brand-with-logo" href="{{ route('dashboard') }}"><img src="{{ asset('images/logo.jpg') }}" alt="" width="36" height="36"><span>Cris Barber Shop</span></a>
</header>
