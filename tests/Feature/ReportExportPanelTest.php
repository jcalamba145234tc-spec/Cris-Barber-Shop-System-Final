<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_shows_report_period_choices_and_no_quick_shortcuts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/dashboard')->assertOk()
            ->assertSee('Reports &amp; exports', false)
            ->assertSee('This month')->assertSee('Last month')->assertSee('Choose month')->assertSee('Single day')
            ->assertSee('View report')->assertSee('Download CSV')->assertSee('Print / Save as PDF')
            ->assertDontSee('Open POS')->assertDontSee('Open queue (')->assertDontSee('Export period');
    }

    public function test_last_month_report_opens_on_screen_without_forcing_the_print_dialog(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lastMonth = now()->startOfMonth()->subMonth();

        $this->actingAs($admin)->get('/reports/pdf?period=month&month='.$lastMonth->format('Y-m'))
            ->assertOk()->assertSee($lastMonth->format('F Y'))
            ->assertSee('id="print-report"', false)
            ->assertSee("addEventListener('click', () => window.print())", false)
            ->assertDontSee("addEventListener('load', () => window.print())", false);
    }

    public function test_print_option_still_opens_the_print_dialog(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/reports/pdf?period=month&month='.now()->format('Y-m').'&print=1')
            ->assertOk()->assertSee('window.print()', false);
    }

    public function test_barber_cannot_view_or_export_reports(): void
    {
        $barber = User::factory()->create(['role' => 'barber']);
        Barber::create(['user_id' => $barber->id, 'first_name' => 'Report', 'last_name' => 'Barber', 'hired_date' => today(), 'status' => 'active', 'availability' => 'available']);

        $this->actingAs($barber)->get('/reports/pdf?period=month&month='.now()->format('Y-m'))->assertForbidden();
        $this->actingAs($barber)->get('/reports/csv?period=month&month='.now()->format('Y-m'))->assertForbidden();
    }
}
