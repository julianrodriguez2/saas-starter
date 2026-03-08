import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

const formatDateTime = (value) => {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString();
};

export default function AdminOrganizationsIndex({
    organizations,
    filters,
    planOptions,
    impersonation,
}) {
    const { flash } = usePage().props;
    const form = useForm({
        organization: filters?.organization ?? '',
        owner_email: filters?.owner_email ?? '',
        plan: filters?.plan ?? '',
        suspended: filters?.suspended ?? 'all',
    });

    const applyFilters = (event) => {
        event.preventDefault();

        router.get(route('admin.organizations.index'), form.data, {
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        form.setData({
            organization: '',
            owner_email: '',
            plan: '',
            suspended: 'all',
        });

        router.get(
            route('admin.organizations.index'),
            {
                organization: '',
                owner_email: '',
                plan: '',
                suspended: 'all',
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    const suspendOrganization = (organization) => {
        const reason = prompt(`Suspension reason for "${organization.name}"`);

        if (!reason || reason.trim() === '') {
            return;
        }

        router.post(
            route('admin.organizations.suspend', organization.id),
            { reason: reason.trim() },
            { preserveScroll: true },
        );
    };

    const unsuspendOrganization = (organization) => {
        router.post(route('admin.organizations.unsuspend', organization.id), {}, {
            preserveScroll: true,
        });
    };

    const impersonateOrganization = (organization) => {
        router.post(route('admin.organizations.impersonate', organization.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Admin Organizations
                </h2>
            }
        >
            <Head title="Admin Organizations" />

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
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Filters
                        </h3>
                        <form
                            onSubmit={applyFilters}
                            className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-5"
                        >
                            <div>
                                <InputLabel
                                    htmlFor="organization_filter"
                                    value="Organization Name"
                                />
                                <TextInput
                                    id="organization_filter"
                                    value={form.data.organization}
                                    onChange={(event) =>
                                        form.setData(
                                            'organization',
                                            event.target.value,
                                        )
                                    }
                                    className="mt-1 block w-full"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="owner_email_filter"
                                    value="Owner Email"
                                />
                                <TextInput
                                    id="owner_email_filter"
                                    value={form.data.owner_email}
                                    onChange={(event) =>
                                        form.setData(
                                            'owner_email',
                                            event.target.value,
                                        )
                                    }
                                    className="mt-1 block w-full"
                                />
                            </div>
                            <div>
                                <InputLabel htmlFor="plan_filter" value="Plan" />
                                <select
                                    id="plan_filter"
                                    value={form.data.plan}
                                    onChange={(event) =>
                                        form.setData('plan', event.target.value)
                                    }
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                >
                                    <option value="">All Plans</option>
                                    {planOptions.map((planName) => (
                                        <option key={planName} value={planName}>
                                            {planName}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="suspended_filter"
                                    value="Suspended"
                                />
                                <select
                                    id="suspended_filter"
                                    value={form.data.suspended}
                                    onChange={(event) =>
                                        form.setData(
                                            'suspended',
                                            event.target.value,
                                        )
                                    }
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                >
                                    <option value="all">All</option>
                                    <option value="yes">Suspended</option>
                                    <option value="no">Not Suspended</option>
                                </select>
                            </div>
                            <div className="flex items-end gap-2">
                                <PrimaryButton className="justify-center">
                                    Apply
                                </PrimaryButton>
                                <button
                                    type="button"
                                    onClick={resetFilters}
                                    className="rounded-md border border-gray-300 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    Reset
                                </button>
                            </div>
                        </form>
                    </div>

                    <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Organization
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Owner
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Plan
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Subscription
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            API Calls (Month)
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Members
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Suspended
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Created
                                        </th>
                                        <th className="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                    {organizations?.data?.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={9}
                                                className="px-3 py-3 text-sm text-gray-500 dark:text-gray-400"
                                            >
                                                No organizations found.
                                            </td>
                                        </tr>
                                    ) : (
                                        organizations.data.map((organization) => {
                                            const isImpersonatingTarget =
                                                impersonation?.active &&
                                                impersonation?.organization_id ===
                                                    organization.id;

                                            return (
                                                <tr key={organization.id}>
                                                    <td className="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                        <Link
                                                            href={route(
                                                                'admin.organizations.show',
                                                                organization.id,
                                                            )}
                                                            className="font-semibold text-indigo-600 hover:text-indigo-500"
                                                        >
                                                            {organization.name}
                                                        </Link>
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-gray-600 dark:text-gray-300">
                                                        <p>{organization.owner.name}</p>
                                                        <p className="text-xs text-gray-500 dark:text-gray-400">
                                                            {
                                                                organization.owner
                                                                    .email
                                                            }
                                                        </p>
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-gray-700 dark:text-gray-200">
                                                        {organization.plan}
                                                    </td>
                                                    <td className="px-3 py-2 text-sm capitalize text-gray-700 dark:text-gray-200">
                                                        {
                                                            organization.subscription_status
                                                        }
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-gray-700 dark:text-gray-200">
                                                        {
                                                            organization.monthly_api_calls
                                                        }
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-gray-700 dark:text-gray-200">
                                                        {organization.member_count}
                                                    </td>
                                                    <td className="px-3 py-2 text-sm">
                                                        <span
                                                            className={
                                                                organization.is_suspended
                                                                    ? 'rounded-full bg-red-100 px-2 py-1 text-xs font-semibold text-red-700'
                                                                    : 'rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700'
                                                            }
                                                        >
                                                            {organization.is_suspended
                                                                ? 'suspended'
                                                                : 'active'}
                                                        </span>
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                                                        {formatDateTime(
                                                            organization.created_at,
                                                        )}
                                                    </td>
                                                    <td className="space-x-2 px-3 py-2 text-right">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                impersonateOrganization(
                                                                    organization,
                                                                )
                                                            }
                                                            className="rounded-md border border-transparent bg-indigo-600 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-white transition hover:bg-indigo-500"
                                                        >
                                                            {isImpersonatingTarget
                                                                ? 'Impersonating'
                                                                : 'Impersonate'}
                                                        </button>
                                                        {organization.is_suspended ? (
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    unsuspendOrganization(
                                                                        organization,
                                                                    )
                                                                }
                                                                className="rounded-md border border-emerald-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-emerald-700 transition hover:bg-emerald-50"
                                                            >
                                                                Unsuspend
                                                            </button>
                                                        ) : (
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    suspendOrganization(
                                                                        organization,
                                                                    )
                                                                }
                                                                className="rounded-md border border-red-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-red-700 transition hover:bg-red-50"
                                                            >
                                                                Suspend
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

                        <div className="mt-4 flex flex-wrap gap-2">
                            {(organizations?.links ?? []).map((link, index) => (
                                <Link
                                    key={`${link.label}-${index}`}
                                    href={link.url ?? '#'}
                                    preserveState
                                    preserveScroll
                                    className={
                                        link.active
                                            ? 'rounded-md border border-indigo-600 bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white'
                                            : link.url
                                              ? 'rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50'
                                              : 'cursor-not-allowed rounded-md border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-400'
                                    }
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
