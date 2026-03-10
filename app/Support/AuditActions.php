<?php

namespace App\Support;

class AuditActions
{
    public const ORGANIZATION_CREATED = 'organization.created';
    public const ORGANIZATION_SUSPENDED = 'organization.suspended';
    public const ORGANIZATION_UNSUSPENDED = 'organization.unsuspended';
    public const ORGANIZATION_IMPERSONATION_STARTED = 'organization.impersonation_started';
    public const ORGANIZATION_IMPERSONATION_STOPPED = 'organization.impersonation_stopped';
    public const MEMBER_INVITED = 'member.invited';
    public const MEMBER_REMOVED = 'member.removed';
    public const MEMBER_ROLE_UPDATED = 'member.role_updated';
    public const BILLING_CHECKOUT_STARTED = 'billing.checkout_started';
    public const BILLING_PORTAL_OPENED = 'billing.portal_opened';
    public const BILLING_SUBSCRIPTION_SYNCED = 'billing.subscription_synced';
    public const USAGE_RECORDED = 'usage.recorded';
    public const API_KEY_CREATED = 'api_key.created';
    public const API_KEY_REVOKED = 'api_key.revoked';
    public const AUTH_REGISTERED = 'auth.registered';
    public const AUTH_LOGIN = 'auth.login';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::ORGANIZATION_CREATED,
            self::ORGANIZATION_SUSPENDED,
            self::ORGANIZATION_UNSUSPENDED,
            self::ORGANIZATION_IMPERSONATION_STARTED,
            self::ORGANIZATION_IMPERSONATION_STOPPED,
            self::MEMBER_INVITED,
            self::MEMBER_REMOVED,
            self::MEMBER_ROLE_UPDATED,
            self::BILLING_CHECKOUT_STARTED,
            self::BILLING_PORTAL_OPENED,
            self::BILLING_SUBSCRIPTION_SYNCED,
            self::USAGE_RECORDED,
            self::API_KEY_CREATED,
            self::API_KEY_REVOKED,
            self::AUTH_REGISTERED,
            self::AUTH_LOGIN,
        ];
    }
}
