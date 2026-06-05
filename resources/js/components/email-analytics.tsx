import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { MetricCard } from '@/components/metric-card';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import {
    MousePointerClick,
    Mail,
    TrendingUp,
    Target,
    Eye,
    LogOut,
    BarChart3,
    AlertTriangle,
} from 'lucide-react';

export interface EmailAnalyticsData {
    bounces: number;
    bounce_rate: number;
    opens: number;
    open_rate: number;
    clicks: number;
    click_rate: number;
    total_delivered: number;
    unique_opens: number;
    unique_clicks: number;
    opt_out_rate: number;
}

interface BouncesOpensData {
    bounces: number;
    opens: number;
}

interface EmailAnalyticsProps {
    analytics: EmailAnalyticsData | null;
    bouncesOpens?: BouncesOpensData | null;
    isLoading?: boolean;
}

export function EmailAnalytics({
    analytics,
    bouncesOpens,
    isLoading = false,
}: EmailAnalyticsProps) {
    if (!analytics && !isLoading) {
        return (
            <Card className="border-dashed">
                <CardContent className="flex flex-col items-center justify-center py-10 gap-3">
                    <div className="rounded-full bg-muted p-4">
                        <BarChart3 className="h-6 w-6 text-muted-foreground" />
                    </div>
                    <div className="text-center">
                        <p className="text-sm font-medium text-foreground">No analytics yet</p>
                        <p className="text-xs text-muted-foreground mt-1">Select an email to view analytics</p>
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
                        <CardTitle className="text-base font-semibold">Email Analytics</CardTitle>
                    </div>
                    {analytics && (
                        <Badge variant="secondary" className="text-xs font-normal">
                            {analytics.total_delivered.toLocaleString()} delivered
                        </Badge>
                    )}
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Bounces & Opens highlight card */}
                <div className="grid grid-cols-2 gap-3">
                    <div className="rounded-xl border border-l-4 border-l-amber-500 bg-card p-4 shadow-sm">
                        <div className="flex items-center justify-between gap-2">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-2">Bounces</p>
                                {isLoading ? (
                                    <Skeleton className="h-7 w-16" />
                                ) : (
                                    <p className="text-2xl font-bold text-amber-600 dark:text-amber-400">
                                        {(bouncesOpens?.bounces ?? 0).toLocaleString()}
                                    </p>
                                )}
                            </div>
                            <div className="rounded-lg bg-amber-500/10 p-2.5">
                                <AlertTriangle className="h-5 w-5 text-amber-600 dark:text-amber-400" />
                            </div>
                        </div>
                    </div>
                    <div className="rounded-xl border border-l-4 border-l-sky-500 bg-card p-4 shadow-sm">
                        <div className="flex items-center justify-between gap-2">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-2">Opens</p>
                                {isLoading ? (
                                    <Skeleton className="h-7 w-16" />
                                ) : (
                                    <p className="text-2xl font-bold text-sky-600 dark:text-sky-400">
                                        {(bouncesOpens?.opens ?? 0).toLocaleString()}
                                    </p>
                                )}
                            </div>
                            <div className="rounded-lg bg-sky-500/10 p-2.5">
                                <Eye className="h-5 w-5 text-sky-600 dark:text-sky-400" />
                            </div>
                        </div>
                    </div>
                </div>

                {/* Detailed metrics grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <MetricCard
                        title="Open Rate"
                        value={`${analytics?.open_rate ?? 0}%`}
                        subtitle={`${(analytics?.opens ?? 0).toLocaleString()} total opens`}
                        icon={TrendingUp}
                        variant="success"
                        isLoading={isLoading}
                    />
                    <MetricCard
                        title="Click-Through Rate"
                        value={`${analytics?.click_rate ?? 0}%`}
                        subtitle={`${(analytics?.clicks ?? 0).toLocaleString()} total clicks`}
                        icon={Target}
                        variant="success"
                        isLoading={isLoading}
                    />
                    <MetricCard
                        title="Total Delivered"
                        value={analytics?.total_delivered ?? 0}
                        subtitle="messages sent"
                        icon={Mail}
                        variant="default"
                        isLoading={isLoading}
                    />
                    <MetricCard
                        title="Unique Opens"
                        value={analytics?.unique_opens ?? 0}
                        subtitle={`${analytics?.open_rate ?? 0}% of delivered`}
                        icon={Eye}
                        variant="default"
                        isLoading={isLoading}
                    />
                    <MetricCard
                        title="Unique Clicks"
                        value={analytics?.unique_clicks ?? 0}
                        subtitle={`${analytics?.click_rate ?? 0}% of delivered`}
                        icon={MousePointerClick}
                        variant="default"
                        isLoading={isLoading}
                    />
                    <MetricCard
                        title="Opt-Out Rate"
                        value={`${analytics?.opt_out_rate ?? 0}%`}
                        subtitle={`${(analytics?.bounces ?? 0).toLocaleString()} bounces`}
                        icon={LogOut}
                        variant="destructive"
                        isLoading={isLoading}
                    />
                </div>
            </CardContent>
        </Card>
    );
}
