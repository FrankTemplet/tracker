import { Head, router, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { CampaignSelector, type Campaign } from '@/components/campaign-selector';
import { EmailListItem, type Email } from '@/components/email-list-item';
import { EmailDetailsPanel } from '@/components/email-details-panel';
import { EmailAnalytics, type EmailAnalyticsData } from '@/components/email-analytics';
import { EngagementSection, type EngagementData } from '@/components/engagement-section';
import { RefreshIndicator } from '@/components/refresh-indicator';
import { DashboardSkeleton } from '@/components/dashboard-skeleton';
import { dashboard } from '@/routes';
import { Mail } from 'lucide-react';

interface BouncesOpensData {
    bounces: number;
    opens: number;
}

interface DashboardProps {
    campaigns?: Campaign[];
    emails?: Email[];
    selectedCampaignId?: string;
    selectedEmailId?: string;
    analytics?: EmailAnalyticsData;
    bouncesOpens?: BouncesOpensData;
    engagement?: EngagementData;
    lastUpdated?: string;
}

export default function Dashboard({
    campaigns = [],
    emails = [],
    selectedCampaignId,
    selectedEmailId,
    analytics,
    bouncesOpens,
    engagement,
    lastUpdated,
}: DashboardProps) {
    const [isLoading, setIsLoading] = useState(false);
    const [pendingEmailId, setPendingEmailId] = useState<string | null>(null);
    const [selectedEmail, setSelectedEmail] = useState<Email | null>(
        emails.find((e) => e.id === selectedEmailId) || null
    );

    const lastUpdatedDate = lastUpdated ? new Date(lastUpdated) : new Date();

    useEffect(() => {
        if (selectedEmailId) {
            const email = emails.find((e) => e.id === selectedEmailId);
            setSelectedEmail(email || null);
        } else if (emails.length > 0 && !selectedEmail) {
            handleEmailSelect(emails[0]);
        }
    }, [emails, selectedEmailId]);

    const handleCampaignChange = (campaignId: string) => {
        setIsLoading(true);
        router.get(
            dashboard(),
            { campaign_id: campaignId },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['emails', 'selectedCampaignId', 'lastUpdated'],
                onFinish: () => {
                    setIsLoading(false);
                    setSelectedEmail(null);
                },
            }
        );
    };

    const handleEmailSelect = (email: Email) => {
        setSelectedEmail(email);
        setPendingEmailId(email.id);
        setIsLoading(true);
        router.get(
            dashboard(),
            {
                campaign_id: selectedCampaignId,
                email_id: email.id,
            },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['analytics', 'bouncesOpens', 'engagement', 'selectedEmailId', 'lastUpdated'],
                onFinish: () => {
                    setIsLoading(false);
                    setPendingEmailId(null);
                },
            }
        );
    };

    if (campaigns.length === 0 && !isLoading) {
        return (
            <>
                <Head title="Dashboard" />
                <div className="flex h-full items-center justify-center p-8">
                    <div className="text-center space-y-3">
                        <div className="mx-auto rounded-full bg-muted p-5 w-fit">
                            <Mail className="h-8 w-8 text-muted-foreground" />
                        </div>
                        <h2 className="text-xl font-semibold">No Campaigns Available</h2>
                        <p className="text-sm text-muted-foreground max-w-sm">
                            Please configure your Power BI credentials and ensure
                            campaigns are available.
                        </p>
                    </div>
                </div>
            </>
        );
    }

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
                    <RefreshIndicator
                        lastUpdated={lastUpdatedDate}
                        isRefreshing={isLoading}
                    />
                </div>

                {/* Campaign Selector */}
                <div className="w-full max-w-sm">
                    <CampaignSelector
                        campaigns={campaigns}
                        selectedCampaignId={selectedCampaignId}
                        onCampaignChange={handleCampaignChange}
                        isLoading={isLoading}
                    />
                </div>

                {/* Main Content */}
                {!selectedCampaignId ? (
                    <div className="flex-1 flex items-center justify-center">
                        <div className="text-center space-y-2">
                            <div className="mx-auto rounded-full bg-muted p-4 w-fit">
                                <Mail className="h-6 w-6 text-muted-foreground" />
                            </div>
                            <p className="text-sm text-muted-foreground">
                                Select a campaign to view emails
                            </p>
                        </div>
                    </div>
                ) : isLoading && emails.length === 0 ? (
                    <DashboardSkeleton />
                ) : (
                    <div className="flex flex-1 gap-4 overflow-hidden min-h-0">
                        {/* Email List */}
                        <div className="w-72 shrink-0 flex flex-col min-h-0">
                            <div className="flex items-center justify-between mb-3">
                                <h2 className="text-sm font-semibold text-foreground">Sent Emails</h2>
                                <span className="text-xs text-muted-foreground bg-muted rounded-full px-2 py-0.5 font-medium">
                                    {emails.length}
                                </span>
                            </div>
                            <div className="flex-1 overflow-y-auto space-y-2 pr-1">
                                {emails.length === 0 ? (
                                    <div className="flex flex-col items-center justify-center py-12 gap-2">
                                        <Mail className="h-5 w-5 text-muted-foreground/50" />
                                        <p className="text-xs text-muted-foreground text-center">
                                            No emails found for this campaign
                                        </p>
                                    </div>
                                ) : (
                                    emails.map((email) => (
                                        <EmailListItem
                                            key={email.id}
                                            email={email}
                                            isSelected={selectedEmail?.id === email.id}
                                            onClick={() => handleEmailSelect(email)}
                                        />
                                    ))
                                )}
                            </div>
                        </div>

                        {/* Right Panel */}
                        <div className="flex-1 flex flex-col gap-4 min-h-0 overflow-y-auto">
                            <div className="shrink-0">
                                <EmailDetailsPanel email={selectedEmail} />
                            </div>
                            <div className="shrink-0">
                                <EmailAnalytics
                                    key={selectedEmail?.id ?? 'no-email'}
                                    analytics={pendingEmailId ? null : (analytics || null)}
                                    bouncesOpens={pendingEmailId ? null : (bouncesOpens || null)}
                                    isLoading={isLoading && !!selectedEmail}
                                />
                            </div>
                            <div className="shrink-0">
                                <EngagementSection
                                    key={selectedEmail?.id ?? 'no-email'}
                                    engagement={pendingEmailId ? null : (engagement || null)}
                                    isLoading={isLoading && !!selectedEmail}
                                />
                            </div>
                        </div>
                    </div>
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

