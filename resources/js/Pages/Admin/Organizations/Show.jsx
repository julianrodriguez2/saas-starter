import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';

const formatDateTime = (value) => {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString();
};

const truncate = (value, max = 120) => {
    if (!value) {
        return '';
    }

    return value.length > max ? `${value.slice(0, max)}...` : value;
};

export default function AdminOrganizationShow({
    organizationDetail,
    isImpersonatingTarget,
}) {
    const { flash } = usePage().props;

    const suspendOrganization = () => {
        const reason = prompt(
            `Suspension reason for "${organizationDetail.name}"`,
        );

        if (!reason || reason.trim() === '') {
            return;
        }

        router.post(
            route('admin.organizations.suspend', organizationDetail.id),
            { reason: reason.trim() },
            { preserveScroll: true },
        );
    };

    const unsuspendOrganization = () => {
        router.post(
            route('admin.organizations.unsuspend', organizationDetail.id),
            {},
            { preserveScroll: true },
        );
    };

    const impersonateOrganization = () => {
        router.post(route('admin.organizations.impersonate', organizationDetail.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Organization Detail
                </h2>
            }
        >
            <Head title={`Admin - ${organizationDetail.name}`} />

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

                    <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    {organizationDetail.name}
                                </h3>
                                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Created {formatDateTime(organizationDetail.created_at)}
                                </p>
                                <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                    Owner: {organizationDetail.owner.name} (
                                    {organizationDetail.owner.email})
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    onClick={impersonateOrganization}
                                    className="rounded-md border border-transparent bg-indigo-600 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-white transition hover:bg-indigo-500"
                                >
                                    {isImpersonatingTarget
                                        ? 'Impersonating'
                                        : 'Impersonate'}
                                </button>
                                {organizationDetail.suspension.is_suspended ? (
                                    <button
                                        type="button"
                                        onClick={unsuspendOrganization}
                                        className="rounded-md border border-emerald-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-emerald-700 transition hover:bg-emerald-50"
                                    >
                                        Unsuspend
                                    </button>
                                ) : (
                                    <button
                                        type="button"
                                        onClick={suspendOrganization}
                                        className="rounded-md border border-red-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-red-700 transition hover:bg-red-50"
                                    >
                                        Suspend
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <div className="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                            <p className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Plan
                            </p>
                            <p className="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                {organizationDetail.plan.name}
                            </p>
                        </div>
                        <div className="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                            <p className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Subscription Status
                            </p>
                            <p className="mt-2 text-lg font-semibold capitalize text-gray-900 dark:text-gray-100">
                                {organizationDetail.billing.subscription_status}
                            </p>
                        </div>
                        <div className="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                            <p className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                API Calls ({organizationDetail.monthly_usage.label})
                            </p>
                            <p className="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                {organizationDetail.monthly_usage.api_call}
                            </p>
                        </div>
                    </div>

                    <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Billing and Suspension
                        </h3>
                        <dl className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <dt className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Subscription Name
                                </dt>
                                <dd className="mt-1 text-sm text-gray-700 dark:text-gray-200">
                                    {organizationDetail.billing.subscription_name || '-'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Trial Ends At
                                </dt>
                                <dd className="mt-1 text-sm text-gray-700 dark:text-gray-200">
                                    {formatDateTime(
                                        organizationDetail.billing.trial_ends_at,
                                    )}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Stripe Customer ID
                                </dt>
                                <dd className="mt-1 break-all text-sm text-gray-700 dark:text-gray-200">
                                    {organizationDetail.billing.stripe_customer_id || '-'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Stripe Subscription ID
                                </dt>
                                <dd className="mt-1 break-all text-sm text-gray-700 dark:text-gray-200">
                                    {organizationDetail.billing.stripe_subscription_id ||
                                        '-'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Suspended
                                </dt>
                                <dd className="mt-1 text-sm text-gray-700 dark:text-gray-200">
                                    {organizationDetail.suspension.is_suspended
                                        ? 'Yes'
                                        : 'No'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Suspended At
                                </dt>
                                <dd className="mt-1 text-sm text-gray-700 dark:text-gray-200">
                                    {formatDateTime(
                                        organizationDetail.suspension.suspended_at,
                                    )}
                                </dd>
                            </div>
                            <div className="md:col-span-2">
                                <dt className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Suspension Reason
                                </dt>
                                <dd className="mt-1 text-sm text-gray-700 dark:text-gray-200">
                                    {organizationDetail.suspension.reason || '-'}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Members
                        </h3>
                        <div className="mt-4 overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Name
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Email
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Role
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                    {organizationDetail.members.map((member) => (
                                        <tr key={member.id}>
                                            <td className="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                {member.name}
                                            </td>
                                            <td className="px-3 py-2 text-sm text-gray-600 dark:text-gray-300">
                                                {member.email}
                                            </td>
                                            <td className="px-3 py-2 text-sm capitalize text-gray-700 dark:text-gray-200">
                                                {member.role}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Recent Usage Events
                        </h3>
                        <div className="mt-4 overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Event Type
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Quantity
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Recorded At
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                    {organizationDetail.recent_usage_events.length ===
                                    0 ? (
                                        <tr>
                                            <td
                                                colSpan={3}
                                                className="px-3 py-3 text-sm text-gray-500 dark:text-gray-400"
                                            >
                                                No usage events found.
                                            </td>
                                        </tr>
                                    ) : (
                                        organizationDetail.recent_usage_events.map(
                                            (usageEvent) => (
                                                <tr key={usageEvent.id}>
                                                    <td className="px-3 py-2 text-sm text-gray-700 dark:text-gray-200">
                                                        {usageEvent.event_type}
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-gray-700 dark:text-gray-200">
                                                        {usageEvent.quantity}
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                                                        {formatDateTime(
                                                            usageEvent.recorded_at,
                                                        )}
                                                    </td>
                                                </tr>
                                            ),
                                        )
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Recent Audit Logs
                        </h3>
                        <div className="mt-4 overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Action
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Actor
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Created
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Metadata
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                    {organizationDetail.recent_audit_logs.length ===
                                    0 ? (
                                        <tr>
                                            <td
                                                colSpan={4}
                                                className="px-3 py-3 text-sm text-gray-500 dark:text-gray-400"
                                            >
                                                No audit logs found.
                                            </td>
                                        </tr>
                                    ) : (
                                        organizationDetail.recent_audit_logs.map(
                                            (auditLog) => (
                                                <tr key={auditLog.id}>
                                                    <td className="px-3 py-2 text-sm text-gray-700 dark:text-gray-200">
                                                        {auditLog.action}
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-gray-700 dark:text-gray-200">
                                                        {auditLog.actor_name}
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                                                        {formatDateTime(
                                                            auditLog.created_at,
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                                                        {truncate(
                                                            JSON.stringify(
                                                                auditLog.metadata,
                                                            ),
                                                        )}
                                                    </td>
                                                </tr>
                                            ),
                                        )
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Related Failed Domain Events
                        </h3>
                        <div className="mt-4 overflow-x-auto">
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
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                    {organizationDetail.recent_failed_domain_events
                                        .length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="px-3 py-3 text-sm text-gray-500 dark:text-gray-400"
                                            >
                                                No related failed domain events.
                                            </td>
                                        </tr>
                                    ) : (
                                        organizationDetail.recent_failed_domain_events.map(
                                            (failedEvent) => (
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
                                                        {formatDateTime(
                                                            failedEvent.failed_at,
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-2 text-sm">
                                                        <span
                                                            className={
                                                                failedEvent.resolved_at
                                                                    ? 'rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700'
                                                                    : 'rounded-full bg-red-100 px-2 py-1 text-xs font-semibold text-red-700'
                                                            }
                                                        >
                                                            {failedEvent.resolved_at
                                                                ? 'resolved'
                                                                : 'unresolved'}
                                                        </span>
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                                                        {truncate(
                                                            failedEvent.error_message,
                                                        )}
                                                    </td>
                                                </tr>
                                            ),
                                        )
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
