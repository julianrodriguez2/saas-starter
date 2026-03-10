import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function AuthenticatedLayout({ header, children }) {
    const { auth, organization, impersonation } = usePage().props;
    const user = auth.user;
    const isSuperAdmin = Boolean(auth?.is_super_admin);
    const organizations = organization?.all ?? [];
    const currentOrganization = organization?.current;
    const isImpersonating = Boolean(impersonation?.active);
    const canManageMembers =
        currentOrganization?.role === 'owner' ||
        currentOrganization?.role === 'admin' ||
        (isSuperAdmin && isImpersonating);

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);

    return (
        <div className="min-h-screen bg-gray-100 dark:bg-gray-900">
            <nav className="border-b border-gray-100 bg-white dark:border-gray-700 dark:bg-gray-800">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 justify-between">
                        <div className="flex">
                            <div className="flex shrink-0 items-center">
                                <Link href="/">
                                    <ApplicationLogo className="block h-9 w-auto fill-current text-gray-800 dark:text-gray-200" />
                                </Link>
                            </div>

                            <div className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink
                                    href={route('dashboard')}
                                    active={route().current('dashboard')}
                                >
                                    Dashboard
                                </NavLink>
                                <NavLink
                                    href={route('organizations.settings')}
                                    active={route().current('organizations.settings')}
                                >
                                    Organization Settings
                                </NavLink>
                                <NavLink
                                    href={route('billing.index')}
                                    active={route().current('billing.*')}
                                >
                                    Billing
                                </NavLink>
                                <NavLink
                                    href={route('usage.index')}
                                    active={route().current('usage.*')}
                                >
                                    Usage
                                </NavLink>
                                <NavLink
                                    href={route('developers.api')}
                                    active={route().current('developers.api')}
                                >
                                    Developer API
                                </NavLink>
                                {canManageMembers && (
                                    <NavLink
                                        href={route('organizations.members.index')}
                                        active={route().current('organizations.members.*')}
                                    >
                                        Members
                                    </NavLink>
                                )}
                                {canManageMembers && (
                                    <NavLink
                                        href={route('settings.api-keys.index')}
                                        active={route().current('settings.api-keys.*')}
                                    >
                                        API Keys
                                    </NavLink>
                                )}
                                {isSuperAdmin && (
                                    <>
                                        <div className="my-4 w-px bg-gray-200 dark:bg-gray-700" />
                                        <NavLink
                                            href={route('admin.dashboard')}
                                            active={route().current('admin.dashboard')}
                                        >
                                            Admin Dashboard
                                        </NavLink>
                                        <NavLink
                                            href={route('admin.organizations.index')}
                                            active={route().current(
                                                'admin.organizations.*',
                                            )}
                                        >
                                            Organizations
                                        </NavLink>
                                        <NavLink
                                            href={route('system.events.index')}
                                            active={route().current(
                                                'system.events.*',
                                            )}
                                        >
                                            System Events
                                        </NavLink>
                                    </>
                                )}
                            </div>
                        </div>

                        <div className="hidden sm:ms-6 sm:flex sm:items-center">
                            <div className="relative ms-3">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                            >
                                                {currentOrganization?.name ??
                                                    'Select Organization'}
                                                <svg
                                                    className="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>
                                    <Dropdown.Content>
                                        {organizations.map((org) => (
                                            <Dropdown.Link
                                                key={org.id}
                                                href={route('organizations.switch')}
                                                method="post"
                                                as="button"
                                                data={{ organization_id: org.id }}
                                                className={
                                                    currentOrganization?.id === org.id
                                                        ? 'bg-gray-100 dark:bg-gray-800'
                                                        : ''
                                                }
                                            >
                                                {org.name}
                                            </Dropdown.Link>
                                        ))}
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>

                            <div className="relative ms-3">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none dark:bg-gray-800 dark:text-gray-400 dark:hover:text-gray-300"
                                            >
                                                {user.name}

                                                <svg
                                                    className="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        <Dropdown.Link
                                            href={route('profile.edit')}
                                        >
                                            Profile
                                        </Dropdown.Link>
                                        <Dropdown.Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        </div>

                        <div className="-me-2 flex items-center sm:hidden">
                            <button
                                onClick={() =>
                                    setShowingNavigationDropdown(
                                        (previousState) => !previousState,
                                    )
                                }
                                className="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none dark:text-gray-500 dark:hover:bg-gray-900 dark:hover:text-gray-400 dark:focus:bg-gray-900 dark:focus:text-gray-400"
                            >
                                <svg
                                    className="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        className={
                                            !showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        className={
                                            showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    className={
                        (showingNavigationDropdown ? 'block' : 'hidden') +
                        ' sm:hidden'
                    }
                >
                    <div className="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            href={route('dashboard')}
                            active={route().current('dashboard')}
                        >
                            Dashboard
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            href={route('organizations.settings')}
                            active={route().current('organizations.settings')}
                        >
                            Organization Settings
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            href={route('billing.index')}
                            active={route().current('billing.*')}
                        >
                            Billing
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            href={route('usage.index')}
                            active={route().current('usage.*')}
                        >
                            Usage
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            href={route('developers.api')}
                            active={route().current('developers.api')}
                        >
                            Developer API
                        </ResponsiveNavLink>
                        {canManageMembers && (
                            <ResponsiveNavLink
                                href={route('organizations.members.index')}
                                active={route().current('organizations.members.*')}
                            >
                                Members
                            </ResponsiveNavLink>
                        )}
                        {canManageMembers && (
                            <ResponsiveNavLink
                                href={route('settings.api-keys.index')}
                                active={route().current('settings.api-keys.*')}
                            >
                                API Keys
                            </ResponsiveNavLink>
                        )}
                        {isSuperAdmin && (
                            <>
                                <ResponsiveNavLink
                                    href={route('admin.dashboard')}
                                    active={route().current('admin.dashboard')}
                                >
                                    Admin Dashboard
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    href={route('admin.organizations.index')}
                                    active={route().current(
                                        'admin.organizations.*',
                                    )}
                                >
                                    Organizations
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    href={route('system.events.index')}
                                    active={route().current('system.events.*')}
                                >
                                    System Events
                                </ResponsiveNavLink>
                            </>
                        )}
                    </div>

                    <div className="border-t border-gray-200 pb-1 pt-4 dark:border-gray-600">
                        <div className="px-4">
                            <div className="text-base font-medium text-gray-800 dark:text-gray-200">
                                {user.name}
                            </div>
                            <div className="text-sm font-medium text-gray-500">
                                {user.email}
                            </div>
                        </div>

                        <div className="mt-3 space-y-1">
                            {organizations.map((org) => (
                                <ResponsiveNavLink
                                    key={org.id}
                                    method="post"
                                    href={route('organizations.switch')}
                                    data={{ organization_id: org.id }}
                                    as="button"
                                >
                                    {org.name}
                                </ResponsiveNavLink>
                            ))}
                            {isImpersonating && isSuperAdmin && (
                                <ResponsiveNavLink
                                    method="post"
                                    href={route('admin.impersonation.stop')}
                                    as="button"
                                >
                                    Stop Impersonation
                                </ResponsiveNavLink>
                            )}
                            <ResponsiveNavLink href={route('profile.edit')}>
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                method="post"
                                href={route('logout')}
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>
            {isImpersonating && (
                <div className="border-b border-amber-200 bg-amber-50">
                    <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-2 text-sm text-amber-900 sm:px-6 lg:px-8">
                        <span className="font-medium">
                            Impersonating organization:{' '}
                            {impersonation?.organization?.name ??
                                currentOrganization?.name ??
                                'Unknown organization'}
                        </span>
                        {isSuperAdmin && (
                            <Link
                                href={route('admin.impersonation.stop')}
                                method="post"
                                as="button"
                                className="rounded-md border border-amber-400 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-amber-800 transition hover:bg-amber-100"
                            >
                                Stop Impersonation
                            </Link>
                        )}
                    </div>
                </div>
            )}

            {header && (
                <header className="bg-white shadow dark:bg-gray-800">
                    <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        {header}
                    </div>
                </header>
            )}

            <main>{children}</main>
        </div>
    );
}
