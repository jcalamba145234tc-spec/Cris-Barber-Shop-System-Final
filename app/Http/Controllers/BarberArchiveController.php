<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Barber;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BarberArchiveController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->role === 'admin', 403);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $query = Barber::whereNotNull('archived_at')->with(['user', 'archivedBy'])->withCount('transactions');
        if (isset($data['q']) && $data['q'] !== '') {
            $term = '%'.$data['q'].'%';
            $query->where(fn ($q) => $q->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)
                ->orWhereHas('user', fn ($user) => $user->where('name', 'like', $term)->orWhere('email', 'like', $term)));
        }

        return view('barbers.archived', ['barbers' => $query->orderByDesc('archived_at')->orderBy('id')->paginate(20)->withQueryString()]);
    }

    public function archive(Request $request, Barber $barber)
    {
        abort_unless($request->user()?->role === 'admin', 403);
        $request->validate(['confirmation' => ['required', 'accepted']]);
        DB::transaction(function () use ($request, $barber) {
            $locked = Barber::whereKey($barber->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->archived_at, 409, 'This barber is already archived.');
            $user = User::whereKey($locked->user_id)->where('role', 'barber')->lockForUpdate()->firstOrFail();
            if ($locked->transactions()->whereIn('status', ['queued', 'in_progress', 'ready'])->exists()) {
                throw ValidationException::withMessages(['archive' => 'Finish or cancel the barber’s active services before archiving.']);
            }
            if (Transaction::valid()->where('barber_id', $locked->id)->whereNull('claim_id')->where('barber_share', '>', 0)->exists()) {
                throw ValidationException::withMessages(['archive' => 'Settle the barber’s unclaimed earnings in Earnings payouts before archiving.']);
            }
            if (Attendance::where('barber_id', $locked->id)->whereNotNull('time_in')->whereNull('time_out')->exists()) {
                throw ValidationException::withMessages(['archive' => 'Close the barber’s open attendance record before archiving.']);
            }
            $locked->forceFill(['archived_at' => now(), 'archived_by' => $request->user()->id,
                'restored_at' => null, 'restored_by' => null, 'status' => 'inactive', 'availability' => 'unavailable'])->save();
            // Revoke database sessions and remember-me tokens without deleting the account or its history.
            $user->forceFill(['remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }, 3);

        return redirect()->route('barbers.index')->with('status', 'Barber archived. Login is blocked; transaction, attendance and payout history is preserved.');
    }

    public function restore(Request $request, Barber $barber)
    {
        abort_unless($request->user()?->role === 'admin', 403);
        $request->validate(['confirmation' => ['required', 'accepted']]);
        DB::transaction(function () use ($request, $barber) {
            $locked = Barber::whereKey($barber->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->archived_at, 409, 'This barber is not archived.');
            User::whereKey($locked->user_id)->where('role', 'barber')->lockForUpdate()->firstOrFail();
            // Explicitly unavailable: the owner decides when a returning barber can take new services.
            $locked->forceFill(['archived_at' => null, 'restored_at' => now(), 'restored_by' => $request->user()->id,
                'status' => 'active', 'availability' => 'unavailable'])->save();
        }, 3);

        return redirect()->route('barbers.index')->with('status', 'Barber restored with the same login and history. Set status to Available when ready for new services.');
    }
}
