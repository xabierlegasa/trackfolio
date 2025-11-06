<?php

namespace App\Auth\Service;

use App\User\Domain\Entity\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginService
{
    /**
     * Attempt to authenticate a user.
     *
     * @param array<string, mixed> $credentials
     * @return User
     * @throws ValidationException
     */
    public function login(array $credentials): User
    {
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Log in the user (creates session)
        Auth::login($user);

        return $user;
    }
}

