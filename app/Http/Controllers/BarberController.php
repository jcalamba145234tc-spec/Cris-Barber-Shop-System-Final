<?php

namespace App\Http\Controllers;

use App\Models\Barber;
use App\Models\User;
use App\Rules\ShopEmailDomain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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

        return view('barbers.index', ['barbers' => $query->orderBy('first_name')->paginate(20)->withQueryString(), 'editing' => $request->filled('edit') ? Barber::with('user')->findOrFail($request->input('edit')) : null]);
    }

    public function edit(Request $request, Barber $barber)
    {
        abort_unless($request->user()->role === 'admin', 403);
        abort_unless($barber->user?->role === 'barber', 422);

        return redirect()->route('barbers.index', ['edit' => $barber->id]);
    }

    public function update(Request $request, Barber $barber)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $user = User::whereKey($barber->user_id)->where('role', 'barber')->firstOrFail();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', new ShopEmailDomain($user->email), Rule::unique('users', 'email')->ignore($user->id)],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'hired_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'role' => ['prohibited'], 'user_id' => ['prohibited'], 'password' => ['prohibited'],
        ]);
        DB::transaction(function () use ($barber, $user, $data) {
            $locked = Barber::whereKey($barber->id)->lockForUpdate()->firstOrFail();
            $account = User::whereKey($user->id)->where('role', 'barber')->lockForUpdate()->firstOrFail();
            $name = preg_split('/\s+/', trim($data['name']), 2);
            $locked->update(['first_name' => mb_substr($name[0], 0, 100), 'last_name' => mb_substr($name[1] ?? '-', 0, 100),
                'phone_number' => $data['phone_number'] ?? null, 'hired_date' => $data['hired_date']]);
            if ($account->email !== $data['email']) {
                $account->email_verified_at = null;
            }
            $account->fill(['name' => $data['name'], 'email' => $data['email']])->save();
        });

        return redirect()->route('barbers.index')->with('status', 'Barber details updated; account identity and history retained.');
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
