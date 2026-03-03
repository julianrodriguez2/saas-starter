import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function Settings({ settingsOrganization, owner, members }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Organization Settings
                </h2>
            }
        >
            <Head title="Organization Settings" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow sm:rounded-lg dark:bg-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {settingsOrganization.name}
                        </h3>
                        <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            Owner: {owner.name}
                        </p>
                    </div>

                    <div className="bg-white p-6 shadow sm:rounded-lg dark:bg-gray-800">
                        <h4 className="text-base font-semibold text-gray-900 dark:text-gray-100">
                            Members
                        </h4>
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
                                    {members.map((member) => (
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
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
