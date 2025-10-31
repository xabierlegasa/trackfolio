<?php

namespace App\Auth\Controllers;

use App\Auth\Requests\LoginRequest;
use App\Auth\Requests\RegisterRequest;
use App\Auth\Services\LoginService;
use App\Auth\Services\RegisterService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(
        private readonly RegisterService $registerService,
        private readonly LoginService $loginService
    ) {}

    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->registerService->register($request->validated());

        return response()->json([
            'message' => 'User registered successfully',
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
            ],
        ], 201);
    }

    /**
     * Authenticate a user and log them in.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->loginService->login($request->validated());

        return response()->json([
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
            ],
        ]);
    }
}

