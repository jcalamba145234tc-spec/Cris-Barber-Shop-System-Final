@extends('layouts.app', ['title' => 'Reset Password | Cris Barber Shop'])

@section('content')
<main class="page" id="main-content" tabindex="-1">
    <section class="card">
        <div class="auth-brand flex flex-col items-center gap-3">
            <img src="{{ asset('images/logo.jpg') }}" alt="Cris Barber Shop logo" class="w-20 h-20 rounded-full object-cover shadow-sm">
            <div class="brand brand-with-logo">Cris Barber Shop</div>
        </div>
        <h1>Reset your password</h1>
        <p class="muted">Choose a new password for your account.</p>

        @if ($errors->any())
            <div class="error form-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label for="email">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required autofocus>

            <label for="password">New password</label>
            <input id="password" type="password" name="password" autocomplete="new-password" required>
            @error('password') <p class="error">{{ $message }}</p> @enderror

            <label for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>

            <div class="actions">
                <button class="w-full" type="submit">Reset password</button>
                <a href="{{ route('login') }}">Back to sign in</a>
            </div>
        </form>
    </section>
</main>
@endsection
