import Field from '@/components/field';
import { Input } from '@/components/ui/input';
import { useT } from '@/lib/i18n';

/**
 * First name and last name entered separately; the system shows them as one full name.
 */
export default function NameFields({
    errors,
    prefix = '',
    firstName,
    lastName,
    required = true,
    lastNameRequired = required,
    autoFocus = false,
    firstLabel,
    lastLabel,
}: {
    errors: Record<string, string | undefined>;
    prefix?: string;
    firstName?: string | null;
    lastName?: string | null;
    required?: boolean;
    lastNameRequired?: boolean;
    autoFocus?: boolean;
    firstLabel?: string;
    lastLabel?: string;
}) {
    const t = useT();

    return (
        <>
            <Field
                label={firstLabel ?? t('First name')}
                error={errors[`${prefix}first_name`]}
            >
                <Input
                    name={`${prefix}first_name`}
                    defaultValue={firstName ?? ''}
                    required={required}
                    autoFocus={autoFocus}
                    autoComplete="given-name"
                />
            </Field>
            <Field
                label={lastLabel ?? t('Last name')}
                error={errors[`${prefix}last_name`]}
            >
                <Input
                    name={`${prefix}last_name`}
                    defaultValue={lastName ?? ''}
                    required={lastNameRequired}
                    autoComplete="family-name"
                />
            </Field>
        </>
    );
}
