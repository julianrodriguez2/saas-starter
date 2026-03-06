import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Members({
    membersOrganization,
    members,
    invites,
    currentUserRole,
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        role: 'member',
    });

    const [roleSelections, setRoleSelections] = useState(() =>
        Object.fromEntries(
            members
                .filter((member) => member.role !== 'owner')
                .map((member) => [member.id, member.role]),
        ),
    );

    const submitInvite = (event) => {
        event.preventDefault();

        post(route('organizations.members.invite'), {
            preserveScroll: true,
            onSuccess: () => reset('email'),
        });
    };

    const removeMember = (member) => {
        if (!confirm(`Remove ${member.name} from ${membersOrganization.name}?`)) {
            return;
        }

        router.delete(route('organizations.members.destroy', member.id), {
            preserveScroll: true,
        });
    };

    const updateRole = (member) => {
        const role = roleSelections[member.id] ?? member.role;

        router.patch(
            route('organizations.members.update-role', member.id),
            { role },
            { preserveScroll: true },
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Members
                </h2>
            }
        >
            <Head title="Members" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow sm:rounded-lg dark:bg-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Invite Member
                        </h3>
                        <form
                            onSubmit={submitInvite}
                            className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-4"
                        >
                            <div className="md:col-span-2">
                                <InputLabel htmlFor="invite_email" value="Email" />
                                <TextInput
                                    id="invite_email"
                                    type="email"
                                    value={data.email}
                                    onChange={(event) =>
                                        setData('email', event.target.value)
                                    }
                                    className="mt-1 block w-full"
                                    required
                                />
                                <InputError
                                    message={errors.email}
                                    className="mt-2"
                                />
                            </div>
                            <div>
                                <InputLabel htmlFor="invite_role" value="Role" />
                                <select
                                    id="invite_role"
                                    value={data.role}
                                    onChange={(event) =>
                                        setData('role', event.target.value)
                                    }
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                >
                                    <option value="member">member</option>
                                    {currentUserRole === 'owner' && (
                                        <option value="admin">admin</option>
                                    )}
                                </select>
                                <InputError
                                    message={errors.role}
                                    className="mt-2"
                                />
                            </div>
                            <div className="flex items-end">
                                <PrimaryButton
                                    disabled={processing}
                                    className="w-full justify-center"
                                >
                                    Invite
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>

                    <div className="bg-white p-6 shadow sm:rounded-lg dark:bg-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Organization Members
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
                                        <th className="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                    {members.map((member) => (
                                        <tr key={member.id}>
                                            <td className="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                {member.name}
                                            </td>
                                            <td className="px-3 py-2 text-sm text-gray-600 dark:text-gray-300">
                                                {member.email}
                                            </td>
                                            <td className="px-3 py-2 text-sm text-gray-700 dark:text-gray-200">
                                                {member.role === 'owner' ? (
                                                    <span className="capitalize">
                                                        owner
                                                    </span>
                                                ) : currentUserRole === 'admin' &&
                                                  member.role === 'admin' ? (
                                                    <span className="capitalize">
                                                        admin
                                                    </span>
                                                ) : (
                                                    <select
                                                        value={
                                                            roleSelections[member.id] ??
                                                            member.role
                                                        }
                                                        onChange={(event) =>
                                                            setRoleSelections(
                                                                (previous) => ({
                                                                    ...previous,
                                                                    [member.id]:
                                                                        event.target
                                                                            .value,
                                                                }),
                                                            )
                                                        }
                                                        className="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                                    >
                                                        <option value="member">
                                                            member
                                                        </option>
                                                        {currentUserRole ===
                                                            'owner' && (
                                                            <option value="admin">
                                                                admin
                                                            </option>
                                                        )}
                                                    </select>
                                                )}
                                            </td>
                                            <td className="space-x-2 px-3 py-2 text-right">
                                                {member.role !== 'owner' &&
                                                    !(
                                                        currentUserRole ===
                                                            'admin' &&
                                                        member.role === 'admin'
                                                    ) && (
                                                    <>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                updateRole(
                                                                    member,
                                                                )
                                                            }
                                                            className="rounded-md border border-transparent bg-indigo-600 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-white transition hover:bg-indigo-500"
                                                        >
                                                            Save Role
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                removeMember(
                                                                    member,
                                                                )
                                                            }
                                                            className="rounded-md border border-red-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-red-700 transition hover:bg-red-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-900/30"
                                                        >
                                                            Remove
                                                        </button>
                                                    </>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="bg-white p-6 shadow sm:rounded-lg dark:bg-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Pending Invites
                        </h3>
                        <div className="mt-4 overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Email
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Role
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                            Created
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                    {invites.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={3}
                                                className="px-3 py-3 text-sm text-gray-500 dark:text-gray-400"
                                            >
                                                No pending invites.
                                            </td>
                                        </tr>
                                    ) : (
                                        invites.map((invite) => (
                                            <tr key={invite.id}>
                                                <td className="px-3 py-2 text-sm text-gray-700 dark:text-gray-200">
                                                    {invite.email}
                                                </td>
                                                <td className="px-3 py-2 text-sm capitalize text-gray-700 dark:text-gray-200">
                                                    {invite.role}
                                                </td>
                                                <td className="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                                                    {invite.created_at
                                                        ? new Date(
                                                              invite.created_at,
                                                          ).toLocaleString()
                                                        : '-'}
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
