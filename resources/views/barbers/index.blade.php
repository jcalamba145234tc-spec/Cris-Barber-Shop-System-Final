@extends('layouts.shop', ['heading' => 'Barbers'])
@section('module')
<style>
    .barber-table th:nth-child(4), .barber-table td:nth-child(4) { width: 200px; }
    .barber-filters label, .barber-table label { color: var(--muted); }
    .barber-state-form { display: inline-block; margin: 0; }
    .barber-table .barber-state-select { width: 180px; max-width: 100%; min-width: 160px; min-height: 40px; margin: 0; padding: 8px 12px; font-size: .88rem; }
    .barber-availability { display: inline-flex; align-items: center; padding: 6px 10px; border: 1px solid var(--line); border-radius: 999px; color: var(--ink); font-size: .82rem; font-weight: 600; white-space: nowrap; }
    .barber-availability.is-available { background: color-mix(in srgb, var(--brand) 12%, var(--surface)); }
    .barber-availability.is-busy { background: color-mix(in srgb, #d97706 12%, var(--surface)); }
    .barber-status-help { margin: 0 0 12px; font-size: .82rem; }
</style>
<section class="panel">
    <div class="panel-heading"><h2>Team / barber accounts</h2><button type="button" x-data @click="$dispatch('open-modal', 'add-barber')">Add Barber</button></div>
    <form class="report-filters barber-filters" method="GET">
        <div><label for="q">Search name or email</label><input id="q" name="q" value="{{ request('q') }}"></div>
        <div><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option>@foreach(['active','inactive','on_leave'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_',' ',$status)) }}</option>@endforeach</select></div>
        <div><label for="availability">Availability</label><select id="availability" name="availability"><option value="">All availability</option>@foreach(['available','unavailable','busy'] as $availability)<option value="{{ $availability }}" @selected(request('availability') === $availability)>{{ ucfirst($availability) }}</option>@endforeach</select></div>
        <button>Filter</button><a href="{{ route('barbers.index') }}">Reset</a>
    </form>
    <p class="muted barber-status-help" id="barber-status-help">Select a status to save it automatically. Busy is calculated from assigned active services.</p>
    <table class="barber-table">
        <thead><tr><th>Barber</th><th>Email</th><th>Current availability</th><th>Status / availability</th><th>Account</th></tr></thead>
        <tbody>
        @forelse($barbers as $barber)
            <tr>
                <td>{{ $barber->name }}</td>
                <td>{{ $barber->user?->email }}</td>
                <td><span @class(['barber-availability', 'is-available' => $barber->status === 'active' && $barber->availability === 'available' && (int) $barber->open_queue_count === 0, 'is-busy' => $barber->status === 'active' && $barber->availability === 'available' && $barber->open_queue_count > 0])>{{ $barber->status !== 'active' || $barber->availability !== 'available' ? 'Unavailable' : ($barber->open_queue_count > 0 ? 'Busy' : 'Available') }}</span></td>
                <td>
                    <form class="barber-state-form" method="POST" action="{{ route('barbers.status',$barber) }}">
                        @csrf @method('PATCH')
                        <select class="barber-state-select" name="barber_state" aria-label="Status and availability for {{ $barber->name }}" aria-describedby="barber-status-help" onchange="this.form.requestSubmit()" required>
                            <option value="available" @selected($barber->status === 'active' && $barber->availability === 'available')>Available</option>
                            <option value="unavailable" @selected($barber->status === 'active' && $barber->availability !== 'available')>Unavailable</option>
                            <option value="inactive" @selected($barber->status === 'inactive')>Inactive</option>
                            <option value="on_leave" @selected($barber->status === 'on_leave')>On leave</option>
                        </select>
                        <noscript><button type="submit">Save status</button></noscript>
                    </form>
                </td>
                <td>
                    @if($barber->user?->role === 'barber')
                        <a href="{{ route('barbers.edit',$barber) }}">Edit details</a>
                        <button type="button" class="secondary-button" x-data @click="$dispatch('open-modal', 'reset-{{ $barber->id }}')">Reset password</button>
                    @else
                        No linked account
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5">No matching barbers.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $barbers->links() }}
</section>
<x-modal name="add-barber" title="Add Barber" :open="request()->boolean('add')">@include('barbers.create-form')</x-modal>
@if($editing)<x-modal name="edit-barber" title="Edit barber details" :open="true">@include('barbers.edit-form', ['barber'=>$editing])</x-modal>@endif
@foreach($barbers as $barber)
<x-modal :name="'reset-'.$barber->id" :title="'Reset password · '.$barber->name">
<form method="POST" action="{{ route('barbers.password',$barber) }}">@csrf<input type="hidden" name="_modal" value="reset-{{ $barber->id }}"><p class="muted">Share the new password with the barber securely.</p><label>Temporary password<input type="password" name="password" minlength="8" autocomplete="new-password" required></label><x-field-error name="password" /><label>Confirm password<input type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required></label><div class="actions justify-end"><button>Reset Password</button></div></form>
</x-modal>
@endforeach
@endsection
