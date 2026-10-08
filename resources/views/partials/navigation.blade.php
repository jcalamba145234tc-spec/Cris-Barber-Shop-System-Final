<nav class="shop-nav" aria-label="{{ auth()->user()->role === 'admin' ? 'Admin' : 'Barber' }} navigation">
    <a class="brand brand-with-logo" href="{{ route('dashboard') }}"><img src="{{ asset('images/logo.jpg') }}" alt="" width="36" height="36">Cris Barber Shop</a>
    <div class="nav-links">
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
