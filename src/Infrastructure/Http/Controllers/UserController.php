<?php

declare(strict_types=1);

namespace Corvant\Infrastructure\Http\Controllers;

use Corvant\Domain\Authentication\Entities\User;
use Corvant\Domain\Authentication\Exceptions\EmailAlreadyExistsException;
use Corvant\Domain\Authentication\Exceptions\InvalidCredentialsException;
use Corvant\Domain\Authentication\Exceptions\InvalidEmailException;
use Corvant\Domain\Authentication\Exceptions\InvalidOrExpiredTokenException;
use Corvant\Domain\Audit\Entities\AuditLogEntry;
use Corvant\Domain\Authentication\Services\AuthenticationService;
use Corvant\Ports\AuditLoggerPort;
use Corvant\Domain\Authentication\ValueObjects\Email;
use Corvant\Infrastructure\Authentication\CurrentUser;
use Corvant\Infrastructure\Http\Requests\ConfirmEmailChangeRequest;
use Corvant\Infrastructure\Http\Requests\UpdateEmailRequest;
use Corvant\Infrastructure\Http\Requests\UpdatePasswordRequest;
use Corvant\Infrastructure\Http\Requests\UpdatePhoneRequest;
use Corvant\Infrastructure\Http\Requests\UpdateProfileRequest;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Locale;

final class UserController extends Controller
{
    public function __construct(
        private AuthenticationService $authentication,
        private AuditLoggerPort $auditLogger,
    ) {}

    public function me(CurrentUser $currentUser): JsonResponse
    {
        $user = $this->requireUser($currentUser);

        return response()->json($this->userPayload($user));
    }

    public function update(UpdateProfileRequest $request, CurrentUser $currentUser): JsonResponse
    {
        $user = $this->requireUser($currentUser);

        $locale = $request->validated('locale');
        $timezone = $request->validated('timezone');

        if ($locale !== null && $locale !== '' && ! $this->isValidLocale($locale)) {
            return response()->json(['message' => 'Invalid locale.'], 422);
        }

        if ($timezone !== null && $timezone !== '' && ! $this->isValidTimezone($timezone)) {
            return response()->json(['message' => 'Invalid timezone.'], 422);
        }

        $updated = $this->authentication->updateProfile(
            $user,
            $request->validated('name'),
            $request->validated('avatar_url'),
            $locale,
            $timezone,
        );

        return response()->json($this->userPayload($updated));
    }

    public function updateEmail(UpdateEmailRequest $request, CurrentUser $currentUser): JsonResponse
    {
        $user = $this->requireUser($currentUser);

        try {
            $this->authentication->requestEmailChange($user, new Email($request->validated('email')));
        } catch (InvalidEmailException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (EmailAlreadyExistsException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Confirmation link sent to the new email address if it is available.',
        ]);
    }

    public function confirmEmail(ConfirmEmailChangeRequest $request, CurrentUser $currentUser): JsonResponse
    {
        $user = $this->requireUser($currentUser);

        try {
            $updated = $this->authentication->confirmEmailChange($user, $request->validated('token'));
        } catch (InvalidOrExpiredTokenException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->userPayload($updated));
    }

    public function updatePhone(UpdatePhoneRequest $request, CurrentUser $currentUser): JsonResponse
    {
        $user = $this->requireUser($currentUser);

        $updated = $this->authentication->updatePhone($user, $request->validated('phone'));

        return response()->json($this->userPayload($updated));
    }

    public function updatePassword(UpdatePasswordRequest $request, CurrentUser $currentUser): JsonResponse
    {
        $user = $this->requireUser($currentUser);

        try {
            $this->authentication->changePassword(
                $user,
                $request->validated('current_password'),
                $request->validated('password'),
            );
        } catch (InvalidCredentialsException) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        return response()->json(['message' => 'Password updated.']);
    }

    public function audit(CurrentUser $currentUser): JsonResponse
    {
        $user = $this->requireUser($currentUser);

        $entries = $this->auditLogger->forUser($user->id());

        return response()->json([
            'data' => array_map(
                fn (AuditLogEntry $entry) => $this->auditPayload($entry),
                $entries,
            ),
        ]);
    }

    public function destroy(Request $request, CurrentUser $currentUser): JsonResponse
    {
        $user = $this->requireUser($currentUser);

        $token = $request->bearerToken() ?? $request->input('token');
        if ($token === null || $token === '') {
            return response()->json(['message' => 'Session token is required.'], 401);
        }

        $this->authentication->deleteAccount($user, $token);

        return response()->json(['message' => 'Account deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function auditPayload(AuditLogEntry $entry): array
    {
        return [
            'id' => $entry->id(),
            'event' => $entry->event(),
            'tenant_id' => $entry->tenantId(),
            'metadata' => $entry->metadata(),
            'occurred_at' => $entry->occurredAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id(),
            'email' => $user->email()->value(),
            'name' => $user->name(),
            'email_verified_at' => $user->emailVerifiedAt()?->format(DATE_ATOM),
            'pending_email' => $user->pendingEmail()?->value(),
            'avatar_url' => $user->avatarUrl(),
            'locale' => $user->locale(),
            'timezone' => $user->timezone(),
            'phone' => $user->phone(),
        ];
    }

    private function requireUser(CurrentUser $currentUser): User
    {
        $user = $currentUser->get();
        if ($user === null || $user->id() === null) {
            abort(401, 'Unauthenticated.');
        }

        return $user;
    }

    private function isValidLocale(string $locale): bool
    {
        $parsed = Locale::parseLocale(str_replace('-', '_', $locale));

        return isset($parsed['language']) && $parsed['language'] !== '';
    }

    private function isValidTimezone(string $timezone): bool
    {
        return in_array($timezone, DateTimeZone::listIdentifiers(), true);
    }
}
