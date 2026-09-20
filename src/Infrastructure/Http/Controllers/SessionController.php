<?php

declare(strict_types=1);

namespace Corvant\Infrastructure\Http\Controllers;

use Corvant\Domain\Authentication\Exceptions\SessionNotFoundException;
use Corvant\Domain\Authentication\Services\SessionManagementService;
use Corvant\Domain\Authentication\ValueObjects\Session;
use Corvant\Infrastructure\Authentication\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class SessionController extends Controller
{
    public function __construct(
        private SessionManagementService $sessions,
    ) {}

    public function index(Request $request, CurrentUser $currentUser): JsonResponse
    {
        $user = $this->requireUser($currentUser);
        $currentToken = $this->requireBearerToken($request);

        $listed = $this->sessions->listSessions($user->id());

        return response()->json([
            'data' => array_map(
                fn (Session $session) => $this->sessionPayload($session, $currentToken),
                $listed,
            ),
        ]);
    }

    public function destroy(string $id, Request $request, CurrentUser $currentUser): JsonResponse
    {
        $user = $this->requireUser($currentUser);
        $currentToken = $this->requireBearerToken($request);

        try {
            $this->sessions->revokeSession($user->id(), $id, $currentToken);
        } catch (SessionNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json(['message' => 'Session revoked.']);
    }

    public function destroyOthers(Request $request, CurrentUser $currentUser): JsonResponse
    {
        $user = $this->requireUser($currentUser);
        $currentToken = $this->requireBearerToken($request);

        $this->sessions->revokeOtherSessions($user->id(), $currentToken);

        return response()->json(['message' => 'Other sessions revoked.']);
    }

    /**
     * @return array{id: string, expires_at: string, is_current: bool}
     */
    private function sessionPayload(Session $session, string $currentToken): array
    {
        return [
            'id' => $session->id(),
            'expires_at' => $session->expiresAt()->format(DATE_ATOM),
            'is_current' => hash_equals($session->token(), $currentToken),
        ];
    }

    private function requireUser(CurrentUser $currentUser): \Corvant\Domain\Authentication\Entities\User
    {
        $user = $currentUser->get();
        if ($user === null || $user->id() === null) {
            abort(401, 'Unauthenticated.');
        }

        return $user;
    }

    private function requireBearerToken(Request $request): string
    {
        $token = $request->bearerToken() ?? $request->input('token');

        if ($token === null || $token === '') {
            abort(401, 'Unauthenticated.');
        }

        return $token;
    }
}
