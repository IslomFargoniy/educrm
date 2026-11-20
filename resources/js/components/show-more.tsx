import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';

/**
 * Draw long lists in steps: the first rows, then more on request, so thousands of rows never render at once.
 * The limit starts over when the list itself changes (new filter or period).
 */
export function useShowMore<T>(
    items: T[],
    step = 100,
): { shown: T[]; button: React.ReactNode } {
    const t = useT();
    const [limit, setLimit] = useState(step);

    useEffect(() => setLimit(step), [items.length, step]);

    const rest = items.length - limit;

    return {
        shown: rest > 0 ? items.slice(0, limit) : items,
        button:
            rest > 0 ? (
                <div className="flex justify-center p-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setLimit(limit + step)}
                    >
                        {t('Show :count more (of :total)', {
                            count: Math.min(step, rest),
                            total: items.length,
                        })}
                    </Button>
                </div>
            ) : null,
    };
}
