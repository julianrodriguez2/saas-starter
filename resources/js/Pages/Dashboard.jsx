import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';

export default function Dashboard() {
    const { auth, organization } = usePage().props;
    const currentOrganization = organization?.current;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                        <div className="space-y-3 p-6 text-gray-900 dark:text-gray-100">
                            <p>
                                <span className="font-semibold">User:</span>{' '}
                                {auth?.user?.name ?? 'Unknown'}
                            </p>
                            <p>
                                <span className="font-semibold">
                                    Current organization:
                                </span>{' '}
                                {currentOrganization?.name ?? 'No organization selected'}
                            </p>
                            <p>Multi-Tenant SaaS Starter {'\u2014'} Increment 1</p>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

