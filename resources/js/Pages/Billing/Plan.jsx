import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

const formatLimit = (value) => {
    if (value === null || value === undefined) {
        return 'Unlimited';
    }

    if (typeof value === 'boolean') {
        return value ? 'Enabled' : 'Disabled';
    }

    return value.toLocaleString();
};

export default function Plan({ organization, plan }) {
    const limits = plan?.limits ?? {};

    const featureRows = [
        { label: 'Team Members', value: limits.team_members },
        { label: 'Projects', value: limits.projects },
        { label: 'API Calls Per Month', value: limits.api_calls_per_month },
        { label: 'Advanced Features', value: limits.advanced_features },
    ];

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Billing Plan
                </h2>
            }
        >
            <Head title="Billing Plan" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow sm:rounded-lg dark:bg-gray-800">
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            Organization: {organization.name}
                        </p>
                        <h3 className="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                            Plan: {plan.name}
                        </h3>
                    </div>

                    <div className="bg-white p-6 shadow sm:rounded-lg dark:bg-gray-800">
                        <h4 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Limits
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
                                    {featureRows.map((feature) => (
                                        <tr key={feature.label}>
                                            <td className="px-3 py-2 text-sm text-gray-800 dark:text-gray-100">
                                                {feature.label}
                                            </td>
                                            <td className="px-3 py-2 text-sm text-gray-600 dark:text-gray-300">
                                                {formatLimit(feature.value)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="mt-6">
                            <button
                                type="button"
                                className="rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500"
                            >
                                Upgrade Plan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
