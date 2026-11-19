import { useState } from 'react';
import { formatMoney } from '@/lib/format';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export type ChartItem = { label: string; value: number };

/** Validated categorical order (see --series-* in app.css); the 8th slot and beyond fold into "Other". */
const SERIES = [1, 2, 3, 4, 5, 6, 7].map((n) => `var(--series-${n})`);
const OTHER = 'var(--series-other)';

const format = (value: number, money: boolean) =>
    money ? formatMoney(value) : value.toLocaleString('ru-RU');

/**
 * Horizontal bars, one measure: one hue, value labels at the end, a tooltip on hover.
 */
export function BarChart({
    items,
    money = true,
    limit = 20,
}: {
    items: ChartItem[];
    money?: boolean;
    limit?: number;
}) {
    const t = useT();
    const [hover, setHover] = useState<number | null>(null);
    const rows = [...items]
        .filter((i) => i.value !== 0)
        .sort((a, b) => b.value - a.value);
    const shown = rows.slice(0, limit);
    const max = Math.max(1, ...shown.map((r) => Math.abs(r.value)));

    if (shown.length === 0) {
        return <Empty />;
    }

    return (
        <div className="space-y-1.5" role="list">
            {shown.map((row, index) => (
                <div
                    key={row.label + index}
                    role="listitem"
                    className={cn(
                        'grid grid-cols-[minmax(6rem,12rem)_1fr_auto] items-center gap-3 rounded-md px-1 py-0.5 text-sm',
                        hover === index && 'bg-muted/60',
                    )}
                    onMouseEnter={() => setHover(index)}
                    onMouseLeave={() => setHover(null)}
                    title={`${row.label}: ${format(row.value, money)}`}
                >
                    <span className="truncate text-secondary-foreground">
                        {row.label}
                    </span>
                    <div className="h-4">
                        <div
                            className="h-4 rounded-r-[4px]"
                            style={{
                                width: `${Math.max(1.5, (Math.abs(row.value) / max) * 100)}%`,
                                background: SERIES[0],
                                opacity:
                                    hover === null || hover === index
                                        ? 1
                                        : 0.55,
                            }}
                        />
                    </div>
                    <span className="text-right font-semibold tabular-nums">
                        {format(row.value, money)}
                    </span>
                </div>
            ))}
            {rows.length > shown.length && (
                <p className="pt-1 text-xs text-muted-foreground">
                    {t(':count more not shown', {
                        count: rows.length - shown.length,
                    })}
                </p>
            )}
        </div>
    );
}

/**
 * Donut of shares: largest seven slices in fixed hue order, the rest as "Other"; legend with value and percent.
 */
export function PieChart({
    items,
    money = true,
}: {
    items: ChartItem[];
    money?: boolean;
}) {
    const t = useT();
    const [hover, setHover] = useState<number | null>(null);
    const positive = [...items]
        .filter((i) => i.value > 0)
        .sort((a, b) => b.value - a.value);

    if (positive.length === 0) {
        return <Empty />;
    }

    const head = positive.slice(0, positive.length > 8 ? 7 : 8);
    const rest = positive.slice(head.length);
    const slices = [
        ...head.map((item, i) => ({ ...item, color: SERIES[i] ?? OTHER })),
        ...(rest.length > 0
            ? [
                  {
                      label: t('Other (:count)', { count: rest.length }),
                      value: rest.reduce((s, i) => s + i.value, 0),
                      color: OTHER,
                  },
              ]
            : []),
    ];
    const total = slices.reduce((s, i) => s + i.value, 0);
    const radius = 70;
    const width = 26;
    const circumference = 2 * Math.PI * radius;
    let offset = 0;
    const active = hover === null ? null : slices[hover];

    return (
        <div className="flex flex-col items-center gap-6 sm:flex-row sm:items-start">
            <svg
                viewBox="0 0 200 200"
                className="size-52 shrink-0"
                role="img"
                aria-label={slices
                    .map((s) => `${s.label} ${format(s.value, money)}`)
                    .join(', ')}
            >
                <g transform="rotate(-90 100 100)">
                    {slices.map((slice, index) => {
                        const length = (slice.value / total) * circumference;
                        const dash = Math.max(
                            0,
                            length - (slices.length > 1 ? 2 : 0),
                        );
                        const circle = (
                            <circle
                                key={slice.label + index}
                                cx="100"
                                cy="100"
                                r={radius}
                                fill="none"
                                stroke={slice.color}
                                strokeWidth={
                                    hover === index ? width + 6 : width
                                }
                                strokeDasharray={`${dash} ${circumference - dash}`}
                                strokeDashoffset={-offset}
                                opacity={
                                    hover === null || hover === index ? 1 : 0.45
                                }
                                onMouseEnter={() => setHover(index)}
                                onMouseLeave={() => setHover(null)}
                                className="cursor-pointer transition-[stroke-width,opacity]"
                            >
                                <title>{`${slice.label}: ${format(slice.value, money)} (${Math.round((slice.value / total) * 100)}%)`}</title>
                            </circle>
                        );
                        offset += length;

                        return circle;
                    })}
                </g>
                <text
                    x="100"
                    y="94"
                    textAnchor="middle"
                    className="fill-muted-foreground text-[11px]"
                >
                    {active ? active.label.slice(0, 22) : t('Total')}
                </text>
                <text
                    x="100"
                    y="114"
                    textAnchor="middle"
                    className="fill-foreground text-[15px] font-bold"
                >
                    {format(active ? active.value : total, money)}
                </text>
            </svg>
            <ul className="w-full min-w-0 flex-1 space-y-1 text-sm">
                {slices.map((slice, index) => (
                    <li
                        key={slice.label + index}
                        className={cn(
                            'grid grid-cols-[auto_1fr_auto_3rem] items-center gap-2 rounded-md px-2 py-1',
                            hover === index && 'bg-muted/60',
                        )}
                        onMouseEnter={() => setHover(index)}
                        onMouseLeave={() => setHover(null)}
                    >
                        <span
                            className="size-3 rounded-sm"
                            style={{ background: slice.color }}
                        />
                        <span className="truncate text-secondary-foreground">
                            {slice.label}
                        </span>
                        <span className="font-semibold tabular-nums">
                            {format(slice.value, money)}
                        </span>
                        <span className="text-right text-xs text-muted-foreground tabular-nums">
                            {Math.round((slice.value / total) * 100)}%
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function Empty() {
    const t = useT();

    return (
        <p className="py-6 text-center text-sm text-muted-foreground">
            {t('No data.')}
        </p>
    );
}
