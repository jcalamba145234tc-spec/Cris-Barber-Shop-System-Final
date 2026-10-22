@extends('layouts.shop', ['heading' => 'Barbers'])
@section('module')

<section class="panel barber-accounts-panel">
    <div class="panel-heading barber-accounts-heading">
        <h2>Team / barber accounts</h2>
        <div class="barber-toolbar-actions">
            <a class="button secondary-button" href="{{ route('barbers.archived') }}">Archived barbers ({{ $archivedCount }})</a>
            <button type="button" x-data @click="$dispatch('open-modal', 'add-barber')">Add Barber</button>
        </div>
    </div>
    <form class="report-filters barber-filters" method="GET">
        <div><label for="q">Search name or email</label><input id="q" name="q" value="{{ request('q') }}"></div>
        <div><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option>@foreach(['active','inactive','on_leave'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_',' ',$status)) }}</option>@endforeach</select></div>
        <div><label for="availability">Availability</label><select id="availability" name="availability"><option value="">All availability</option>@foreach(['available','unavailable','busy'] as $availability)<option value="{{ $availability }}" @selected(request('availability') === $availability)>{{ ucfirst($availability) }}</option>@endforeach</select></div>
        <button>Filter</button><a href="{{ route('barbers.index') }}">Reset</a>
    </form>
    @if(session('status') === 'Barber status updated.')<p class="inline-feedback" role="status">Saved — Barber status updated.</p>@endif
    <p class="muted barber-status-help" id="barber-status-help">Select a status to save it automatically. Inactive blocks login. Busy is calculated from assigned active services.</p>
    <div class="table-scroll" role="region" aria-label="Current barber accounts" tabindex="0">
    <table class="barber-table">
        <thead><tr><th>Barber</th><th>Email</th><th>Availability</th><th>Status</th><th>Account</th></tr></thead>
        <tbody>
        @forelse($barbers as $barber)
            <tr>
                <td>{{ $barber->name }}</td>
                <td>{{ $barber->user?->email }}</td>
                <td><span @class(['barber-availability', 'is-available' => $barber->status === 'active' && $barber->availability === 'available' && (int) $barber->open_queue_count === 0, 'is-busy' => $barber->status === 'active' && $barber->availability === 'available' && $barber->open_queue_count > 0])>{{ $barber->status === 'inactive' ? 'Inactive · login blocked' : ($barber->status !== 'active' || $barber->availability !== 'available' ? 'Unavailable' : ($barber->open_queue_count > 0 ? 'Busy' : 'Available')) }}</span></td>
                <td>
                    <form class="barber-state-form" data-autosave method="POST" action="{{ route('barbers.status',$barber) }}">
                        @csrf @method('PATCH')
                        <select class="barber-state-select" name="barber_state" aria-label="Status and availability for {{ $barber->name }}" aria-describedby="barber-status-help" data-autosave-control required>
                            <option value="available" @selected($barber->status === 'active' && $barber->availability === 'available')>Available</option>
                            <option value="unavailable" @selected($barber->status === 'active' && $barber->availability !== 'available')>Unavailable</option>
                            <option value="inactive" @selected($barber->status === 'inactive')>Inactive</option>
                            <option value="on_leave" @selected($barber->status === 'on_leave')>On leave</option>
                        </select>
                        <span class="inline-feedback" data-save-feedback role="status" aria-live="polite"></span><noscript><button type="submit">Save status</button></noscript>
                    </form>
                </td>
                <td>
                    @if($barber->user?->role === 'barber')
                        <div class="barber-account-actions">
                            <a class="button secondary-button" href="{{ route('barbers.edit',$barber) }}">Edit details</a>
                            <button type="button" class="secondary-button" x-data @click="$dispatch('open-modal', 'archive-{{ $barber->id }}')">Archive barber</button>
                        </div>
                    @else
                        No linked account
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5">No matching barbers. <a href="{{ route('barbers.index') }}">Reset filters</a></td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    {{ $barbers->links() }}
</section>
<x-modal name="add-barber" title="Add Barber" :open="request()->boolean('add')">@include('barbers.create-form')</x-modal>
@if($editing)<x-modal name="edit-barber" title="Edit barber details" :open="true">@include('barbers.edit-form', ['barber'=>$editing])</x-modal>@endif
@foreach($barbers as $barber)
<x-modal :name="'archive-'.$barber->id" :title="'Archive barber · '.$barber->name">
<form method="POST" action="{{ route('barbers.archive', $barber) }}">
    @csrf<input type="hidden" name="_modal" value="archive-{{ $barber->id }}">
    <p>Archive <strong>{{ $barber->name }}</strong> after resignation?</p>
    <p class="muted">This removes the barber from the current team and blocks login and new assignments. Their account, transactions, attendance and payout records stay intact. You can restore the same account if they return.</p>
    <p class="muted">You can archive now, even with active services, open attendance or unclaimed earnings. Those records remain unchanged; archiving does not cancel services, clock anyone out or mark earnings as paid.</p>
    <x-field-error name="archive" />
    <div class="actions"><button type="button" class="secondary-button" x-data @click="$el.closest('dialog').close()">Keep account</button><button type="submit">Archive barber</button></div>
</form>
</x-modal>
@endforeach
@endsection
