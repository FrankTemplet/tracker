import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { CampaignSelector, type Campaign } from '@/components/campaign-selector';
import { CampaignFilters, type Region } from '@/components/campaign-filters';
import { CampaignMetrics, type CampaignMetricsData, type MemberStatus } from '@/components/campaign-metrics';
import { MemberListPanel, type Member } from '@/components/member-list-panel';
import { RefreshIndicator } from '@/components/refresh-indicator';
import { DashboardSkeleton } from '@/components/dashboard-skeleton';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { Mail, X } from 'lucide-react';

interface DashboardProps {
    campaigns?: Campaign[];
    selectedCampaignId?: string;
    selectedRegion?: Region;
    selectedYear?: string;
    analytics?: CampaignMetricsData;
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
}: DashboardProps) {
    const [isLoading, setIsLoading] = useState(false);
    const [members, setMembers] = useState<Member[]>([]);
    const [selectedMemberStatus, setSelectedMemberStatus] = useState<MemberStatus | null>(null);
    const [isLoadingMembers, setIsLoadingMembers] = useState(false);

    const lastUpdatedDate = lastUpdated ? new Date(lastUpdated) : new Date();

    const handleRegionChange = (region: Region) => {
        setIsLoading(true);
        setMembers([]);
        setSelectedMemberStatus(null);
        router.get(
            dashboard(),
            { region, year: selectedYear },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['campaigns', 'selectedRegion', 'selectedYear', 'lastUpdated'],
                onFinish: () => setIsLoading(false),
            },
        );
    };

    const handleYearChange = (year: string) => {
        setIsLoading(true);
        setMembers([]);
        setSelectedMemberStatus(null);
        router.get(
            dashboard(),
            { region: selectedRegion, year },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['campaigns', 'selectedRegion', 'selectedYear', 'lastUpdated'],
                onFinish: () => setIsLoading(false),
            },
        );
    };

    const handleCampaignChange = (campaignId: string) => {
        setIsLoading(true);
        setMembers([]);
        setSelectedMemberStatus(null);
        router.get(
            dashboard(),
            { region: selectedRegion, year: selectedYear, campaign_id: campaignId },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['analytics', 'selectedCampaignId', 'lastUpdated'],
                onFinish: () => setIsLoading(false),
            },
        );
    };

    const handleMetricClick = async (status: MemberStatus) => {
        if (!selectedCampaignId) return;
        setSelectedMemberStatus(status);
        setIsLoadingMembers(true);
        setMembers([]);
        try {
            const response = await fetch(`/api/powerbi/campaigns/${selectedCampaignId}/members/${status}`);
            const data = await response.json();
            if (data.success) {
                setMembers(data.data);
            }
        } catch {
            // member fetch failed silently — panel stays open showing 0 results
        } finally {
            setIsLoadingMembers(false);
        }
    };

    const handleCloseMembers = () => {
        setMembers([]);
        setSelectedMemberStatus(null);
    };

    const handleClear = () => {
        setIsLoading(true);
        setMembers([]);
        setSelectedMemberStatus(null);
        router.get(dashboard(), {}, { onFinish: () => setIsLoading(false) });
    };

    const filtersSelected = selectedRegion && selectedYear;

    return (
        <>
            <Head title="Dashboard - Email Campaign Monitor" />
            <div className="flex h-full flex-col gap-4 p-4 md:p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-bold tracking-tight">Email Send Report</h1>
                        <p className="text-xs text-muted-foreground mt-0.5">
                            Monitor your email campaigns and analytics
                        </p>
                    </div>
                    <RefreshIndicator lastUpdated={lastUpdatedDate} isRefreshing={isLoading} />
                </div>

                {/* Filter Row — Region + Year + Campaign + Clear all in one line */}
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

                {/* Metrics — only when a campaign is selected */}
                {selectedCampaignId && (
                    isLoading && !analytics ? (
                        <DashboardSkeleton />
                    ) : (
                        <div className="flex flex-col gap-4">
                            <CampaignMetrics
                                metrics={analytics ?? null}
                                isLoading={isLoading}
                                onMetricClick={handleMetricClick}
                            />
                            {selectedMemberStatus && (
                                <MemberListPanel
                                    members={members}
                                    status={selectedMemberStatus}
                                    isLoading={isLoadingMembers}
                                    onClose={handleCloseMembers}
                                />
                            )}
                        </div>
                    )
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

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};

