import { Form } from '@inertiajs/react';
import type { FormComponentProps } from '@inertiajs/core';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';

type FormAction = {
    action: string;
    method: 'get' | 'post' | 'put' | 'patch' | 'delete';
};

/**
 * A dialog with an Inertia form; closes itself after a successful submit.
 */
export default function FormDialog({
    trigger,
    title,
    description,
    form,
    submitLabel,
    destructive = false,
    transform,
    children,
    wide = false,
}: {
    trigger: ReactNode;
    title: ReactNode;
    description?: ReactNode;
    form: FormAction;
    submitLabel?: string;
    destructive?: boolean;
    transform?: FormComponentProps['transform'];
    children: (errors: Record<string, string>) => ReactNode;
    wide?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const t = useT();

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className={wide ? 'sm:max-w-2xl' : 'sm:max-w-md'}>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description && (
                        <DialogDescription>{description}</DialogDescription>
                    )}
                </DialogHeader>
                <Form
                    {...form}
                    transform={transform}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            {children(errors)}
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setOpen(false)}
                                >
                                    {t('Cancel')}
                                </Button>
                                <Button
                                    type="submit"
                                    variant={
                                        destructive ? 'destructive' : 'default'
                                    }
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    {submitLabel ?? t('Save')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
