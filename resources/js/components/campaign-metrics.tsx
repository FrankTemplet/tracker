import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { BarChart3, Eye, MousePointerClick, AlertTriangle, Mail, TrendingUp } from 'lucide-react';
import { useState } from 'react';
import type { ComponentType } from 'react';
import { MetricEmailsModal } from '@/components/metric-emails-modal';

export interface CampaignMetricsData {
    delivered: number;
    unique_opens: number;
    open_rate: number;
    unique_clicks: number;
    click_rate: number;
    unique_click_through_rate: number;
    click_to_open_rate: number;
    total_click_through_rate: number;
    total_opens: number;
    hard_bounces: number;
    delivery_rate: number;
    segment: string | null;
}

export interface CampaignAnalyticsData {
    campaign_id: string;
    campaign_name: string;
    segment: string | null;
    summary: CampaignMetricsData;
    emails: EmailCampaignMetric[];
}

export interface EmailCampaignMetric {
    id: string;
    name: string;
    subject: string;
    scheduled_date: string;
    campaign_id: string;
    campaign_name: string;
    delivered: number;
    unique_opens: number;
    open_rate: number;
    unique_clicks: number;
    unique_click_through_rate: number;
    click_to_open_ratio: number;
    total_click_through_rate: number;
    total_opens: number;
    hard_bounces: number;
    delivery_rate: number;
    segment: string | null;
}

export type MetricDrilldownKey =
    | 'delivered'
    | 'unique-opens'
    | 'total-opens'
    | 'unique-clicks'
    | 'hard-bounces';

export type MemberStatus = 'Opened' | 'Clicked' | 'Bounced' | 'Sent';

interface MetricTileProps {
    label: string;
    value: string | number;
    subtitle?: string | null;
    colorClass: string;
    iconBgClass: string;
    Icon: ComponentType<{ className?: string }>;
    isLoading: boolean;
    clickable?: boolean;
    onClick?: () => void;
}

function MetricTile({
    label,
    value,
    subtitle,
    colorClass,
    iconBgClass,
    Icon,
    isLoading,
    clickable = false,
    onClick,
}: MetricTileProps) {
    const isInteractive = clickable && !!onClick && !isLoading;

    return (
        <button
            type="button"
            onClick={isInteractive ? onClick : undefined}
            disabled={!isInteractive}
            className={`w-full text-left rounded-xl border bg-card p-4 shadow-sm transition-all ${
                isInteractive
                    ? 'cursor-pointer hover:border-primary/50 hover:shadow-md active:scale-[0.98]'
                    : 'cursor-default'
            }`}
        >
            <div className="flex items-center justify-between gap-2">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-2">
                        {label}
                        {isInteractive && (
                            <span className="ml-1 text-[10px] normal-case font-normal opacity-60">↗ view</span>
                        )}
                    </p>
                    {isLoading ? (
                        <Skeleton className="h-7 w-16" />
                    ) : (
                        <p className={`text-2xl font-bold ${colorClass}`}>
                            {typeof value === 'number' ? value.toLocaleString() : value}
                        </p>
                    )}
                    {subtitle && !isLoading && (
                        <p className="text-xs text-muted-foreground mt-1">{subtitle}</p>
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
    emails?: EmailCampaignMetric[];
    isLoading?: boolean;
}

export function CampaignMetrics({ metrics, emails = [], isLoading = false }: CampaignMetricsProps) {
    const [activeMetric, setActiveMetric] = useState<MetricDrilldownKey | null>(null);
    const [modalTitle, setModalTitle] = useState('');

    const openDrilldown = (metric: MetricDrilldownKey, title: string, count: number) => {
        if (count <= 0 || emails.length === 0) {
            return;
        }

        setActiveMetric(metric);
        setModalTitle(title);
    };

    const closeDrilldown = () => {
        setActiveMetric(null);
        setModalTitle('');
    };
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
                        <CardTitle className="text-base font-semibold">Campaign Summary</CardTitle>
                    </div>
                    {metrics && (
                        <Badge variant="secondary" className="text-xs font-normal">
                            {metrics.delivered.toLocaleString()} delivered
                        </Badge>
                    )}
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <MetricTile
                        label="Delivered"
                        value={metrics?.delivered ?? 0}
                        colorClass="text-emerald-600 dark:text-emerald-400"
                        iconBgClass="bg-emerald-500/10"
                        Icon={Mail}
                        isLoading={isLoading}
                        clickable
                        onClick={() => openDrilldown('delivered', 'Delivered', metrics?.delivered ?? 0)}
                    />
                    <MetricTile
                        label="Unique Opens"
                        value={metrics?.unique_opens ?? 0}
                        subtitle={`${metrics?.open_rate ?? 0}% open rate`}
                        colorClass="text-sky-600 dark:text-sky-400"
                        iconBgClass="bg-sky-500/10"
                        Icon={Eye}
                        isLoading={isLoading}
                        clickable
                        onClick={() => openDrilldown('unique-opens', 'Unique Opens', metrics?.unique_opens ?? 0)}
                    />
                    <MetricTile
                        label="Total Opens"
                        value={metrics?.total_opens ?? 0}
                        colorClass="text-sky-600 dark:text-sky-400"
                        iconBgClass="bg-sky-500/10"
                        Icon={TrendingUp}
                        isLoading={isLoading}
                        clickable
                        onClick={() => openDrilldown('total-opens', 'Total Opens', metrics?.total_opens ?? 0)}
                    />
                    <MetricTile
                        label="Unique Clicks"
                        value={metrics?.unique_clicks ?? 0}
                        subtitle={`${metrics?.click_rate ?? 0}% click rate`}
                        colorClass="text-indigo-600 dark:text-indigo-400"
                        iconBgClass="bg-indigo-500/10"
                        Icon={MousePointerClick}
                        isLoading={isLoading}
                        clickable
                        onClick={() => openDrilldown('unique-clicks', 'Unique Clicks', metrics?.unique_clicks ?? 0)}
                    />
                </div>

                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <MetricTile
                        label="Hard Bounces"
                        value={metrics?.hard_bounces ?? 0}
                        subtitle={`${metrics?.delivery_rate ?? 0}% delivery rate`}
                        colorClass="text-amber-600 dark:text-amber-400"
                        iconBgClass="bg-amber-500/10"
                        Icon={AlertTriangle}
                        isLoading={isLoading}
                        clickable
                        onClick={() => openDrilldown('hard-bounces', 'Hard Bounces', metrics?.hard_bounces ?? 0)}
                    />
                    <MetricTile
                        label="Unique CTR"
                        value={`${metrics?.unique_click_through_rate ?? 0}%`}
                        colorClass="text-indigo-600 dark:text-indigo-400"
                        iconBgClass="bg-indigo-500/10"
                        Icon={MousePointerClick}
                        isLoading={isLoading}
                    />
                    <MetricTile
                        label="Click-to-Open"
                        value={`${metrics?.click_to_open_rate ?? 0}%`}
                        colorClass="text-violet-600 dark:text-violet-400"
                        iconBgClass="bg-violet-500/10"
                        Icon={BarChart3}
                        isLoading={isLoading}
                    />
                    <MetricTile
                        label="Total CTR"
                        value={`${metrics?.total_click_through_rate ?? 0}%`}
                        colorClass="text-violet-600 dark:text-violet-400"
                        iconBgClass="bg-violet-500/10"
                        Icon={BarChart3}
                        isLoading={isLoading}
                    />
                </div>
            </CardContent>

            <MetricEmailsModal
                open={activeMetric !== null}
                onOpenChange={(open) => !open && closeDrilldown()}
                emails={emails}
                metric={activeMetric}
                title={modalTitle}
            />
        </Card>
    );
}
