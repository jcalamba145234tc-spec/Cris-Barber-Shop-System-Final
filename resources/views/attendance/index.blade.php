@extends('layouts.shop', ['heading' => 'Attendance'])
@section('module')
@if(auth()->user()->role === 'admin')
<div class="stats compact-stats grid grid-cols-1 sm:grid-cols-3">
@foreach(['present'=>'Present','absent'=>'Absent','on_leave'=>'On Leave'] as $key=>$label)<article class="stat"><span>{{ $label }}</span><strong>{{ $summary[$key] }}</strong></article>@endforeach
</div>
<section class="panel">
<div class="panel-heading"><h2>Daily team roster</h2><span class="muted">{{ $barbers->count() }} {{ Illuminate\Support\Str::plural('barber', $barbers->count()) }}</span></div>
<form method="GET" class="report-filters"><div><label for="attendance-date">Attendance date</label><input id="attendance-date" type="date" name="date" value="{{ $selectedDate }}"></div><button>View date</button><a href="{{ route('attendance.index') }}">Today</a></form>
<p class="muted field-hint">Late is set by the owner; no shift-start schedule is assumed. Missing past records show Absent; missing current/future records show Not Yet Clocked In. On Leave follows the recorded status or the barber's current leave setting.</p>
<table><thead><tr><th>Barber</th><th>Clock In</th><th>Clock Out</th><th>Status</th><th>Hours</th><th>Correction</th></tr></thead><tbody>
@forelse($barbers as $barber)
@php($record = $barber->attendance->first())
@php($label = match($barber->attendance_status) { 'late' => 'Late', 'absent' => 'Absent', 'on_leave' => 'On Leave', 'not_yet' => 'Not Yet Clocked In', default => $record?->time_out ? 'Clocked Out' : 'Clocked In' })
<tr><td><strong>{{ $barber->name }}</strong></td><td>{{ $record?->time_in?->format('g:i A') ?? '—' }}</td><td>{{ $record?->time_out?->format('g:i A') ?? '—' }}</td><td><span class="badge badge-{{ $barber->attendance_status }}">{{ $label }}</span></td><td>{{ $record?->hours ?? '—' }}</td><td>
@if($selectedDate <= today()->toDateString())<button type="button" class="secondary-button" x-data @click="$dispatch('open-modal', 'attendance-{{ $barber->id }}')">Set / correct</button>@else<span class="muted">Future date</span>@endif
</td></tr>
@empty<tr><td colspan="6" class="empty-state">No barbers yet. Create a barber account to see the team roster.</td></tr>@endforelse
</tbody></table>
</section>
@foreach($barbers as $barber)
@php($record = $barber->attendance->first())
<x-modal :name="'attendance-'.$barber->id" :title="'Attendance · '.$barber->name">
<form method="POST" action="{{ $record ? route('attendance.update',$record) : route('attendance.store') }}">
@csrf @if($record) @method('PUT') @endif
<input type="hidden" name="_modal" value="attendance-{{ $barber->id }}"><input type="hidden" name="barber_id" value="{{ $barber->id }}"><input type="hidden" name="date" value="{{ $selectedDate }}">
<p class="muted">{{ $selectedDate }} · Asia/Manila. Corrections do not alter transaction earnings.</p>
<label for="status-{{ $barber->id }}">Status</label><select id="status-{{ $barber->id }}" name="status">@foreach(['present'=>'Present','late'=>'Late','absent'=>'Absent','on_leave'=>'On Leave'] as $value=>$label)<option value="{{ $value }}" @selected(old('_modal') === 'attendance-'.$barber->id ? old('status') === $value : ($record?->status ?? 'present') === $value)>{{ $label }}</option>@endforeach</select><x-field-error name="status" />
<label for="in-{{ $barber->id }}">Clock In</label><input id="in-{{ $barber->id }}" type="datetime-local" name="time_in" value="{{ old('_modal') === 'attendance-'.$barber->id ? old('time_in') : $record?->time_in?->format('Y-m-d\TH:i') }}"><x-field-error name="time_in" />
<label for="out-{{ $barber->id }}">Clock Out</label><input id="out-{{ $barber->id }}" type="datetime-local" name="time_out" value="{{ old('_modal') === 'attendance-'.$barber->id ? old('time_out') : $record?->time_out?->format('Y-m-d\TH:i') }}"><x-field-error name="time_out" />
<x-field-error name="date" /><div class="actions justify-end"><button>Save attendance</button></div></form>
</x-modal>
@endforeach
@else
<section class="panel">
<div class="panel-heading"><h2>Today's timecard</h2><span class="badge">{{ today()->format('M j, Y') }}</span></div>
<p class="muted">Recorded using server time in Asia/Manila. Historical corrections are owner-only.</p>
<div class="timecard-status"><span class="muted">Attendance status</span><span class="badge badge-{{ $todayAttendance?->status ?? 'not_yet' }}">{{ ucfirst(str_replace('_', ' ', $todayAttendance?->status ?? 'Not clocked in')) }}</span>@if($todayAttendance?->time_out)<span class="badge badge-positive">Clocked out</span>@endif</div>
<dl class="timecard-grid"><div><dt>Clock In</dt><dd>{{ $todayAttendance?->time_in?->format('g:i A') ?? '—' }}</dd></div><div><dt>Clock Out</dt><dd>{{ $todayAttendance?->time_out?->format('g:i A') ?? '—' }}</dd></div></dl>
<form class="actions" method="POST" action="{{ route('attendance.clock') }}">@csrf<button name="action" value="in" @disabled($todayAttendance || !$canClockIn)>Clock In</button><button name="action" value="out" class="secondary-button" @disabled(!$todayAttendance?->time_in || $todayAttendance?->time_out || !$canClockIn)>Clock Out</button></form><x-field-error name="action" />
<p class="field-hint timecard-help">
@if(!$canClockIn)Clock actions are unavailable while your barber account is inactive or on leave.
@elseif($todayAttendance?->time_out)Today's timecard is complete. Ask the shop owner for corrections.
@elseif($todayAttendance?->time_in)Clock-in recorded. Clock out when your shift is complete.
@elseif($todayAttendance)Today's attendance has already been recorded. Ask the shop owner for corrections.
@else Clock in to start today's timecard. Times are recorded automatically.
@endif
</p>
</section>
<section class="panel"><h2>My attendance records</h2>
<form class="report-filters" method="GET"><div><label for="from">From</label><input id="from" name="from" type="date" value="{{ request('from') }}"></div><div><label for="to">To</label><input id="to" name="to" type="date" value="{{ request('to') }}"></div><button>Filter</button><a href="{{ route('attendance.index') }}">Reset</a></form>
<table><thead><tr><th>Date</th><th>Clock In</th><th>Clock Out</th><th>Status</th><th>Hours</th></tr></thead><tbody>@forelse($attendance as $record)<tr><td>{{ $record->date->format('M j, Y') }}</td><td>{{ $record->time_in?->format('g:i A') ?? '—' }}</td><td>{{ $record->time_out?->format('g:i A') ?? '—' }}</td><td><span class="badge badge-{{ $record->status }}">{{ ucfirst(str_replace('_',' ',$record->status)) }}</span></td><td>{{ $record->hours ?? '—' }}</td></tr>@empty<tr><td colspan="5" class="empty-state">No attendance records in this period. <a href="{{ route('attendance.index') }}">Reset filters</a></td></tr>@endforelse</tbody></table>{{ $attendance->links() }}</section>
@endif
@endsection
