<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Barber;
use App\Models\Customer;
use App\Models\EarningClaim;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Services\EarningsClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class ProfessionalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function barber(string $first = 'Juan'): Barber
    {
        $user = User::factory()->create(['name' => $first.' Dela Cruz', 'role' => 'barber']);

        return Barber::create(['user_id' => $user->id, 'first_name' => $first, 'last_name' => 'Dela Cruz', 'hired_date' => today(), 'status' => 'active']);
    }

    private function sale(Barber $barber, array $attributes = [], string $verification = 'verified'): Transaction
    {
        $transaction = Transaction::create($attributes + ['barber_id' => $barber->id, 'transaction_date' => now(),
            'total_amount' => '150.01', 'barber_share' => '75.01', 'shop_share' => '75.00', 'status' => 'completed']);
        Payment::create(['transaction_id' => $transaction->id, 'payment_method' => 'cash', 'amount' => $transaction->total_amount, 'verification_status' => $verification]);

        return $transaction;
    }

    private function payout(Barber $barber): array
    {
        $service = app(EarningsClaimService::class);
        $from = today()->subYear()->toDateString();
        $to = today()->toDateString();
        $snapshot = $service->snapshot($service->eligible($barber, $from, $to)->get());

        return ['from' => $from, 'to' => $to, 'claim_token' => (string) Str::uuid(), 'fingerprint' => $snapshot['fingerprint'], 'amount' => $snapshot['amount'], 'confirmed' => '1'];
    }

    public function test_generated_email_sanitizes_names_handles_duplicates_and_composes_display_name(): void
    {
        $owner = User::factory()->create(['role' => 'admin']);
        $data = ['first_name' => '  José  Luis ', 'last_name' => 'Dela-Cruz!', 'role' => 'barber',
            'password' => 'Valid-password-123', 'password_confirmation' => 'Valid-password-123', 'email' => 'override@invalid.test'];
        $this->actingAs($owner)->get('/accounts/email-preview?first_name=Jos%C3%A9%20Luis&last_name=Dela-Cruz!')
            ->assertOk()->assertJsonPath('email', 'joseluis.delacruz@crisbarbershop.local');
        $this->post('/accounts', $data)->assertRedirect('/barbers')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'joseluis.delacruz@crisbarbershop.local', 'name' => 'José Luis Dela-Cruz!']);
        $this->assertDatabaseHas('barbers', ['first_name' => 'José Luis', 'last_name' => 'Dela-Cruz!']);
        $this->post('/accounts', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'joseluis.delacruz2@crisbarbershop.local']);
        $this->get('/barbers?add=1')->assertOk()->assertSee('<dialog', false)->assertSee('First Name')->assertSee('readonly', false);
    }

    public function test_account_form_requires_both_names_and_rejects_role_escalation(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/accounts', ['role' => 'admin'])
            ->assertSessionHasErrors(['first_name', 'last_name', 'password', 'role']);
        $this->assertSame(0, Barber::count());
    }

    public function test_earnings_accumulate_across_days_and_claims_are_atomic_and_idempotent(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(15, 0));
        $owner = User::factory()->create(['role' => 'admin']);
        $barber = $this->barber();
        $yesterday = $this->sale($barber, ['transaction_date' => now()->subDay()]);
        $today = $this->sale($barber);
        $this->sale($barber, ['status' => 'queued']);
        $this->sale($barber, [], 'pending');
        $this->sale($this->barber('Other'));
        $this->actingAs($barber->user)->get('/dashboard')->assertOk()
            ->assertViewHas('todayEarnings', '75.01')->assertViewHas('unclaimedBalance', '150.02')
            ->assertViewHas('carriedBalance', '75.01')->assertViewHas('claimedTotal', 0);
        $data = $this->payout($barber);
        $this->actingAs($owner)->get('/earning-claims')->assertOk()->assertSee('150.02')->assertSee('Confirm cash paid');
        $this->post('/earning-claims/'.$barber->id, $data)->assertSessionHasNoErrors();
        $claim = EarningClaim::firstOrFail();
        $this->assertSame('150.02', $claim->amount);
        $this->assertSame($owner->id, $claim->confirmed_by);
        $this->assertSame(2, $claim->transaction_count);
        $this->assertSame('2026-10-19', $claim->period_start->toDateString());
        $this->assertSame('2026-10-20', $claim->period_end->toDateString());
        $this->assertTrue($claim->confirmed_at->equalTo(now()));
        $this->assertSame($claim->id, $yesterday->fresh()->claim_id);
        $this->assertSame($claim->id, $today->fresh()->claim_id);
        $this->post('/earning-claims/'.$barber->id, $data)->assertSessionHasNoErrors();
        $this->assertSame(1, EarningClaim::count());
        $data['claim_token'] = (string) Str::uuid();
        $this->post('/earning-claims/'.$barber->id, $data)->assertSessionHasErrors('claim');
        $this->assertSame(1, EarningClaim::count());
        $this->actingAs($barber->user)->get('/dashboard')->assertViewHas('unclaimedBalance', 0)->assertViewHas('claimedTotal', '150.02');
        $this->get('/transactions?claim_status=claimed')->assertOk()->assertSee('Claimed')->assertViewHas('transactions', fn ($rows) => $rows->total() === 2);
        $this->travel(1)->days();
        $this->sale($barber);
        $this->get('/dashboard')->assertViewHas('unclaimedBalance', '75.01')->assertViewHas('claimedTotal', '150.02');
    }

    public function test_stale_payout_confirmation_cannot_claim_new_earnings_or_a_tampered_amount(): void
    {
        $owner = User::factory()->create(['role' => 'admin']);
        $barber = $this->barber();
        $first = $this->sale($barber);
        $data = $this->payout($barber);
        $this->sale($barber);
        $this->actingAs($owner)->post('/earning-claims/'.$barber->id, $data)->assertSessionHasErrors('claim');
        $this->assertNull($first->fresh()->claim_id);
        $data = $this->payout($barber);
        $data['amount'] = '0.01';
        $this->post('/earning-claims/'.$barber->id, $data)->assertSessionHasErrors('claim');
        $this->assertSame(0, EarningClaim::count());
    }

    public function test_claim_requires_handover_confirmation_and_cannot_reuse_another_barbers_token(): void
    {
        $owner = User::factory()->create(['role' => 'admin']);
        $barber = $this->barber();
        $other = $this->barber('Other');
        $this->sale($barber);
        $this->sale($other);
        $data = $this->payout($barber);
        $this->actingAs($owner)->post('/earning-claims/'.$barber->id, array_replace($data, ['confirmed' => '0']))->assertSessionHasErrors('confirmed');
        $this->assertSame(0, EarningClaim::count());
        $this->post('/earning-claims/'.$barber->id, $data)->assertSessionHasNoErrors();
        $otherData = $this->payout($other);
        $otherData['claim_token'] = $data['claim_token'];
        $this->post('/earning-claims/'.$other->id, $otherData)->assertSessionHasErrors('claim');
        $this->assertSame(1, EarningClaim::count());
    }

    public function test_non_eligible_transactions_never_enter_claims(): void
    {
        $barber = $this->barber();
        $this->sale($barber, ['status' => 'queued']);
        $this->sale($barber, ['status' => 'cancelled']);
        $this->sale($barber, [], 'pending');
        $this->sale($barber, [], 'flagged');
        $snapshot = $this->payout($barber);
        $this->assertSame('0.00', $snapshot['amount']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/earning-claims/'.$barber->id, $snapshot)->assertSessionHasErrors('claim');
        $this->assertSame(0, EarningClaim::count());
    }

    public function test_new_owner_endpoints_are_forbidden_to_barbers_and_guests(): void
    {
        $barber = $this->barber();
        $customer = Customer::create(['name' => 'Archived']);
        $customer->delete();
        $this->get('/earning-claims')->assertRedirect('/login');
        $this->post('/earning-claims/'.$barber->id, [])->assertRedirect('/login');
        $this->actingAs($barber->user);
        foreach (['/earning-claims', '/customers/recycle-bin', '/accounts/email-preview?first_name=A&last_name=B'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/earning-claims/'.$barber->id, [])->assertForbidden();
        $this->post('/customers/'.$customer->id.'/restore')->assertForbidden();
        $this->delete('/customers/'.$customer->id.'/permanent')->assertForbidden();
    }

    public function test_barber_clock_buttons_and_server_block_duplicate_actions(): void
    {
        $this->freezeTime();
        $barber = $this->barber();
        $this->actingAs($barber->user)->get('/attendance')->assertOk()->assertSee('value="out" class="secondary-button" disabled', false);
        $this->post('/attendance/clock', ['action' => 'out'])->assertSessionHasErrors('action');
        $this->post('/attendance/clock', ['action' => 'in'])->assertSessionHasNoErrors();
        $timeIn = Attendance::firstOrFail()->time_in;
        $this->get('/attendance')->assertSee('value="in" disabled', false)->assertSee($timeIn->format('g:i A'));
        $this->post('/attendance/clock', ['action' => 'in'])->assertSessionHasErrors('action');
        $this->assertSame(1, Attendance::count());
        $this->travel(1)->hours();
        $this->post('/attendance/clock', ['action' => 'out'])->assertSessionHasNoErrors();
        $this->post('/attendance/clock', ['action' => 'out'])->assertSessionHasErrors('action');
        $this->assertTrue(Attendance::firstOrFail()->time_in->equalTo($timeIn));
        $this->get('/attendance')->assertSee('value="out" class="secondary-button" disabled', false);
    }

    public function test_admin_roster_includes_all_barbers_even_without_attendance(): void
    {
        $this->freezeTime();
        $owner = User::factory()->create(['role' => 'admin']);
        $present = $this->barber('Present');
        $late = $this->barber('Late');
        $leave = $this->barber('Leave');
        $leave->update(['status' => 'on_leave']);
        $this->barber('Missing');
        Attendance::create(['barber_id' => $present->id, 'date' => today(), 'status' => 'present', 'time_in' => now()->subHours(2), 'time_out' => now()->subHour()]);
        Attendance::create(['barber_id' => $late->id, 'date' => today(), 'status' => 'late', 'time_in' => now()]);
        $this->actingAs($owner)->get('/attendance')->assertOk()->assertSee('Present Dela Cruz')->assertSee('Missing Dela Cruz')
            ->assertSee('Clocked Out')->assertSee('Late')->assertSee('On Leave')->assertSee('Not Yet Clocked In')
            ->assertViewHas('barbers', fn ($rows) => $rows->count() === 4)
            ->assertViewHas('summary', ['present' => 2, 'absent' => 0, 'on_leave' => 1]);
        $this->get('/attendance?date='.today()->subDay()->toDateString())->assertViewHas('summary', ['present' => 0, 'absent' => 3, 'on_leave' => 1]);
        $this->post('/attendance', ['barber_id' => $leave->id, 'date' => today()->toDateString(), 'status' => 'on_leave'])->assertSessionHasNoErrors();
    }

    public function test_inventory_search_is_case_insensitive_partial_and_keeps_complete_names(): void
    {
        Product::create(['name' => 'Professional SHAMPOO Coconut 500ml', 'unit' => 'bottle', 'stock' => 2, 'low_stock_threshold' => 5]);
        Product::create(['name' => 'Hair Wax', 'unit' => 'piece', 'stock' => 20, 'low_stock_threshold' => 5]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/inventory?q=sham')->assertOk()
            ->assertSee('Professional SHAMPOO Coconut 500ml')->assertDontSee('Hair Wax')->assertSee('Low Stock')->assertSee('data-live-search', false);
        $this->get('/inventory?q=SHAM')->assertSee('Professional SHAMPOO Coconut 500ml')->assertViewHas('products', fn ($rows) => $rows->total() === 1);
    }

    public function test_customer_archiving_preserves_reports_claims_and_restorable_identity(): void
    {
        $owner = User::factory()->create(['role' => 'admin']);
        $barber = $this->barber();
        $customer = Customer::create(['name' => 'Juan Dela Cruz']);
        $sale = $this->sale($barber, ['customer_id' => $customer->id]);
        $this->actingAs($owner)->delete('/customers/'.$customer->id)->assertRedirect('/customers');
        $this->assertSoftDeleted($customer);
        $this->assertSame($customer->id, $sale->fresh()->customer_id);
        $this->get('/transactions')->assertSee('Juan Dela Cruz (archived)');
        $this->get('/reports/pdf')->assertOk()->assertSee('Juan Dela Cruz (archived)')->assertSee('Unclaimed');
        $this->get('/customers/recycle-bin')->assertSee('Juan Dela Cruz')->assertSee('Protected: linked transactions');
        $this->delete('/customers/'.$customer->id.'/permanent')->assertSessionHasErrors('customer');
        $this->post('/earning-claims/'.$barber->id, $this->payout($barber))->assertSessionHasNoErrors();
        $csv = $this->get('/reports/csv')->streamedContent();
        $this->assertStringContainsString('Juan Dela Cruz (archived)', $csv);
        $this->assertStringContainsString('Claimed', $csv);
        $this->assertStringContainsString('75.01', $csv);
        $this->post('/customers/'.$customer->id.'/restore')->assertSessionHasNoErrors();
        $this->assertNotSoftDeleted($customer);
        $this->assertSame($customer->id, $sale->fresh()->customer_id);
        $this->get('/transactions')->assertSee('Juan Dela Cruz')->assertDontSee('Juan Dela Cruz (archived)');
        $this->assertSame('75.01', EarningClaim::firstOrFail()->amount);
    }

    public function test_permanent_delete_counts_soft_deleted_transactions_and_only_allows_empty_archives(): void
    {
        $owner = User::factory()->create(['role' => 'admin']);
        $protected = Customer::create(['name' => 'Protected']);
        $sale = $this->sale($this->barber(), ['customer_id' => $protected->id, 'status' => 'cancelled']);
        $sale->delete();
        $protected->delete();
        $empty = Customer::create(['name' => 'Empty']);
        $empty->delete();
        $this->actingAs($owner)->delete('/customers/'.$protected->id.'/permanent')->assertSessionHasErrors('customer');
        $this->assertSoftDeleted($protected);
        $this->delete('/customers/'.$empty->id.'/permanent')->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('customers', ['id' => $empty->id]);
        $this->assertDatabaseHas('transactions', ['id' => $sale->id, 'customer_id' => $protected->id]);
    }

    public function test_removed_history_has_no_route_or_navigation_and_transactions_remain_private(): void
    {
        $barber = $this->barber();
        $own = $this->sale($barber);
        $other = $this->sale($this->barber('Other'));
        $this->actingAs($barber->user)->get('/history')->assertNotFound();
        $this->get('/transactions?status=completed&from='.today()->toDateString().'&q='.substr($own->id, 0, 12))
            ->assertOk()->assertViewHas('transactions', fn ($rows) => $rows->total() === 1)->assertDontSee($other->id)
            ->assertDontSee('href="'.url('/history').'"', false);
        $this->get('/transactions/'.$other->id)->assertForbidden();
    }

    public function test_forgot_password_for_local_login_explains_owner_reset_and_logout_flashes_success(): void
    {
        $this->get('/forgot-password')->assertOk();
        $this->post('/forgot-password', ['email' => 'juan.cruz@crisbarbershop.local'])->assertSessionHas('status');
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/logout')->assertRedirect('/login')->assertSessionHas('status');
        $this->get('/login')->assertOk()->assertSee('signed out securely')->assertSee('Forgot password?');
    }

    public function test_schema_has_claim_audit_links_and_mysql_uses_decimal_money_and_integer_quantities(): void
    {
        $this->assertTrue(Schema::hasColumns('earning_claims', ['barber_id', 'confirmed_by', 'confirmed_at', 'amount', 'period_start', 'period_end', 'claim_token']));
        $this->assertTrue(Schema::hasColumn('transactions', 'claim_id'));
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        foreach (['amount', 'cash_received', 'change_amount'] as $column) {
            $row = DB::selectOne('SELECT COLUMN_TYPE AS column_type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', ['payments', $column]);
            $this->assertSame('decimal(10,2)', $row->column_type);
        }
        $this->assertStringContainsString('int', Schema::getColumnType('products', 'stock'));
    }

    private function sourceBackup(bool $invalidSplit = false): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cris-import-');
        $sqlite = new PDO('sqlite:'.$path);
        foreach (['users', 'barbers', 'transactions', 'payments'] as $table) {
            $sqlite->exec('CREATE TABLE '.$table.' (id TEXT PRIMARY KEY)');
        }
        $sqlite->exec('CREATE TABLE customers (id INTEGER PRIMARY KEY, name TEXT, phone TEXT, email TEXT, created_at TEXT, updated_at TEXT, deleted_at TEXT)');
        $sqlite->exec("INSERT INTO customers VALUES (41, 'Preserved customer', NULL, NULL, '2026-10-01 10:00:00', '2026-10-01 10:00:00', '2026-10-02 10:00:00')");
        if ($invalidSplit) {
            $sqlite->exec('DROP TABLE transactions');
            $sqlite->exec('CREATE TABLE transactions (id TEXT, total_amount TEXT, barber_share TEXT, shop_share TEXT)');
            $sqlite->exec("INSERT INTO transactions VALUES ('invalid', '100.00', '60.00', '60.00')");
        }
        unset($sqlite);

        return $path;
    }

    public function test_mysql_import_dry_run_and_import_preserve_ids_archives_and_source_file(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Transactional SQLite-to-MySQL transfer runs in the MySQL suite.');
        }
        $source = $this->sourceBackup();
        try {
            $hash = hash_file('sha256', $source);
            $this->artisan('shop:import-sqlite', ['source' => $source, '--dry-run' => true])->assertSuccessful();
            $this->assertSame(0, Customer::withTrashed()->count());
            $this->artisan('shop:import-sqlite', ['source' => $source])->assertSuccessful();
            $this->assertDatabaseHas('customers', ['id' => 41, 'name' => 'Preserved customer', 'deleted_at' => '2026-10-02 10:00:00']);
            $this->assertSame($hash, hash_file('sha256', $source));
            $this->artisan('shop:import-sqlite', ['source' => $source])->assertFailed();
        } finally {
            unlink($source);
        }
    }

    public function test_mysql_import_rejects_invalid_financial_splits_without_partial_writes(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL transfer safeguard.');
        }
        $source = $this->sourceBackup(true);
        try {
            $this->artisan('shop:import-sqlite', ['source' => $source])->assertFailed();
            $this->assertSame(0, Customer::withTrashed()->count());
        } finally {
            unlink($source);
        }
    }
}
