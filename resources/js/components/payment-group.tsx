import { BookOpen } from 'lucide-react';
import { useT } from '@/lib/i18n';

/**
 * Which group (course, teacher) a payment goes to: money is kept per group,
 * and a student can study several courses at once.
 */
export default function PaymentGroup({
    group,
    course,
    teacher,
}: {
    group: string;
    course?: string | null;
    teacher?: string | null;
}) {
    const t = useT();

    return (
        <div className="grid gap-2">
            <span className="text-sm font-medium">{t('Group')}</span>
            <div className="flex items-center gap-3 rounded-lg border bg-muted/40 px-3 py-2">
                <BookOpen className="size-4 shrink-0 text-primary" />
                <div className="min-w-0">
                    <div className="truncate font-semibold">{group}</div>
                    {(course || teacher) && (
                        <div className="truncate text-xs text-muted-foreground">
                            {[course, teacher].filter(Boolean).join(' · ')}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
