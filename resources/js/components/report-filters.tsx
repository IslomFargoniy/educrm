import { router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { weekdayName } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export type ReportFilterValues = {
    group_id: number | null;
    teacher_id: number | null;
    course_id: number | null;
    student_id: number | null;
    days: number[];
};

export type ReportFilterOptions = {
    groups: {
        id: number;
        name: string;
        teacher_id: number | null;
        course_id: number | null;
        days: number[] | null;
    }[];
    teachers: { id: number; name: string }[];
    courses: { id: number; name: string }[];
    students: { id: number; full_name: string }[];
};

/**
 * Group / teacher / course / student / lesson weekday filters of the reports page (kept in the URL).
 */
export default function ReportFilters({
    filters,
    options,
}: {
    filters: ReportFilterValues;
    options: ReportFilterOptions;
}) {
    const t = useT();
    const locale = useLocale();
    const [studentSearch, setStudentSearch] = useState('');

    const apply = (next: Partial<ReportFilterValues>) => {
        const merged = { ...filters, ...next };
        const params: Record<string, string | number | number[]> =
            Object.fromEntries(
                [...new URLSearchParams(window.location.search)].filter(
                    ([key]) =>
                        ![
                            'group_id',
                            'teacher_id',
                            'course_id',
                            'student_id',
                        ].includes(key) && !key.startsWith('days'),
                ),
            );

        (
            ['group_id', 'teacher_id', 'course_id', 'student_id'] as const
        ).forEach((key) => {
            if (merged[key]) {
                params[key] = merged[key] as number;
            }
        });

        if (merged.days.length > 0) {
            params.days = merged.days;
        }

        router.get(window.location.pathname, params, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const groups = options.groups.filter(
        (g) =>
            (!filters.teacher_id || g.teacher_id === filters.teacher_id) &&
            (!filters.course_id || g.course_id === filters.course_id),
    );
    const needle = studentSearch.trim().toLowerCase();
    const students = options.students.filter(
        (s) =>
            s.id === filters.student_id ||
            needle === '' ||
            s.full_name.toLowerCase().includes(needle),
    );
    const active =
        Boolean(
            filters.group_id ||
            filters.teacher_id ||
            filters.course_id ||
            filters.student_id,
        ) || filters.days.length > 0;
    const toId = (value: string) => (value === '' ? null : Number(value));

    return (
        <div className="flex flex-col gap-2 rounded-xl border bg-card p-3">
            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <NativeSelect
                    value={filters.teacher_id ?? ''}
                    onChange={(e) =>
                        apply({
                            teacher_id: toId(e.target.value),
                            group_id: null,
                        })
                    }
                    aria-label={t('Teacher')}
                >
                    <option value="">{t('All teachers')}</option>
                    {options.teachers.map((o) => (
                        <option key={o.id} value={o.id}>
                            {o.name}
                        </option>
                    ))}
                </NativeSelect>
                <NativeSelect
                    value={filters.course_id ?? ''}
                    onChange={(e) =>
                        apply({
                            course_id: toId(e.target.value),
                            group_id: null,
                        })
                    }
                    aria-label={t('Course')}
                >
                    <option value="">{t('All courses')}</option>
                    {options.courses.map((o) => (
                        <option key={o.id} value={o.id}>
                            {o.name}
                        </option>
                    ))}
                </NativeSelect>
                <NativeSelect
                    value={filters.group_id ?? ''}
                    onChange={(e) => apply({ group_id: toId(e.target.value) })}
                    aria-label={t('Group')}
                >
                    <option value="">{t('All groups')}</option>
                    {groups.map((o) => (
                        <option key={o.id} value={o.id}>
                            {o.name}
                        </option>
                    ))}
                </NativeSelect>
                <div className="flex gap-1">
                    <Input
                        value={studentSearch}
                        onChange={(e) => setStudentSearch(e.target.value)}
                        placeholder={t('Find student')}
                        className="w-28 shrink-0"
                    />
                    <NativeSelect
                        value={filters.student_id ?? ''}
                        onChange={(e) =>
                            apply({ student_id: toId(e.target.value) })
                        }
                        aria-label={t('Student')}
                    >
                        <option value="">{t('All students')}</option>
                        {students.map((o) => (
                            <option key={o.id} value={o.id}>
                                {o.full_name}
                            </option>
                        ))}
                    </NativeSelect>
                </div>
            </div>
            <div className="flex flex-wrap items-center gap-1.5">
                <span className="mr-1 text-xs font-semibold text-muted-foreground">
                    {t('Lesson days')}:
                </span>
                {[1, 2, 3, 4, 5, 6, 7].map((day) => {
                    const on = filters.days.includes(day);

                    return (
                        <button
                            key={day}
                            type="button"
                            onClick={() =>
                                apply({
                                    days: on
                                        ? filters.days.filter((d) => d !== day)
                                        : [...filters.days, day].sort(
                                              (a, b) => a - b,
                                          ),
                                })
                            }
                            className={cn(
                                'rounded-md border px-2.5 py-1 text-xs font-semibold',
                                on
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {weekdayName(day, locale)}
                        </button>
                    );
                })}
                <button
                    type="button"
                    onClick={() => apply({ days: [1, 3, 5] })}
                    className="rounded-md border px-2.5 py-1 text-xs font-semibold text-muted-foreground hover:text-foreground"
                >
                    {t('Odd days')}
                </button>
                <button
                    type="button"
                    onClick={() => apply({ days: [2, 4, 6] })}
                    className="rounded-md border px-2.5 py-1 text-xs font-semibold text-muted-foreground hover:text-foreground"
                >
                    {t('Even days')}
                </button>
                {active && (
                    <Button
                        size="sm"
                        variant="ghost"
                        className="ml-auto"
                        onClick={() =>
                            apply({
                                group_id: null,
                                teacher_id: null,
                                course_id: null,
                                student_id: null,
                                days: [],
                            })
                        }
                    >
                        <X />
                        {t('Clear filters')}
                    </Button>
                )}
            </div>
            {active && (
                <p className="text-xs text-muted-foreground">
                    {t(
                        'Filtered: payments, groups, courses, debtors and leads follow the filter. Expenses and cashbox change are not tied to a group, so they are not shown.',
                    )}
                </p>
            )}
        </div>
    );
}
