<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Transaction;
use App\Services\TransactionWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function verify(Request $request, Payment $payment, TransactionWorkflow $workflow)
    {
        $data = $request->validate(['status' => ['required', 'in:verified,flagged']]);
        abort_unless($request->user()?->role === 'admin', 403);
        DB::transaction(function () use ($payment, $data, $workflow) {
            // Same lock order as completion: transaction, then payment.
            $transaction = Transaction::whereKey($payment->transaction_id)->lockForUpdate()->firstOrFail();
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->payment_method === 'gcash', 422);
            if ($transaction->status === 'completed') {
                if ($locked->verification_status === $data['status']) {
                    return;
                }
                throw ValidationException::withMessages(['payment' => 'Completed financial records are immutable. Owner review is required.']);
            }
            if ($transaction->status === 'cancelled') {
                throw ValidationException::withMessages(['payment' => 'Cancelled transactions cannot be verified.']);
            }
            if ($data['status'] === 'verified' && ! preg_match('/^[0-9]{6,30}$/', $locked->gcash_reference_no ?? '')) {
                throw ValidationException::withMessages(['payment' => 'This legacy GCash reference needs owner review; a 6–30 digit reference is required.']);
            }
            if ($data['status'] === 'verified') {
                if (Payment::where('gcash_reference_no', $locked->gcash_reference_no)->whereKeyNot($locked->id)->exists()) {
                    throw ValidationException::withMessages(['payment' => 'Duplicate legacy reference requires owner review.']);
                }
                $workflow->checkSnapshotStock($transaction);
            }
            $locked->update(['verification_status' => $data['status']]);
        });

        return back()->with('status', 'Payment verification updated. Complete the transaction separately after service delivery.');
    }
}
