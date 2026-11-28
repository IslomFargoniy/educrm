import { router } from '@inertiajs/react';
import { Search, UserPlus, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import Field from '@/components/field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';
import groups from '@/routes/groups';
import groupEnrollments from '@/routes/groups/enrollments';

type Candidate = { id: number; name: string; phone: string | null };

const today = () => new Date().toLocaleDateString('en-CA');

/**
 * "Add students" on the group page: search the branch's students, tick several and add them at once.
 */
export default function AddStudentsDialog({ groupId }: { groupId: number }) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<Candidate[]>([]);
    const [searching, setSearching] = useState(false);
    const [selected, setSelected] = useState<Candidate[]>([]);
    const [joinedAt, setJoinedAt] = useState(today());
    const [trialLessons, setTrialLessons] = useState('0');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }
        const controller = new AbortController();
        const timer = setTimeout(() => {
            setSearching(true);
            fetch(
                groups.studentCandidates.url(groupId, {
                    query: { q: query.trim() },
                }),
                {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                },
            )
                .then((response) => response.json())
                .then((body: { data: Candidate[] }) => setResults(body.data))
                .catch(() => {})
                .finally(() => setSearching(false));
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [open, query, groupId]);

    const isSelected = (id: number) => selected.some((s) => s.id === id);
    const toggle = (candidate: Candidate) =>
        setSelected((current) =>
            current.some((s) => s.id === candidate.id)
                ? current.filter((s) => s.id !== candidate.id)
                : [...current, candidate],
        );

    const reset = () => {
        setQuery('');
        setResults([]);
        setSelected([]);
        setJoinedAt(today());
        setTrialLessons('0');
        setErrors({});
    };

    const submit = () => {
        router.post(
            groupEnrollments.store.url(groupId),
            {
                student_ids: selected.map((s) => s.id),
                joined_at: joinedAt,
                trial_lessons: Number(trialLessons) || 0,
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: (e) => setErrors(e),
                onSuccess: () => {
                    setOpen(false);
                    reset();
                },
            },
        );
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (!next) {
                    reset();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button>
                    <UserPlus />
                    {t('Add students')}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{t('Add students')}</DialogTitle>
                    <DialogDescription>
                        {t('Tick the students to add to this group')}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-3">
                    <div className="relative">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            autoFocus
                            className="pl-9"
                            placeholder={t('Name or phone')}
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                        />
                        {searching && (
                            <Spinner className="absolute top-1/2 right-3 -translate-y-1/2" />
                        )}
                    </div>

                    {selected.length > 0 && (
                        <div className="flex flex-wrap gap-1.5">
                            {selected.map((s) => (
                                <button
                                    key={s.id}
                                    type="button"
                                    onClick={() => toggle(s)}
                                    className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary hover:bg-primary/20"
                                >
                                    {s.name}
                                    <X className="size-3" />
                                </button>
                            ))}
                        </div>
                    )}

                    <div className="max-h-72 overflow-y-auto rounded-lg border">
                        {results.map((s) => (
                            <label
                                key={s.id}
                                className="flex cursor-pointer items-center gap-3 border-b px-3 py-2 last:border-b-0 hover:bg-muted"
                            >
                                <Checkbox
                                    checked={isSelected(s.id)}
                                    onCheckedChange={() => toggle(s)}
                                />
                                <span className="min-w-0">
                                    <span className="block truncate font-medium">
                                        {s.name}
                                    </span>
                                    {s.phone && (
                                        <span className="block text-xs text-muted-foreground">
                                            {s.phone}
                                        </span>
                                    )}
                                </span>
                            </label>
                        ))}
                        {!searching && results.length === 0 && (
                            <p className="px-3 py-6 text-center text-sm text-muted-foreground">
                                {t('No students found')}
                            </p>
                        )}
                    </div>
                    {errors.student_ids && (
                        <p className="text-sm text-destructive">
                            {errors.student_ids}
                        </p>
                    )}

                    <div className="grid grid-cols-2 gap-3">
                        <Field label={t('Joined on')} error={errors.joined_at}>
                            <Input
                                type="date"
                                value={joinedAt}
                                onChange={(e) => setJoinedAt(e.target.value)}
                                required
                            />
                        </Field>
                        <Field
                            label={t('Trial lessons')}
                            error={errors.trial_lessons}
                        >
                            <Input
                                type="number"
                                min={0}
                                max={30}
                                value={trialLessons}
                                onChange={(e) => setTrialLessons(e.target.value)}
                            />
                        </Field>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() => setOpen(false)}
                    >
                        {t('Cancel')}
                    </Button>
                    <Button
                        type="button"
                        disabled={processing || selected.length === 0}
                        onClick={submit}
                    >
                        {processing && <Spinner />}
                        {selected.length > 0
                            ? t('Add :count', { count: selected.length })
                            : t('Add')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
