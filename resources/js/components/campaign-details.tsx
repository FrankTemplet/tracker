import { Tag, Users } from 'lucide-react';

export interface CampaignDetailsData {
    campaign_name: string | null;
    segment: string | null;
}

interface CampaignDetailsProps {
    details: CampaignDetailsData;
}

export function CampaignDetails({ details }: CampaignDetailsProps) {
    if (!details.campaign_name && !details.segment) {
        return null;
    }

    return (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            {details.campaign_name && (
                <div className="rounded-xl border bg-card px-5 py-4 shadow-sm">
                    <div className="flex items-start gap-2.5">
                        <div className="rounded-md bg-primary/10 p-1.5 mt-0.5 shrink-0">
                            <Tag className="h-3.5 w-3.5 text-primary" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Campaign
                            </p>
                            <p className="text-sm font-medium mt-0.5 break-words">
                                {details.campaign_name}
                            </p>
                        </div>
                    </div>
                </div>
            )}
            {details.segment && (
                <div className="rounded-xl border bg-card px-5 py-4 shadow-sm">
                    <div className="flex items-start gap-2.5">
                        <div className="rounded-md bg-primary/10 p-1.5 mt-0.5 shrink-0">
                            <Users className="h-3.5 w-3.5 text-primary" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Segment
                            </p>
                            <p className="text-sm font-medium truncate mt-0.5">
                                {details.segment}
                            </p>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
