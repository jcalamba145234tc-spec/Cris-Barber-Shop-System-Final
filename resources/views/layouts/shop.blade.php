@extends('layouts.app')
@section('content')
<main class="dashboard module-page">
    <header class="topbar"><div><p class="eyebrow">{{ ucfirst(auth()->user()->role) }} workspace</p><h1>{{ $heading ?? 'Cris Barber Shop' }}</h1></div><span class="muted">{{ auth()->user()->name }}</span></header>
    @if(session('status'))<p class="alert" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="error form-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('module')
</main>
@endsection
