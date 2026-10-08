@extends('layouts.shop', ['heading' => $customer->exists ? 'Edit customer' : 'Add customer'])
@section('module')
<section class="panel"><form method="POST" action="{{ $customer->exists ? route('customers.update',$customer) : route('customers.store') }}">@csrf @if($customer->exists) @method('PUT') @endif
<label for="name">Name</label><input id="name" name="name" maxlength="255" value="{{ old('name',$customer->name) }}" required>
<label for="phone">Phone (optional)</label><input id="phone" name="phone" maxlength="30" value="{{ old('phone',$customer->phone) }}">
<label for="email">Email (optional)</label><input id="email" name="email" type="email" maxlength="255" value="{{ old('email',$customer->email) }}">
<div class="actions"><button>Save customer</button><a href="{{ route('customers.index') }}">Cancel</a></div></form></section>
@endsection
