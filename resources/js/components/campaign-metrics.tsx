import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { BarChart3, Mail, Eye, MousePointerClick, AlertTriangle, Users } from 'lucide-react';
import type { ComponentType } from 'react';

export interface CampaignMetricsData {
    sent: number;
    delivered: number;
    opened: number;
    clicked: number;
    bounced: number;
    open_rate: number;
    click_rate: number;
    bounce_rate: number;
}

export type MemberStatus = 'Opened' | 'Clicked' | 'Bounced' | 'Sent';

interface MetricButtonProps {
    label: string;
    value: number;
    rate: number | null;
    status: MemberStatus;
    colorClass: string;
    iconBgClass: string;
    Icon: ComponentType<{ className?: string }>;
    isLoading: boolean;
    onClick?: (status: MemberStatus) => void;
}

function MetricButton({ label, value, rate, status, colorClass, iconBgClass, Icon, isLoading, onClick }: MetricButtonProps) {
    const isClickable = !!onClick;
    return (
        <button
            type="button"
            onClick={isClickable ? () => onClick(status) : undefined}
            disabled={!isClickable || isLoading}
            className={`w-full text-left rounded-xl border bg-card p-4 shadow-sm transition-all ${
                isClickable ? 'cursor-pointer hover:border-primary/50 hover:shadow-md active:scale-[0.98]' : 'cursor-default'
            }`}
        >
            <div className="flex items-center justify-between gap-2">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-2">
                        {label}
                        {isClickable && <span className="ml-1 text-[10px] normal-case font-normal opacity-60">↗ click</span>}
                    </p>
                    {isLoading ? (
                        <Skeleton className="h-7 w-16" />
                    ) : (
                        <p className={`text-2xl font-bold ${colorClass}`}>{value.toLocaleString()}</p>
                    )}
                    {rate !== null && !isLoading && (
                        <p className="text-xs text-muted-foreground mt-1">{rate}% rate</p>
                    )}
                </div>
                <div className={`rounded-lg ${iconBgClass} p-2.5`}>
                    <Icon className={`h-5 w-5 ${colorClass}`} />
                </div>
            </div>
        </button>
    );
}

interface CampaignMetricsProps {
    metrics: CampaignMetricsData | null;
    isLoading?: boolean;
    onMetricClick?: (status: MemberStatus) => void;
}

export function CampaignMetrics({ metrics, isLoading = false, onMetricClick }: CampaignMetricsProps) {
    if (!metrics && !isLoading) {
        return (
            <Card className="border-dashed">
                <CardContent className="flex flex-col items-center justify-center py-10 gap-3">
                    <div className="rounded-full bg-muted p-4">
                        <BarChart3 className="h-6 w-6 text-muted-foreground" />
                    </div>
                    <div className="text-center">
                        <p className="text-sm font-medium text-foreground">No metrics yet</p>
                        <p className="text-xs text-muted-foreground mt-1">Select a campaign to view metrics</p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <div className="rounded-lg bg-violet-500/10 p-1.5">
                            <BarChart3 className="h-4 w-4 text-violet-600 dark:text-violet-400" />
                        </div>
                        <CardTitle className="text-base font-semibold">Campaign Metrics</CardTitle>
                    </div>
                    {metrics && (
                        <Badge variant="secondary" className="text-xs font-normal">
                            {metrics.delivered.toLocaleString()} delivered
                        </Badge>
                    )}
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Non-clickable totals */}
                <div className="grid grid-cols-2 gap-3">
                    <MetricButton
                        label="Sent"
                        value={metrics?.sent ?? 0}
                        rate={null}
                        status="Sent"
                        colorClass="text-foreground"
                        iconBgClass="bg-muted/50"
                        Icon={Mail}
                        isLoading={isLoading}
                    />
                    <MetricButton
                        label="Delivered"
                        value={metrics?.delivered ?? 0}
                        rate={null}
                        status="Sent"
                        colorClass="text-emerald-600 dark:text-emerald-400"
                        iconBgClass="bg-emerald-500/10"
                        Icon={Users}
                        isLoading={isLoading}
                    />
                </div>

                {/* Clickable engagement metrics */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <MetricButton
                        label="Opens"
                        value={metrics?.opened ?? 0}
                        rate={metrics?.open_rate ?? null}
                        status="Opened"
                        colorClass="text-sky-600 dark:text-sky-400"
                        iconBgClass="bg-sky-500/10"
                        Icon={Eye}
                        isLoading={isLoading}
                        onClick={onMetricClick}
                    />
                    <MetricButton
                        label="Clicks"
                        value={metrics?.clicked ?? 0}
                        rate={metrics?.click_rate ?? null}
                        status="Clicked"
                        colorClass="text-indigo-600 dark:text-indigo-400"
                        iconBgClass="bg-indigo-500/10"
                        Icon={MousePointerClick}
                        isLoading={isLoading}
                        onClick={onMetricClick}
                    />
                    <MetricButton
                        label="Bounced"
                        value={metrics?.bounced ?? 0}
                        rate={metrics?.bounce_rate ?? null}
                        status="Bounced"
                        colorClass="text-amber-600 dark:text-amber-400"
                        iconBgClass="bg-amber-500/10"
                        Icon={AlertTriangle}
                        isLoading={isLoading}
                        onClick={onMetricClick}
                    />
                </div>

                {/* Rate summary */}
                <div className="grid grid-cols-3 gap-3 pt-2 border-t">
                    <div className="text-center">
                        <p className="text-xs text-muted-foreground">Open Rate</p>
                        {isLoading ? (
                            <Skeleton className="h-5 w-12 mx-auto mt-1" />
                        ) : (
                            <p className="text-sm font-semibold text-sky-600 dark:text-sky-400">{metrics?.open_rate ?? 0}%</p>
                        )}
                    </div>
                    <div className="text-center">
                        <p className="text-xs text-muted-foreground">Click Rate</p>
                        {isLoading ? (
                            <Skeleton className="h-5 w-12 mx-auto mt-1" />
                        ) : (
                            <p className="text-sm font-semibold text-indigo-600 dark:text-indigo-400">{metrics?.click_rate ?? 0}%</p>
                        )}
                    </div>
                    <div className="text-center">
                        <p className="text-xs text-muted-foreground">Bounce Rate</p>
                        {isLoading ? (
                            <Skeleton className="h-5 w-12 mx-auto mt-1" />
                        ) : (
                            <p className="text-sm font-semibold text-amber-600 dark:text-amber-400">{metrics?.bounce_rate ?? 0}%</p>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
