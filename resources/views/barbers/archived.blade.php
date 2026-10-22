@extends('layouts.shop', ['heading' => 'Archived barbers'])
@section('module')
<section class="panel">
    <div class="panel-heading actions"><h2>Former team members</h2><a class="button secondary-button" href="{{ route('barbers.index') }}">Back to barbers</a></div>
    <p class="muted">Archived accounts cannot sign in or receive new services. Transaction, attendance and payout history stays linked to the same barber. No permanent deletion is available.</p>
    <form class="report-filters" method="GET">
        <div><label for="q">Search archived barbers</label><input id="q" name="q" maxlength="100" value="{{ request('q') }}" placeholder="Name or login email"></div>
        <button>Search</button><a href="{{ route('barbers.archived') }}">Reset</a>
    </form>
    @if($barbers->isEmpty())
        <p>No archived barbers match your search.</p>
    @else
    <div class="table-scroll" role="region" aria-label="Archived barber accounts" tabindex="0">
        <table>
            <thead><tr><th>Barber / login</th><th>Archived on</th><th>History</th><th>Account action</th></tr></thead>
            <tbody>@foreach($barbers as $barber)
                <tr>
                    <td><strong>{{ $barber->name }}</strong><br><span class="muted">{{ $barber->user?->email }}</span><br><span class="badge badge-warning">Archived · login blocked</span></td>
                    <td>{{ $barber->archived_at->format('M j, Y') }}<br><span class="muted">{{ $barber->archived_at->format('g:i A') }} · Asia/Manila</span><br><span class="muted">By {{ $barber->archivedBy?->name ?? 'Administrator' }}</span></td>
                    <td><a href="{{ route('transactions.index', ['barber_id' => $barber->id]) }}">View transactions</a><br><span class="muted">{{ $barber->transactions_count }} recorded transactions</span></td>
                    <td><button type="button" class="secondary-button" x-data @click="$dispatch('open-modal', 'restore-{{ $barber->id }}')">Restore account</button></td>
                </tr>
            @endforeach</tbody>
        </table>
    </div>
    @endif
    {{ $barbers->links() }}
</section>
@foreach($barbers as $barber)
<x-modal :name="'restore-'.$barber->id" :title="'Restore account · '.$barber->name">
    <form method="POST" action="{{ route('barbers.restore', $barber) }}">
        @csrf<input type="hidden" name="_modal" value="restore-{{ $barber->id }}">
        <p>Welcome <strong>{{ $barber->name }}</strong> back to the team.</p>
        <p class="muted">Restore the same login, barber identity and all history. The password is unchanged. The account will be active but unavailable for new services until you select Available on the Barbers page.</p>
        <x-field-error name="confirmation" />
        <label><input type="checkbox" name="confirmation" value="1" required> I confirm this account should be restored.</label>
        <div class="actions"><button type="button" class="secondary-button" x-data @click="$el.closest('dialog').close()">Keep archived</button><button type="submit">Restore account</button></div>
    </form>
</x-modal>
@endforeach
@endsection
