<?php

declare(strict_types=1);

namespace Corvant\Infrastructure\Http\Middleware;

use Closure;
use Corvant\Infrastructure\Authentication\CurrentUser;
use Corvant\Ports\SessionStorePort;
use Corvant\Ports\UserRepositoryPort;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateSessionMiddleware
{
    public function __construct(
        private SessionStorePort $sessions,
        private UserRepositoryPort $users,
        private CurrentUser $currentUser,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $session = $this->sessions->find($token);
        if ($session === null || $session->expiresAt() < new DateTimeImmutable()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = $this->users->findById($session->userId());
        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $this->currentUser->set($user);

        return $next($request);
    }
}
