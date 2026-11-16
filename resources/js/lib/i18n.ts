import { usePage } from '@inertiajs/react';
import { en } from '@/lang/en';
import { ru } from '@/lang/ru';
import { uz } from '@/lang/uz';
import type { Locale } from '@/types';

type Params = Record<string, string | number | boolean | null | undefined>;

const dictionaries: Record<Locale, Record<string, string>> = { uz, ru, en };

/**
 * Translate an English source string; ":name" placeholders are replaced from params.
 */
export function translate(locale: Locale, key: string, params?: Params): string {
    let text = dictionaries[locale]?.[key] ?? key;

    if (params) {
        for (const [name, value] of Object.entries(params)) {
            text = text.replaceAll(`:${name}`, String(value ?? ''));
        }
    }

    return text;
}

export function useLocale(): Locale {
    return usePage().props.locale ?? 'uz';
}

export function useT(): (key: string, params?: Params) => string {
    const locale = useLocale();

    return (key, params) => translate(locale, key, params);
}
