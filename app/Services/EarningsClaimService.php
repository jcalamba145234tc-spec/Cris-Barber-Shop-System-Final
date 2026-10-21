<?php

namespace App\Services;

use App\Models\Barber;
use App\Models\EarningClaim;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EarningsClaimService
{
    public function eligible(Barber $barber, string $from, string $to)
    {
        return Transaction::valid()->where('barber_id', $barber->id)->whereNull('claim_id')
            ->whereDate('transaction_date', '>=', $from)->whereDate('transaction_date', '<=', $to)->orderBy('id');
    }

    public function snapshot(Collection $transactions): array
    {
        $cents = $transactions->sum(fn ($transaction) => Money::cents((string) $transaction->barber_share));

        return [
            'amount' => Money::decimal($cents),
            'fingerprint' => hash('sha256', $transactions->sortBy('id')->map(fn ($t) => $t->id.':'.Money::cents((string) $t->barber_share))->implode('|')),
            'count' => $transactions->count(),
            'from' => $transactions->min('transaction_date')?->toDateString(),
            'to' => $transactions->max('transaction_date')?->toDateString(),
        ];
    }

    public function confirm(Barber $barber, User $owner, array $data): EarningClaim
    {
        abort_unless($owner->role === 'admin', 403);

        return DB::transaction(function () use ($barber, $owner, $data): EarningClaim {
            Barber::whereKey($barber->id)->lockForUpdate()->firstOrFail();
            $existing = EarningClaim::where('claim_token', $data['claim_token'])->lockForUpdate()->first();
            if ($existing) {
                if ($existing->barber_id !== $barber->id || $existing->fingerprint !== $data['fingerprint']
                    || Money::cents((string) $existing->amount) !== Money::cents($data['amount'])) {
                    throw ValidationException::withMessages(['claim' => 'This confirmation token was already used for a different payout.']);
                }

                return $existing;
            }
            $transactions = $this->eligible($barber, $data['from'], $data['to'])->lockForUpdate()->get();
            $snapshot = $this->snapshot($transactions);
            if ($snapshot['count'] === 0 || $snapshot['fingerprint'] !== $data['fingerprint'] || $snapshot['amount'] !== $data['amount']) {
                throw ValidationException::withMessages(['claim' => 'The unclaimed balance changed. Refresh and review the payout again; nothing was claimed.']);
            }
            if (Money::cents($snapshot['amount']) > 9999999999) {
                throw ValidationException::withMessages(['claim' => 'Payout exceeds the storage limit. Choose a smaller date range.']);
            }
            $claim = EarningClaim::create([
                'barber_id' => $barber->id, 'confirmed_by' => $owner->id, 'confirmed_at' => now(),
                'amount' => $snapshot['amount'], 'period_start' => $snapshot['from'], 'period_end' => $snapshot['to'],
                'transaction_count' => $snapshot['count'], 'claim_token' => $data['claim_token'], 'fingerprint' => $snapshot['fingerprint'],
            ]);
            $changed = Transaction::whereKey($transactions->pluck('id'))->whereNull('claim_id')->update(['claim_id' => $claim->id]);
            if ($changed !== $snapshot['count']) {
                throw ValidationException::withMessages(['claim' => 'Another payout changed these transactions. Refresh and try again.']);
            }

            return $claim;
        }, 3);
    }
}
