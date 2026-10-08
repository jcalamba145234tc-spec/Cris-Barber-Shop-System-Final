@extends('layouts.shop', ['heading' => 'Point of Sale'])
@section('module')
<section class="panel">
    <div class="panel-heading"><h2>New service / checkout</h2><span class="live-badge">Available barbers: {{ $barbers->count() }}</span></div>
    <p class="muted">Only active services with enough current stock for one unit are listed. Quantities are rechecked on the server. No stock is deducted until completion. The transaction date and time are recorded automatically when you save.</p>
    @if($services->isEmpty() || $barbers->isEmpty())<p class="alert">No available {{ $services->isEmpty() ? 'services' : 'barbers' }}. Update availability or inventory before checkout.</p>@endif
    <form method="POST" action="{{ route('transactions.store') }}" data-pos-form>
        @csrf
        <input type="hidden" name="checkout_token" value="{{ old('checkout_token', (string)\Illuminate\Support\Str::uuid()) }}">
        <div class="form-grid grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            <div><label for="barber_id">Barber</label><select id="barber_id" name="barber_id" required><option value="">Choose barber</option>@foreach($barbers as $barber)<option value="{{ $barber->id }}" @selected(old('barber_id') === $barber->id)>{{ $barber->name }}</option>@endforeach</select></div>
            <div><label for="service_id">Service / price</label><select id="service_id" name="service_id" data-pos-service required><option value="" data-price="0">Choose available service</option>@foreach($services as $service)<option value="{{ $service->id }}" data-price="{{ $service->price }}" @selected(old('service_id') === $service->id)>{{ $service->name }} — ₱{{ number_format($service->price,2) }}</option>@endforeach</select></div>
            <div><label for="quantity">Quantity</label><input id="quantity" name="quantity" type="number" min="1" max="100" value="{{ old('quantity',1) }}" data-pos-quantity required></div>
            <div><label for="customer_id">Customer (optional)</label><select id="customer_id" name="customer_id"><option value="">Walk-in / not recorded</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string)old('customer_id') === (string)$customer->id)>{{ $customer->name }}</option>@endforeach</select><a href="{{ route('customers.create') }}">Add customer</a></div>
            <div><label for="payment_method">Payment method</label><select id="payment_method" name="payment_method" data-payment-method required><option value="cash" @selected(old('payment_method') === 'cash')>Cash</option><option value="gcash" @selected(old('payment_method') === 'gcash')>GCash</option></select></div>
            <div data-gcash-field hidden><label for="gcash_reference_no">GCash Reference Number</label><input id="gcash_reference_no" name="gcash_reference_no" value="{{ old('gcash_reference_no') }}" inputmode="numeric" pattern="[0-9]{6,30}" minlength="6" maxlength="30" disabled><p class="muted">6–30 digits, manually verified by admin.</p></div>
            <div><label for="status">Transaction status</label><select id="status" name="status"><option value="queued" @selected(old('status') === 'queued')>Add to queue (no stock deduction)</option><option value="completed" @selected(old('status') === 'completed')>Service delivered / complete</option></select><p class="muted">GCash remains queued until verified and explicitly completed.</p></div>
            <article class="stat"><span>Transaction total</span><strong data-pos-total aria-live="polite">₱0.00</strong><p class="muted">Price and earnings calculated by server.</p></article>
            <div data-cash-field>
                <label for="cash_received">Customer's money / Cash received</label>
                <input id="cash_received" name="cash_received" data-cash-received type="number" inputmode="decimal" min="0" max="9999999999.99" step="0.01" value="{{ old('cash_received') }}" required>
                <p class="muted">Cash payments only. Enter the amount handed over by the customer.</p>
                <p class="error" data-cash-error role="status" hidden></p>
            </div>
            <article class="stat" data-cash-change-card>
                <span>Customer's change</span><strong data-cash-change aria-live="polite">₱0.00</strong>
                <p class="muted">Customer's money − transaction total.</p>
            </article>
        </div>
        <div class="actions"><button @disabled($services->isEmpty() || $barbers->isEmpty())>Save transaction</button></div>
    </form>
</section>
@endsection
