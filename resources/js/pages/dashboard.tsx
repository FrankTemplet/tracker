import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { CampaignSelector, type Campaign } from '@/components/campaign-selector';
import { CampaignFilters, type Region } from '@/components/campaign-filters';
import { CampaignDetails } from '@/components/campaign-details';
import { CampaignMetrics, type CampaignAnalyticsData } from '@/components/campaign-metrics';
import { EmailCampaignList } from '@/components/email-campaign-list';
import { RefreshIndicator } from '@/components/refresh-indicator';
import { DashboardSkeleton } from '@/components/dashboard-skeleton';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { X } from 'lucide-react';

interface DashboardProps {
    campaigns?: Campaign[];
    selectedCampaignId?: string;
    selectedRegion?: Region;
    selectedYear?: string;
    analytics?: CampaignAnalyticsData;
    lastUpdated?: string;
    error?: string;
}

export default function Dashboard({
    campaigns = [],
    selectedCampaignId,
    selectedRegion,
    selectedYear,
    analytics,
    lastUpdated,
    error,
}: DashboardProps) {
    const [isLoading, setIsLoading] = useState(false);

    const lastUpdatedDate = lastUpdated ? new Date(lastUpdated) : new Date();

    const handleRegionChange = (region: Region) => {
        setIsLoading(true);
        router.get(
            dashboard(),
            { region, year: selectedYear },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['campaigns', 'selectedRegion', 'selectedYear', 'selectedCampaignId', 'analytics', 'lastUpdated', 'error'],
                onFinish: () => setIsLoading(false),
            },
        );
    };

    const handleYearChange = (year: string) => {
        setIsLoading(true);
        router.get(
            dashboard(),
            { region: selectedRegion, year },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['campaigns', 'selectedRegion', 'selectedYear', 'selectedCampaignId', 'analytics', 'lastUpdated', 'error'],
                onFinish: () => setIsLoading(false),
            },
        );
    };

    const handleCampaignChange = (campaignId: string) => {
        setIsLoading(true);
        router.get(
            dashboard(),
            { region: selectedRegion, year: selectedYear, campaign_id: campaignId },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['analytics', 'selectedCampaignId', 'lastUpdated', 'error'],
                onFinish: () => setIsLoading(false),
            },
        );
    };

    const handleClear = () => {
        setIsLoading(true);
        router.get(dashboard(), {}, { onFinish: () => setIsLoading(false) });
    };

    const filtersSelected = selectedRegion && selectedYear;

    return (
        <>
            <Head title="Dashboard - Email Campaign Monitor" />
            <div className="flex h-full flex-col gap-4 p-4 md:p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-bold tracking-tight">Email Send Report</h1>
                        <p className="text-xs text-muted-foreground mt-0.5">
                            Monitor your email campaigns and analytics
                        </p>
                    </div>
                    <RefreshIndicator lastUpdated={lastUpdatedDate} isRefreshing={isLoading} />
                </div>

                <div className="flex flex-wrap items-stretch gap-3">
                    <CampaignFilters
                        selectedRegion={selectedRegion}
                        selectedYear={selectedYear}
                        onRegionChange={handleRegionChange}
                        onYearChange={handleYearChange}
                    />
                    <CampaignSelector
                        campaigns={campaigns}
                        selectedCampaignId={selectedCampaignId}
                        onCampaignChange={handleCampaignChange}
                        isLoading={isLoading}
                        isDisabled={!filtersSelected}
                    />
                    {(selectedRegion || selectedCampaignId) && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleClear}
                            className="self-center gap-1.5"
                        >
                            <X className="h-3.5 w-3.5" />
                            Clear
                        </Button>
                    )}
                </div>

                {error && (
                    <div className="rounded-xl border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                        {error}
                    </div>
                )}

                {selectedCampaignId && (
                    isLoading && !analytics ? (
                        <DashboardSkeleton />
                    ) : (
                        <div className="flex flex-col gap-4">
                            {analytics && (
                                <CampaignDetails
                                    details={{
                                        campaign_name: analytics.campaign_name,
                                        segment: analytics.segment,
                                        primary_purpose: analytics.primary_purpose,
                                        category: analytics.category,
                                        sub_category: analytics.sub_category,
                                    }}
                                />
                            )}
                            <CampaignMetrics
                                campaignId={selectedCampaignId}
                                metrics={analytics?.summary ?? null}
                                emails={analytics?.emails ?? []}
                                isLoading={isLoading}
                            />
                            {analytics && analytics.emails.length > 0 && (
                                <EmailCampaignList emails={analytics.emails} />
                            )}
                        </div>
                    )
                )}

                {filtersSelected && !selectedCampaignId && !isLoading && (
                    <CampaignMetrics metrics={null} isLoading={false} />
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
