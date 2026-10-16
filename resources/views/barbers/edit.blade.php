@extends('layouts.shop', ['heading' => 'Edit barber details'])
@section('module')
<section class="panel">
<p class="muted">Update the managed barber account without changing its role or historical transaction identity. Use the existing status and password controls separately.</p>
<form method="POST" action="{{ route('barbers.update', $barber) }}">
@csrf @method('PUT')
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
<div><label for="name">Full name</label><input id="name" name="name" value="{{ old('name',$barber->user->name) }}" maxlength="255" required></div>
<div><label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email',$barber->user->email) }}" maxlength="255" required><p class="muted">New email addresses must use @crisbarber.com. An unchanged legacy address can be retained.</p></div>
<div><label for="phone_number">Phone (optional)</label><input id="phone_number" name="phone_number" type="tel" value="{{ old('phone_number',$barber->phone_number) }}" maxlength="20"></div>
<div><label for="hired_date">Hired date</label><input id="hired_date" name="hired_date" type="date" value="{{ old('hired_date',$barber->hired_date) }}" max="{{ today()->format('Y-m-d') }}" required></div>
</div>
<div class="actions"><button>Save barber details</button><a href="{{ route('barbers.index') }}">Cancel</a></div>
</form>
</section>
@endsection
