@extends('layouts.shop', ['heading' => 'Account Settings'])

@section('module')
<div class="settings-layout">
    <section class="panel profile-settings-card">
        <div class="brand brand-with-logo">
            <img src="{{ asset('images/logo.jpg') }}" alt="Cris Barber Shop logo" class="w-12 h-12 rounded-full object-cover shadow-sm">
            <span>Cris Barber Shop</span>
        </div>
        <h2>Profile &amp; Account Settings</h2>
        <p class="muted">Update your account name or change your password.</p>

        <section
            class="appearance-setting"
            aria-labelledby="appearance-title"
            x-data="{
                darkMode: document.documentElement.classList.contains('dark'),
                saveTheme() {
                    document.documentElement.classList.toggle('dark', this.darkMode);
                    localStorage.setItem('theme', this.darkMode ? 'dark' : 'light');
                }
            }"
        >
            <div>
                <h2 id="appearance-title">Appearance</h2>
                <p class="muted">Choose the theme used across your dashboard.</p>
            </div>
            <label class="theme-control" for="dark-mode-toggle">
                <span>Dark mode</span>
                <input id="dark-mode-toggle" type="checkbox" role="switch" x-model="darkMode" @change="saveTheme()" :aria-checked="darkMode.toString()">
            </label>
        </section>

        <form method="POST" action="{{ route('profile.update') }}">
            @csrf
            @method('PUT')

            <h2 class="settings-section">Account information</h2>
            <label for="name">Full name</label>
            <input id="name" name="name" value="{{ old('name', $user->name) }}" autocomplete="name" required>
            @error('name') <p class="error">{{ $message }}</p> @enderror

            <label for="email">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" placeholder="name@crisbarber.com" aria-describedby="email-domain-help" required>
            <p id="email-domain-help" class="muted">New email addresses must use @crisbarber.com. You may keep your existing email unchanged.</p>
            @error('email') <p class="error">{{ $message }}</p> @enderror

            <h2 class="settings-section">Change password</h2>
            <p id="password-help" class="field-hint">Leave both fields blank to keep your current password. New passwords must contain at least 8 characters.</p>
           
            <label for="password">New password</label>
            <input id="password" type="password" name="password" autocomplete="new-password" aria-describedby="password-help">
            @error('password') <p class="error">{{ $message }}</p> @enderror

            <label for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password">

            <div class="actions">
                <button type="submit">Save changes</button>
                <a href="{{ route('dashboard') }}">Back to dashboard</a>
            </div>
        </form>
    </section>
</div>
@endsection
