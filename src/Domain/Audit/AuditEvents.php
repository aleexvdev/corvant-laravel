<?php

declare(strict_types=1);

namespace Corvant\Domain\Audit;

final class AuditEvents
{
    public const string LOGIN = 'auth.login';

    public const string LOGOUT = 'auth.logout';

    public const string PASSWORD_CHANGED = 'auth.password_changed';

    public const string ROLE_ASSIGNED = 'rbac.role_assigned';

    public const string ROLE_REVOKED = 'rbac.role_revoked';

    public const string MFA_ENABLED = 'mfa.enabled';

    public const string MFA_DISABLED = 'mfa.disabled';

    public const string SESSION_REVOKED = 'session.revoked';
}
