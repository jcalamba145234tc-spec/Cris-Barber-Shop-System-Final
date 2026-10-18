<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Rules\ShopEmailDomain;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ProvisionAdmin extends Command
{
    protected $signature = 'shop:admin {email} {--name= : Administrator name}';

    protected $description = 'Owner-only administrator provisioning; password is entered securely, never as a command argument';

    public function handle(): int
    {
        $data = [
            'name' => $this->option('name') ?: $this->ask('Administrator name'),
            'email' => $this->argument('email'),
            'password' => $this->secret('Password (minimum 12 characters)'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', new ShopEmailDomain, 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        unset($data['password_confirmation']);
        User::create($data + ['role' => 'admin']);
        $this->info('Administrator provisioned. No password was printed or stored in plaintext.');

        return self::SUCCESS;
    }
}
