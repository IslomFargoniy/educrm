import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';
import { store } from '@/routes/login';

export default function Login({ status }: { status?: string }) {
    const t = useT();

    return (
        <>
            <Head title={t('Log in')} />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-5">
                        <div className="grid gap-2">
                            <Label htmlFor="phone">{t('Phone number')}</Label>
                            <Input
                                id="phone"
                                type="tel"
                                name="phone"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="tel"
                                inputMode="tel"
                                placeholder="+998 90 123 45 67"
                            />
                            <InputError message={errors.phone} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">{t('Password')}</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                tabIndex={2}
                                autoComplete="current-password"
                                placeholder={t('Password')}
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center space-x-3">
                            <Checkbox
                                id="remember"
                                name="remember"
                                tabIndex={3}
                            />
                            <Label htmlFor="remember">{t('Remember me')}</Label>
                        </div>

                        <Button
                            type="submit"
                            className="mt-2 w-full"
                            size="lg"
                            tabIndex={4}
                            disabled={processing}
                            data-test="login-button"
                        >
                            {processing && <Spinner />}
                            {t('Log in')}
                        </Button>

                        <p className="text-center text-xs text-muted-foreground">
                            {t(
                                'Forgot your password? Ask the administrator to reset it.',
                            )}
                        </p>
                    </div>
                )}
            </Form>

            {status && (
                <div className="text-center text-sm font-medium text-success">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Log in to Mensa',
    description: 'Enter your phone number and password',
};
