<?php

declare(strict_types=1);

namespace Corvant\Domain\Authentication\Services;

use Corvant\Domain\Authentication\Exceptions\SessionNotFoundException;
use Corvant\Domain\Audit\AuditEvents;
use Corvant\Domain\Authentication\ValueObjects\Session;
use Corvant\Ports\AuditLoggerPort;
use Corvant\Ports\SessionStorePort;

final class SessionManagementService
{
    public function __construct(
        private SessionStorePort $sessions,
        private AuditLoggerPort $auditLogger,
    ) {}

    /**
     * @return list<Session>
     */
    public function listSessions(int $userId): array
    {
        return $this->sessions->allForUser($userId);
    }

    public function revokeSession(int $userId, string $sessionId, string $currentToken): void
    {
        foreach ($this->sessions->allForUser($userId) as $session) {
            if ($session->id() === $sessionId) {
                $this->sessions->revoke($session->token());
                $this->auditLogger->log(AuditEvents::SESSION_REVOKED, $userId, null, [
                    'session_id' => $session->id(),
                ]);

                return;
            }
        }

        throw new SessionNotFoundException($sessionId);
    }

    public function revokeOtherSessions(int $userId, string $currentToken): void
    {
        foreach ($this->sessions->allForUser($userId) as $session) {
            if ($session->token() !== $currentToken) {
                $this->sessions->revoke($session->token());
                $this->auditLogger->log(AuditEvents::SESSION_REVOKED, $userId, null, [
                    'session_id' => $session->id(),
                ]);
            }
        }
    }
}
