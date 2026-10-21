@extends('layouts.app')
@section('content')
<main class="dashboard module-page" id="main-content" tabindex="-1">
    <header class="topbar">
        <div><p class="eyebrow">Cris Barber Shop · {{ now()->format('D, M j') }}</p><h1>{{ $heading ?? 'Workspace' }}</h1></div>
        <div class="user-controls"><div class="user-identity"><strong>{{ auth()->user()->name }}</strong><span class="role-label">{{ auth()->user()->role === 'admin' ? 'Admin' : 'Barber' }}</span></div>
        <form method="POST" action="{{ route('logout') }}" data-confirm="Sign out of your workspace? Unsaved form changes will be lost.">@csrf<button type="submit" class="secondary-button">Logout</button></form></div>
    </header>
    @if($errors->any())<div class="error-summary" role="alert"><strong>Please review the highlighted fields.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('module')
    <footer class="workspace-footer">Cris Barber Shop <span>All times Asia/Manila · Amounts in PHP (₱)</span></footer>
</main>
@endsection
