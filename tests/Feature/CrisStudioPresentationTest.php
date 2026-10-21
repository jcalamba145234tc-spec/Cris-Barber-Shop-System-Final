<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Barber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CrisStudioPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_split_login_retains_the_real_sign_in_form_and_security_notice(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('A sharper way')
            ->assertSee('One workspace for your team, services, and daily operations.')
            ->assertSee('autocomplete="current-password"', false)
            ->assertSee('data-password-toggle="password"', false)
            ->assertSee('these addresses do not receive email.');
    }

    public function test_barber_overview_renders_its_existing_six_metrics(): void
    {
        $user = User::factory()->create(['role' => 'barber']);
        Barber::create(['user_id' => $user->id, 'first_name' => 'Studio', 'last_name' => 'Barber', 'hired_date' => today(), 'status' => 'active']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk()->assertSee("Today's Earnings", false)->assertSee('Unclaimed Balance')
            ->assertSee('Claimed Total')->assertSee('Total Earnings')->assertSee('Assigned / active services')
            ->assertSee("Today's Attendance", false)->assertViewHas('todayEarnings', 0);
        $this->assertSame(6, substr_count($response->getContent(), 'metric-card'));
        $this->get('/earnings')->assertOk()->assertSee('aria-current="page"', false);
    }

    public function test_timecard_feedback_and_disabled_actions_follow_the_existing_record(): void
    {
        $user = User::factory()->create(['role' => 'barber']);
        $barber = Barber::create(['user_id' => $user->id, 'first_name' => 'Studio', 'last_name' => 'Barber', 'hired_date' => today(), 'status' => 'active']);
        $this->actingAs($user);

        $response = $this->get('/attendance');
        $response->assertOk()->assertSee('Clock in to start today', false);
        $this->assertClockActions($response, false, true);

        $attendance = Attendance::create(['barber_id' => $barber->id, 'date' => today(), 'status' => 'present', 'time_in' => now()->startOfDay()->addHour()]);
        $response = $this->get('/attendance');
        $response->assertOk()->assertSee('Clock-in recorded. Clock out when your shift is complete.');
        $this->assertClockActions($response, true, false);

        $attendance->update(['time_out' => now()->startOfDay()->addHours(2)]);
        $response = $this->get('/attendance');
        $response->assertOk()->assertSee('Clocked out')->assertSee('Ask the shop owner for corrections.');
        $this->assertClockActions($response, true, true);
    }

    private function assertClockActions(TestResponse $response, bool $inDisabled, bool $outDisabled): void
    {
        foreach (['in' => $inDisabled, 'out' => $outDisabled] as $action => $disabled) {
            $found = preg_match('/<button\s[^>]*name="action"[^>]*value="'.$action.'"[^>]*>/', $response->getContent(), $matches);
            $this->assertSame(1, $found, 'Clock '.$action.' action exists');
            $this->assertSame($disabled, str_contains($matches[0] ?? '', 'disabled'), 'Clock '.$action.' disabled state');
        }
    }
}
