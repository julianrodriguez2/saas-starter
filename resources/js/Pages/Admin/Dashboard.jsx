import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';

const metricCards = [
    {
        key: 'total_organizations',
        label: 'Total Organizations',
    },
    {
        key: 'active_paid_organizations',
        label: 'Active Paid Organizations',
    },
    {
        key: 'free_organizations',
        label: 'Free Organizations',
    },
    {
        key: 'suspended_organizations',
        label: 'Suspended Organizations',
    },
    {
        key: 'total_users',
        label: 'Total Users',
    },
    {
        key: 'unresolved_failed_domain_events',
        label: 'Unresolved Failed Events',
    },
];

export default function AdminDashboard({ metrics = {} }) {
    const { flash } = usePage().props;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Admin Dashboard
                </h2>
            }
        >
            <Head title="Admin Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {(flash?.success || flash?.warning || flash?.error) && (
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

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {metricCards.map((metric) => (
                            <div
                                key={metric.key}
                                className="rounded-lg bg-white p-5 shadow dark:bg-gray-800"
                            >
                                <p className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {metric.label}
                                </p>
                                <p className="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    {metrics?.[metric.key] ?? 0}
                                </p>
                            </div>
                        ))}
                    </div>

                    <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Quick Actions
                        </h3>
                        <div className="mt-4 flex flex-wrap gap-3">
                            <Link
                                href={route('admin.organizations.index')}
                                className="rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-white transition hover:bg-indigo-500"
                            >
                                Manage Organizations
                            </Link>
                            <Link
                                href={route('system.events.index')}
                                className="rounded-md border border-gray-300 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                            >
                                View System Events
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
