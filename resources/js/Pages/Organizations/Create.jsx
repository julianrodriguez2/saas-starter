import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Create({ defaultName }) {
    const { data, setData, post, processing, errors } = useForm({
        name: defaultName ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        post(route('organizations.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Create Organization
                </h2>
            }
        >
            <Head title="Create Organization" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow sm:rounded-lg dark:bg-gray-800">
                        <p className="mb-6 text-sm text-gray-600 dark:text-gray-300">
                            Create your first organization to continue.
                        </p>

                        <form onSubmit={submit}>
                            <div>
                                <InputLabel
                                    htmlFor="organization_name"
                                    value="Organization Name"
                                />
                                <TextInput
                                    id="organization_name"
                                    name="name"
                                    value={data.name}
                                    className="mt-1 block w-full"
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    required
                                    isFocused
                                />
                                <InputError
                                    message={errors.name}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-6">
                                <PrimaryButton disabled={processing}>
                                    Create Organization
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
