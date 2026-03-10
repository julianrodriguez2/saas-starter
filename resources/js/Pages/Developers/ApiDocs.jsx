import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

const usageExampleRequest = `curl -X POST "$BASE_URL/api/v1/usage-events" \\
  -H "Authorization: Bearer sk_xxxxx_xxxxx" \\
  -H "Content-Type: application/json" \\
  -d '{
    "event_type": "api_call",
    "quantity": 1,
    "metadata": { "endpoint": "/v1/jobs" },
    "idempotency_key": "evt-12345"
  }'`;

const usageExampleResponse = `{
  "success": true,
  "usage_event_id": 42,
  "event_type": "api_call",
  "quantity": 1
}`;

const entitlementRequest = `curl -X POST "$BASE_URL/api/v1/entitlements/check" \\
  -H "Authorization: Bearer sk_xxxxx_xxxxx" \\
  -H "Content-Type: application/json" \\
  -d '{
    "feature": "api_calls_per_month",
    "current_value": 150
  }'`;

const entitlementResponse = `{
  "success": true,
  "feature": "api_calls_per_month",
  "allowed": true,
  "limit": 1000,
  "unlimited": false,
  "current_value": 150
}`;

export default function ApiDocs({ docsOrganization, baseUrl }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Developer API
                </h2>
            }
        >
            <Head title="Developer API" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            API Access for {docsOrganization.name}
                        </h3>
                        <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                            Create API keys from Settings {'>'} API Keys. Keys are
                            shown only once.
                        </p>
                        <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                            Base URL: <code>{baseUrl}</code>
                        </p>
                    </div>

                    <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Authentication
                        </h3>
                        <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                            Use a Bearer token header:
                        </p>
                        <pre className="mt-3 overflow-x-auto rounded bg-gray-900 p-4 text-xs text-gray-100">
                            <code>Authorization: Bearer sk_xxxxx_xxxxx</code>
                        </pre>
                        <p className="mt-3 text-sm text-gray-600 dark:text-gray-300">
                            API routes are rate limited per organization (60
                            requests/minute).
                        </p>
                    </div>

                    <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            POST /api/v1/usage-events
                        </h3>
                        <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                            Records usage for your organization and enforces plan
                            limits. Send `idempotency_key` to avoid duplicate usage
                            on retries.
                        </p>
                        <pre className="mt-3 overflow-x-auto rounded bg-gray-900 p-4 text-xs text-gray-100">
                            <code>
                                {usageExampleRequest.replaceAll('$BASE_URL', baseUrl)}
                            </code>
                        </pre>
                        <pre className="mt-3 overflow-x-auto rounded bg-gray-900 p-4 text-xs text-gray-100">
                            <code>{usageExampleResponse}</code>
                        </pre>
                    </div>

                    <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            POST /api/v1/entitlements/check
                        </h3>
                        <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                            Checks if a feature is allowed for the current plan.
                            Include `current_value` for limit checks.
                        </p>
                        <pre className="mt-3 overflow-x-auto rounded bg-gray-900 p-4 text-xs text-gray-100">
                            <code>
                                {entitlementRequest.replaceAll('$BASE_URL', baseUrl)}
                            </code>
                        </pre>
                        <pre className="mt-3 overflow-x-auto rounded bg-gray-900 p-4 text-xs text-gray-100">
                            <code>{entitlementResponse}</code>
                        </pre>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
