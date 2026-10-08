<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Models\Barber;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Transaction;
use App\Services\TransactionWorkflow;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'barber_id' => ['nullable', 'exists:barbers,id'],
            'service_id' => ['nullable', 'exists:services,id'],
            'payment_method' => ['nullable', 'in:cash,gcash'],
            'status' => ['nullable', 'in:queued,in_progress,ready,completed,cancelled'],
        ]);
        $query = Transaction::with(['barber.user', 'customer', 'payment']);
        if ($request->user()->role === 'barber') {
            $query->where('barber_id', $this->ownBarber($request)->id);
        }
        foreach (['barber_id', 'service_id', 'status'] as $filter) {
            if (! empty($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }
        if (! empty($filters['payment_method'])) {
            $query->whereHas('payment', fn ($p) => $p->where('payment_method', $filters['payment_method']));
        }
        if (! empty($filters['from'])) {
            $query->whereDate('transaction_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('transaction_date', '<=', $filters['to']);
        }
        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(fn ($q) => $q->where('id', 'like', $term)->orWhere('service_name', 'like', $term)
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term))
                ->orWhereHas('payment', fn ($p) => $p->where('gcash_reference_no', 'like', $term)));
        }
        if ($request->routeIs('history') && empty($filters['status'])) {
            $query->whereIn('status', ['completed', 'cancelled']);
        }

        return view('transactions.index', [
            'transactions' => $query->orderByDesc('transaction_date')->paginate(20)->withQueryString(),
            'barbers' => $request->user()->role === 'admin' ? Barber::with('user')->get() : collect([$this->ownBarber($request)]),
            'services' => Service::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('transactions.pos', [
            'barbers' => Barber::available()->with('user')->get(),
            'services' => Service::available()->orderBy('name')->get(),
            'customers' => Customer::orderBy('name')->get(),
        ]);
    }

    public function store(CheckoutRequest $request, TransactionWorkflow $workflow)
    {
        try {
            $transaction = $workflow->create($request->validated(), $request->user());
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['checkout_token' => 'This checkout or GCash reference has already been used. Review transaction history before retrying.']);
        }

        return redirect()->route('transactions.show', $transaction)->with('status',
            $transaction->status === 'completed' ? 'Transaction completed; stock deducted and earnings calculated.' : 'Added to queue. GCash requires manual verification before completion. Stock has not been deducted.');
    }

    public function show(Request $request, Transaction $transaction)
    {
        $this->authorizeRecord($request, $transaction);

        return view('transactions.show', ['transaction' => $transaction->load(['barber.user', 'payment', 'customer', 'products'])]);
    }

    public function edit(Transaction $transaction)
    {
        return view('transactions.edit', ['transaction' => $transaction, 'customers' => Customer::orderBy('name')->get()]);
    }

    public function update(Request $request, Transaction $transaction)
    {
        $payment = $transaction->payment;
        $editableReference = $payment?->payment_method === 'gcash' && ! in_array($transaction->status, ['completed', 'cancelled']);
        $data = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'transaction_date' => ['required', 'date_format:Y-m-d\TH:i', 'before_or_equal:now'],
            'gcash_reference_no' => $editableReference
                ? ['required', 'regex:/^[0-9]{6,30}$/', Rule::unique('payments', 'gcash_reference_no')->ignore($payment->id)]
                : ['prohibited'],
            'service_id' => ['prohibited'], 'barber_id' => ['prohibited'], 'quantity' => ['prohibited'],
            'total_amount' => ['prohibited'], 'payment_method' => ['prohibited'],
            'cash_received' => ['prohibited'], 'change_amount' => ['prohibited'],
            'barber_share' => ['prohibited'], 'shop_share' => ['prohibited'], 'unit_price' => ['prohibited'],
        ]);
        try {
            DB::transaction(function () use ($transaction, $data) {
                $locked = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
                if (isset($data['gcash_reference_no'])) {
                    if (in_array($locked->status, ['completed', 'cancelled'])) {
                        throw ValidationException::withMessages(['gcash_reference_no' => 'Completed/cancelled payment records are immutable.']);
                    }
                    $payment = Payment::where('transaction_id', $locked->id)->lockForUpdate()->firstOrFail();
                    if ($payment->gcash_reference_no !== $data['gcash_reference_no']) {
                        $payment->update([
                            'gcash_reference_no' => $data['gcash_reference_no'],
                            'gcash_reference_key' => $data['gcash_reference_no'],
                            'verification_status' => 'pending',
                        ]);
                    }
                    unset($data['gcash_reference_no']);
                }
                $locked->update($data);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['gcash_reference_no' => 'This GCash reference is already used.']);
        }

        return redirect()->route('transactions.show', $transaction)->with('status', 'Customer/date corrected. Financial and inventory snapshots preserved.');
    }

    public function destroy(Transaction $transaction)
    {
        DB::transaction(function () use ($transaction) {
            $locked = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $locked->delete();
        });

        return redirect()->route('history')->with('status', 'Record removed from active history. Audit data and actual stock consumption retained.');
    }

    public function status(Request $request, Transaction $transaction, TransactionWorkflow $workflow)
    {
        $this->authorizeRecord($request, $transaction);
        $isAdmin = $request->user()->role === 'admin';
        $data = $request->validate([
            'status' => ['required', $isAdmin ? 'in:in_progress,ready,completed,cancelled' : 'in:in_progress,ready'],
            'amount' => ['prohibited'], 'total_amount' => ['prohibited'], 'payment_method' => ['prohibited'],
            'unit_price' => ['prohibited'], 'barber_share' => ['prohibited'], 'shop_share' => ['prohibited'],
            'price' => ['prohibited'], 'cash_received' => ['prohibited'], 'gcash_amount' => ['prohibited'],
            'change_amount' => ['prohibited'],
            'payment_amount' => ['prohibited'], 'revenue' => ['prohibited'], 'sales_amount' => ['prohibited'],
        ]);
        if ($data['status'] === 'completed') {
            $workflow->complete($transaction, $request->user());
        } else {
            DB::transaction(function () use ($transaction, $data) {
                $locked = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
                $allowed = [
                    'queued' => ['in_progress', 'cancelled'],
                    'in_progress' => ['ready', 'cancelled'],
                    'ready' => ['cancelled'],
                ];
                if (! in_array($data['status'], $allowed[$locked->status] ?? [], true)) {
                    throw ValidationException::withMessages(['status' => 'This status transition is not allowed.']);
                }
                $locked->update(['status' => $data['status']]);
            });
        }

        return back()->with('status', 'Queue status updated.');
    }

    private function ownBarber(Request $request): Barber
    {
        return Barber::where('user_id', $request->user()->id)->firstOrFail();
    }

    private function authorizeRecord(Request $request, Transaction $transaction): void
    {
        abort_unless($request->user()->role === 'admin' || $transaction->barber_id === $this->ownBarber($request)->id, 403);
    }
}
