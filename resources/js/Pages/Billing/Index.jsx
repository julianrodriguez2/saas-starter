import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';

const statusClasses = {
    free: 'bg-gray-100 text-gray-700',
    trialing: 'bg-blue-100 text-blue-700',
    active: 'bg-emerald-100 text-emerald-700',
    past_due: 'bg-amber-100 text-amber-800',
    canceled: 'bg-red-100 text-red-700',
};

const formatLimit = (value) => {
    if (value === null || value === undefined) {
        return 'Unlimited';
    }

    if (typeof value === 'boolean') {
        return value ? 'Enabled' : 'Disabled';
    }

    return value.toLocaleString();
};

const formatDate = (value) => {
    if (!value) {
        return 'N/A';
    }

    return new Date(value).toLocaleString();
};

export default function BillingIndex({
    billingOrganization,
    localPlan,
    subscription,
    availablePlans = [],
    hasPaidSubscription,
    hasStripeCustomer,
}) {
    const { organization, flash, errors } = usePage().props;
    const currentRole = organization?.current?.role;
    const canManageBilling = currentRole === 'owner' || currentRole === 'admin';

    const limits = localPlan?.limits ?? {};
    const featureRows = [
        { label: 'Team Members', value: limits.team_members },
        { label: 'Projects', value: limits.projects },
        { label: 'API Calls Per Month', value: limits.api_calls_per_month },
        { label: 'Advanced Features', value: limits.advanced_features },
    ];

    const statusClass = statusClasses[subscription?.status] ?? 'bg-gray-100 text-gray-700';

    const checkout = (planSlug) => {
        router.post(route('billing.checkout', planSlug));
    };

    const openPortal = () => {
        router.post(route('billing.portal'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Billing
                </h2>
            }
        >
            <Head title="Billing" />

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

                    {errors?.plan && (
                        <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {errors.plan}
                        </div>
                    )}

                    <div className="grid gap-6 lg:grid-cols-3">
                        <div className="space-y-4 bg-white p-6 shadow sm:rounded-lg dark:bg-gray-800 lg:col-span-2">
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Organization: {billingOrganization.name}
                            </p>
                            <h3 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                Current Plan: {localPlan.name}
                            </h3>
                            <div>
                                <span
                                    className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide ${statusClass}`}
                                >
                                    {subscription.status}
                                </span>
                            </div>
                            <dl className="space-y-2 text-sm">
                                <div>
                                    <dt className="font-medium text-gray-700 dark:text-gray-300">
                                        Stripe Subscription
                                    </dt>
                                    <dd className="text-gray-600 dark:text-gray-400">
                                        {subscription?.stripe_plan_name ||
                                            subscription?.name ||
                                            'N/A'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="font-medium text-gray-700 dark:text-gray-300">
                                        Trial Ends
                                    </dt>
                                    <dd className="text-gray-600 dark:text-gray-400">
                                        {formatDate(subscription?.trial_ends_at)}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div className="space-y-3 bg-white p-6 shadow sm:rounded-lg dark:bg-gray-800">
                            <h4 className="text-base font-semibold text-gray-900 dark:text-gray-100">
                                Actions
                            </h4>
                            <button
                                type="button"
                                onClick={openPortal}
                                disabled={!canManageBilling || !hasStripeCustomer}
                                className="w-full rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                            >
                                Manage Billing
                            </button>
                            {!canManageBilling && (
                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                    Owner or admin role is required.
                                </p>
                            )}
                            {!hasStripeCustomer && (
                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                    Stripe customer will be created on first checkout.
                                </p>
                            )}
                            {hasPaidSubscription && (
                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                    Active paid subscription detected. Use billing portal to change plans.
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="bg-white p-6 shadow sm:rounded-lg dark:bg-gray-800">
                        <h4 className="text-base font-semibold text-gray-900 dark:text-gray-100">
                            Upgrade Plans
                        </h4>
                        <div className="mt-4 grid gap-4 md:grid-cols-2">
                            {availablePlans.map((plan) => (
                                <div
                                    key={plan.id}
                                    className="rounded-lg border border-gray-200 p-4 dark:border-gray-700"
                                >
                                    <h5 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                        {plan.name}
                                    </h5>
                                    <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        Stripe Price ID: {plan.stripe_price_id || 'Not configured'}
                                    </p>
                                    <button
                                        type="button"
                                        disabled={
                                            !canManageBilling ||
                                            hasPaidSubscription ||
                                            !plan.stripe_price_id
                                        }
                                        onClick={() => checkout(plan.slug)}
                                        className="mt-4 w-full rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        Upgrade to {plan.name}
                                    </button>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="bg-white p-6 shadow sm:rounded-lg dark:bg-gray-800">
                        <h4 className="text-base font-semibold text-gray-900 dark:text-gray-100">
                            Local Plan Limits
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
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
