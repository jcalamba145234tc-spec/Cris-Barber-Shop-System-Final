<?php

namespace App\Services;

use App\Models\Barber;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use App\Support\WholeQuantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TransactionWorkflow
{
    public function create(array $data, User $actor): Transaction
    {
        abort_unless($actor->role === 'admin', 403);
        Validator::make($data, ['quantity' => ['required', 'integer', 'min:1', 'max:100']])->validate();

        return DB::transaction(function () use ($data, $actor) {
            $existing = Transaction::withTrashed()->where('checkout_token', $data['checkout_token'])->first();
            if ($existing) {
                if ($existing->trashed()) {
                    throw ValidationException::withMessages(['checkout_token' => 'This checkout was already removed. Open a new POS form.']);
                }

                return $this->sameCheckout($existing, $data);
            }
            $barber = Barber::whereKey($data['barber_id'])->lockForUpdate()->firstOrFail();
            // Recheck after the barber lock: concurrent identical checkout retries serialize here.
            $existing = Transaction::withTrashed()->where('checkout_token', $data['checkout_token'])->first();
            if ($existing) {
                if ($existing->trashed()) {
                    throw ValidationException::withMessages(['checkout_token' => 'This checkout was already removed. Open a new POS form.']);
                }

                return $this->sameCheckout($existing, $data);
            }
            if (! Barber::available()->whereKey($barber->id)->exists()) {
                throw ValidationException::withMessages(['barber_id' => 'This barber is not currently available.']);
            }
            $service = Service::whereKey($data['service_id'])->lockForUpdate()->firstOrFail();
            if (! $service->active) {
                throw ValidationException::withMessages(['service_id' => 'This service is inactive.']);
            }
            $quantity = (int) $data['quantity'];
            $requirements = DB::table('service_products')->where('service_id', $service->id)->orderBy('product_id')->get();
            foreach ($requirements as $requirement) {
                $product = Product::whereKey($requirement->product_id)->lockForUpdate()->firstOrFail();
                $needed = WholeQuantity::require($requirement->quantity, 'service_id') * $quantity;
                $stock = WholeQuantity::require($product->stock, 'service_id');
                if ($needed < 1 || $needed > 999999999) {
                    throw ValidationException::withMessages(['service_id' => 'Invalid whole-number service consumption. Owner review required.']);
                }
                if (! $product->active || $stock < $needed) {
                    throw ValidationException::withMessages(['service_id' => "Insufficient available stock: {$product->name}. Reduce quantity or restock."]);
                }
            }
            $cents = Money::cents((string) $service->price) * $quantity;
            if ($cents > 9999999999) {
                throw ValidationException::withMessages(['quantity' => 'The transaction total exceeds the monetary storage limit.']);
            }
            // Preserve the existing half-up barber share for odd-cent totals.
            $barberCents = intdiv($cents + 1, 2);
            $cashReceivedCents = null;
            if ($data['payment_method'] === 'cash') {
                $cash = trim((string) ($data['cash_received'] ?? ''));
                if (! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $cash)) {
                    throw ValidationException::withMessages(['cash_received' => 'Enter the customer\'s cash amount, with at most two decimal places.']);
                }
                $cashReceivedCents = Money::cents($cash);
                if ($cashReceivedCents < $cents) {
                    throw ValidationException::withMessages(['cash_received' => 'Customer\'s money must cover the transaction total of ₱'.Money::decimal($cents).'.']);
                }
            }
            $transaction = Transaction::create([
                'checkout_token' => $data['checkout_token'],
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'service_name' => $service->name,
                'customer_id' => $data['customer_id'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $service->price,
                'total_amount' => Money::decimal($cents),
                'barber_share' => Money::decimal($barberCents),
                'shop_share' => Money::decimal($cents - $barberCents),
                'transaction_date' => now(),
                'status' => 'queued',
                'inventory_managed' => true,
            ]);
            foreach ($requirements as $requirement) {
                $transaction->products()->attach($requirement->product_id, ['quantity' => WholeQuantity::require($requirement->quantity, 'service_id') * $quantity]);
            }
            Payment::create([
                'transaction_id' => $transaction->id,
                'payment_method' => $data['payment_method'],
                'gcash_reference_no' => $data['payment_method'] === 'gcash' ? $data['gcash_reference_no'] : null,
                'gcash_reference_key' => $data['payment_method'] === 'gcash' ? $data['gcash_reference_no'] : null,
                'verification_status' => $data['payment_method'] === 'gcash' ? 'pending' : 'verified',
                'amount' => Money::decimal($cents),
                'cash_received' => $cashReceivedCents !== null ? Money::decimal($cashReceivedCents) : null,
                'change_amount' => $cashReceivedCents !== null ? Money::decimal($cashReceivedCents - $cents) : null,
            ]);
            // Manual GCash verification must precede completion, even for immediate checkout.
            if ($data['status'] === 'completed' && $data['payment_method'] === 'cash') {
                $this->complete($transaction, $actor);
            }

            return $transaction->refresh();
        }, 3);
    }

    public function complete(Transaction $transaction, User $actor): void
    {
        abort_unless($actor->role === 'admin', 403);
        DB::transaction(function () use ($transaction, $actor) {
            $locked = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'completed') {
                return; // Retries must never deduct inventory twice.
            }
            if (! in_array($locked->status, ['queued', 'in_progress', 'ready'], true)) {
                throw ValidationException::withMessages(['status' => 'A cancelled transaction cannot be completed.']);
            }
            $payment = Payment::where('transaction_id', $locked->id)->lockForUpdate()->first();
            if (! $payment || $payment->verification_status !== 'verified') {
                throw ValidationException::withMessages(['payment' => 'Verify the payment before completing this transaction.']);
            }
            if ($locked->inventory_managed && ! $locked->inventory_deducted_at) {
                $requirements = DB::table('transaction_products')->where('transaction_id', $locked->id)->orderBy('product_id')->get();
                foreach ($requirements as $requirement) {
                    $product = Product::whereKey($requirement->product_id)->lockForUpdate()->firstOrFail();
                    $needed = WholeQuantity::require($requirement->quantity);
                    WholeQuantity::require($product->stock);
                    if ($needed < 1) {
                        throw ValidationException::withMessages(['stock' => 'Invalid service consumption snapshot. Owner review required.']);
                    }
                    // Conditional UPDATE protects stock even on engines without row locks (SQLite).
                    $changed = Product::whereKey($product->id)->where('active', true)
                        ->where('stock', '>=', $needed)
                        ->update([
                            'stock' => DB::raw('stock - '.$needed),
                            'updated_at' => now(),
                        ]);
                    if (! $changed) {
                        throw ValidationException::withMessages(['stock' => "Insufficient available stock: {$product->name}. Nothing was deducted."]);
                    }
                    $product->refresh();
                    InventoryMovement::create([
                        'product_id' => $product->id, 'transaction_id' => $locked->id,
                        'user_id' => $actor->id, 'type' => 'service',
                        'quantity' => -$needed, 'balance' => $product->stock,
                        'notes' => 'Completed service consumption',
                    ]);
                }
                $locked->inventory_deducted_at = now();
            }
            $locked->status = 'completed';
            $locked->completed_at = now();
            $locked->save();
        }, 3);
    }

    // Caller holds transaction/payment locks; product locks follow in ID order.
    public function checkSnapshotStock(Transaction $transaction): void
    {
        if (! $transaction->inventory_managed || $transaction->inventory_deducted_at) {
            return;
        }
        foreach (DB::table('transaction_products')->where('transaction_id', $transaction->id)->orderBy('product_id')->get() as $requirement) {
            $product = Product::whereKey($requirement->product_id)->lockForUpdate()->firstOrFail();
            $needed = WholeQuantity::require($requirement->quantity);
            $stock = WholeQuantity::require($product->stock);
            if ($needed < 1 || ! $product->active || $stock < $needed) {
                throw ValidationException::withMessages(['stock' => "Insufficient available stock: {$product->name}. Payment was not verified and nothing was deducted."]);
            }
        }
    }

    private function sameCheckout(Transaction $existing, array $data): Transaction
    {
        $payment = $existing->payment;
        $matches = $existing->barber_id === $data['barber_id']
            && $existing->service_id === $data['service_id']
            && (int) $existing->quantity === (int) $data['quantity']
            && (string) $existing->customer_id === (string) ($data['customer_id'] ?? '')
            && $payment?->payment_method === $data['payment_method'];
        if ($data['payment_method'] === 'gcash') {
            $matches = $matches && $payment?->gcash_reference_no === ($data['gcash_reference_no'] ?? null);
        } elseif ($payment?->cash_received !== null) {
            $matches = $matches && Money::cents((string) $payment->cash_received) === Money::cents((string) ($data['cash_received'] ?? ''));
        }
        if (! $matches) {
            throw ValidationException::withMessages(['checkout_token' => 'This token belongs to another checkout. Review history and open a new POS form.']);
        }

        return $existing;
    }
}
