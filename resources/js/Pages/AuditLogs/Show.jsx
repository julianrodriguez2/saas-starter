import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const formatDateTime = (value) => {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString();
};

const prettyJson = (value) => {
    try {
        return JSON.stringify(value ?? {}, null, 2);
    } catch {
        return '{}';
    }
};

const formatActor = (actor) => {
    if (!actor) {
        return 'system';
    }

    if (actor.name) {
        return `${actor.name} (${actor.type || 'user'})`;
    }

    return actor.type || 'system';
};

export default function AuditLogShow({ auditOrganization, auditLog }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Audit Log Detail
                </h2>
            }
        >
            <Head title="Audit Log Detail" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                        <div className="flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                {auditOrganization.name}
                            </h3>
                            <Link
                                href={route('audit-logs.index')}
                                className="text-sm font-semibold text-indigo-600 hover:text-indigo-500"
                            >
                                Back to Audit Logs
                            </Link>
                        </div>

                        <dl className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <dt className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Action
                                </dt>
                                <dd className="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    {auditLog.action}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Timestamp
                                </dt>
                                <dd className="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    {formatDateTime(auditLog.created_at)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Actor
                                </dt>
                                <dd className="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    {formatActor(auditLog.actor)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Target
                                </dt>
                                <dd className="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    {auditLog.target_type || '-'}{' '}
                                    {auditLog.target_id ? `(${auditLog.target_id})` : ''}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    IP Address
                                </dt>
                                <dd className="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    {auditLog.ip_address || '-'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    User Agent
                                </dt>
                                <dd className="mt-1 break-all text-sm text-gray-900 dark:text-gray-100">
                                    {auditLog.user_agent || '-'}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Metadata
                        </h3>
                        <pre className="mt-4 overflow-x-auto rounded bg-gray-900 p-4 text-xs text-gray-100">
                            <code>{prettyJson(auditLog.metadata)}</code>
                        </pre>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
