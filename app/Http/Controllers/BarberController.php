<?php

namespace App\Http\Controllers;

use App\Models\Barber;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class BarberController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive,on_leave'],
            'availability' => ['nullable', 'in:available,unavailable,busy'],
        ]);
        $query = Barber::with('user')->withCount(['transactions as open_queue_count' => fn ($q) => $q->whereIn('status', ['queued', 'in_progress', 'ready'])]);
        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(fn ($q) => $q->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term)));
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (($filters['availability'] ?? '') === 'available') {
            $query->available();
        } elseif (($filters['availability'] ?? '') === 'busy') {
            $query->whereHas('transactions', fn ($q) => $q->whereIn('status', ['queued', 'in_progress', 'ready']));
        } elseif (($filters['availability'] ?? '') === 'unavailable') {
            $query->where(fn ($q) => $q->where('availability', 'unavailable')->orWhere('status', '!=', 'active'));
        }

        return view('barbers.index', ['barbers' => $query->orderBy('first_name')->paginate(20)->withQueryString()]);
    }

    public function updateStatus(Request $request, Barber $barber)
    {
        abort_unless($request->user()->role === 'admin', 403);
        if ($request->has('barber_state')) {
            $validated = $request->validate([
                'barber_state' => ['required', 'in:available,unavailable,inactive,on_leave'],
                'status' => ['prohibited'],
                'availability' => ['prohibited'],
            ]);
            $data = match ($validated['barber_state']) {
                'available' => ['status' => 'active', 'availability' => 'available'],
                'unavailable' => ['status' => 'active', 'availability' => 'unavailable'],
                'inactive' => ['status' => 'inactive', 'availability' => 'unavailable'],
                'on_leave' => ['status' => 'on_leave', 'availability' => 'unavailable'],
            };
        } else {
            // Retain compatibility with previously rendered two-field forms.
            $data = $request->validate([
                'status' => ['required', 'in:active,inactive,on_leave'],
                'availability' => ['required', 'in:available,unavailable'],
            ]);
        }
        DB::transaction(fn () => Barber::whereKey($barber->id)->lockForUpdate()->firstOrFail()->update($data));

        return back()->with('status', 'Barber status updated.');
    }

    public function resetPassword(Request $request, Barber $barber): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::query()
            ->whereKey($barber->user_id)
            ->where('role', 'barber')
            ->firstOrFail();

        $user->password = $validated['password'];
        $user->save();

        return back()->with('status', 'Barber password reset successfully. Share the temporary password with the barber securely.');
    }
}
