import { Camera, RotateCcw } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useT } from '@/lib/i18n';

/**
 * Takes a photo with the computer's (or phone's) camera and hands it back as a JPEG file.
 */
export default function CameraCaptureDialog({
    open,
    onOpenChange,
    onCapture,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onCapture: (file: File) => void;
}) {
    const t = useT();
    const video = useRef<HTMLVideoElement>(null);
    const [stream, setStream] = useState<MediaStream | null>(null);
    const [shot, setShot] = useState<Blob | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        let active: MediaStream | null = null;
        let cancelled = false;

        setError(null);
        setShot(null);

        if (!navigator.mediaDevices?.getUserMedia) {
            setError(t('This browser cannot use the camera.'));

            return;
        }

        navigator.mediaDevices
            .getUserMedia({
                video: {
                    facingMode: 'user',
                    width: { ideal: 1280 },
                    height: { ideal: 960 },
                },
                audio: false,
            })
            .then((media) => {
                if (cancelled) {
                    media.getTracks().forEach((track) => track.stop());

                    return;
                }

                active = media;
                setStream(media);
            })
            .catch(() =>
                setError(
                    t(
                        'No access to the camera. Allow the camera for this site in the browser.',
                    ),
                ),
            );

        return () => {
            cancelled = true;
            active?.getTracks().forEach((track) => track.stop());
            setStream(null);
        };
    }, [open, t]);

    useEffect(() => {
        if (video.current && stream) {
            video.current.srcObject = stream;
        }
    }, [stream, shot]);

    const take = () => {
        const element = video.current;

        if (!element || !element.videoWidth) {
            return;
        }

        const canvas = document.createElement('canvas');
        canvas.width = element.videoWidth;
        canvas.height = element.videoHeight;
        const context = canvas.getContext('2d');

        if (!context) {
            return;
        }

        // The preview is mirrored like a selfie; the saved photo is not.
        context.drawImage(element, 0, 0);
        canvas.toBlob((blob) => blob && setShot(blob), 'image/jpeg', 0.92);
    };

    const preview = shot ? URL.createObjectURL(shot) : null;

    useEffect(
        () => () => {
            if (preview) {
                URL.revokeObjectURL(preview);
            }
        },
        [preview],
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{t('Take a photo')}</DialogTitle>
                </DialogHeader>
                <div className="relative aspect-[4/3] overflow-hidden rounded-xl bg-black">
                    {error ? (
                        <p className="flex size-full items-center justify-center p-6 text-center text-sm text-white">
                            {error}
                        </p>
                    ) : preview ? (
                        <img
                            src={preview}
                            alt=""
                            className="size-full object-cover"
                        />
                    ) : (
                        <>
                            <video
                                ref={video}
                                autoPlay
                                playsInline
                                muted
                                className="size-full -scale-x-100 object-cover"
                            />
                            <div className="pointer-events-none absolute inset-[18%_28%] rounded-[50%] border-2 border-white/70" />
                        </>
                    )}
                </div>
                <p className="text-xs text-muted-foreground">
                    {t(
                        'Face straight at the camera, good light, no glasses or cap. The face fills the oval.',
                    )}
                </p>
                <DialogFooter>
                    {shot ? (
                        <>
                            <Button
                                variant="outline"
                                onClick={() => setShot(null)}
                            >
                                <RotateCcw />
                                {t('Retake')}
                            </Button>
                            <Button
                                onClick={() => {
                                    onCapture(
                                        new File([shot], 'face.jpg', {
                                            type: 'image/jpeg',
                                        }),
                                    );
                                    onOpenChange(false);
                                }}
                            >
                                {t('Save')}
                            </Button>
                        </>
                    ) : (
                        <Button disabled={!stream} onClick={take}>
                            <Camera />
                            {t('Take photo')}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
