<?php

namespace App\Domain\Auth\Services;

use App\Domain\User\Models\User;
use Illuminate\Support\Facades\Hash;

class RegisterService
{
    /**
     * Register a new user.
     *
     * @param array<string, mixed> $data
     * @return User
     */
    public function register(array $data): User
    {
        return User::create([
            'name' => $data['email'], // Using email as default name, can be updated later
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
    }
}


