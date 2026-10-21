<form method="POST" action="{{ route('accounts.store') }}" x-data="barberEmail(@js(route('accounts.email-preview')), @js(old('first_name', '')), @js(old('last_name', '')))" x-init="preview()">
@csrf<input type="hidden" name="role" value="barber"><input type="hidden" name="_modal" value="add-barber">
<p class="muted">Create a staff workspace. The login address is generated automatically; share the initial password securely.</p>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
<div><label for="first_name">First Name</label><input id="first_name" name="first_name" x-model="first" @input.debounce.350ms="preview()" value="{{ old('first_name') }}" autocomplete="given-name" maxlength="100" required><x-field-error name="first_name" /></div>
<div><label for="last_name">Last Name</label><input id="last_name" name="last_name" x-model="last" @input.debounce.350ms="preview()" value="{{ old('last_name') }}" autocomplete="family-name" maxlength="100" required><x-field-error name="last_name" /></div>
</div>
<label for="generated-email">Login email</label><input id="generated-email" type="email" :value="email" readonly aria-describedby="generated-help">
<p class="muted field-hint" id="generated-help" x-text="hint">Numbers are appended when a name is already taken. Final address is assigned on save.</p>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
<div><label for="new-password">Initial password</label><input id="new-password" type="password" name="password" minlength="8" autocomplete="new-password" required><x-field-error name="password" /></div>
<div><label for="confirm-password">Confirm password</label><input id="confirm-password" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required></div>
</div><div class="actions justify-end"><button type="button" class="secondary-button" @click="$el.closest('dialog').close()">Cancel</button><button type="submit">Create barber account</button></div>
</form>
