import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

const formatDateTime = (value) => {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString();
};

export default function SystemHealth({
    checks = [],
    unresolvedFailedDomainEvents = 0,
    generatedAt,
}) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    System Health
                </h2>
            }
        >
            <Head title="System Health" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div className="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                            <p className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Unresolved Failed Events
                            </p>
                            <p className="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                {unresolvedFailedDomainEvents}
                            </p>
                        </div>
                        <div className="rounded-lg bg-white p-5 shadow dark:bg-gray-800 md:col-span-2">
                            <p className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Report Generated
                            </p>
                            <p className="mt-2 text-sm text-gray-700 dark:text-gray-200">
                                {formatDateTime(generatedAt)}
                            </p>
                        </div>
                    </div>

                    <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Check
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Status
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Details
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                    {checks.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={3}
                                                className="px-3 py-3 text-sm text-gray-500 dark:text-gray-400"
                                            >
                                                No health checks available.
                                            </td>
                                        </tr>
                                    ) : (
                                        checks.map((check) => (
                                            <tr key={check.key}>
                                                <td className="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                    {check.label}
                                                </td>
                                                <td className="px-3 py-2 text-sm">
                                                    <span
                                                        className={
                                                            check.ok
                                                                ? 'rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700'
                                                                : 'rounded-full bg-red-100 px-2 py-1 text-xs font-semibold text-red-700'
                                                        }
                                                    >
                                                        {check.ok
                                                            ? 'healthy'
                                                            : 'issue'}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2 text-sm text-gray-600 dark:text-gray-300">
                                                    {check.details}
                                                </td>
                                            </tr>
                                        ))
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
