<?php

namespace App\User\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Get the authenticated user's account information.
     */
    public function account(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'email' => $user->email,
            'name' => $user->name,
        ]);
    }
}

