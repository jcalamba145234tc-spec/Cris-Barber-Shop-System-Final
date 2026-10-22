<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Barber;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Services\EarningsClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BarberArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function barber(): Barber
    {
        $user = User::factory()->create(['name' => 'Returning Barber', 'role' => 'barber', 'password' => 'Return-password-123']);

        return Barber::create(['user_id' => $user->id, 'first_name' => 'Returning', 'last_name' => 'Barber',
            'hired_date' => today(), 'status' => 'active', 'availability' => 'available']);
    }

    private function sale(Barber $barber, string $status = 'completed'): Transaction
    {
        $transaction = Transaction::create(['barber_id' => $barber->id, 'total_amount' => 150, 'barber_share' => 75,
            'shop_share' => 75, 'status' => $status, 'transaction_date' => now()->subDay(), 'checkout_token' => Str::uuid()]);
        Payment::create(['transaction_id' => $transaction->id, 'payment_method' => 'cash', 'verification_status' => 'verified', 'amount' => 150]);

        return $transaction;
    }

    public function test_archive_and_restore_keep_same_identity_password_transactions_and_paid_claims(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $barber = $this->barber();
        $sale = $this->sale($barber);
        $service = app(EarningsClaimService::class);
        $snapshot = $service->snapshot($service->eligible($barber, today()->subDays(2)->toDateString(), today()->toDateString())->get());
        $claim = $service->confirm($barber, $admin, ['from' => $snapshot['from'], 'to' => $snapshot['to'], 'amount' => $snapshot['amount'], 'fingerprint' => $snapshot['fingerprint'], 'claim_token' => (string) Str::uuid()]);
        $original = $sale->fresh()->getRawOriginal();
        $password = $barber->user->password;
        $attendance = Attendance::create(['barber_id' => $barber->id, 'date' => today()->subDay(), 'status' => 'present', 'time_in' => now()->subDay()->setTime(9, 0), 'time_out' => now()->subDay()->setTime(17, 0)]);
        $this->actingAs($admin)->post(route('barbers.archive', $barber), ['confirmation' => 1])->assertRedirect(route('barbers.index'));
        $this->assertNotNull($barber->fresh()->archived_at);
        $this->assertSame($admin->id, $barber->fresh()->archived_by);
        $this->assertSame('inactive', $barber->fresh()->status);
        $this->assertSame($original, $sale->fresh()->getRawOriginal());
        $this->assertSame($password, $barber->user->fresh()->password);
        $this->assertDatabaseHas('earning_claims', ['id' => $claim->id, 'barber_id' => $barber->id]);
        $this->assertDatabaseHas('attendance', ['id' => $attendance->id]);
        $this->get(route('barbers.index'))->assertDontSee($barber->user->email)->assertSee('Archived barbers (1)');
        $this->get(route('barbers.archived'))->assertSee($barber->user->email)->assertSee('Restore account');
        $this->get(route('transactions.show', $sale))->assertOk()->assertSee('Returning Barber');
        $this->post(route('barbers.restore', $barber), ['confirmation' => 1])->assertRedirect(route('barbers.index'));
        $this->assertNull($barber->fresh()->archived_at);
        $this->assertNotNull($barber->fresh()->restored_at);
        $this->assertSame('active', $barber->fresh()->status);
        $this->assertSame('unavailable', $barber->fresh()->availability);
        $this->assertSame($password, $barber->user->fresh()->password);
        $this->assertSame($original, $sale->fresh()->getRawOriginal());
        $this->assertSame('Returning Barber', $sale->fresh()->barber->name);
    }

    public function test_owner_can_archive_without_prerequisites_or_checkbox_and_preserves_open_obligations(): void
    {
        $barber = $this->barber();
        $transactions = collect(['queued', 'in_progress', 'ready', 'completed'])->map(fn ($status) => $this->sale($barber, $status));
        $originalTransactions = $transactions->mapWithKeys(fn ($sale) => [$sale->id => $sale->fresh()->getRawOriginal()]);
        $originalPayments = Payment::orderBy('id')->get()->map(fn ($payment) => $payment->getRawOriginal())->all();
        $attendance = Attendance::create(['barber_id' => $barber->id, 'date' => today(), 'status' => 'present', 'time_in' => now()]);
        $originalAttendance = $attendance->fresh()->getRawOriginal();
        $password = $barber->user->password;
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('barbers.archive', $barber))->assertRedirect(route('barbers.index'))->assertSessionHasNoErrors();
        $this->assertNotNull($barber->fresh()->archived_at);
        $this->assertSame($admin->id, $barber->fresh()->archived_by);
        $this->assertSame('inactive', $barber->fresh()->status);
        $this->assertSame('unavailable', $barber->fresh()->availability);
        $this->assertSame($password, $barber->user->fresh()->password);
        foreach ($transactions as $sale) {
            $this->assertSame($originalTransactions[$sale->id], $sale->fresh()->getRawOriginal());
        }
        $this->assertSame($originalPayments, Payment::orderBy('id')->get()->map(fn ($payment) => $payment->getRawOriginal())->all());
        $this->assertSame($originalAttendance, $attendance->fresh()->getRawOriginal());
        $this->assertDatabaseCount('earning_claims', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertFalse(Barber::available()->whereKey($barber->id)->exists());

        $this->get(route('barbers.index'))->assertDontSee($barber->user->email);
        $this->get(route('barbers.archived'))->assertSee($barber->user->email);
        $this->get(route('transactions.show', $transactions->first()))->assertOk();
        $this->get(route('attendance.index'))->assertOk()->assertSee('Returning Barber');
        $this->post(route('barbers.restore', $barber), ['confirmation' => 1])->assertRedirect();
        $this->assertNull($barber->fresh()->archived_at);
        $this->assertSame($originalAttendance, $attendance->fresh()->getRawOriginal());
        foreach ($transactions as $sale) {
            $this->assertSame($originalTransactions[$sale->id], $sale->fresh()->getRawOriginal());
        }
    }

    public function test_archive_dialog_has_no_required_checkbox_and_explains_preserved_obligations(): void
    {
        $this->barber();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('barbers.index'))->assertOk()
            ->assertSee('You can archive now, even with active services, open attendance or unclaimed earnings.')
            ->assertDontSee('name="confirmation"', false)
            ->assertDontSee('Finish or cancel active services, settle unclaimed earnings, and close open attendance first.');
    }

    public function test_guest_and_barber_cannot_archive_restore_or_view_archive(): void
    {
        $barber = $this->barber();
        $this->get(route('barbers.archived'))->assertRedirect('/login');
        foreach (['barbers.archive', 'barbers.restore'] as $route) {
            $this->post(route($route, $barber), ['confirmation' => 1])->assertRedirect('/login');
        }
        $this->actingAs($barber->user)->get(route('barbers.archived'))->assertForbidden();
        foreach (['barbers.archive', 'barbers.restore'] as $route) {
            $this->post(route($route, $barber), ['confirmation' => 1])->assertForbidden();
        }
    }

    public function test_repeat_action_guards_and_restore_confirmation_do_not_delete_accounts(): void
    {
        $barber = $this->barber();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post(route('barbers.restore', $barber), ['confirmation' => 1])->assertStatus(409);
        $this->post(route('barbers.archive', $barber))->assertRedirect();
        $this->post(route('barbers.archive', $barber))->assertStatus(409);
        $this->post(route('barbers.restore', $barber))->assertSessionHasErrors('confirmation');
        $this->post(route('barbers.restore', $barber), ['confirmation' => 1])->assertRedirect();
        $this->post(route('barbers.restore', $barber), ['confirmation' => 1])->assertStatus(409);
        $this->assertDatabaseHas('barbers', ['id' => $barber->id]);
        $this->assertDatabaseHas('users', ['id' => $barber->user_id]);
    }

    public function test_archived_accounts_cannot_sign_in_even_if_status_is_tampered(): void
    {
        $barber = $this->barber();
        $barber->forceFill(['archived_at' => now(), 'status' => 'active'])->save();
        $this->post('/login', ['email' => $barber->user->email, 'password' => 'Return-password-123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertFalse(Barber::available()->whereKey($barber->id)->exists());
        $this->actingAs($barber->user)->get('/dashboard')->assertForbidden();
        $this->post('/attendance/clock', ['action' => 'in'])->assertForbidden();
        $this->post('/logout')->assertRedirect('/login');
    }

    public function test_archiving_revokes_database_sessions_but_not_the_password(): void
    {
        $barber = $this->barber();
        DB::table('sessions')->insert(['id' => 'archived-session', 'user_id' => $barber->user_id, 'payload' => '', 'last_activity' => time()]);
        $hash = $barber->user->password;
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('barbers.archive', $barber), ['confirmation' => 1])->assertRedirect();
        $this->assertDatabaseMissing('sessions', ['id' => 'archived-session']);
        $this->assertSame($hash, $barber->user->fresh()->password);
    }

    public function test_stale_admin_edit_status_and_reset_forms_cannot_reactivate_an_archive(): void
    {
        $barber = $this->barber();
        $barber->forceFill(['archived_at' => now(), 'status' => 'inactive'])->save();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('barbers.edit', $barber))->assertStatus(409);
        $this->get(route('barbers.index', ['edit' => $barber->id]))->assertNotFound();
        $this->put(route('barbers.update', $barber), ['name' => 'Changed', 'email' => $barber->user->email, 'hired_date' => today()->toDateString()])->assertStatus(409);
        $this->patch(route('barbers.status', $barber), ['barber_state' => 'available'])->assertStatus(409);
        $this->post(route('barbers.password', $barber), ['password' => 'Changed-password-123', 'password_confirmation' => 'Changed-password-123'])->assertStatus(409);
        $this->assertNotNull($barber->fresh()->archived_at);
    }

    public function test_restore_does_not_replay_stock_or_restore_archived_transactions(): void
    {
        $barber = $this->barber();
        $sale = $this->sale($barber, 'queued');
        $sale->delete();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('barbers.archive', $barber), ['confirmation' => 1])->assertRedirect();
        $this->post(route('transactions.restore', $sale->id))->assertSessionHasErrors('archive');
        $this->assertSoftDeleted('transactions', ['id' => $sale->id]);
        $this->post(route('barbers.restore', $barber), ['confirmation' => 1])->assertRedirect();
        $this->assertSoftDeleted('transactions', ['id' => $sale->id]);
        $this->assertSame(0, DB::table('inventory_movements')->count());
        $this->post(route('transactions.restore', $sale->id))->assertRedirect();
        $this->assertDatabaseHas('transactions', ['id' => $sale->id, 'deleted_at' => null, 'status' => 'queued']);
    }

    public function test_historical_attendance_stays_visible_but_archived_staff_are_not_counted_absent_today(): void
    {
        $barber = $this->barber();
        Attendance::create(['barber_id' => $barber->id, 'date' => today()->subDay(), 'status' => 'absent']);
        $barber->forceFill(['archived_at' => now(), 'status' => 'inactive'])->save();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/attendance')->assertOk()->assertDontSee('Returning Barber');
        $this->get('/attendance?date='.today()->subDay()->toDateString())->assertOk()->assertSee('Returning Barber');
    }

    public function test_search_is_validated_escaped_and_limited_to_archived_accounts(): void
    {
        $barber = $this->barber();
        $barber->user->update(['name' => '<script>alert(1)</script>']);
        $barber->forceFill(['archived_at' => now()])->save();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('barbers.archived', ['q' => 'alert']))
            ->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('barbers.archived', ['q' => str_repeat('a', 101)]))->assertSessionHasErrors('q');
    }

    public function test_restored_account_can_sign_in_to_dashboard_with_existing_password(): void
    {
        $barber = $this->barber();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('barbers.archive', $barber), ['confirmation' => 1])->assertRedirect();
        $this->post(route('barbers.restore', $barber), ['confirmation' => 1])->assertRedirect();
        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['email' => $barber->user->email, 'password' => 'Return-password-123'])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk()->assertSee('Barber dashboard');
        $this->assertFalse(Barber::available()->whereKey($barber->id)->exists());
    }

    public function test_archive_pagination_is_bounded_and_keeps_search(): void
    {
        for ($i = 0; $i < 21; $i++) {
            $barber = $this->barber();
            $barber->forceFill(['archived_at' => now()])->save();
        }
        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('barbers.archived', ['q' => 'Returning']));
        $response->assertOk()->assertViewHas('barbers', fn ($rows) => $rows->total() === 21 && $rows->count() === 20);
        $this->assertStringContainsString('q=Returning', $response->viewData('barbers')->nextPageUrl());
    }

    public function test_archived_account_is_rejected_by_pos_checkout_workflow(): void
    {
        $barber = $this->barber();
        $barber->forceFill(['archived_at' => now(), 'status' => 'active', 'availability' => 'available'])->save();
        $service = Service::create(['name' => 'Archive safety cut', 'price' => 150, 'duration_minutes' => 30, 'active' => true]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/pos')->assertOk()->assertDontSee($barber->user->name);
        $this->post('/transactions', ['barber_id' => $barber->id, 'service_ids' => [$service->id],
            'service_quantities' => [$service->id => 1], 'payment_method' => 'cash', 'cash_received' => '150.00', 'status' => 'queued',
            'checkout_token' => (string) Str::uuid()])->assertSessionHasErrors('barber_id');
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_inactive_dropdown_blocks_login_without_archiving_or_changing_history(): void
    {
        $barber = $this->barber();
        $transaction = $this->sale($barber, 'queued');
        $original = $transaction->fresh()->getRawOriginal();
        DB::table('sessions')->insert(['id' => 'deactivate-session', 'user_id' => $barber->user_id, 'payload' => '', 'last_activity' => time()]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('barbers.index'))->assertOk()->assertSee('Inactive blocks login.')->assertDontSee('Deactivate account');
        $this->patch(route('barbers.status', $barber), ['barber_state' => 'inactive'])->assertRedirect();
        $this->assertNull($barber->fresh()->archived_at);
        $this->assertSame('inactive', $barber->fresh()->status);
        $this->assertSame('unavailable', $barber->fresh()->availability);
        $this->assertSame($original, $transaction->fresh()->getRawOriginal());
        $this->assertDatabaseMissing('sessions', ['id' => 'deactivate-session']);
        $this->get(route('barbers.index'))->assertSee($barber->user->email)->assertSee('Inactive · login blocked');
        $this->post('/logout');
        $this->post('/login', ['email' => $barber->user->email, 'password' => 'Return-password-123'])->assertSessionHasErrors('email');
    }

    public function test_admin_can_reactivate_a_deactivated_account_without_restoring_an_archive(): void
    {
        $barber = $this->barber();
        $barber->update(['status' => 'inactive', 'availability' => 'unavailable']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('barbers.status', $barber), ['barber_state' => 'unavailable'])->assertRedirect();
        $this->assertSame('active', $barber->fresh()->status);
        $this->assertNull($barber->fresh()->archived_at);
        $this->post('/logout');
        $this->post('/login', ['email' => $barber->user->email, 'password' => 'Return-password-123'])->assertRedirect('/dashboard');
    }
}
