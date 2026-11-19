import { useState } from 'react';
import Field from '@/components/field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import MoneyInput from '@/components/money-input';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { useCan } from '@/hooks/use-can';
import { today, weekdayName } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';

export type GroupFormOptions = {
    teachers: { id: number; name: string }[];
    courses: { id: number; name: string }[];
    rooms: { id: number; name: string; branch_id: number }[];
    defaultBranchId: number | null;
};

export type GroupValues = {
    branch_id?: number;
    name?: string;
    subject?: string | null;
    course_id?: number | null;
    teacher_id?: number | null;
    room_id?: number | null;
    teacher_percent?: number;
    days?: number[];
    start_time?: string;
    end_time?: string;
    start_date?: string;
    end_date?: string | null;
    status?: string;
};

export default function GroupFormFields({
    errors,
    options,
    values = {},
    creating,
}: {
    errors: Record<string, string>;
    options: GroupFormOptions;
    values?: GroupValues;
    creating: boolean;
}) {
    const t = useT();
    const locale = useLocale();
    const { can } = useCan();
    const branchId = values.branch_id ?? options.defaultBranchId ?? undefined;
    const [days, setDays] = useState<number[]>(values.days ?? [1, 3, 5]);
    const toggleDay = (day: number, checked: boolean) =>
        setDays((current) =>
            checked
                ? [...current.filter((d) => d !== day), day].sort(
                      (a, b) => a - b,
                  )
                : current.filter((d) => d !== day),
        );
    const sameDays = (a: number[], b: number[]) =>
        a.length === b.length && a.every((d) => b.includes(d));
    const dayPresets = [
        { label: t('Odd days'), days: [1, 3, 5] },
        { label: t('Even days'), days: [2, 4, 6] },
    ];

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <input type="hidden" name="branch_id" value={branchId ?? ''} />
            <Field label={t('Group name')} error={errors.name}>
                <Input
                    name="name"
                    defaultValue={values.name}
                    required
                    autoFocus
                />
            </Field>
            <Field label={t('Course')} error={errors.course_id}>
                <NativeSelect
                    name="course_id"
                    defaultValue={values.course_id ?? ''}
                >
                    <option value="">—</option>
                    {options.courses.map((course) => (
                        <option key={course.id} value={course.id}>
                            {course.name}
                        </option>
                    ))}
                </NativeSelect>
            </Field>
            <Field label={t('Teacher')} error={errors.teacher_id}>
                <NativeSelect
                    name="teacher_id"
                    defaultValue={values.teacher_id ?? ''}
                >
                    <option value="">—</option>
                    {options.teachers.map((teacher) => (
                        <option key={teacher.id} value={teacher.id}>
                            {teacher.name}
                        </option>
                    ))}
                </NativeSelect>
            </Field>
            <Field label={t('Room')} error={errors.room_id}>
                <NativeSelect
                    name="room_id"
                    defaultValue={values.room_id ?? ''}
                >
                    <option value="">—</option>
                    {options.rooms
                        .filter((r) => !branchId || r.branch_id === branchId)
                        .map((room) => (
                            <option key={room.id} value={room.id}>
                                {room.name}
                            </option>
                        ))}
                </NativeSelect>
            </Field>
            <Field
                label={t('Days')}
                error={errors.days}
                className="sm:col-span-2"
            >
                <div className="flex flex-wrap items-center gap-3">
                    {[1, 2, 3, 4, 5, 6, 7].map((day) => (
                        <Label
                            key={day}
                            className="flex items-center gap-1.5 font-normal"
                        >
                            <Checkbox
                                name="days[]"
                                value={String(day)}
                                checked={days.includes(day)}
                                onCheckedChange={(checked) =>
                                    toggleDay(day, checked === true)
                                }
                            />
                            {weekdayName(day, locale)}
                        </Label>
                    ))}
                    <div className="ml-auto flex gap-1.5">
                        {dayPresets.map((preset) => (
                            <Button
                                key={preset.label}
                                type="button"
                                size="sm"
                                variant={
                                    sameDays(days, preset.days)
                                        ? 'default'
                                        : 'outline'
                                }
                                className="h-7 px-2.5 text-xs"
                                onClick={() => setDays(preset.days)}
                            >
                                {preset.label}
                            </Button>
                        ))}
                    </div>
                </div>
            </Field>
            <Field label={t('Starts at')} error={errors.start_time}>
                <Input
                    name="start_time"
                    type="time"
                    defaultValue={values.start_time ?? '09:00'}
                    required
                />
            </Field>
            <Field label={t('Ends at')} error={errors.end_time}>
                <Input
                    name="end_time"
                    type="time"
                    defaultValue={values.end_time ?? '10:30'}
                    required
                />
            </Field>
            <Field label={t('Start date')} error={errors.start_date}>
                <Input
                    name="start_date"
                    type="date"
                    defaultValue={values.start_date ?? today()}
                    required
                />
            </Field>
            <Field label={t('End date (optional)')} error={errors.end_date}>
                <Input
                    name="end_date"
                    type="date"
                    defaultValue={values.end_date ?? ''}
                />
            </Field>
            {(creating || can('groups.prices')) && (
                <Field
                    label={t('Teacher percent (default)')}
                    error={errors.teacher_percent}
                >
                    <Input
                        name="teacher_percent"
                        type="number"
                        min={0}
                        max={100}
                        step={1}
                        defaultValue={values.teacher_percent ?? 40}
                        required
                    />
                </Field>
            )}
            {creating && (
                <>
                    <Field
                        label={t('Monthly price')}
                        error={errors.monthly_price}
                    >
                        <MoneyInput name="monthly_price" min={0} required />
                    </Field>
                    <Field
                        label={t('Lessons per month (N)')}
                        error={errors.lessons_per_month}
                        hint={t('Lesson price = monthly price / N')}
                    >
                        <Input
                            name="lessons_per_month"
                            type="number"
                            min={1}
                            max={60}
                            defaultValue={12}
                            required
                        />
                    </Field>
                </>
            )}
            {!creating && (
                <Field label={t('Status')} error={errors.status}>
                    <NativeSelect
                        name="status"
                        defaultValue={values.status ?? 'active'}
                    >
                        <option value="active">{t('status.active')}</option>
                        <option value="archived">{t('status.archived')}</option>
                    </NativeSelect>
                </Field>
            )}
        </div>
    );
}
