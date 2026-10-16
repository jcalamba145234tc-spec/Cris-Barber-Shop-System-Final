<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Barber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'barber_id' => ['nullable', 'exists:barbers,id'], 'status' => ['nullable', 'in:present,late,absent,on_leave'],
        ]);
        $query = Attendance::with('barber.user');
        $barbers = Barber::with('user');
        if ($request->user()->role === 'barber') {
            $own = Barber::where('user_id', $request->user()->id)->firstOrFail();
            $query->where('barber_id', $own->id);
            $barbers->whereKey($own->id);
        }
        foreach (['barber_id', 'status'] as $key) {
            if (! empty($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }
        if (! empty($filters['from'])) {
            $query->whereDate('date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('date', '<=', $filters['to']);
        }

        return view('attendance.index', [
            'attendance' => $query->latest('date')->paginate(20)->withQueryString(),
            'barbers' => $barbers->get(),
        ]);
    }

    public function clock(Request $request)
    {
        $data = $request->validate(['action' => ['required', 'in:in,out'], 'barber_id' => ['prohibited'], 'date' => ['prohibited'], 'time_in' => ['prohibited'], 'time_out' => ['prohibited']]);
        try {
            DB::transaction(function () use ($request, $data) {
                $barber = Barber::where('user_id', $request->user()->id)->lockForUpdate()->firstOrFail();
                abort_unless($barber->status === 'active', 403);
                $attendance = Attendance::where('barber_id', $barber->id)->whereDate('date', today())->lockForUpdate()->first();
                if ($data['action'] === 'in') {
                    if ($attendance) {
                        throw ValidationException::withMessages(['action' => 'Today already has an attendance record. Ask the admin for corrections.']);
                    }
                    Attendance::create(['barber_id' => $barber->id, 'date' => today(), 'time_in' => now(), 'status' => 'present']);
                } else {
                    if (! $attendance?->time_in || $attendance->time_out) {
                        throw ValidationException::withMessages(['action' => 'Clock in first; clock-out can only be recorded once.']);
                    }
                    $attendance->update(['time_out' => now()]);
                }
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['action' => 'Attendance already exists for this date. Refresh before retrying.']);
        }

        return back()->with('status', 'Attendance recorded using server time.');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        try {
            DB::transaction(function () use ($data) {
                Barber::whereKey($data['barber_id'])->lockForUpdate()->firstOrFail();
                Attendance::create($data);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['date' => 'This barber already has attendance on that date.']);
        }

        return back()->with('status', 'Attendance added.');
    }

    public function update(Request $request, Attendance $attendance)
    {
        $data = $this->validated($request, $attendance);
        try {
            DB::transaction(fn () => Attendance::whereKey($attendance->id)->lockForUpdate()->firstOrFail()->update($data));
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['date' => 'This barber already has attendance on that date.']);
        }

        return back()->with('status', 'Attendance corrected by admin.');
    }

    private function validated(Request $request, ?Attendance $attendance = null): array
    {
        $data = $request->validate([
            'barber_id' => ['required', 'exists:barbers,id'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', Rule::unique('attendance')->where('barber_id', $request->input('barber_id'))->ignore($attendance?->id)],
            'status' => ['required', 'in:present,late,absent,on_leave'],
            'time_in' => ['nullable', 'required_if:status,present,late', 'date_format:Y-m-d\TH:i', 'before_or_equal:now'],
            'time_out' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:time_in', 'before_or_equal:now'],
        ]);
        if ((! empty($data['time_in']) && substr($data['time_in'], 0, 10) !== $data['date']) ||
            (! empty($data['time_out']) && (empty($data['time_in']) || substr($data['time_out'], 0, 10) !== $data['date'])) ||
            (in_array($data['status'], ['absent', 'on_leave']) && (! empty($data['time_in']) || ! empty($data['time_out'])))) {
            throw ValidationException::withMessages(['time_in' => 'Attendance times must match the date; absent/on-leave records cannot have clock times.']);
        }

        return $data;
    }
}
