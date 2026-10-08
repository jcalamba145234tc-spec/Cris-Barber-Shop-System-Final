<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function csv(Request $request): StreamedResponse
    {
        $report = $this->reportData($request);
        $filenamePeriod = $report['period']['start']->format($report['period']['type'] === 'date' ? 'Y-m-d' : 'Y-m');

        return response()->streamDownload(function () use ($report): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");

            $this->csvRow($output, ['Cris Barber Shop Financial Report']);
            $this->csvRow($output, ['Report period', $report['period']['label']]);
            $this->csvRow($output, ['Total gross sales', $this->csvAmount($report['grossSales'])]);
            $this->csvRow($output, ['Shop share (50%)', $this->csvAmount($report['shopShare'])]);
            $this->csvRow($output, []);
            $this->csvRow($output, ['Barber earnings breakdown']);
            $this->csvRow($output, ['Barber', 'Completed services', 'Barber earnings']);

            foreach ($report['barbers'] as $barber) {
                $this->csvRow($output, [
                    $this->safeCsvText($barber->barber_name),
                    $barber->completed_services,
                    $this->csvAmount((float) $barber->barber_earnings),
                ]);
            }

            $this->csvRow($output, []);
            $this->csvRow($output, ['Payment summary']);
            $this->csvRow($output, ['Payment method', 'Transactions', 'Amount']);

            foreach (['cash' => 'Cash', 'gcash' => 'GCash'] as $key => $label) {
                $this->csvRow($output, [
                    $label,
                    $report['payments'][$key]['count'],
                    $this->csvAmount($report['payments'][$key]['amount']),
                ]);
            }

            fclose($output);
        }, 'cris-barber-financial-report-'.$filenamePeriod.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function pdf(Request $request)
    {
        return view('reports.print', $this->reportData($request));
    }

    /**
     * @return array{
     *     period: array{type: string, label: string, start: Carbon, end: Carbon},
     *     generatedAt: Carbon,
     *     grossSales: float,
     *     shopShare: float,
     *     barbers: Collection,
     *     payments: array<string, array{count: int, amount: float}>
     * }
     */
    private function reportData(Request $request): array
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $filters = $request->validate([
            'period' => ['nullable', 'in:date,month'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        $periodType = $filters['period'] ?? 'month';

        if ($periodType === 'date') {
            $start = Carbon::createFromFormat('Y-m-d', $filters['date'] ?? today()->format('Y-m-d'))->startOfDay();
            $end = $start->copy()->endOfDay();
            $periodLabel = $start->format('F j, Y');
        } else {
            $month = Carbon::createFromFormat('Y-m-d', ($filters['month'] ?? now()->format('Y-m')).'-01');
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();
            $periodLabel = $month->format('F Y');
        }

        $grossSales = (float) Transaction::valid()
            ->whereBetween('transaction_date', [$start, $end])
            ->sum('total_amount');

        $shopShare = (float) Transaction::valid()->whereBetween('transaction_date', [$start, $end])->sum('shop_share');

        $barbers = DB::table('transactions as t')
            ->join('barbers as b', 't.barber_id', '=', 'b.id')
            ->leftJoin('users as u', 'b.user_id', '=', 'u.id')
            ->whereNull('t.deleted_at')
            ->where('t.status', 'completed')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('payments as valid_payment')->whereColumn('valid_payment.transaction_id', 't.id')->where('valid_payment.verification_status', 'verified'))
            ->whereBetween('t.transaction_date', [$start, $end])
            ->groupBy('b.id', 'b.first_name', 'b.last_name', 'u.name')
            ->select('b.first_name', 'b.last_name', 'u.name')
            ->selectRaw('COUNT(t.id) as completed_services, SUM(t.barber_share) as barber_earnings')
            ->orderBy('b.first_name')
            ->get()
            ->map(function ($barber) {
                $barber->barber_name = $barber->name ?? trim($barber->first_name.' '.$barber->last_name);

                return $barber;
            });

        $payments = [
            'cash' => ['count' => 0, 'amount' => 0.0],
            'gcash' => ['count' => 0, 'amount' => 0.0],
        ];

        $paymentTotals = DB::table('transactions as t')
            ->join('payments as p', 'p.transaction_id', '=', 't.id')
            ->whereNull('t.deleted_at')
            ->where('t.status', 'completed')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('payments as valid_payment')->whereColumn('valid_payment.transaction_id', 't.id')->where('valid_payment.verification_status', 'verified'))
            ->whereBetween('t.transaction_date', [$start, $end])
            ->where('p.verification_status', 'verified')
            ->groupBy('p.payment_method')
            ->selectRaw('p.payment_method, COUNT(DISTINCT t.id) as transaction_count, SUM(p.amount) as total_amount')
            ->get();

        foreach ($paymentTotals as $payment) {
            if (array_key_exists($payment->payment_method, $payments)) {
                $payments[$payment->payment_method] = [
                    'count' => (int) $payment->transaction_count,
                    'amount' => (float) $payment->total_amount,
                ];
            }
        }

        return [
            'period' => [
                'type' => $periodType,
                'label' => $periodLabel,
                'start' => $start,
                'end' => $end,
            ],
            'generatedAt' => now(),
            'grossSales' => $grossSales,
            'shopShare' => $shopShare,
            'barbers' => $barbers,
            'payments' => $payments,
        ];
    }

    private function csvRow($output, array $row): void
    {
        fputcsv($output, $row, ',', '"', '');
    }

    private function csvAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function safeCsvText(string $value): string
    {
        return preg_match('/^[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}
