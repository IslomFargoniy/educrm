import type { ChangeEvent, ComponentProps, Ref } from 'react';
import { useLayoutEffect, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import { formatPhone, phoneLocalDigits } from '@/lib/format';

type PhoneInputProps = Omit<
    ComponentProps<'input'>,
    'type' | 'value' | 'defaultValue' | 'onChange'
> & {
    defaultValue?: string | null;
    onValueChange?: (value: string) => void;
    ref?: Ref<HTMLInputElement>;
};

/** Position in `formatted` right after its `count`-th local digit. */
function caretAfterLocalDigit(formatted: string, count: number): number {
    if (count <= 0) {
        return Math.min(formatted.length, 5);
    }

    let seen = 0;

    for (let i = 5; i < formatted.length; i++) {
        if (/\d/.test(formatted[i])) {
            seen++;

            if (seen === count) {
                return i + 1;
            }
        }
    }

    return formatted.length;
}

/**
 * Phone field that accepts digits only and formats them as
 * "+998 90 123 45 67" while typing.
 */
export default function PhoneInput({
    defaultValue,
    onValueChange,
    ref,
    ...props
}: PhoneInputProps) {
    const [value, setValue] = useState(() => formatPhone(defaultValue));
    const inputRef = useRef<HTMLInputElement | null>(null);
    const caret = useRef<number | null>(null);

    useLayoutEffect(() => {
        if (caret.current !== null && inputRef.current) {
            inputRef.current.setSelectionRange(caret.current, caret.current);
            caret.current = null;
        }
    }, [value]);

    const handleChange = (event: ChangeEvent<HTMLInputElement>) => {
        const raw = event.target.value;
        const cursor = event.target.selectionStart ?? raw.length;
        let local = phoneLocalDigits(raw);
        const allDigits = raw.replace(/\D/g, '');
        const prefixDigits =
            allDigits.length - local.length > 0 &&
            (raw.trim().startsWith('+998') || allDigits.startsWith('998'))
                ? 3
                : 0;
        let digitsBeforeCaret = Math.min(
            local.length,
            Math.max(
                0,
                raw.slice(0, cursor).replace(/\D/g, '').length - prefixDigits,
            ),
        );

        if (
            local === phoneLocalDigits(value) &&
            raw.length < value.length &&
            digitsBeforeCaret > 0
        ) {
            local =
                local.slice(0, digitsBeforeCaret - 1) +
                local.slice(digitsBeforeCaret);
            digitsBeforeCaret--;
        }

        const formatted = formatPhone(local);
        caret.current = caretAfterLocalDigit(formatted, digitsBeforeCaret);
        setValue(formatted);
        onValueChange?.(formatted);
    };

    return (
        <Input
            {...props}
            ref={(node) => {
                inputRef.current = node;

                if (typeof ref === 'function') {
                    ref(node);
                } else if (ref) {
                    ref.current = node;
                }
            }}
            type="tel"
            inputMode="numeric"
            autoComplete={props.autoComplete ?? 'tel'}
            placeholder={props.placeholder ?? '+998 90 123 45 67'}
            maxLength={17}
            value={value}
            onChange={handleChange}
        />
    );
}
