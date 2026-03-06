import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';

const formatNumber = (value) => Number(value ?? 0).toLocaleString();

export default function UsageIndex({
    usageOrganization,
    currentPlan,
    monthLabel,
    usageTotals,
    apiCallSummary,
    canRecordTestUsage,
}) {
    const { flash, errors } = usePage().props;
    const usageRows = Object.entries(usageTotals ?? {});

    const recordTestApiCall = () => {
        router.post(route('usage.test-record'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Usage
                </h2>
            }
        >
            <Head title="Usage" />

            <div className="py-12">
                <div className="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
                    {(flash?.success || flash?.warning || flash?.error || flash?.status) && (
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
                            {flash?.status && (
                                <div className="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-700">
                                    {flash.status}
                                </div>
                            )}
                        </div>
                    )}

                    {errors?.usage && (
                        <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {errors.usage}
                        </div>
                    )}

                    <div className="grid gap-6 md:grid-cols-3">
                        <div className="space-y-2 bg-white p-6 shadow sm:rounded-lg dark:bg-gray-800 md:col-span-2">
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Organization
                            </p>
                            <h3 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                {usageOrganization.name}
                            </h3>
                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                Plan: {currentPlan.name}
                            </p>
                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                Month: {monthLabel}
                            </p>
                        </div>

                        <div className="space-y-3 bg-white p-6 shadow sm:rounded-lg dark:bg-gray-800">
                            <h4 className="text-base font-semibold text-gray-900 dark:text-gray-100">
                                Test Action
                            </h4>
                            <button
                                type="button"
                                onClick={recordTestApiCall}
                                disabled={!canRecordTestUsage}
                                className="w-full rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                Record Test API Call
                            </button>
                            {!canRecordTestUsage && (
                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                    Owner or admin role is required.
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="grid gap-6 lg:grid-cols-2">
                        <div className="bg-white p-6 shadow sm:rounded-lg dark:bg-gray-800">
                            <h4 className="text-base font-semibold text-gray-900 dark:text-gray-100">
                                API Call Usage ({monthLabel})
                            </h4>
                            <dl className="mt-4 space-y-2 text-sm">
                                <div className="flex items-center justify-between">
                                    <dt className="text-gray-600 dark:text-gray-300">Used</dt>
                                    <dd className="font-semibold text-gray-900 dark:text-gray-100">
                                        {formatNumber(apiCallSummary.used)}
                                    </dd>
                                </div>
                                <div className="flex items-center justify-between">
                                    <dt className="text-gray-600 dark:text-gray-300">Limit</dt>
                                    <dd className="font-semibold text-gray-900 dark:text-gray-100">
                                        {apiCallSummary.unlimited
                                            ? 'Unlimited'
                                            : formatNumber(apiCallSummary.limit)}
                                    </dd>
                                </div>
                                <div className="flex items-center justify-between">
                                    <dt className="text-gray-600 dark:text-gray-300">
                                        Percent Used
                                    </dt>
                                    <dd className="font-semibold text-gray-900 dark:text-gray-100">
                                        {apiCallSummary.unlimited
                                            ? 'N/A'
                                            : `${apiCallSummary.percent_used ?? 0}%`}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div className="bg-white p-6 shadow sm:rounded-lg dark:bg-gray-800">
                            <h4 className="text-base font-semibold text-gray-900 dark:text-gray-100">
                                Usage-Related Plan Limits
                            </h4>
                            <div className="mt-4 overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead>
                                        <tr>
                                            <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                                Feature
                                            </th>
                                            <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                                Limit
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                        <tr>
                                            <td className="px-3 py-2 text-sm text-gray-800 dark:text-gray-100">
                                                API Calls Per Month
                                            </td>
                                            <td className="px-3 py-2 text-sm text-gray-600 dark:text-gray-300">
                                                {apiCallSummary.unlimited
                                                    ? 'Unlimited'
                                                    : formatNumber(
                                                          apiCallSummary.limit,
                                                      )}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white p-6 shadow sm:rounded-lg dark:bg-gray-800">
                        <h4 className="text-base font-semibold text-gray-900 dark:text-gray-100">
                            Monthly Usage Totals by Event Type
                        </h4>
                        <div className="mt-4 overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Event Type
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Total Quantity
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                    {usageRows.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={2}
                                                className="px-3 py-3 text-sm text-gray-500 dark:text-gray-400"
                                            >
                                                No usage events recorded for this month.
                                            </td>
                                        </tr>
                                    ) : (
                                        usageRows.map(([eventType, total]) => (
                                            <tr key={eventType}>
                                                <td className="px-3 py-2 text-sm text-gray-800 dark:text-gray-100">
                                                    {eventType}
                                                </td>
                                                <td className="px-3 py-2 text-sm text-gray-600 dark:text-gray-300">
                                                    {formatNumber(total)}
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
