import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';

const formatDateTime = (value) => {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString();
};

export default function ApiKeys({
    apiKeysOrganization,
    activeKeys = [],
    revokedKeys = [],
}) {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
    });

    const submit = (event) => {
        event.preventDefault();

        post(route('settings.api-keys.store'), {
            preserveScroll: true,
            onSuccess: () => reset('name'),
        });
    };

    const revokeKey = (apiKeyId) => {
        if (!confirm('Revoke this API key?')) {
            return;
        }

        router.post(route('settings.api-keys.revoke', apiKeyId), {}, {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    API Keys
                </h2>
            }
        >
            <Head title="API Keys" />

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

                    {flash?.api_key_plaintext && (
                        <div className="rounded-lg border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-800">
                            <p className="font-semibold">
                                Save this API key now. It will not be shown again.
                            </p>
                            <code className="mt-2 block overflow-x-auto rounded bg-white px-3 py-2 text-xs text-indigo-900">
                                {flash.api_key_plaintext}
                            </code>
                        </div>
                    )}

                    <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Create API Key for {apiKeysOrganization.name}
                        </h3>
                        <form onSubmit={submit} className="mt-4 max-w-xl space-y-4">
                            <div>
                                <InputLabel htmlFor="api_key_name" value="Key Name" />
                                <TextInput
                                    id="api_key_name"
                                    value={data.name}
                                    onChange={(event) =>
                                        setData('name', event.target.value)
                                    }
                                    className="mt-1 block w-full"
                                    placeholder="Production ingest key"
                                    required
                                />
                                <InputError message={errors.name} className="mt-2" />
                            </div>
                            <PrimaryButton disabled={processing}>
                                Create API Key
                            </PrimaryButton>
                        </form>
                    </div>

                    <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Active Keys
                        </h3>
                        <div className="mt-4 overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Name
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Prefix
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Last Used
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Created
                                        </th>
                                        <th className="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                    {activeKeys.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={5}
                                                className="px-3 py-3 text-sm text-gray-500 dark:text-gray-400"
                                            >
                                                No active API keys.
                                            </td>
                                        </tr>
                                    ) : (
                                        activeKeys.map((apiKey) => (
                                            <tr key={apiKey.id}>
                                                <td className="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                    {apiKey.name}
                                                </td>
                                                <td className="px-3 py-2 text-sm text-gray-600 dark:text-gray-300">
                                                    <code>{apiKey.key_prefix}</code>
                                                </td>
                                                <td className="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                                                    {formatDateTime(apiKey.last_used_at)}
                                                </td>
                                                <td className="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                                                    {formatDateTime(apiKey.created_at)}
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            revokeKey(apiKey.id)
                                                        }
                                                        className="rounded-md border border-red-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-red-700 transition hover:bg-red-50"
                                                    >
                                                        Revoke
                                                    </button>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Revoked Keys
                        </h3>
                        <div className="mt-4 overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Name
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Prefix
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Revoked At
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Created
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                    {revokedKeys.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={4}
                                                className="px-3 py-3 text-sm text-gray-500 dark:text-gray-400"
                                            >
                                                No revoked API keys.
                                            </td>
                                        </tr>
                                    ) : (
                                        revokedKeys.map((apiKey) => (
                                            <tr key={apiKey.id}>
                                                <td className="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                    {apiKey.name}
                                                </td>
                                                <td className="px-3 py-2 text-sm text-gray-600 dark:text-gray-300">
                                                    <code>{apiKey.key_prefix}</code>
                                                </td>
                                                <td className="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                                                    {formatDateTime(apiKey.revoked_at)}
                                                </td>
                                                <td className="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                                                    {formatDateTime(apiKey.created_at)}
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
