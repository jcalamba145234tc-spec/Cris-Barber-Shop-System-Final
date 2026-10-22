<p class="muted">Update the managed barber account without changing its role or historical transaction identity. Manage status in the table. Reset the password below when needed.</p>
<form method="POST" action="{{ route('barbers.update', $barber) }}">
<input type="hidden" name="_modal" value="edit-barber">@csrf @method('PUT')
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
<div><label for="name">Full name</label><input id="name" name="name" value="{{ old('name',$barber->user->name) }}" maxlength="255" required></div>
<div><label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email',$barber->user->email) }}" maxlength="255" required><p class="muted">New email addresses must use @crisbarber.com. An unchanged legacy address can be retained.</p></div>
<div><label for="phone_number">Phone (optional)</label><input id="phone_number" name="phone_number" type="tel" value="{{ old('phone_number',$barber->phone_number) }}" maxlength="20"></div>
<div><label for="hired_date">Hired date</label><input id="hired_date" name="hired_date" type="date" value="{{ old('hired_date',$barber->hired_date) }}" max="{{ today()->format('Y-m-d') }}" required></div>
</div>
<div class="actions"><button>Save barber details</button><button type="button" class="secondary-button" x-data @click="$el.closest('dialog').close()">Cancel</button></div>
</form>

<details class="barber-password-section" @if($errors->has('password') || $errors->has('password_confirmation')) open @endif>
    <summary>Reset password</summary>
    <p class="muted">Only reset this password when needed. Share the new password with the barber securely.</p>
    <form method="POST" action="{{ route('barbers.password', $barber) }}">
        @csrf
        <input type="hidden" name="_modal" value="edit-barber">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label for="barber-new-password">Temporary password</label><input id="barber-new-password" type="password" name="password" minlength="8" autocomplete="new-password" required><x-field-error name="password" /></div>
            <div><label for="barber-confirm-password">Confirm password</label><input id="barber-confirm-password" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required><x-field-error name="password_confirmation" /></div>
        </div>
        <div class="actions"><button type="submit">Reset password</button></div>
    </form>
</details>
