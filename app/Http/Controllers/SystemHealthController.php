<?php

namespace App\Http\Controllers;

use App\Models\FailedDomainEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SystemHealthController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('System/Health', [
            'checks' => [
                $this->databaseCheck(),
                $this->cacheCheck(),
                $this->queueCheck(),
                $this->stripeConfigCheck(),
            ],
            'unresolvedFailedDomainEvents' => FailedDomainEvent::query()
                ->whereNull('resolved_at')
                ->count(),
            'generatedAt' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array{key: string, label: string, ok: bool, details: string}
     */
    private function databaseCheck(): array
    {
        try {
            DB::connection()->select('select 1');

            return [
                'key' => 'database',
                'label' => 'Database',
                'ok' => true,
                'details' => sprintf('Connection "%s" reachable.', (string) config('database.default')),
            ];
        } catch (Throwable) {
            return [
                'key' => 'database',
                'label' => 'Database',
                'ok' => false,
                'details' => sprintf('Connection "%s" is not reachable.', (string) config('database.default')),
            ];
        }
    }

    /**
     * @return array{key: string, label: string, ok: bool, details: string}
     */
    private function cacheCheck(): array
    {
        $pingKey = sprintf('platform:health:cache:%s', str_replace('.', '', (string) microtime(true)));

        try {
            Cache::put($pingKey, 'ok', now()->addMinute());
            $ok = Cache::get($pingKey) === 'ok';
            Cache::forget($pingKey);

            return [
                'key' => 'cache',
                'label' => 'Cache/Redis',
                'ok' => $ok,
                'details' => sprintf('Store "%s" is responding.', (string) config('cache.default')),
            ];
        } catch (Throwable) {
            return [
                'key' => 'cache',
                'label' => 'Cache/Redis',
                'ok' => false,
                'details' => sprintf('Store "%s" is not reachable.', (string) config('cache.default')),
            ];
        }
    }

    /**
     * @return array{key: string, label: string, ok: bool, details: string}
     */
    private function queueCheck(): array
    {
        $defaultConnection = (string) config('queue.default');
        $connectionConfig = config("queue.connections.{$defaultConnection}");
        $driver = is_array($connectionConfig) ? (string) ($connectionConfig['driver'] ?? '') : '';
        $configured = $defaultConnection !== '' && is_array($connectionConfig);

        return [
            'key' => 'queue',
            'label' => 'Queue Configuration',
            'ok' => $configured,
            'details' => $configured
                ? sprintf('Connection "%s" (%s) configured.', $defaultConnection, $driver !== '' ? $driver : 'unknown')
                : 'Queue default connection is not configured.',
        ];
    }

    /**
     * @return array{key: string, label: string, ok: bool, details: string}
     */
    private function stripeConfigCheck(): array
    {
        $hasKey = filled(config('cashier.key'));
        $hasSecret = filled(config('cashier.secret'));
        $hasWebhook = filled(config('cashier.webhook.secret'));
        $ok = $hasKey && $hasSecret && $hasWebhook;

        return [
            'key' => 'stripe',
            'label' => 'Stripe Configuration',
            'ok' => $ok,
            'details' => sprintf(
                'key:%s secret:%s webhook:%s',
                $hasKey ? 'present' : 'missing',
                $hasSecret ? 'present' : 'missing',
                $hasWebhook ? 'present' : 'missing'
            ),
        ];
    }
}
