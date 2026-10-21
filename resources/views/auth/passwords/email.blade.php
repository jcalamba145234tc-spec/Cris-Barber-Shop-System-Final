@extends('layouts.app', ['title' => 'Forgot Password | Cris Barber Shop'])

@section('content')
<main class="page" id="main-content" tabindex="-1">
    <section class="card">
        <div class="auth-brand flex flex-col items-center gap-3">
            <img src="{{ asset('images/logo.jpg') }}" alt="Cris Barber Shop logo" class="w-20 h-20 rounded-full object-cover shadow-sm">
            <div class="brand brand-with-logo">Cris Barber Shop</div>
        </div>
        <h1>Forgot your password?</h1>
        <p class="muted">Enter your account email and we’ll send you a password reset link.</p>

        @if (session('status'))
            <p class="alert">{{ __(session('status')) }}</p>
        @endif
        @error('email') <p class="error">{{ $message }}</p> @enderror

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <label for="email">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
            <div class="actions">
                <button class="w-full" type="submit">Email password reset link</button>
                <a href="{{ route('login') }}">Back to sign in</a>
            </div>
        </form>
    </section>
</main>
@endsection
