import { router } from '@inertiajs/react';
import { Camera, ScanFace, Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import CameraCaptureDialog from '@/components/camera-capture-dialog';
import RowNumber from '@/components/row-number';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { formatDateTime } from '@/lib/format';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import faceRoutes from '@/routes/students/face';

export type StudentFaceProps = {
    photo: string | null;
    devices: {
        id: number;
        name: string;
        status: 'synced' | 'failed' | 'removed';
        error: string | null;
        synced_at: string | null;
    }[];
    has_devices: boolean;
};

export type StudentVisit = {
    id: number;
    scanned_at: string;
    direction: 'in' | 'out';
    device: string | null;
};

/**
 * Face photo for the terminals (Face ID), where it was written, and the latest passes.
 */
export default function StudentFace({
    studentId,
    face,
    visits,
}: {
    studentId: number;
    face: StudentFaceProps;
    visits: StudentVisit[];
}) {
    const t = useT();
    const { can } = useCan();
    const input = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [cameraOpen, setCameraOpen] = useState(false);

    if (!face.has_devices && !face.photo && visits.length === 0) {
        return null;
    }

    const upload = (file: File | undefined) => {
        if (!file) {
            return;
        }

        router.post(
            faceRoutes.store(studentId).url,
            { photo: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setUploading(true),
                onFinish: () => {
                    setUploading(false);

                    if (input.current) {
                        input.current.value = '';
                    }
                },
            },
        );
    };

    return (
        <section className="grid gap-4 rounded-2xl border bg-card p-4 md:grid-cols-[auto_1fr]">
            <div className="flex flex-col items-center gap-2">
                <div className="flex size-32 items-center justify-center overflow-hidden rounded-xl border bg-muted">
                    {face.photo ? (
                        <img
                            src={face.photo}
                            alt={t('Face photo')}
                            className="size-full object-cover"
                        />
                    ) : (
                        <ScanFace className="size-12 text-muted-foreground" />
                    )}
                </div>
                {(can('students.update') || can('students.face')) && (
                    <div className="flex flex-wrap justify-center gap-1">
                        <input
                            ref={input}
                            type="file"
                            accept="image/*"
                            className="hidden"
                            onChange={(e) => upload(e.target.files?.[0])}
                        />
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={uploading}
                            onClick={() => setCameraOpen(true)}
                        >
                            <Camera />
                            {t('Camera')}
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={uploading}
                            onClick={() => input.current?.click()}
                        >
                            <Upload />
                            {t('Upload')}
                        </Button>
                        <CameraCaptureDialog
                            open={cameraOpen}
                            onOpenChange={setCameraOpen}
                            onCapture={upload}
                        />
                        {face.photo && (
                            <Button
                                size="icon"
                                variant="ghost"
                                className="size-8 text-danger"
                                aria-label={t('Delete')}
                                onClick={() =>
                                    confirm(
                                        t(
                                            'Delete the photo? The student is removed from the devices.',
                                        ),
                                    ) &&
                                    router.delete(
                                        faceRoutes.destroy(studentId).url,
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Trash2 />
                            </Button>
                        )}
                    </div>
                )}
            </div>

            <div className="flex min-w-0 flex-col gap-3 text-sm">
                <div>
                    <h2 className="font-bold">Face ID</h2>
                    <p className="text-xs text-muted-foreground">
                        {t(
                            'A clear front photo of the face. It is sent to the devices of the branches the student studies in.',
                        )}
                    </p>
                </div>
                {face.devices.length > 0 && (
                    <ul className="flex flex-wrap gap-1.5">
                        {face.devices.map((d) => (
                            <li
                                key={d.id}
                                title={d.error ?? undefined}
                                className={cn(
                                    'rounded-full border px-2.5 py-0.5 text-xs font-medium',
                                    d.status === 'synced' &&
                                        'border-success/40 text-success',
                                    d.status === 'failed' &&
                                        'border-danger/40 text-danger',
                                    d.status === 'removed' &&
                                        'text-muted-foreground line-through',
                                )}
                            >
                                {d.status === 'synced'
                                    ? '✓'
                                    : d.status === 'failed'
                                      ? '✗'
                                      : '–'}{' '}
                                {d.name}
                                {d.status === 'failed' && d.error && (
                                    <span className="ml-1 opacity-80">
                                        ({d.error})
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                )}

                <div>
                    <h3 className="mb-1 text-xs font-semibold text-muted-foreground uppercase">
                        {t('Arrivals and departures')}
                    </h3>
                    {visits.length === 0 ? (
                        <p className="text-xs text-muted-foreground">—</p>
                    ) : (
                        <ul className="max-h-48 divide-y overflow-y-auto rounded-lg border">
                            {visits.map((v, index) => (
                                <li
                                    key={v.id}
                                    className="flex items-center gap-2 px-3 py-1.5 text-xs"
                                >
                                    <RowNumber value={index + 1} />
                                    <span
                                        className={cn(
                                            'font-semibold',
                                            v.direction === 'in'
                                                ? 'text-success'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {v.direction === 'in'
                                            ? t('Came in')
                                            : t('Went out')}
                                    </span>
                                    <span className="tabular-nums">
                                        {formatDateTime(v.scanned_at)}
                                    </span>
                                    <span className="ml-auto text-muted-foreground">
                                        {v.device}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </section>
    );
}
