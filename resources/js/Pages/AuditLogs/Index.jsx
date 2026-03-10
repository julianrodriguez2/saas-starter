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

const truncate = (value, max = 120) => {
    if (!value) {
        return '';
    }

    return value.length > max ? `${value.slice(0, max)}...` : value;
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

const formatTarget = (targetType, targetId) => {
    if (!targetType && !targetId) {
        return '-';
    }

    if (targetType && targetId) {
        return `${targetType}:${targetId}`;
    }

    return targetType || targetId;
};

export default function AuditLogIndex({
    auditOrganization,
    filters,
    auditLogs,
    actorTypeOptions = [],
    actionOptions = [],
}) {
    const { flash } = usePage().props;
    const form = useForm({
        action_query: filters?.action_query ?? '',
        action_exact: filters?.action_exact ?? '',
        actor_type: filters?.actor_type ?? '',
        from_date: filters?.from_date ?? '',
        to_date: filters?.to_date ?? '',
    });

    const submit = (event) => {
        event.preventDefault();

        router.get(route('audit-logs.index'), form.data, {
            preserveState: true,
            replace: true,
        });
    };

    const reset = () => {
        const emptyFilters = {
            action_query: '',
            action_exact: '',
            actor_type: '',
            from_date: '',
            to_date: '',
        };

        form.setData(emptyFilters);

        router.get(route('audit-logs.index'), emptyFilters, {
            preserveState: true,
            replace: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Audit Logs
                </h2>
            }
        >
            <Head title="Audit Logs" />

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
                            {auditOrganization.name} Audit History
                        </h3>
                        <form
                            onSubmit={submit}
                            className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-6"
                        >
                            <div>
                                <InputLabel htmlFor="action_query" value="Action Contains" />
                                <TextInput
                                    id="action_query"
                                    value={form.data.action_query}
                                    onChange={(event) =>
                                        form.setData('action_query', event.target.value)
                                    }
                                    className="mt-1 block w-full"
                                />
                            </div>
                            <div>
                                <InputLabel htmlFor="action_exact" value="Action Exact" />
                                <select
                                    id="action_exact"
                                    value={form.data.action_exact}
                                    onChange={(event) =>
                                        form.setData('action_exact', event.target.value)
                                    }
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                >
                                    <option value="">Any</option>
                                    {actionOptions.map((action) => (
                                        <option key={action} value={action}>
                                            {action}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <InputLabel htmlFor="actor_type" value="Actor Type" />
                                <select
                                    id="actor_type"
                                    value={form.data.actor_type}
                                    onChange={(event) =>
                                        form.setData('actor_type', event.target.value)
                                    }
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                >
                                    <option value="">Any</option>
                                    {actorTypeOptions.map((type) => (
                                        <option key={type} value={type}>
                                            {type}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <InputLabel htmlFor="from_date" value="From Date" />
                                <TextInput
                                    id="from_date"
                                    type="date"
                                    value={form.data.from_date}
                                    onChange={(event) =>
                                        form.setData('from_date', event.target.value)
                                    }
                                    className="mt-1 block w-full"
                                />
                            </div>
                            <div>
                                <InputLabel htmlFor="to_date" value="To Date" />
                                <TextInput
                                    id="to_date"
                                    type="date"
                                    value={form.data.to_date}
                                    onChange={(event) =>
                                        form.setData('to_date', event.target.value)
                                    }
                                    className="mt-1 block w-full"
                                />
                            </div>
                            <div className="flex items-end gap-2">
                                <PrimaryButton className="justify-center">
                                    Apply
                                </PrimaryButton>
                                <button
                                    type="button"
                                    onClick={reset}
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
                                            Timestamp
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Action
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Actor
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Target
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Metadata
                                        </th>
                                        <th className="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Detail
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                    {auditLogs?.data?.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="px-3 py-3 text-sm text-gray-500 dark:text-gray-400"
                                            >
                                                No audit logs found.
                                            </td>
                                        </tr>
                                    ) : (
                                        auditLogs.data.map((log) => (
                                            <tr key={log.id}>
                                                <td className="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                                                    {formatDateTime(log.created_at)}
                                                </td>
                                                <td className="px-3 py-2 text-sm">
                                                    <span className="rounded bg-indigo-100 px-2 py-1 text-xs font-semibold text-indigo-700">
                                                        {log.action}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2 text-sm text-gray-700 dark:text-gray-200">
                                                    {formatActor(log.actor)}
                                                </td>
                                                <td className="px-3 py-2 text-sm text-gray-700 dark:text-gray-200">
                                                    {formatTarget(
                                                        log.target_type,
                                                        log.target_id,
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                                                    {truncate(
                                                        JSON.stringify(
                                                            log.metadata ?? {},
                                                        ),
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 text-right text-sm">
                                                    <Link
                                                        href={route(
                                                            'audit-logs.show',
                                                            log.id,
                                                        )}
                                                        className="font-semibold text-indigo-600 hover:text-indigo-500"
                                                    >
                                                        View
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="mt-4 flex flex-wrap gap-2">
                            {(auditLogs?.links ?? []).map((link, index) => (
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
