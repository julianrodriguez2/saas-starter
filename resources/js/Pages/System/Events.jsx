import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';

const truncate = (value, max = 140) => {
    if (!value) {
        return '';
    }

    return value.length > max ? `${value.slice(0, max)}...` : value;
};

export default function SystemEvents({ failedEvents = [] }) {
    const { flash } = usePage().props;

    const resolveEvent = (failedEventId) => {
        router.post(route('system.events.resolve', failedEventId));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    System Event Diagnostics
                </h2>
            }
        >
            <Head title="System Event Diagnostics" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {(flash?.success || flash?.error || flash?.warning) && (
                        <div className="space-y-3">
                            {flash?.success && (
                                <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                                    {flash.success}
                                </div>
                            )}
                            {flash?.warning && (
                                <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                                    {flash.warning}
                                </div>
                            )}
                            {flash?.error && (
                                <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                    {flash.error}
                                </div>
                            )}
                        </div>
                    )}

                    <div className="bg-white p-6 shadow sm:rounded-lg dark:bg-gray-800">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Source
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Event Type
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Event Key
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Failed At
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Status
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Error
                                        </th>
                                        <th className="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                    {failedEvents.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="px-3 py-3 text-sm text-gray-500 dark:text-gray-400"
                                            >
                                                No failed domain events found.
                                            </td>
                                        </tr>
                                    ) : (
                                        failedEvents.map((failedEvent) => {
                                            const isResolved = Boolean(
                                                failedEvent.resolved_at,
                                            );

                                            return (
                                                <tr key={failedEvent.id}>
                                                    <td className="px-3 py-2 text-sm text-gray-700 dark:text-gray-200">
                                                        {failedEvent.source}
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-gray-700 dark:text-gray-200">
                                                        {failedEvent.event_type || '-'}
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                                                        {failedEvent.event_key || '-'}
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                                                        {failedEvent.failed_at
                                                            ? new Date(
                                                                  failedEvent.failed_at,
                                                              ).toLocaleString()
                                                            : '-'}
                                                    </td>
                                                    <td className="px-3 py-2 text-sm">
                                                        <span
                                                            className={
                                                                isResolved
                                                                    ? 'rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700'
                                                                    : 'rounded-full bg-red-100 px-2 py-1 text-xs font-semibold text-red-700'
                                                            }
                                                        >
                                                            {isResolved
                                                                ? 'resolved'
                                                                : 'unresolved'}
                                                        </span>
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                                                        {truncate(
                                                            failedEvent.error_message,
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-2 text-right">
                                                        {!isResolved && (
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    resolveEvent(
                                                                        failedEvent.id,
                                                                    )
                                                                }
                                                                className="rounded-md border border-transparent bg-indigo-600 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-white transition hover:bg-indigo-500"
                                                            >
                                                                Resolve
                                                            </button>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
