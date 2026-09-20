<?php

declare(strict_types=1);

namespace Corvant\Infrastructure\Http\Controllers;

use Corvant\Domain\Authentication\Exceptions\EmailAlreadyExistsException;
use Corvant\Domain\Authentication\Exceptions\InvalidCredentialsException;
use Corvant\Domain\Authentication\Exceptions\InvalidEmailException;
use Corvant\Domain\Authentication\Exceptions\InvalidOrExpiredTokenException;
use Corvant\Domain\Authentication\Exceptions\MfaChallengeRequiredException;
use Corvant\Domain\Authentication\Services\AuthenticationService;
use Corvant\Domain\Authentication\ValueObjects\Email;
use Corvant\Infrastructure\Authentication\CurrentUser;
use Corvant\Infrastructure\Http\Requests\ForgotPasswordRequest;
use Corvant\Infrastructure\Http\Requests\LoginRequest;
use Corvant\Infrastructure\Http\Requests\RegisterRequest;
use Corvant\Infrastructure\Http\Requests\ResetPasswordRequest;
use Corvant\Infrastructure\Http\Requests\VerifyEmailRequest;
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
        } catch (MfaChallengeRequiredException $e) {
            return response()->json([
                'mfa_required' => true,
                'challenge_token' => $e->challengeToken(),
            ]);
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

    public function refresh(Request $request): JsonResponse
    {
        $token = $request->bearerToken() ?? $request->input('token');

        if ($token === null || $token === '') {
            return response()->json(['message' => 'Session token is required.'], 401);
        }

        try {
            $session = $this->authentication->refresh($token);
        } catch (InvalidOrExpiredTokenException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        return response()->json([
            'token' => $session->token(),
            'expires_at' => $session->expiresAt()->format(DATE_ATOM),
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $this->authentication->requestPasswordReset(new Email($request->validated('email')));
        } catch (InvalidEmailException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'If an account exists for that email, a password reset link has been sent.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $this->authentication->resetPassword(
                $request->validated('token'),
                $request->validated('password'),
            );
        } catch (InvalidOrExpiredTokenException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Password has been reset.']);
    }

    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        try {
            $this->authentication->verifyEmail($request->validated('token'));
        } catch (InvalidOrExpiredTokenException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Email verified.']);
    }

    public function resendVerification(CurrentUser $currentUser): JsonResponse
    {
        $user = $currentUser->get();
        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $this->authentication->requestEmailVerification($user);

        return response()->json(['message' => 'Verification email sent if your address is not yet verified.']);
    }
}
