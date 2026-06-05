import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Layers } from 'lucide-react';

export interface Campaign {
    id: string;
    name: string;
    created_at: string;
}

interface CampaignSelectorProps {
    campaigns: Campaign[];
    selectedCampaignId?: string;
    onCampaignChange: (campaignId: string) => void;
    isLoading?: boolean;
}

export function CampaignSelector({
    campaigns,
    selectedCampaignId,
    onCampaignChange,
    isLoading = false,
}: CampaignSelectorProps) {
    return (
        <div className="flex items-center gap-3 rounded-xl border bg-card px-4 py-3 shadow-sm">
            <div className="rounded-lg bg-primary/10 p-1.5 shrink-0">
                <Layers className="h-4 w-4 text-primary" />
            </div>
            <div className="flex-1 min-w-0">
                <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">Campaign</p>
                <Select
                    value={selectedCampaignId}
                    onValueChange={onCampaignChange}
                    disabled={isLoading || campaigns.length === 0}
                >
                    <SelectTrigger className="h-7 border-0 p-0 shadow-none bg-transparent font-medium focus:ring-0 focus:ring-offset-0 text-sm">
                        <SelectValue
                            placeholder={
                                isLoading
                                    ? 'Loading campaigns...'
                                    : campaigns.length === 0
                                      ? 'No campaigns available'
                                      : 'Select a campaign'
                            }
                        />
                    </SelectTrigger>
                    <SelectContent>
                        {campaigns.map((campaign) => (
                            <SelectItem key={campaign.id} value={campaign.id}>
                                {campaign.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
        </div>
    );
}
