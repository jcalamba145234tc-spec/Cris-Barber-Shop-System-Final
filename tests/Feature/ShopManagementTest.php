<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Barber;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionWorkflow;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShopManagementTest extends TestCase
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
        $this->barber = Barber::create(['user_id' => $this->barberUser->id, 'first_name' => 'Jane', 'last_name' => 'Barber', 'hired_date' => today(), 'status' => 'active', 'availability' => 'available']);
        $this->product = Product::create(['name' => 'Wax', 'stock' => 10, 'unit' => 'portion', 'low_stock_threshold' => 5, 'active' => true]);
        $this->service = Service::create(['name' => 'Haircut', 'price' => 150, 'duration_minutes' => 30, 'active' => true]);
        $this->service->products()->attach($this->product->id, ['quantity' => 2]);
    }

    private function checkout(array $overrides = []): array
    {
        return array_replace([
            'checkout_token' => (string) Str::uuid(),
            'barber_id' => $this->barber->id, 'service_id' => $this->service->id,
            'quantity' => 1, 'payment_method' => 'cash', 'cash_received' => '1000.00', 'status' => 'completed',
        ], $overrides);
    }

    private function queued(array $overrides = []): Transaction
    {
        return app(TransactionWorkflow::class)->create($this->checkout(array_replace(['status' => 'queued'], $overrides)), $this->admin);
    }

    public function test_public_admin_registration_is_removed(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['role' => 'admin'])->assertNotFound();
        $this->get('/login')->assertOk()->assertDontSee('Set up administrator account');
        $this->get('/accounts/create')->assertRedirect('/login');
    }

    public function test_owner_cli_provisions_admin_without_public_registration(): void
    {
        $this->artisan('shop:admin', ['email' => 'owner@crisbarber.com', '--name' => 'Owner'])
            ->expectsQuestion('Password (minimum 12 characters)', 'Owner-secret-123')
            ->expectsQuestion('Confirm password', 'Owner-secret-123')->assertSuccessful();
        $owner = User::where('email', 'owner@crisbarber.com')->firstOrFail();
        $this->assertSame('admin', $owner->role);
        $this->assertTrue(Hash::check('Owner-secret-123', $owner->password));
    }

    public function test_admin_and_barber_login_logout(): void
    {
        foreach ([$this->admin, $this->barberUser] as $user) {
            $user->update(['password' => 'Valid-password-123']);
            $this->post('/login', ['email' => $user->email, 'password' => 'Valid-password-123'])->assertRedirect('/dashboard');
            $this->assertAuthenticatedAs($user);
            $this->get('/dashboard')->assertOk();
            $this->post('/logout')->assertRedirect('/login');
            $this->assertGuest();
        }
    }

    public function test_invalid_login_is_rejected(): void
    {
        $this->post('/login', ['email' => $this->admin->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_account_password_email_change_for_both_roles(): void
    {
        foreach ([$this->admin, $this->barberUser] as $index => $user) {
            $user->update(['password' => 'Old-password-123']);
            $this->actingAs($user)->put('/profile', ['name' => 'Updated', 'email' => 'updated'.$index.'@crisbarber.com', 'password' => 'New-password-123', 'password_confirmation' => 'New-password-123'])->assertRedirect('/profile');
            $this->assertTrue(Hash::check('New-password-123', $user->fresh()->password));
            $this->assertSame('updated'.$index.'@crisbarber.com', $user->fresh()->email);
        }
    }

    public function test_barber_can_change_password_without_current_password(): void
    {
        $this->actingAs($this->barberUser)->get('/profile')->assertOk()->assertDontSee('name="current_password"', false);
        $data = ['name' => $this->barberUser->name, 'email' => $this->barberUser->email, 'password' => 'New-password-123', 'password_confirmation' => 'New-password-123'];
        $this->put('/profile', $data)->assertRedirect('/profile')->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('New-password-123', $this->barberUser->fresh()->password));
    }

    public function test_admin_can_change_password_without_current_password(): void
    {
        $this->actingAs($this->admin)->get('/profile')->assertOk()->assertDontSee('name="current_password"', false);
        $this->put('/profile', [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'password' => 'Admin-new-password-123',
            'password_confirmation' => 'Admin-new-password-123',
        ])->assertRedirect('/profile')->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Admin-new-password-123', $this->admin->fresh()->password));
        $this->actingAs($this->barberUser)->get('/profile')->assertOk()->assertDontSee('name="current_password"', false);
    }

    public function test_admin_new_password_still_requires_confirmation_and_minimum_length(): void
    {
        $data = ['name' => $this->admin->name, 'email' => $this->admin->email];
        $originalHash = $this->admin->password;
        $this->actingAs($this->admin)->put('/profile', $data + ['password' => 'Admin-new-password-123', 'password_confirmation' => 'Mismatch'])->assertSessionHasErrors('password');
        $this->put('/profile', $data + ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->assertSame($originalHash, $this->admin->fresh()->password);
    }

    public function test_admin_can_save_account_details_without_changing_password(): void
    {
        $originalHash = $this->admin->password;
        $this->actingAs($this->admin)->put('/profile', ['name' => 'Updated Admin', 'email' => $this->admin->email])->assertRedirect('/profile')->assertSessionHasNoErrors();
        $this->assertSame($originalHash, $this->admin->fresh()->password);
    }

    public function test_admin_can_create_barber_but_not_web_admin(): void
    {
        $data = ['name' => 'New Barber', 'email' => 'new@crisbarber.com', 'role' => 'barber', 'password' => 'Valid-password-123', 'password_confirmation' => 'Valid-password-123'];
        $this->actingAs($this->admin)->post('/accounts', $data)->assertRedirect('/barbers');
        $new = User::where('email', $data['email'])->firstOrFail();
        $this->assertDatabaseHas('barbers', ['user_id' => $new->id]);
        $data['role'] = 'admin';
        $data['email'] = 'forbidden@example.test';
        $this->post('/accounts', $data)->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => $data['email']]);
    }

    public function test_admin_pages_render_without_php_errors(): void
    {
        $transaction = $this->queued();
        $customer = Customer::create(['name' => 'Customer A']);
        $urls = ['/dashboard', '/pos', '/transactions', '/history', '/inventory', '/inventory/create', '/inventory/'.$this->product->id, '/inventory/'.$this->product->id.'/edit', '/services', '/services/create', '/services/'.$this->service->id, '/services/'.$this->service->id.'/edit', '/barbers', '/attendance', '/customers', '/customers/create', '/customers/'.$customer->id, '/customers/'.$customer->id.'/edit', '/profile', '/accounts/create', '/transactions/'.$transaction->id, '/transactions/'.$transaction->id.'/edit', '/reports/pdf', '/reports/csv'];
        foreach ($urls as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_barber_cannot_access_admin_modules_or_write_finances(): void
    {
        $transaction = $this->queued();
        foreach (['/pos', '/inventory', '/services', '/barbers', '/customers', '/accounts/create', '/reports/csv', '/reports/pdf', '/transactions/'.$transaction->id.'/edit'] as $url) {
            $this->actingAs($this->barberUser)->get($url)->assertForbidden();
        }
        foreach ([['post', '/transactions'], ['post', '/services'], ['post', '/inventory'], ['post', '/accounts'], ['post', '/customers'], ['post', '/attendance'], ['put', '/transactions/'.$transaction->id], ['delete', '/transactions/'.$transaction->id], ['post', '/payments/'.$transaction->payment->id.'/verify'], ['post', '/inventory/'.$this->product->id.'/restock'], ['patch', '/barbers/'.$this->barber->id.'/status'], ['post', '/barbers/'.$this->barber->id.'/reset-password']] as [$method, $url]) {
            $this->actingAs($this->barberUser)->{$method}($url, $this->checkout())->assertForbidden();
        }
        $this->get('/dashboard')->assertDontSee('data-pos-form')->assertDontSee('name="payment_method"', false)->assertDontSee('href="http://localhost/inventory"', false);
    }

    public function test_unknown_customer_role_cannot_access_shop_features(): void
    {
        $customerUser = User::factory()->create(['role' => 'customer']);
        foreach (['/dashboard', '/pos', '/inventory', '/transactions', '/attendance'] as $url) {
            $this->actingAs($customerUser)->get($url)->assertForbidden();
        }
        $this->assertDatabaseHas('users', ['id' => $customerUser->id, 'role' => 'customer']);
    }

    public function test_cash_checkout_deducts_stock_and_preserves_split(): void
    {
        $this->actingAs($this->admin)->post('/transactions', $this->checkout(['quantity' => 2, 'gcash_reference_no' => 'irrelevant']))->assertSessionHasNoErrors()->assertRedirect();
        $transaction = Transaction::firstOrFail();
        $this->assertSame('completed', $transaction->status);
        $this->assertEquals(300, $transaction->total_amount);
        $this->assertEquals(150, $transaction->barber_share);
        $this->assertEquals(6, $this->product->fresh()->stock);
        $this->assertNull($transaction->payment->gcash_reference_no);
        $this->assertDatabaseHas('inventory_movements', ['transaction_id' => $transaction->id, 'quantity' => -4]);
    }

    public function test_no_inventory_deduction_for_queued_transaction_and_idempotent_completion(): void
    {
        $transaction = $this->queued();
        $this->assertEquals(10, $this->product->fresh()->stock);
        $workflow = app(TransactionWorkflow::class);
        $workflow->complete($transaction, $this->admin);
        $workflow->complete($transaction, $this->admin);
        $this->assertEquals(8, $this->product->fresh()->stock);
        $this->assertSame(1, InventoryMovement::where('transaction_id', $transaction->id)->count());
    }

    public function test_gcash_requires_valid_unique_reference_and_manual_verification(): void
    {
        foreach ([null, '', 'abc', '123'] as $reference) {
            $this->actingAs($this->admin)->post('/transactions', $this->checkout(['payment_method' => 'gcash', 'gcash_reference_no' => $reference]))->assertSessionHasErrors('gcash_reference_no');
        }
        $this->assertSame(0, Transaction::count());
        $this->post('/transactions', $this->checkout(['payment_method' => 'gcash', 'gcash_reference_no' => '1234567890123']))->assertSessionHasNoErrors();
        $transaction = Transaction::firstOrFail();
        $this->assertSame('queued', $transaction->status);
        $this->assertEquals(10, $this->product->fresh()->stock);
        $this->patch('/transactions/'.$transaction->id.'/status', ['status' => 'completed'])->assertSessionHasErrors('payment');
        $this->post('/payments/'.$transaction->payment->id.'/verify', ['status' => 'verified'])->assertSessionHasNoErrors();
        $this->patch('/transactions/'.$transaction->id.'/status', ['status' => 'completed'])->assertSessionHasNoErrors();
        $this->assertEquals(8, $this->product->fresh()->stock);
        $this->post('/transactions', $this->checkout(['payment_method' => 'gcash', 'gcash_reference_no' => '1234567890123']))->assertSessionHasErrors('gcash_reference_no');
    }

    public function test_out_of_stock_inactive_services_products_are_hidden_and_blocked(): void
    {
        $this->product->update(['stock' => 1]);
        $this->actingAs($this->admin)->get('/pos')->assertOk()->assertDontSee('Haircut');
        $this->post('/transactions', $this->checkout())->assertSessionHasErrors('service_id');
        $this->product->update(['stock' => 10, 'active' => false]);
        $this->get('/pos')->assertDontSee('Haircut');
        $this->post('/transactions', $this->checkout())->assertSessionHasErrors('service_id');
        $this->product->update(['active' => true]);
        $this->service->update(['active' => false]);
        $this->post('/transactions', $this->checkout())->assertSessionHasErrors('service_id');
    }

    public function test_cart_quantity_stock_check_and_tampered_amounts(): void
    {
        $this->actingAs($this->admin)->post('/transactions', $this->checkout(['quantity' => 6]))->assertSessionHasErrors('service_id');
        foreach (['total_amount', 'barber_share', 'shop_share', 'unit_price', 'amount'] as $field) {
            $this->post('/transactions', $this->checkout([$field => 1]))->assertSessionHasErrors($field);
        }
        $this->assertEquals(10, $this->product->fresh()->stock);
        $this->assertSame(0, Transaction::count());
    }

    public function test_completion_stock_shortage_rolls_back_every_product(): void
    {
        $second = Product::create(['name' => 'Shampoo', 'stock' => 10, 'unit' => 'portion', 'active' => true]);
        $this->service->products()->attach($second->id, ['quantity' => 2]);
        $transaction = $this->queued();
        $second->update(['stock' => 0]);
        $this->actingAs($this->admin)->patch('/transactions/'.$transaction->id.'/status', ['status' => 'completed'])->assertSessionHasErrors('stock');
        $this->assertEquals(10, $this->product->fresh()->stock);
        $this->assertSame('queued', $transaction->fresh()->status);
        $this->assertSame(0, InventoryMovement::where('transaction_id', $transaction->id)->count());
        $this->assertNull($transaction->fresh()->inventory_deducted_at);
    }

    public function test_price_and_requirements_snapshots_do_not_change_after_service_edit(): void
    {
        $transaction = $this->queued();
        $this->service->update(['price' => 999]);
        $this->service->products()->sync([$this->product->id => ['quantity' => 5]]);
        app(TransactionWorkflow::class)->complete($transaction, $this->admin);
        $this->assertEquals(150, $transaction->fresh()->total_amount);
        $this->assertEquals(8, $this->product->fresh()->stock);
    }

    public function test_available_barbers_use_status_availability_and_open_queue(): void
    {
        $this->assertSame(1, Barber::available()->count());
        $this->barber->update(['availability' => 'unavailable']);
        $this->actingAs($this->admin)->post('/transactions', $this->checkout())->assertSessionHasErrors('barber_id');
        $this->barber->update(['availability' => 'available', 'status' => 'on_leave']);
        $this->assertSame(0, Barber::available()->count());
        $this->barber->update(['status' => 'active']);
        $transaction = $this->queued();
        $this->assertSame(0, Barber::available()->count());
        $this->post('/transactions', $this->checkout())->assertSessionHasErrors('barber_id');
        $this->patch('/transactions/'.$transaction->id.'/status', ['status' => 'cancelled'])->assertSessionHasNoErrors();
        $this->assertSame(1, Barber::available()->count());
    }

    public function test_barber_can_only_advance_own_queue_not_financial_completion(): void
    {
        $transaction = $this->queued();
        $this->actingAs($this->barberUser)->patch('/transactions/'.$transaction->id.'/status', ['status' => 'in_progress'])->assertSessionHasNoErrors();
        $this->patch('/transactions/'.$transaction->id.'/status', ['status' => 'ready', 'total_amount' => 1])->assertSessionHasErrors('total_amount');
        $this->patch('/transactions/'.$transaction->id.'/status', ['status' => 'ready'])->assertSessionHasNoErrors();
        $this->patch('/transactions/'.$transaction->id.'/status', ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->assertSame('ready', $transaction->fresh()->status);
        $this->assertEquals(10, $this->product->fresh()->stock);
        $otherUser = User::factory()->create(['role' => 'barber']);
        Barber::create(['user_id' => $otherUser->id, 'first_name' => 'Other', 'last_name' => 'Barber', 'hired_date' => today()]);
        $this->actingAs($otherUser)->get('/transactions/'.$transaction->id)->assertForbidden();
        $this->patch('/transactions/'.$transaction->id.'/status', ['status' => 'in_progress'])->assertForbidden();
        $this->get('/transactions')->assertDontSee($transaction->id);
    }

    public function test_completed_history_customer_correction_preserves_date_stock_and_audit(): void
    {
        $transaction = app(TransactionWorkflow::class)->create($this->checkout(), $this->admin);
        $customer = Customer::create(['name' => 'Correct customer']);
        $date = $transaction->transaction_date->format('Y-m-d\TH:i');
        $original = $transaction->transaction_date->format('Y-m-d H:i:s');
        $this->actingAs($this->admin)->put('/transactions/'.$transaction->id, ['customer_id' => $customer->id, 'transaction_date' => $date])->assertSessionHasNoErrors();
        $this->assertEquals($customer->id, $transaction->fresh()->customer_id);
        $this->put('/transactions/'.$transaction->id, ['transaction_date' => now()->subHour()->format('Y-m-d\TH:i')])->assertSessionHasErrors('transaction_date');
        $this->put('/transactions/'.$transaction->id, ['total_amount' => 1, 'transaction_date' => $date])->assertSessionHasErrors('total_amount');
        $this->delete('/transactions/'.$transaction->id)->assertSessionHasErrors('transaction');
        $this->assertSame($original, $transaction->fresh()->transaction_date->format('Y-m-d H:i:s'));
        $this->assertFalse($transaction->fresh()->trashed());
        $this->assertEquals(8, $this->product->fresh()->stock);
        $this->assertSame(1, Transaction::valid()->count());
        $this->assertDatabaseHas('inventory_movements', ['transaction_id' => $transaction->id]);
    }

    public function test_inventory_crud_restock_and_monitoring(): void
    {
        $this->actingAs($this->admin)->post('/inventory', ['name' => 'Shampoo', 'category' => 'Hair', 'stock' => 10, 'unit' => 'bottle', 'low_stock_threshold' => 5, 'active' => 1])->assertSessionHasNoErrors();
        $product = Product::where('name', 'Shampoo')->firstOrFail();
        $this->assertSame('In Stock', $product->stock_status);
        $this->post('/inventory/'.$product->id.'/restock', ['quantity' => 20, 'restock_token' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->assertEquals(30, $product->fresh()->stock);
        $this->assertDatabaseHas('inventory_movements', ['product_id' => $product->id, 'type' => 'restock', 'quantity' => 20]);
        $this->post('/inventory/'.$product->id.'/restock', ['quantity' => -1, 'restock_token' => (string) Str::uuid()])->assertSessionHasErrors('quantity');
        $this->post('/inventory/'.$product->id.'/restock', ['quantity' => 0, 'restock_token' => (string) Str::uuid()])->assertSessionHasErrors('quantity');
        $this->put('/inventory/'.$product->id, ['name' => 'Updated Shampoo', 'unit' => 'bottle', 'category' => 'Hair', 'low_stock_threshold' => 35, 'active' => 1])->assertSessionHasNoErrors();
        $this->assertSame('Low Stock', $product->fresh()->stock_status);
        $this->put('/inventory/'.$product->id, ['stock' => -1])->assertSessionHasErrors('stock');
        $this->delete('/inventory/'.$product->id)->assertRedirect('/inventory');
        $this->assertFalse($product->fresh()->active);
    }

    public function test_service_crud_requirements_and_deactivation(): void
    {
        $data = ['name' => 'Premium', 'price' => 300, 'duration_minutes' => 40, 'active' => 1, 'requirements' => [['product_id' => $this->product->id, 'quantity' => 3]]];
        $this->actingAs($this->admin)->post('/services', $data)->assertSessionHasNoErrors();
        $service = Service::where('name', 'Premium')->firstOrFail();
        $this->assertEquals(3, $service->products->first()->pivot->quantity);
        $data['price'] = 350;
        $this->put('/services/'.$service->id, $data)->assertSessionHasNoErrors();
        $this->assertEquals(350, $service->fresh()->price);
        $this->delete('/services/'.$service->id)->assertRedirect('/services');
        $this->assertFalse($service->fresh()->active);
        $this->assertDatabaseHas('service_products', ['service_id' => $service->id]);
        $data['price'] = -1;
        $this->post('/services', $data)->assertSessionHasErrors('price');
    }

    public function test_server_side_search_and_filters(): void
    {
        $this->product->update(['stock' => 0, 'category' => 'Hair']);
        $this->actingAs($this->admin)->get('/inventory?stock_status=out&category=Hair&active=1&q=Wax')->assertOk()->assertSee('Wax');
        $this->get('/inventory?stock_status=in')->assertDontSee('Wax');
        $this->get('/services?q=does-not-exist')->assertDontSee('Haircut');
        $this->product->update(['stock' => 10]);
        $transaction = $this->queued();
        $this->get('/transactions?status=queued&payment_method=cash&barber_id='.$this->barber->id.'&service_id='.$this->service->id.'&from='.today()->format('Y-m-d').'&to='.today()->format('Y-m-d'))->assertSee($transaction->id);
        $this->get('/transactions?status=completed')->assertDontSee($transaction->id);
        $this->get('/transactions?payment_method=gcash')->assertDontSee($transaction->id);
        $this->get('/barbers?availability=available')->assertDontSee($this->barberUser->email);
        $this->get('/barbers?availability=busy')->assertSee($this->barberUser->email);
        $this->get('/inventory?stock_status=bad')->assertSessionHasErrors('stock_status');
    }

    public function test_dashboard_and_reports_exclude_invalid_transactions_and_use_actual_counts(): void
    {
        Customer::create(['name' => 'Customer']);
        $transaction = app(TransactionWorkflow::class)->create($this->checkout(), $this->admin);
        $this->product->update(['stock' => 4]);
        $response = $this->actingAs($this->admin)->get('/dashboard');
        $response->assertOk()->assertViewHas('totalCustomers', 1)->assertViewHas('availableBarbers', 1)->assertViewHas('lowStock', 1)->assertViewHas('totalRevenue', 150)->assertDontSee('Pending GCash')->assertDontSee('Transaction Rate');
        $this->get('/reports/pdf')->assertViewHas('grossSales', 150)->assertViewHas('shopShare', 75);
        $transaction->payment->update(['verification_status' => 'flagged']);
        $this->get('/dashboard')->assertViewHas('totalRevenue', 0);
        $this->get('/reports/pdf')->assertViewHas('grossSales', 0);
        $transaction->payment->update(['verification_status' => 'verified']);
        $transaction->update(['status' => 'cancelled']);
        $this->get('/dashboard')->assertViewHas('totalRevenue', 0);
    }

    public function test_barber_earnings_are_own_completed_verified_records_only(): void
    {
        $transaction = app(TransactionWorkflow::class)->create($this->checkout(), $this->admin);
        $other = Barber::create(['first_name' => 'Other', 'last_name' => 'Barber', 'hired_date' => today()]);
        app(TransactionWorkflow::class)->create($this->checkout(['barber_id' => $other->id]), $this->admin);
        $this->actingAs($this->barberUser)->get('/dashboard')->assertViewHas('todayEarnings', 75)->assertViewHas('totalEarnings', 75)->assertSee("Today's Earnings", false)->assertSee('Total Earnings');
        $this->get('/history')->assertSee($transaction->id)->assertDontSee('Other Barber');
        $transaction->payment->update(['verification_status' => 'pending']);
        $this->get('/dashboard')->assertViewHas('totalEarnings', 0);
    }

    public function test_attendance_clock_in_out_and_barber_history_protection(): void
    {
        $this->actingAs($this->barberUser)->post('/attendance/clock', ['action' => 'in'])->assertSessionHasNoErrors();
        $attendance = Attendance::firstOrFail();
        $this->assertSame($this->barber->id, $attendance->barber_id);
        $this->post('/attendance/clock', ['action' => 'in'])->assertSessionHasErrors('action');
        $this->travel(1)->hours();
        $this->post('/attendance/clock', ['action' => 'out'])->assertSessionHasNoErrors();
        $this->assertEquals(1, $attendance->fresh()->hours);
        $this->post('/attendance/clock', ['action' => 'out'])->assertSessionHasErrors('action');
        $this->put('/attendance/'.$attendance->id, ['status' => 'present'])->assertForbidden();
        $other = Barber::create(['first_name' => 'Private', 'last_name' => 'Other', 'hired_date' => today()]);
        Attendance::create(['barber_id' => $other->id, 'date' => today(), 'status' => 'absent']);
        $this->get('/attendance')->assertOk()->assertDontSee('Private Other');
        $this->get('/attendance?barber_id='.$other->id)->assertDontSee('Private Other');
    }

    public function test_admin_attendance_validation_and_correction(): void
    {
        $date = today()->subDay()->format('Y-m-d');
        $data = ['barber_id' => $this->barber->id, 'date' => $date, 'status' => 'present', 'time_in' => $date.'T09:00', 'time_out' => $date.'T17:00'];
        $this->actingAs($this->admin)->post('/attendance', $data)->assertSessionHasNoErrors();
        $attendance = Attendance::firstOrFail();
        $this->assertEquals(8, $attendance->hours);
        $this->post('/attendance', $data)->assertSessionHasErrors('date');
        $data['status'] = 'late';
        $this->put('/attendance/'.$attendance->id, $data)->assertSessionHasNoErrors();
        $this->assertSame('late', $attendance->fresh()->status);
        $data['time_out'] = $date.'T08:00';
        $this->put('/attendance/'.$attendance->id, $data)->assertSessionHasErrors('time_out');
        $data['status'] = 'absent';
        $data['time_out'] = null;
        $this->put('/attendance/'.$attendance->id, $data)->assertSessionHasErrors('time_in');
    }

    public function test_cancellation_never_deducts_stock_and_cannot_reopen(): void
    {
        $transaction = $this->queued();
        $this->actingAs($this->admin)->patch('/transactions/'.$transaction->id.'/status', ['status' => 'cancelled'])->assertSessionHasNoErrors();
        $this->assertEquals(10, $this->product->fresh()->stock);
        $this->patch('/transactions/'.$transaction->id.'/status', ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->assertSame('cancelled', $transaction->fresh()->status);
    }

    public function test_product_threshold_boundary_and_zero_stock_classification(): void
    {
        $this->product->update(['stock' => 5]);
        $this->assertSame('Low Stock', $this->product->fresh()->stock_status);
        $this->product->update(['stock' => 0]);
        $this->assertSame('Out of Stock', $this->product->fresh()->stock_status);
        $this->assertFalse(Service::available()->whereKey($this->service->id)->exists());
    }

    public function test_exact_stock_can_complete_without_going_negative(): void
    {
        $this->product->update(['stock' => 2]);
        $this->actingAs($this->admin)->post('/transactions', $this->checkout())->assertSessionHasNoErrors();
        $this->assertEquals(0, $this->product->fresh()->stock);
        $this->post('/transactions', $this->checkout())->assertSessionHasErrors('service_id');
    }

    public function test_restocks_preserve_whole_number_transaction_snapshots(): void
    {
        $transaction = $this->queued(['quantity' => 3]);
        $this->actingAs($this->admin)->post('/inventory/'.$this->product->id.'/restock', ['quantity' => 4, 'restock_token' => (string) Str::uuid()])->assertSessionHasNoErrors();
        app(TransactionWorkflow::class)->complete($transaction, $this->admin);
        $this->assertEquals(8, $this->product->fresh()->stock);
        $this->assertEquals(6, $transaction->products()->first()->pivot->quantity);
    }

    public function test_repeated_checkout_token_is_idempotent_even_after_completion(): void
    {
        $data = $this->checkout();
        $this->actingAs($this->admin)->post('/transactions', $data)->assertSessionHasNoErrors();
        $this->post('/transactions', $data)->assertSessionHasNoErrors();
        $this->assertSame(1, Transaction::count());
        $this->assertSame(1, Payment::count());
        $this->assertEquals(8, $this->product->fresh()->stock);
    }

    public function test_removed_checkout_token_cannot_recreate_a_sale(): void
    {
        $data = $this->checkout();
        $transaction = app(TransactionWorkflow::class)->create($data, $this->admin);
        $transaction->delete();
        $this->actingAs($this->admin)->post('/transactions', $data)->assertSessionHasErrors('checkout_token');
        $this->assertSame(1, Transaction::withTrashed()->count());
        $this->assertEquals(8, $this->product->fresh()->stock);
    }

    public function test_gcash_reference_database_key_prevents_duplicate_payments(): void
    {
        $transaction = $this->queued(['payment_method' => 'gcash', 'gcash_reference_no' => '987654321']);
        $this->assertSame('987654321', $transaction->payment->gcash_reference_key);
        $this->expectException(UniqueConstraintViolationException::class);
        Payment::create(['transaction_id' => $transaction->id, 'payment_method' => 'gcash', 'gcash_reference_no' => '987654321', 'gcash_reference_key' => '987654321', 'verification_status' => 'pending', 'amount' => 150]);
    }

    public function test_fractional_service_consumption_is_rejected_server_side(): void
    {
        $data = ['name' => 'Fractional', 'price' => '25.50', 'duration_minutes' => 30, 'active' => 1,
            'requirements' => [['product_id' => $this->product->id, 'quantity' => '0.1']]];
        $this->actingAs($this->admin)->post('/services', $data)->assertSessionHasErrors('requirements.0.quantity');
        $this->assertDatabaseMissing('services', ['name' => 'Fractional']);
        $this->assertEquals(10, $this->product->fresh()->stock);
    }

    public function test_customer_crud_search_and_safe_transaction_retention(): void
    {
        $this->actingAs($this->admin)->post('/customers', ['name' => 'Client One', 'phone' => '09123456789', 'email' => 'client@example.test'])->assertSessionHasNoErrors();
        $customer = Customer::firstOrFail();
        $this->get('/customers?q=09123456789')->assertSee('Client One');
        $this->get('/customers?q=nonexistent')->assertDontSee('Client One');
        $this->put('/customers/'.$customer->id, ['name' => 'Client Updated'])->assertSessionHasNoErrors();
        $transaction = app(TransactionWorkflow::class)->create($this->checkout(['customer_id' => $customer->id]), $this->admin);
        $this->delete('/customers/'.$customer->id)->assertRedirect('/customers');
        $this->assertSame($customer->id, $transaction->fresh()->customer_id);
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $this->assertSame('Client Updated', $transaction->fresh()->customer->name);
        $this->assertEquals(150, $transaction->fresh()->total_amount);
    }

    public function test_barber_status_and_admin_password_reset(): void
    {
        $this->actingAs($this->admin)->patch('/barbers/'.$this->barber->id.'/status', ['status' => 'inactive', 'availability' => 'unavailable'])->assertSessionHasNoErrors();
        $this->assertSame('inactive', $this->barber->fresh()->status);
        $this->post('/barbers/'.$this->barber->id.'/reset-password', ['password' => 'Reset-password-123', 'password_confirmation' => 'Reset-password-123'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Reset-password-123', $this->barberUser->fresh()->password));
    }

    public function test_cash_completion_is_rejected_if_payment_is_flagged(): void
    {
        $transaction = $this->queued();
        $transaction->payment->update(['verification_status' => 'flagged']);
        $this->actingAs($this->admin)->patch('/transactions/'.$transaction->id.'/status', ['status' => 'completed'])->assertSessionHasErrors('payment');
        $this->assertEquals(10, $this->product->fresh()->stock);
        $this->assertSame('queued', $transaction->fresh()->status);
    }

    public function test_attendance_rejects_clock_out_before_clock_in_and_tampered_history(): void
    {
        $this->actingAs($this->barberUser)->post('/attendance/clock', ['action' => 'out'])->assertSessionHasErrors('action');
        $this->post('/attendance/clock', ['action' => 'in', 'date' => '2020-01-01'])->assertSessionHasErrors('date');
        $this->assertSame(0, Attendance::count());
    }

    public function test_service_product_negative_quantity_and_duplicate_requirements_are_rejected(): void
    {
        $data = ['name' => 'Unsafe', 'price' => 100, 'duration_minutes' => 30, 'active' => 1, 'requirements' => [['product_id' => $this->product->id, 'quantity' => -1]]];
        $this->actingAs($this->admin)->post('/services', $data)->assertSessionHasErrors('requirements.0.quantity');
        $data['requirements'] = [['product_id' => $this->product->id, 'quantity' => 1], ['product_id' => $this->product->id, 'quantity' => 1]];
        $this->post('/services', $data)->assertSessionHasErrors('requirements.0.product_id');
        $this->assertDatabaseMissing('services', ['name' => 'Unsafe']);
    }

    public function test_admin_can_correct_uncompleted_gcash_reference_and_must_reverify(): void
    {
        $transaction = $this->queued(['payment_method' => 'gcash', 'gcash_reference_no' => '111111111']);
        $transaction->payment->update(['verification_status' => 'verified']);
        $this->actingAs($this->admin)->put('/transactions/'.$transaction->id, ['transaction_date' => now()->subMinute()->format('Y-m-d\TH:i'), 'gcash_reference_no' => '222222222'])->assertSessionHasNoErrors();
        $this->assertSame('222222222', $transaction->payment()->first()->gcash_reference_no);
        $this->assertSame('pending', $transaction->payment()->first()->verification_status);
        $this->patch('/transactions/'.$transaction->id.'/status', ['status' => 'completed'])->assertSessionHasErrors('payment');
        $this->post('/payments/'.$transaction->payment->id.'/verify', ['status' => 'verified'])->assertSessionHasNoErrors();
        $this->patch('/transactions/'.$transaction->id.'/status', ['status' => 'completed'])->assertSessionHasNoErrors();
        $this->put('/transactions/'.$transaction->id, ['transaction_date' => now()->subMinute()->format('Y-m-d\TH:i'), 'gcash_reference_no' => '333333333'])->assertSessionHasErrors('gcash_reference_no');
    }

    public function test_csv_report_streams_valid_sales_and_sanitizes_formula_names(): void
    {
        $this->barberUser->update(['name' => '=UnsafeFormula']);
        app(TransactionWorkflow::class)->create($this->checkout(), $this->admin);
        $response = $this->actingAs($this->admin)->get('/reports/csv');
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('150.00', $csv);
        $this->assertStringContainsString("'=UnsafeFormula", $csv);
        $this->assertStringContainsString('Cash,1,150.00', $csv);
    }

    public function test_pos_has_no_manual_transaction_date_control(): void
    {
        $this->actingAs($this->admin)->get('/pos')->assertOk()
            ->assertDontSee('name="transaction_date"', false)
            ->assertDontSee('type="datetime-local"', false)
            ->assertSee('recorded automatically when you save');
    }

    public function test_cash_transaction_date_is_server_save_time_with_seconds(): void
    {
        $this->travelTo(Carbon::parse('2026-10-08 18:14:37', config('app.timezone')));
        $expected = now()->format('Y-m-d H:i:s');
        $this->actingAs($this->admin)->post('/transactions', $this->checkout())->assertSessionHasNoErrors();
        $transaction = Transaction::firstOrFail();
        $this->assertSame($expected, $transaction->transaction_date->format('Y-m-d H:i:s'));
        $this->assertSame('completed', $transaction->status);
        $this->assertEquals(8, $this->product->fresh()->stock);
    }

    public function test_browser_supplied_date_is_ignored_for_new_transactions(): void
    {
        $this->travelTo(Carbon::parse('2026-10-08 18:14:37', config('app.timezone')));
        $this->actingAs($this->admin)->post('/transactions', $this->checkout(['transaction_date' => '2000-01-01T00:00']))->assertSessionHasNoErrors();
        $this->assertSame(now()->format('Y-m-d H:i:s'), Transaction::firstOrFail()->transaction_date->format('Y-m-d H:i:s'));
    }

    public function test_workflow_uses_server_time_even_if_called_with_a_client_date(): void
    {
        $this->travelTo(Carbon::parse('2026-10-08 18:14:37', config('app.timezone')));
        $transaction = $this->queued(['transaction_date' => '2000-01-01T00:00']);
        $this->assertSame(now()->format('Y-m-d H:i:s'), $transaction->transaction_date->format('Y-m-d H:i:s'));
        $this->assertEquals(10, $this->product->fresh()->stock);
    }

    public function test_checkout_retry_preserves_original_save_time(): void
    {
        $this->travelTo(Carbon::parse('2026-10-08 18:14:37', config('app.timezone')));
        $data = $this->checkout();
        $this->actingAs($this->admin)->post('/transactions', $data)->assertSessionHasNoErrors();
        $originalDate = Transaction::firstOrFail()->transaction_date->format('Y-m-d H:i:s');
        $this->travel(5)->minutes();
        $this->post('/transactions', $data)->assertSessionHasNoErrors();
        $this->assertSame(1, Transaction::count());
        $this->assertSame($originalDate, Transaction::firstOrFail()->transaction_date->format('Y-m-d H:i:s'));
        $this->assertEquals(8, $this->product->fresh()->stock);
    }

    public function test_gcash_save_time_is_not_replaced_by_later_completion_time(): void
    {
        $this->travelTo(Carbon::parse('2026-10-08 18:14:37', config('app.timezone')));
        $originalDate = now()->format('Y-m-d H:i:s');
        $this->actingAs($this->admin)->post('/transactions', $this->checkout(['payment_method' => 'gcash', 'gcash_reference_no' => '4444444444444']))->assertSessionHasNoErrors();
        $transaction = Transaction::firstOrFail();
        $this->assertSame($originalDate, $transaction->transaction_date->format('Y-m-d H:i:s'));
        $this->assertSame('queued', $transaction->status);
        $this->travel(1)->hours();
        $this->post('/payments/'.$transaction->payment->id.'/verify', ['status' => 'verified'])->assertSessionHasNoErrors();
        $this->patch('/transactions/'.$transaction->id.'/status', ['status' => 'completed'])->assertSessionHasNoErrors();
        $this->assertSame($originalDate, $transaction->fresh()->transaction_date->format('Y-m-d H:i:s'));
        $this->assertSame(now()->format('Y-m-d H:i:s'), $transaction->fresh()->completed_at->format('Y-m-d H:i:s'));
    }

    public function test_barber_new_password_still_requires_confirmation_and_minimum_length(): void
    {
        $data = ['name' => $this->barberUser->name, 'email' => $this->barberUser->email];
        $originalHash = $this->barberUser->password;
        $this->actingAs($this->barberUser)->put('/profile', $data + ['password' => 'Barber-new-password-123', 'password_confirmation' => 'Mismatch'])->assertSessionHasErrors('password');
        $this->put('/profile', $data + ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->assertSame($originalHash, $this->barberUser->fresh()->password);
    }

    public function test_barber_can_save_account_details_without_changing_password(): void
    {
        $originalHash = $this->barberUser->password;
        $this->actingAs($this->barberUser)->put('/profile', ['name' => 'Updated Barber', 'email' => $this->barberUser->email, 'password' => '', 'password_confirmation' => ''])->assertRedirect('/profile')->assertSessionHasNoErrors();
        $this->assertSame($originalHash, $this->barberUser->fresh()->password);
    }

    public function test_barber_profile_password_change_only_affects_authenticated_account(): void
    {
        $adminHash = $this->admin->password;
        $this->actingAs($this->barberUser)->put('/profile', [
            'name' => $this->barberUser->name, 'email' => $this->barberUser->email,
            'password' => 'Own-barber-password-123', 'password_confirmation' => 'Own-barber-password-123',
            'user_id' => $this->admin->id, 'role' => 'admin',
        ])->assertSessionHasNoErrors();
        $this->assertSame($adminHash, $this->admin->fresh()->password);
        $this->assertSame('barber', $this->barberUser->fresh()->role);
        $this->assertTrue(Hash::check('Own-barber-password-123', $this->barberUser->fresh()->password));
    }

    public function test_legacy_current_password_submission_is_ignored_for_barber(): void
    {
        $this->actingAs($this->barberUser)->put('/profile', [
            'name' => $this->barberUser->name, 'email' => $this->barberUser->email,
            'current_password' => 'wrong-old-password',
            'password' => 'Barber-new-password-123', 'password_confirmation' => 'Barber-new-password-123',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Barber-new-password-123', $this->barberUser->fresh()->password));
    }

    public function test_guest_cannot_change_barber_password(): void
    {
        $originalHash = $this->barberUser->password;
        $this->put('/profile', ['name' => $this->barberUser->name, 'email' => $this->barberUser->email, 'password' => 'Barber-new-password-123', 'password_confirmation' => 'Barber-new-password-123'])->assertRedirect('/login');
        $this->assertSame($originalHash, $this->barberUser->fresh()->password);
    }

    public function test_barber_can_log_in_with_new_password_after_changing_without_old_password(): void
    {
        $this->barberUser->update(['password' => 'Old-barber-password-123']);
        $this->actingAs($this->barberUser)->put('/profile', [
            'name' => $this->barberUser->name, 'email' => $this->barberUser->email,
            'password' => 'New-barber-password-123', 'password_confirmation' => 'New-barber-password-123',
        ])->assertSessionHasNoErrors();
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->post('/login', ['email' => $this->barberUser->email, 'password' => 'Old-barber-password-123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => $this->barberUser->email, 'password' => 'New-barber-password-123'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->barberUser);
    }
}
