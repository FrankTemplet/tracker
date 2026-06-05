import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';
import { Skeleton } from '@/components/ui/skeleton';

interface MetricCardProps {
    title: string;
    value: number | string;
    subtitle?: string;
    icon?: LucideIcon;
    variant?: 'default' | 'success' | 'destructive';
    isLoading?: boolean;
}

const variantConfig = {
    default: {
        iconBg: 'bg-blue-500/10 dark:bg-blue-500/20',
        iconColor: 'text-blue-600 dark:text-blue-400',
        valueColor: 'text-foreground',
        accent: 'border-l-blue-500',
    },
    success: {
        iconBg: 'bg-emerald-500/10 dark:bg-emerald-500/20',
        iconColor: 'text-emerald-600 dark:text-emerald-400',
        valueColor: 'text-emerald-600 dark:text-emerald-400',
        accent: 'border-l-emerald-500',
    },
    destructive: {
        iconBg: 'bg-red-500/10 dark:bg-red-500/20',
        iconColor: 'text-red-600 dark:text-red-400',
        valueColor: 'text-red-600 dark:text-red-400',
        accent: 'border-l-red-500',
    },
};

export function MetricCard({
    title,
    value,
    subtitle,
    icon: Icon,
    variant = 'default',
    isLoading = false,
}: MetricCardProps) {
    const config = variantConfig[variant];

    return (
        <div
            className={cn(
                'relative rounded-xl border bg-card p-5 border-l-4 shadow-sm hover:shadow-md transition-shadow',
                config.accent
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="flex-1 min-w-0">
                    <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">
                        {title}
                    </p>
                    {isLoading ? (
                        <div className="space-y-2">
                            <Skeleton className="h-8 w-20" />
                            {subtitle && <Skeleton className="h-3 w-28" />}
                        </div>
                    ) : (
                        <>
                            <p className={cn('text-2xl font-bold tracking-tight', config.valueColor)}>
                                {typeof value === 'number' ? value.toLocaleString() : value}
                            </p>
                            {subtitle && (
                                <p className="text-xs text-muted-foreground mt-1">{subtitle}</p>
                            )}
                        </>
                    )}
                </div>
                {Icon && (
                    <div className={cn('rounded-lg p-2.5 shrink-0', config.iconBg)}>
                        <Icon className={cn('h-5 w-5', config.iconColor)} />
                    </div>
                )}
            </div>
        </div>
    );
}
