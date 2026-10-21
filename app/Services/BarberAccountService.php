<?php

namespace App\Services;

use App\Models\Barber;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BarberAccountService
{
    public function availableEmail(string $first, string $last): string
    {
        $clean = fn (string $name): string => preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii($name)));
        $stem = substr($clean($first) ?: 'barber', 0, 24).'.'.substr($clean($last) ?: 'staff', 0, 24);
        $suffix = 1;
        do {
            $email = $stem.($suffix === 1 ? '' : $suffix).'@crisbarbershop.local';
            $suffix++;
        } while (User::where('email', $email)->exists());

        return $email;
    }

    public function create(array $data): User
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return DB::transaction(function () use ($data): User {
                    $first = Str::squish($data['first_name']);
                    $last = Str::squish($data['last_name']);
                    $user = User::create([
                        'name' => $first.' '.$last,
                        'email' => $this->availableEmail($first, $last),
                        'role' => 'barber', 'password' => $data['password'],
                    ]);
                    Barber::create(['user_id' => $user->id, 'first_name' => $first, 'last_name' => $last,
                        'hired_date' => today(), 'status' => 'active']);

                    return $user;
                }, 3);
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 4) {
                    throw ValidationException::withMessages(['first_name' => 'Account creation conflicted with another request. Please retry.']);
                }
            }
        }
        throw new \LogicException('Account creation attempts exhausted.');
    }
}
