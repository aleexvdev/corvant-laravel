<?php

declare(strict_types=1);

namespace Corvant\Domain\Audit;

final class AuditEvents
{
    public const LOGIN = 'auth.login';

    public const LOGOUT = 'auth.logout';

    public const PASSWORD_CHANGED = 'auth.password_changed';

    public const ROLE_ASSIGNED = 'rbac.role_assigned';

    public const ROLE_REVOKED = 'rbac.role_revoked';

    public const MFA_ENABLED = 'mfa.enabled';

    public const MFA_DISABLED = 'mfa.disabled';

    public const SESSION_REVOKED = 'session.revoked';
}
