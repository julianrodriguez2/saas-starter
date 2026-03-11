<?php

$parseIntegerList = static function (string $value, array $default): array {
    $values = array_values(array_filter(
        array_map(
            static function (string $segment): ?int {
                $segment = trim($segment);

                if ($segment === '' || ! is_numeric($segment)) {
                    return null;
                }

                return max((int) $segment, 1);
            },
            explode(',', $value)
        ),
        static fn (?int $number): bool => $number !== null
    ));

    return $values === [] ? $default : $values;
};

return [
    'super_admin_emails' => array_values(array_filter(
        array_map(
            static fn (string $email): string => strtolower(trim($email)),
            explode(',', (string) env('SUPER_ADMIN_EMAILS', ''))
        ),
        static fn (string $email): bool => $email !== ''
    )),

    'cache' => [
        'organization_summary_ttl_seconds' => max((int) env('PLATFORM_ORG_SUMMARY_CACHE_TTL', 60), 1),
        'organization_access_ttl_seconds' => max((int) env('PLATFORM_ORG_ACCESS_CACHE_TTL', 30), 1),
        'user_organizations_ttl_seconds' => max((int) env('PLATFORM_USER_ORGS_CACHE_TTL', 60), 1),
        'plan_limits_ttl_seconds' => max((int) env('PLATFORM_PLAN_LIMITS_CACHE_TTL', 300), 1),
        'usage_monthly_ttl_seconds' => max((int) env('PLATFORM_USAGE_MONTHLY_CACHE_TTL', 120), 1),
        'member_count_ttl_seconds' => max((int) env('PLATFORM_MEMBER_COUNT_CACHE_TTL', 120), 1),
    ],

    'rate_limits' => [
        'organization_api_per_minute' => max((int) env('ORG_API_RATE_LIMIT_PER_MINUTE', 60), 1),
        'member_invites_per_minute' => max((int) env('MEMBER_INVITE_RATE_LIMIT_PER_MINUTE', 20), 1),
        'api_key_creations_per_minute' => max((int) env('API_KEY_CREATE_RATE_LIMIT_PER_MINUTE', 10), 1),
        'billing_checkout_per_minute' => max((int) env('BILLING_CHECKOUT_RATE_LIMIT_PER_MINUTE', 5), 1),
    ],

    'suspension' => [
        'block_writes' => filter_var(
            env('SUSPENSION_BLOCK_WRITES', true),
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE
        ) ?? true,
    ],

    'queue' => [
        'process_usage_event' => [
            'tries' => max((int) env('PROCESS_USAGE_EVENT_TRIES', 3), 1),
            'timeout_seconds' => max((int) env('PROCESS_USAGE_EVENT_TIMEOUT', 60), 1),
            'backoff_seconds' => $parseIntegerList(
                (string) env('PROCESS_USAGE_EVENT_BACKOFF', '30,60,120'),
                [30, 60, 120]
            ),
        ],
    ],
];
