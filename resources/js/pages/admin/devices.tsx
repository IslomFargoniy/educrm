import { Form, Head, router } from '@inertiajs/react';
import { Copy, RefreshCw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import PageHeader from '@/components/page-header';
import RowNumber from '@/components/row-number';
import SettingsTabs from '@/components/settings-tabs';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { formatDateTime } from '@/lib/format';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';

type Device = {
    id: number;
    name: string;
    branch_id: number;
    branch: string;
    direction: 'in' | 'out';
    isup_id: string;
    isup_key: string;
    is_active: boolean;
    is_online: boolean;
    ip: string | null;
    last_seen_at: string | null;
    synced_count: number;
    failed_count: number;
};

type Props = {
    devices: Device[];
    branches: { id: number; name: string }[];
    server: { address: string; port: number };
    gatewayRunning: boolean;
};

export default function Devices({
    devices,
    branches,
    server,
    gatewayRunning,
}: Props) {
    const t = useT();
    const [openId, setOpenId] = useState<number | null>(null);

    return (
        <>
            <Head title={t('Devices')} />
            <div className="flex flex-col gap-5 p-4 md:p-6">
                <SettingsTabs />
                <div className="flex max-w-3xl flex-col gap-5">
                    <PageHeader
                        title={t('Devices')}
                        description={t(
                            'Hikvision face terminals connected over ISUP. A student who passes is pre-marked as present in the lesson attendance.',
                        )}
                    />

                    {!gatewayRunning && (
                        <div className="rounded-xl border border-danger/40 bg-danger/10 p-3 text-sm">
                            {t(
                                'The device gateway is not running on the server, so devices cannot connect.',
                            )}
                        </div>
                    )}

                    <Form
                        {...admin.devices.store.form()}
                        resetOnSuccess={['name']}
                        options={{ preserveScroll: true }}
                        className="grid gap-2 rounded-xl border bg-card p-3 sm:grid-cols-[1fr_1fr_auto_auto]"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Input
                                    name="name"
                                    required
                                    placeholder={t('e.g. Main entrance')}
                                    aria-invalid={Boolean(errors.name)}
                                />
                                <NativeSelect name="branch_id" required>
                                    {branches.map((b) => (
                                        <option key={b.id} value={b.id}>
                                            {b.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                                <NativeSelect
                                    name="direction"
                                    defaultValue="in"
                                >
                                    <option value="in">{t('Entrance')}</option>
                                    <option value="out">{t('Exit')}</option>
                                </NativeSelect>
                                <Button disabled={processing}>
                                    {t('Add')}
                                </Button>
                            </>
                        )}
                    </Form>

                    {devices.length > 0 && (
                        <ul className="divide-y rounded-xl border bg-card">
                            {devices.map((device, index) => (
                                <li
                                    key={device.id}
                                    className={cn(
                                        'px-4 py-3 text-sm',
                                        !device.is_active && 'opacity-60',
                                    )}
                                >
                                    <button
                                        type="button"
                                        className="flex w-full items-center gap-3 text-left"
                                        onClick={() =>
                                            setOpenId(
                                                openId === device.id
                                                    ? null
                                                    : device.id,
                                            )
                                        }
                                    >
                                        <RowNumber value={index + 1} />
                                        <span
                                            className={cn(
                                                'size-2.5 shrink-0 rounded-full',
                                                device.is_online
                                                    ? 'bg-success'
                                                    : 'bg-danger',
                                            )}
                                            title={
                                                device.is_online
                                                    ? t('Online')
                                                    : t('Offline')
                                            }
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span className="font-semibold">
                                                {device.name}
                                            </span>
                                            <span className="block text-xs text-muted-foreground">
                                                {device.branch} ·{' '}
                                                {device.direction === 'in'
                                                    ? t('Entrance')
                                                    : t('Exit')}{' '}
                                                ·{' '}
                                                {device.is_online
                                                    ? t('Online')
                                                    : device.last_seen_at
                                                      ? t('Last seen :time', {
                                                            time: formatDateTime(
                                                                device.last_seen_at,
                                                            ),
                                                        })
                                                      : t('Never connected')}
                                            </span>
                                        </span>
                                        <span className="shrink-0 text-right text-xs text-muted-foreground">
                                            {t(':count students', {
                                                count: device.synced_count,
                                            })}
                                            {device.failed_count > 0 && (
                                                <span className="block text-danger">
                                                    {t(':count failed', {
                                                        count: device.failed_count,
                                                    })}
                                                </span>
                                            )}
                                        </span>
                                    </button>

                                    {openId === device.id && (
                                        <DeviceDetails
                                            device={device}
                                            branches={branches}
                                            server={server}
                                        />
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </>
    );
}

function DeviceDetails({
    device,
    branches,
    server,
}: {
    device: Device;
    branches: Props['branches'];
    server: Props['server'];
}) {
    const t = useT();
    const settings: [string, string][] = [
        [t('Server address'), server.address],
        [t('Port'), String(server.port)],
        ['Device ID', device.isup_id],
        [t('ISUP key'), device.isup_key],
    ];

    return (
        <div className="mt-3 flex flex-col gap-4 rounded-lg bg-muted/40 p-3">
            <div>
                <p className="mb-2 text-xs font-semibold text-muted-foreground">
                    {t(
                        'On the terminal: Network → Platform Access → ISUP 5.0, turn it on and enter:',
                    )}
                </p>
                <dl className="grid gap-1.5 sm:grid-cols-2">
                    {settings.map(([label, value]) => (
                        <div
                            key={label}
                            className="flex items-center justify-between gap-2 rounded-md border bg-card px-3 py-1.5"
                        >
                            <dt className="text-xs text-muted-foreground">
                                {label}
                            </dt>
                            <dd className="flex items-center gap-1 font-mono text-sm font-semibold">
                                {value}
                                <button
                                    type="button"
                                    className="text-muted-foreground hover:text-foreground"
                                    aria-label={t('Copy')}
                                    onClick={() => {
                                        void navigator.clipboard?.writeText(
                                            value,
                                        );
                                        toast.success(t('Copied.'));
                                    }}
                                >
                                    <Copy className="size-3.5" />
                                </button>
                            </dd>
                        </div>
                    ))}
                </dl>
                <p className="mt-2 text-xs text-muted-foreground">
                    {t(
                        'Protocol version ISUP 5.0, address type IP. The device connects by itself; no port needs to be opened at the center.',
                    )}
                    {device.ip && ' ' + t('Last IP: :ip', { ip: device.ip })}
                </p>
            </div>

            <Form
                {...admin.devices.update.form(device.id)}
                options={{ preserveScroll: true }}
                className="grid gap-2 sm:grid-cols-[1fr_1fr_auto_auto]"
            >
                {({ processing }) => (
                    <>
                        <Input
                            name="name"
                            defaultValue={device.name}
                            required
                        />
                        <NativeSelect
                            name="branch_id"
                            defaultValue={device.branch_id}
                        >
                            {branches.map((b) => (
                                <option key={b.id} value={b.id}>
                                    {b.name}
                                </option>
                            ))}
                        </NativeSelect>
                        <NativeSelect
                            name="direction"
                            defaultValue={device.direction}
                        >
                            <option value="in">{t('Entrance')}</option>
                            <option value="out">{t('Exit')}</option>
                        </NativeSelect>
                        <input
                            type="hidden"
                            name="is_active"
                            value={device.is_active ? '1' : '0'}
                        />
                        <Button variant="outline" disabled={processing}>
                            {t('Save')}
                        </Button>
                    </>
                )}
            </Form>

            <div className="flex flex-wrap gap-2">
                <Button
                    size="sm"
                    variant="outline"
                    disabled={!device.is_online}
                    onClick={() =>
                        router.post(
                            admin.devices.sync(device.id).url,
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    <RefreshCw />
                    {t('Send all students again')}
                </Button>
                <Button
                    size="sm"
                    variant="ghost"
                    onClick={() =>
                        router.put(
                            admin.devices.update(device.id).url,
                            {
                                name: device.name,
                                branch_id: device.branch_id,
                                direction: device.direction,
                                is_active: !device.is_active,
                            },
                            { preserveScroll: true },
                        )
                    }
                >
                    {device.is_active ? t('Disable') : t('Enable')}
                </Button>
                <Button
                    size="sm"
                    variant="ghost"
                    className="text-danger"
                    onClick={() =>
                        confirm(t('Delete?')) &&
                        router.delete(admin.devices.destroy(device.id).url, {
                            preserveScroll: true,
                        })
                    }
                >
                    <Trash2 />
                    {t('Delete')}
                </Button>
            </div>
        </div>
    );
}

Devices.layout = {
    breadcrumbs: [{ title: 'Devices', href: admin.devices.index() }],
};
