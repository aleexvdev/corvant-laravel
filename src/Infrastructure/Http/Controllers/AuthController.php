<?php

declare(strict_types=1);

namespace Corvant\Infrastructure\Http\Controllers;

use Corvant\Domain\Authentication\Exceptions\EmailAlreadyExistsException;
use Corvant\Domain\Authentication\Exceptions\InvalidCredentialsException;
use Corvant\Domain\Authentication\Exceptions\InvalidEmailException;
use Corvant\Domain\Authentication\Services\AuthenticationService;
use Corvant\Domain\Authentication\ValueObjects\Email;
use Corvant\Infrastructure\Http\Requests\LoginRequest;
use Corvant\Infrastructure\Http\Requests\RegisterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class AuthController extends Controller
{
    public function __construct(
        private AuthenticationService $authentication,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $user = $this->authentication->register(
                new Email($request->validated('email')),
                $request->validated('password'),
                $request->validated('name'),
            );
        } catch (InvalidEmailException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (EmailAlreadyExistsException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $user->id(),
            'email' => $user->email()->value(),
            'name' => $user->name(),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $session = $this->authentication->login(
                new Email($request->validated('email')),
                $request->validated('password'),
            );
        } catch (InvalidEmailException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (InvalidCredentialsException) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        return response()->json([
            'token' => $session->token(),
            'expires_at' => $session->expiresAt()->format(DATE_ATOM),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken() ?? $request->input('token');

        if ($token === null || $token === '') {
            return response()->json(['message' => 'Session token is required.'], 401);
        }

        $this->authentication->logout($token);

        return response()->json(['message' => 'Logged out.']);
    }
}
