import type { Locale } from '@/types';

/**
 * 1200000 → "1 200 000"
 */
export function formatMoney(value: number | null | undefined): string {
    if (value === null || value === undefined) {
        return '—';
    }

    const sign = value < 0 ? '−' : '';

    return (
        sign +
        Math.abs(Math.round(value))
            .toString()
            .replace(/\B(?=(\d{3})+(?!\d))/g, ' ')
    );
}

/**
 * "2026-11-17" → "17.11.2026"
 */
export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value.length === 10 ? `${value}T00:00:00` : value);

    return `${pad(date.getDate())}.${pad(date.getMonth() + 1)}.${date.getFullYear()}`;
}

export function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    return `${formatDate(value)} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

const months: Record<Locale, string[]> = {
    uz: [
        'Yanvar',
        'Fevral',
        'Mart',
        'Aprel',
        'May',
        'Iyun',
        'Iyul',
        'Avgust',
        'Sentabr',
        'Oktabr',
        'Noyabr',
        'Dekabr',
    ],
    ru: [
        'Январь',
        'Февраль',
        'Март',
        'Апрель',
        'Май',
        'Июнь',
        'Июль',
        'Август',
        'Сентябрь',
        'Октябрь',
        'Ноябрь',
        'Декабрь',
    ],
    en: [
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December',
    ],
};

/**
 * "2026-11" → "Noyabr 2026"
 */
export function formatMonth(value: string, locale: Locale): string {
    const [year, month] = value.split('-').map(Number);

    return `${months[locale][month - 1]} ${year}`;
}

const weekdays: Record<Locale, string[]> = {
    uz: ['Du', 'Se', 'Ch', 'Pa', 'Ju', 'Sh', 'Ya'],
    ru: ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'],
    en: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
};

/**
 * ISO weekday (1 = Monday) → short name.
 */
export function weekdayName(day: number, locale: Locale): string {
    return weekdays[locale][day - 1];
}

export function formatDays(days: number[], locale: Locale): string {
    return [...days]
        .sort((a, b) => a - b)
        .map((day) => weekdayName(day, locale))
        .join(', ');
}

export function today(): string {
    const date = new Date();

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

export function currentMonth(): string {
    return today().slice(0, 7);
}

/**
 * Shift "2026-11" by n months.
 */
export function shiftMonth(value: string, by: number): string {
    const [year, month] = value.split('-').map(Number);
    const date = new Date(year, month - 1 + by, 1);

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;
}

function pad(value: number): string {
    return value.toString().padStart(2, '0');
}
