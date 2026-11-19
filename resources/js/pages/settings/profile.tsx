import { Form, Head, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Field from '@/components/field';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { formatPhone } from '@/lib/format';
import { useT } from '@/lib/i18n';
import { edit } from '@/routes/profile';

export default function Profile() {
    const { auth } = usePage().props;
    const t = useT();

    return (
        <>
            <Head title={t('Profile settings')} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Profile"
                    description="Update your name, email and language"
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                label={t('Name')}
                                error={errors.name}
                                htmlFor="name"
                            >
                                <Input
                                    id="name"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                />
                            </Field>

                            <Field
                                label={t('Phone number')}
                                hint={t(
                                    'The phone number is changed by the administrator.',
                                )}
                            >
                                <Input
                                    value={formatPhone(auth.user.phone)}
                                    disabled
                                />
                            </Field>

                            <Field
                                label={t('Email (optional)')}
                                error={errors.email}
                                htmlFor="email"
                            >
                                <Input
                                    id="email"
                                    type="email"
                                    defaultValue={auth.user.email ?? ''}
                                    name="email"
                                    autoComplete="email"
                                />
                            </Field>

                            <Field
                                label={t('Language')}
                                error={errors.locale}
                                htmlFor="locale"
                            >
                                <NativeSelect
                                    id="locale"
                                    name="locale"
                                    defaultValue={auth.user.locale}
                                >
                                    <option value="uz">O'zbekcha</option>
                                    <option value="ru">Русский</option>
                                    <option value="en">English</option>
                                </NativeSelect>
                            </Field>

                            <Button
                                disabled={processing}
                                data-test="update-profile-button"
                            >
                                {t('Save')}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [{ title: 'Profile settings', href: edit() }],
};
