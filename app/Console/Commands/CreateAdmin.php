<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('admin:create {--name=} {--email=}')]
#[Description('Create an admin account for the dashboard')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $data = [
            'name' => $this->option('name') ?: text('Name', required: true),
            'email' => mb_strtolower($this->option('email') ?: text('Email', required: true)),
            'password' => password('Password (8+ characters, letters and numbers)', required: true),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create($data);
        $this->info("Admin {$data['email']} created.");

        return self::SUCCESS;
    }
}
