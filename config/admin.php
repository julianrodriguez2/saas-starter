<?php

return [
    'super_admin_emails' => array_values(array_filter(
        array_map(
            static fn (string $email): string => strtolower(trim($email)),
            explode(',', (string) env('SUPER_ADMIN_EMAILS', ''))
        ),
        static fn (string $email): bool => $email !== ''
    )),
];
