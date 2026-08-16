<?php

namespace App\Admin\Infrastructure\Middleware;

use App\Admin\Domain\Service\IsAdminUserService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function __construct(
        private IsAdminUserService $isAdminUserService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || !$this->isAdminUserService->execute((int) $user->id)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
