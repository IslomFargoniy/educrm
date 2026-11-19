import type { ChangeEvent, ComponentProps } from 'react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import { formatMoney } from '@/lib/format';
import { useT } from '@/lib/i18n';

type MoneyInputProps = Omit<
    ComponentProps<'input'>,
    'type' | 'value' | 'defaultValue' | 'onChange' | 'min' | 'max' | 'step'
> & {
    name: string;
    defaultValue?: number | string | null;
    min?: number;
    max?: number;
    onValueChange?: (value: number | null) => void;
};

const group = (digits: string): string =>
    digits.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');

const digitsOf = (value: number | string | null | undefined): string => {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    const n = Math.trunc(Number(value));

    return Number.isFinite(n) && n >= 0 ? String(n) : '';
};

/** Caret position right after the `count`-th digit of `formatted`. */
function caretAfterDigit(formatted: string, count: number): number {
    if (count <= 0) {
        return 0;
    }

    let seen = 0;

    for (let i = 0; i < formatted.length; i++) {
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
 * Amount field that accepts digits only, shows them grouped by thousands
 * ("1 803 000") and submits the plain number under `name`.
 */
export default function MoneyInput({
    name,
    defaultValue,
    min,
    max,
    required,
    onValueChange,
    ...props
}: MoneyInputProps) {
    const t = useT();
    const [digits, setDigits] = useState(() => digitsOf(defaultValue));
    const inputRef = useRef<HTMLInputElement | null>(null);
    const caret = useRef<number | null>(null);
    const formatted = group(digits);

    useLayoutEffect(() => {
        if (caret.current !== null && inputRef.current) {
            inputRef.current.setSelectionRange(caret.current, caret.current);
            caret.current = null;
        }
    }, [formatted]);

    // Native form validation keeps working for min / max.
    useEffect(() => {
        const value = digits === '' ? null : Number(digits);
        let message = '';

        if (value !== null && min !== undefined && value < min) {
            message = t('Must be at least :amount', {
                amount: formatMoney(min),
            });
        } else if (value !== null && max !== undefined && value > max) {
            message = t('Must be at most :amount', {
                amount: formatMoney(max),
            });
        }

        inputRef.current?.setCustomValidity(message);
    }, [digits, min, max, t]);

    const handleChange = (event: ChangeEvent<HTMLInputElement>) => {
        const raw = event.target.value;
        const cursor = event.target.selectionStart ?? raw.length;
        let next = raw
            .replace(/\D/g, '')
            .replace(/^0+(?=\d)/, '')
            .slice(0, 15);
        let before = raw.slice(0, cursor).replace(/\D/g, '').length;

        // Backspace over a space removes the digit before it.
        if (next === digits && raw.length < formatted.length && before > 0) {
            next = next.slice(0, before - 1) + next.slice(before);
            before--;
        }

        caret.current = caretAfterDigit(group(next), before);
        setDigits(next);
        onValueChange?.(next === '' ? null : Number(next));
    };

    return (
        <>
            <Input
                {...props}
                ref={inputRef}
                type="text"
                inputMode="numeric"
                autoComplete="off"
                required={required}
                value={formatted}
                onChange={handleChange}
                className={['tabular-nums', props.className]
                    .filter(Boolean)
                    .join(' ')}
            />
            <input type="hidden" name={name} value={digits} />
        </>
    );
}
