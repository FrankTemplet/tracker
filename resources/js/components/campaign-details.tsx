import { Tag, Users, Target, Folder, Layers } from 'lucide-react';

export interface CampaignDetailsData {
    campaign_name: string | null;
    segment: string | null;
    primary_purpose?: string | null;
    category?: string | null;
    sub_category?: string | null;
}

interface CampaignDetailsProps {
    details: CampaignDetailsData;
}

export function CampaignDetails({ details }: CampaignDetailsProps) {
    if (
        !details.campaign_name &&
        !details.segment &&
        !details.primary_purpose &&
        !details.category &&
        !details.sub_category
    ) {
        return null;
    }

    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
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
            {details.primary_purpose && (
                <div className="rounded-xl border bg-card px-5 py-4 shadow-sm">
                    <div className="flex items-start gap-2.5">
                        <div className="rounded-md bg-primary/10 p-1.5 mt-0.5 shrink-0">
                            <Target className="h-3.5 w-3.5 text-primary" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Campaign Purpose
                            </p>
                            <p className="text-sm font-medium truncate mt-0.5">
                                {details.primary_purpose}
                            </p>
                        </div>
                    </div>
                </div>
            )}
            {details.category && (
                <div className="rounded-xl border bg-card px-5 py-4 shadow-sm">
                    <div className="flex items-start gap-2.5">
                        <div className="rounded-md bg-primary/10 p-1.5 mt-0.5 shrink-0">
                            <Folder className="h-3.5 w-3.5 text-primary" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Category
                            </p>
                            <p className="text-sm font-medium truncate mt-0.5">
                                {details.category}
                            </p>
                        </div>
                    </div>
                </div>
            )}
            {details.sub_category && (
                <div className="rounded-xl border bg-card px-5 py-4 shadow-sm">
                    <div className="flex items-start gap-2.5">
                        <div className="rounded-md bg-primary/10 p-1.5 mt-0.5 shrink-0">
                            <Layers className="h-3.5 w-3.5 text-primary" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Sub-Category
                            </p>
                            <p className="text-sm font-medium truncate mt-0.5">
                                {details.sub_category}
                            </p>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
