<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Barber;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $filters = $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'month' => ['nullable', 'date_format:Y-m']]);
        $selectedDate = $filters['date'] ?? today()->format('Y-m-d');
        $selectedMonth = $filters['month'] ?? now()->format('Y-m');
        $month = Carbon::parse($selectedMonth.'-01');
        $data = ['selectedDate' => $selectedDate, 'selectedMonth' => $selectedMonth, 'user' => $request->user()];
        if ($request->user()->role === 'barber') {
            $barber = Barber::where('user_id', $request->user()->id)->firstOrFail();
            $valid = Transaction::valid()->where('barber_id', $barber->id);
            $period = (clone $valid);
            if ($request->filled('date')) {
                $period->whereDate('transaction_date', $selectedDate);
            } else {
                $period->whereBetween('transaction_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()]);
            }
            $breakdown = (clone $period)->selectRaw('DATE(transaction_date) as date, COUNT(*) as total_services, SUM(total_amount) as gross_sales, SUM(barber_share) as barber_share')
                ->groupByRaw('DATE(transaction_date)')->orderBy('date')->get();

            return view('dashboard', $data + [
                'todayEarnings' => (clone $valid)->whereDate('transaction_date', today())->sum('barber_share'),
                'totalEarnings' => (clone $valid)->sum('barber_share'),
                'monthEarnings' => (clone $valid)->whereBetween('transaction_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->sum('barber_share'),
                'salesBreakdown' => $breakdown,
                'periodGross' => (clone $period)->sum('total_amount'),
                'selectedPeriodBarberShare' => (clone $period)->sum('barber_share'),
                'assignedCount' => Transaction::where('barber_id', $barber->id)->whereIn('status', ['queued', 'in_progress', 'ready'])->count(),
                'todayAttendance' => Attendance::where('barber_id', $barber->id)->whereDate('date', today())->first(),
                'services' => Service::available()->get(),
            ]);
        }
        abort_unless($request->user()->role === 'admin', 403);
        $valid = Transaction::valid();

        return view('dashboard', $data + [
            'totalCustomers' => Customer::count(),
            'totalBarbers' => Barber::count(),
            'totalServices' => Service::count(),
            'totalPayments' => Payment::whereHas('transaction')->count(),
            'availableBarbers' => Barber::available()->count(),
            'totalRevenue' => (clone $valid)->sum('total_amount'),
            'lowStock' => Product::where('active', true)->whereColumn('stock', '<=', 'low_stock_threshold')->count(),
            'todayRevenue' => (clone $valid)->whereDate('transaction_date', $selectedDate)->sum('total_amount'),
            'monthRevenue' => (clone $valid)->whereBetween('transaction_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->sum('total_amount'),
            'monthShopShare' => (clone $valid)->whereBetween('transaction_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->sum('shop_share'),
            'queueCount' => Transaction::whereIn('status', ['queued', 'in_progress', 'ready'])->count(),
        ]);
    }
}
