import { Tag, LayoutGrid, Layers2, Users, TrendingUp } from 'lucide-react';

export interface CampaignDetailsData {
    primary_purpose: string | null;
    category: string | null;
    sub_category: string | null;
    segment: string | null;
    opportunities_in_campaign: number | null;
}

interface CampaignDetailsProps {
    details: CampaignDetailsData;
}

interface DetailItemProps {
    icon: React.ReactNode;
    label: string;
    value: string | number | null;
}

function DetailItem({ icon, label, value }: DetailItemProps) {
    return (
        <div className="flex items-start gap-2.5">
            <div className="rounded-md bg-primary/10 p-1.5 mt-0.5 shrink-0">
                {icon}
            </div>
            <div className="min-w-0">
                <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    {label}
                </p>
                <p className="text-sm font-medium truncate mt-0.5">
                    {value ?? <span className="text-muted-foreground italic">—</span>}
                </p>
            </div>
        </div>
    );
}

export function CampaignDetails({ details }: CampaignDetailsProps) {
    const hasOpportunities =
        details.opportunities_in_campaign !== null &&
        details.opportunities_in_campaign > 0;

    return (
        <div className="rounded-xl border bg-card px-5 py-4 shadow-sm">
            <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-4">
                Campaign Details
            </h3>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <DetailItem
                    icon={<Tag className="h-3.5 w-3.5 text-primary" />}
                    label="Purpose"
                    value={details.primary_purpose}
                />
                <DetailItem
                    icon={<LayoutGrid className="h-3.5 w-3.5 text-primary" />}
                    label="Category"
                    value={details.category}
                />
                <DetailItem
                    icon={<Layers2 className="h-3.5 w-3.5 text-primary" />}
                    label="Sub-Category"
                    value={details.sub_category}
                />
                <DetailItem
                    icon={<Users className="h-3.5 w-3.5 text-primary" />}
                    label="Segment"
                    value={details.segment}
                />
            </div>

            {hasOpportunities && (
                <div className="mt-4 pt-4 border-t">
                    <DetailItem
                        icon={<TrendingUp className="h-3.5 w-3.5 text-primary" />}
                        label="Opportunities in Campaign"
                        value={details.opportunities_in_campaign}
                    />
                </div>
            )}
        </div>
    );
}
