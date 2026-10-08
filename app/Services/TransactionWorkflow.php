<?php

namespace App\Services;

use App\Models\Barber;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionWorkflow
{
    public function create(array $data, User $actor): Transaction
    {
        abort_unless($actor->role === 'admin', 403);

        return DB::transaction(function () use ($data, $actor) {
            $existing = Transaction::withTrashed()->where('checkout_token', $data['checkout_token'])->first();
            if ($existing) {
                if ($existing->trashed()) {
                    throw ValidationException::withMessages(['checkout_token' => 'This checkout was already removed. Open a new POS form.']);
                }

                return $existing;
            }
            $barber = Barber::whereKey($data['barber_id'])->lockForUpdate()->firstOrFail();
            // Recheck after the barber lock: concurrent identical checkout retries serialize here.
            $existing = Transaction::withTrashed()->where('checkout_token', $data['checkout_token'])->first();
            if ($existing) {
                if ($existing->trashed()) {
                    throw ValidationException::withMessages(['checkout_token' => 'This checkout was already removed. Open a new POS form.']);
                }

                return $existing;
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
                $needed = round((float) $requirement->quantity * $quantity, 3);
                if (! $product->active || (float) $product->stock < $needed) {
                    throw ValidationException::withMessages(['service_id' => "Insufficient available stock: {$product->name}. Reduce quantity or restock."]);
                }
            }
            $cents = (int) round((float) $service->price * 100) * $quantity;
            $barberCents = (int) round($cents / 2);
            $cashReceivedCents = null;
            if ($data['payment_method'] === 'cash') {
                $cash = trim((string) ($data['cash_received'] ?? ''));
                if (! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $cash)) {
                    throw ValidationException::withMessages(['cash_received' => 'Enter the customer\'s cash amount, with at most two decimal places.']);
                }
                $parts = explode('.', $cash, 2);
                $cashReceivedCents = ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '', 2, '0');
                if ($cashReceivedCents < $cents) {
                    throw ValidationException::withMessages(['cash_received' => 'Customer\'s money must cover the transaction total of ₱'.number_format($cents / 100, 2).'.']);
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
                'total_amount' => $cents / 100,
                'barber_share' => $barberCents / 100,
                'shop_share' => ($cents - $barberCents) / 100,
                'transaction_date' => now(),
                'status' => 'queued',
                'inventory_managed' => true,
            ]);
            foreach ($requirements as $requirement) {
                $transaction->products()->attach($requirement->product_id, ['quantity' => round((float) $requirement->quantity * $quantity, 3)]);
            }
            Payment::create([
                'transaction_id' => $transaction->id,
                'payment_method' => $data['payment_method'],
                'gcash_reference_no' => $data['payment_method'] === 'gcash' ? $data['gcash_reference_no'] : null,
                'gcash_reference_key' => $data['payment_method'] === 'gcash' ? $data['gcash_reference_no'] : null,
                'verification_status' => $data['payment_method'] === 'gcash' ? 'pending' : 'verified',
                'amount' => $cents / 100,
                'cash_received' => $cashReceivedCents !== null ? $cashReceivedCents / 100 : null,
                'change_amount' => $cashReceivedCents !== null ? ($cashReceivedCents - $cents) / 100 : null,
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
                    // Conditional UPDATE protects stock even on engines without row locks (SQLite).
                    $changed = Product::whereKey($product->id)->where('active', true)
                        ->whereRaw('ROUND(stock, 3) >= CAST(? AS DECIMAL(12,3))', [$requirement->quantity])
                        ->update([
                            'stock' => DB::raw('ROUND(stock - '.sprintf('%.3F', (float) $requirement->quantity).', 3)'),
                            'updated_at' => now(),
                        ]);
                    if (! $changed) {
                        throw ValidationException::withMessages(['stock' => "Insufficient available stock: {$product->name}. Nothing was deducted."]);
                    }
                    $product->refresh();
                    InventoryMovement::create([
                        'product_id' => $product->id, 'transaction_id' => $locked->id,
                        'user_id' => $actor->id, 'type' => 'service',
                        'quantity' => -((float) $requirement->quantity), 'balance' => $product->stock,
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
}
