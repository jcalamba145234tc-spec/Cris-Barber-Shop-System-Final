@extends('layouts.app', ['title' => 'Sign in | Cris Barber Shop'])

@section('content')
<main class="page">
    <section class="card">
        <div class="auth-brand flex flex-col items-center gap-3">
            <img src="{{ asset('images/logo.jpg') }}" alt="Cris Barber Shop logo" class="w-20 h-20 rounded-full object-cover shadow-sm">
            <div class="brand brand-with-logo">Cris Barber Shop</div>
        </div>
        <h1>Welcome back</h1>
        <p class="muted">Sign in to open your dashboard.</p>
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <label for="email">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            @error('email') <p class="error">{{ $message }}</p> @enderror
            <label for="password">Password</label>
            <div class="password-field">
                <input id="password" type="password" name="password" autocomplete="current-password" required>
                <button class="password-toggle" type="button" data-password-toggle="password" aria-controls="password" aria-label="Show password" aria-pressed="false">
                    <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 12s3.3-6 9.5-6 9.5 6 9.5 6-3.3 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.7"/></svg>
                    <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A10.5 10.5 0 0 1 12 6c6.2 0 9.5 6 9.5 6a15.5 15.5 0 0 1-3.1 3.6M6.2 6.3C3.8 7.8 2.5 12 2.5 12s3.3 6 9.5 6c1 0 2-.2 2.8-.5"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                </button>
            </div>
            <div class="actions"><button class="w-full" type="submit">Sign in</button></div>
        </form>

    </section>
</main>
@endsection
