<?php

namespace App\User\Infrastructure\Repository;

use App\User\Domain\Entity\User;
use Illuminate\Support\Facades\Hash;

class UserRepository
{
    /**
     * Create a new user.
     *
     * @param array<string, mixed> $data
     * @return User
     */
    public function create(array $data): User
    {
        return User::create([
            'name' => $data['email'], // Using email as default name, can be updated later
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
    }
}

