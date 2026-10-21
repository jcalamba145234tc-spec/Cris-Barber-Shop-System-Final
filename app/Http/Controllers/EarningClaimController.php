<?php

namespace App\Http\Controllers;

use App\Models\Barber;
use App\Models\EarningClaim;
use App\Models\Transaction;
use App\Services\EarningsClaimService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EarningClaimController extends Controller
{
    public function index(Request $request, EarningsClaimService $service)
    {
        $filters = $request->validate(['from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today']]);
        $earliest = Transaction::valid()->whereNull('claim_id')->min('transaction_date');
        $from = $filters['from'] ?? substr((string) ($earliest ?? today()->toDateString()), 0, 10);
        $to = $filters['to'] ?? today()->toDateString();
        $barbers = Barber::with('user')->orderBy('first_name')->paginate(20, ['*'], 'barbers_page')->withQueryString();
        $snapshots = $barbers->mapWithKeys(fn ($barber) => [$barber->id => $service->snapshot($service->eligible($barber, $from, $to)->get())]);

        return view('earnings.index', ['barbers' => $barbers, 'snapshots' => $snapshots, 'from' => $from, 'to' => $to,
            'claims' => EarningClaim::with(['barber.user', 'confirmedBy'])->latest('confirmed_at')->paginate(20)->withQueryString()]);
    }

    public function store(Request $request, Barber $barber, EarningsClaimService $service)
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today'],
            'claim_token' => ['required', 'uuid'], 'fingerprint' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'amount' => ['required', 'regex:/^\d{1,8}\.\d{2}$/'], 'confirmed' => ['accepted'],
        ]);
        try {
            $service->confirm($barber, $request->user(), $data);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['claim' => 'This confirmation has already been submitted. Refresh the payout ledger.']);
        }

        return back()->with('status', 'Earnings payout recorded. These earnings cannot be claimed again.');
    }
}
