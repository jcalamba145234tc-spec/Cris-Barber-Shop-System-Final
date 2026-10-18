@extends('layouts.shop', ['heading' => 'Point of Sale'])
@section('module')
<section class="panel">
    <div class="panel-heading"><h2>New service / checkout</h2><span class="live-badge">Available barbers: {{ $barbers->count() }}</span></div>
    @if($services->isEmpty() || $barbers->isEmpty())<p class="alert">No available {{ $services->isEmpty() ? 'services' : 'barbers' }}. Update availability or inventory before checkout.</p>@endif
    <form method="POST" action="{{ route('transactions.store') }}" data-pos-form>
        @csrf
        <input type="hidden" name="checkout_token" value="{{ old('checkout_token', (string)\Illuminate\Support\Str::uuid()) }}">
        <div class="form-grid pos-form-grid grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            <div class="pos-barber-field"><label for="barber_id">Barber</label><select id="barber_id" name="barber_id" required><option value="">Choose barber</option>@foreach($barbers as $barber)<option value="{{ $barber->id }}" @selected(old('barber_id') === $barber->id)>{{ $barber->name }}</option>@endforeach</select></div>
            <fieldset class="service-choices" data-pos-services>
                <legend>Services / prices <span class="muted">(select one or more)</span></legend>
                <div class="service-choices-content">
                    <div class="service-picker-box">
                        <button type="button" class="service-picker-trigger" data-service-picker-trigger aria-expanded="false" aria-controls="pos-service-list" @disabled($services->isEmpty())>Select Services</button>
                        <div id="pos-service-list" class="service-choice-list" hidden>
                            @foreach($services as $service)
                                @php($selectedServices = (array) old('service_ids', old('service_id') ? [old('service_id')] : []))
                                @php($isServiceSelected = in_array($service->id, $selectedServices, true))
                                <div class="service-choice">
                                    <label class="service-choice-description">
                                        <input type="checkbox" name="service_ids[]" value="{{ $service->id }}" data-pos-service data-price="{{ $service->price }}" @checked($isServiceSelected)>
                                        <span>{{ $service->name }}</span>
                                        <strong>₱{{ number_format($service->price,2) }}</strong>
                                    </label>
                                    <input type="hidden" name="service_quantities[{{ $service->id }}]" value="{{ old('service_quantities.'.$service->id, old('quantity', 1)) }}" data-pos-service-quantity @disabled(!$isServiceSelected)>
                                </div>
                            @endforeach
                        </div>
                        <div class="service-picker-actions" data-service-picker-actions hidden>
                            <button type="button" class="service-picker-done" data-service-picker-done>Done</button>
                        </div>
                        <div class="service-selection-summary" data-service-selection-summary aria-live="polite" hidden></div>
                    </div>
                </div>
                @error('service_ids')<p class="error" role="alert">{{ $message }}</p>@enderror
                @error('service_ids.*')<p class="error" role="alert">{{ $message }}</p>@enderror
                @error('service_id')<p class="error" role="alert">{{ $message }}</p>@enderror
            </fieldset>
            <div class="pos-customer"><label for="customer_id">Customer (optional)</label><select id="customer_id" name="customer_id"><option value="">Walk-in / not recorded</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string)old('customer_id') === (string)$customer->id)>{{ $customer->name }}</option>@endforeach</select><a href="{{ route('customers.create') }}">Add customer</a></div>
            <div class="pos-payment-method"><label for="payment_method">Payment method</label><select id="payment_method" name="payment_method" data-payment-method required><option value="cash" @selected(old('payment_method') === 'cash')>Cash</option><option value="gcash" @selected(old('payment_method') === 'gcash')>GCash</option></select></div>
            <div data-gcash-field hidden><label for="gcash_reference_no">GCash Reference Number</label><input id="gcash_reference_no" name="gcash_reference_no" value="{{ old('gcash_reference_no') }}" inputmode="numeric" pattern="[0-9]{6,30}" minlength="6" maxlength="30" disabled><p class="muted">6–30 digits, manually verified by admin.</p></div>
            <div class="pos-transaction-status"><label for="status">Transaction status</label><select id="status" name="status"><option value="queued" @selected(old('status') === 'queued')>Add to queue (no stock deduction)</option><option value="completed" @selected(old('status') === 'completed')>Service delivered / complete</option></select><p class="muted">GCash pending until verified and completed.</p></div>
            <article class="stat pos-transaction-total"><span>Transaction total</span><strong data-pos-total aria-live="polite">₱0.00</strong><p class="muted">Price and earnings calculated by server.</p></article>
            <div class="pos-cash-received" data-cash-field>
                <label for="cash_received">Customer's money / Cash received</label>
                <input id="cash_received" name="cash_received" data-cash-received type="number" inputmode="decimal" min="0" max="9999999999.99" step="0.01" value="{{ old('cash_received') }}" required>
                <p class="muted">Cash only. Enter amount received.</p>
                <p class="error" data-cash-error role="status" hidden></p>
            </div>
            <article class="stat pos-cash-change" data-cash-change-card>
                <span>Customer's change</span><strong data-cash-change aria-live="polite">₱0.00</strong>
                <p class="muted">Customer's money − transaction total.</p>
            </article>
            <div class="actions pos-submit-actions"><button @disabled($services->isEmpty() || $barbers->isEmpty())>Save transaction</button></div>
        </div>
    </form>
</section>
@endsection
