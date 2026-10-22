<?php

namespace App\Http\Controllers;

use App\Models\Barber;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionRecycleBinController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $query = Transaction::onlyTrashed()->with(['customer', 'barber.user', 'payment', 'services']);
        if (isset($filters['q']) && $filters['q'] !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(fn ($query) => $query->where('id', 'like', $term)
                ->orWhere('service_name', 'like', $term)
                ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', $term))
                ->orWhereHas('services', fn ($service) => $service->where('transaction_services.service_name', 'like', $term))
                ->orWhereHas('payment', fn ($payment) => $payment->where('gcash_reference_no', 'like', $term)));
        }

        return view('transactions.recycle-bin', [
            'transactions' => $query->orderByDesc('deleted_at')->orderByDesc('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function show(string $transaction)
    {
        return view('transactions.show', [
            'transaction' => Transaction::onlyTrashed()->with(['barber.user', 'payment', 'customer', 'products', 'services', 'claim.confirmedBy'])->findOrFail($transaction),
        ]);
    }

    public function restore(string $transaction)
    {
        DB::transaction(function () use ($transaction) {
            $preview = Transaction::onlyTrashed()->findOrFail($transaction);
            $barber = Barber::whereKey($preview->barber_id)->lockForUpdate()->firstOrFail();
            // Unarchive only. Do not replay checkout, payment, stock or status transitions.
            $record = Transaction::onlyTrashed()->whereKey($transaction)->lockForUpdate()->firstOrFail();
            if ($barber->archived_at && in_array($record->status, ['queued', 'in_progress', 'ready'], true)) {
                throw ValidationException::withMessages(['archive' => 'Restore the barber account before returning this active service to the queue.']);
            }
            $record->restore();
        });

        return redirect()->route('transactions.show', $transaction)->with('status',
            'Transaction restored. Its previous status, payment and inventory snapshots are unchanged.');
    }
}
