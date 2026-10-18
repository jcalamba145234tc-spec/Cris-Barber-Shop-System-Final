<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CashTenderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $barberUser;

    private Barber $barber;

    private Service $service;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->barberUser = User::factory()->create(['role' => 'barber']);
        $this->barber = Barber::create(['user_id' => $this->barberUser->id, 'first_name' => 'Cash', 'last_name' => 'Barber', 'hired_date' => today(), 'status' => 'active', 'availability' => 'available']);
        $this->service = Service::create(['name' => 'Cash Haircut', 'price' => '150.00', 'duration_minutes' => 30, 'active' => true]);
        $this->product = Product::create(['name' => 'Cash Wax', 'stock' => 10, 'unit' => 'portion', 'low_stock_threshold' => 5, 'active' => true]);
        $this->service->products()->attach($this->product->id, ['quantity' => 1]);
    }

    private function checkout(array $overrides = []): array
    {
        return array_replace([
            'checkout_token' => (string) Str::uuid(), 'barber_id' => $this->barber->id,
            'service_id' => $this->service->id, 'quantity' => 1, 'status' => 'completed',
            'payment_method' => 'cash', 'cash_received' => '500.00',
        ], $overrides);
    }

    public function test_cash_received_and_change_are_saved_without_inflating_sales(): void
    {
        $this->actingAs($this->admin)->post('/transactions', $this->checkout())->assertSessionHasNoErrors();
        $transaction = Transaction::firstOrFail();
        $this->assertSame('500.00', $transaction->payment->cash_received);
        $this->assertSame('350.00', $transaction->payment->change_amount);
        $this->assertSame('150.00', $transaction->payment->amount);
        $this->assertEquals(150, $transaction->total_amount);
        $this->assertEquals(75, $transaction->barber_share);
        $this->get('/reports/pdf')->assertViewHas('grossSales', 150);
        $this->get('/transactions/'.$transaction->id)->assertSee('data-saved-cash', false)->assertSee('₱500.00')->assertSee('₱350.00');
    }

    public function test_cash_is_required_for_cash_payment(): void
    {
        $data = $this->checkout();
        unset($data['cash_received']);
        $this->actingAs($this->admin)->post('/transactions', $data)->assertSessionHasErrors('cash_received');
        $this->assertSame(0, Transaction::count());
    }

    public function test_insufficient_cash_rejects_sale_payment_and_stock_deduction(): void
    {
        $this->actingAs($this->admin)->post('/transactions', $this->checkout(['cash_received' => '149.99']))->assertSessionHasErrors('cash_received');
        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, InventoryMovement::count());
        $this->assertEquals(10, $this->product->fresh()->stock);
    }

    public function test_change_uses_the_total_for_all_service_units(): void
    {
        $this->actingAs($this->admin)->post('/transactions', $this->checkout(['quantity' => 2]))->assertSessionHasNoErrors();
        $payment = Payment::firstOrFail();
        $this->assertSame('300.00', $payment->amount);
        $this->assertSame('200.00', $payment->change_amount);
    }

    public function test_exact_cash_records_zero_change(): void
    {
        $this->actingAs($this->admin)->post('/transactions', $this->checkout(['cash_received' => '150']))->assertSessionHasNoErrors();
        $this->assertSame('0.00', Payment::firstOrFail()->change_amount);
    }

    public function test_cent_calculation_is_exact(): void
    {
        $this->service->update(['price' => '149.95']);
        $this->actingAs($this->admin)->post('/transactions', $this->checkout(['cash_received' => '200.10']))->assertSessionHasNoErrors();
        $this->assertSame('50.15', Payment::firstOrFail()->change_amount);
    }

    public function test_invalid_cash_formats_and_amounts_are_rejected(): void
    {
        foreach (['-1', 'abc', '150.001', '1e3', '10000000000', ''] as $cash) {
            $this->actingAs($this->admin)->post('/transactions', $this->checkout(['cash_received' => $cash]))->assertSessionHasErrors('cash_received');
        }
        $this->assertSame(0, Transaction::count());
    }

    public function test_gcash_does_not_require_or_store_cash_received(): void
    {
        $data = $this->checkout(['payment_method' => 'gcash', 'gcash_reference_no' => '8888888888888']);
        unset($data['cash_received']);
        $this->actingAs($this->admin)->post('/transactions', $data)->assertSessionHasNoErrors();
        $payment = Payment::firstOrFail();
        $this->assertNull($payment->cash_received);
        $this->assertNull($payment->change_amount);
        $this->assertSame('pending', $payment->verification_status);
    }

    public function test_gcash_ignores_stale_cash_input_when_switching_payment_method(): void
    {
        $this->actingAs($this->admin)->post('/transactions', $this->checkout(['payment_method' => 'gcash', 'gcash_reference_no' => '9999999999999', 'cash_received' => '-100']))->assertSessionHasNoErrors();
        $this->assertNull(Payment::firstOrFail()->cash_received);
        $this->assertNull(Payment::firstOrFail()->change_amount);
    }

    public function test_submitted_change_is_rejected_rather_than_trusted(): void
    {
        $this->actingAs($this->admin)->post('/transactions', $this->checkout(['change_amount' => '9999']))->assertSessionHasErrors('change_amount');
        $this->assertSame(0, Transaction::count());
    }

    public function test_duplicate_checkout_preserves_original_tender_and_change(): void
    {
        $data = $this->checkout();
        $this->actingAs($this->admin)->post('/transactions', $data)->assertSessionHasNoErrors();
        $data['cash_received'] = '1000.00';
        $this->post('/transactions', $data)->assertSessionHasErrors('checkout_token');
        $this->assertSame(1, Payment::count());
        $this->assertSame('500.00', Payment::firstOrFail()->cash_received);
        $this->assertSame('350.00', Payment::firstOrFail()->change_amount);
        $this->assertEquals(9, $this->product->fresh()->stock);
    }

    public function test_barber_cannot_enter_cash_or_modify_saved_tender(): void
    {
        $this->actingAs($this->barberUser)->post('/transactions', $this->checkout())->assertForbidden();
        $transaction = app(TransactionWorkflow::class)->create($this->checkout(['status' => 'queued']), $this->admin);
        $this->patch('/transactions/'.$transaction->id.'/status', ['status' => 'in_progress', 'cash_received' => 1])->assertSessionHasErrors('cash_received');
        $this->get('/transactions/'.$transaction->id)->assertDontSee('data-saved-cash', false);
        $this->assertSame('500.00', $transaction->payment->cash_received);
    }

    public function test_direct_workflow_call_cannot_bypass_insufficient_cash_validation(): void
    {
        try {
            app(TransactionWorkflow::class)->create($this->checkout(['cash_received' => '1.00']), $this->admin);
            $this->fail('Insufficient tender should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cash_received', $exception->errors());
        }
        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, Payment::count());
    }

    public function test_queued_cash_records_tender_without_deducting_stock(): void
    {
        $this->actingAs($this->admin)->post('/transactions', $this->checkout(['status' => 'queued']))->assertSessionHasNoErrors();
        $this->assertSame('350.00', Payment::firstOrFail()->change_amount);
        $this->assertSame('queued', Transaction::firstOrFail()->status);
        $this->assertEquals(10, $this->product->fresh()->stock);
    }

    public function test_old_cash_records_without_tender_still_render(): void
    {
        $transaction = app(TransactionWorkflow::class)->create($this->checkout(), $this->admin);
        $transaction->payment->update(['cash_received' => null, 'change_amount' => null]);
        $this->actingAs($this->admin)->get('/transactions/'.$transaction->id)->assertOk()->assertSee('Not recorded (existing payment)');
    }

    public function test_saved_cash_and_change_cannot_be_rewritten_through_history_or_queue(): void
    {
        $transaction = app(TransactionWorkflow::class)->create($this->checkout(['status' => 'queued']), $this->admin);
        $this->actingAs($this->admin)->put('/transactions/'.$transaction->id, ['transaction_date' => now()->format('Y-m-d\TH:i'), 'cash_received' => '1000', 'change_amount' => '850'])->assertSessionHasErrors(['cash_received', 'change_amount']);
        $this->actingAs($this->barberUser)->patch('/transactions/'.$transaction->id.'/status', ['status' => 'in_progress', 'change_amount' => '9999'])->assertSessionHasErrors('change_amount');
        $this->assertSame('500.00', $transaction->payment()->first()->cash_received);
        $this->assertSame('350.00', $transaction->payment()->first()->change_amount);
    }
}
