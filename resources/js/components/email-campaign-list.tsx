import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Mail, Clock } from 'lucide-react';
import type { EmailCampaignMetric } from '@/components/campaign-metrics';

interface EmailCampaignListProps {
    emails: EmailCampaignMetric[];
}

function formatScheduledDate(value: string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function EmailCampaignList({ emails }: EmailCampaignListProps) {
    if (emails.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <div className="rounded-lg bg-primary/10 p-1.5">
                            <Mail className="h-4 w-4 text-primary" />
                        </div>
                        <CardTitle className="text-base font-semibold">Emails in Campaign</CardTitle>
                    </div>
                    <Badge variant="secondary" className="text-xs font-normal">
                        {emails.length} email{emails.length === 1 ? '' : 's'}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                {emails.map((email) => (
                    <div key={email.id} className="rounded-xl border bg-card p-4 shadow-sm space-y-4">
                        <div className="flex flex-col md:flex-row md:items-start md:justify-between gap-2">
                            <div className="min-w-0">
                                <p className="text-sm font-semibold break-words">{email.subject}</p>
                                <p className="text-xs text-muted-foreground mt-1 break-all">{email.name}</p>
                            </div>
                            <div className="flex items-center gap-1.5 text-xs text-muted-foreground shrink-0">
                                <Clock className="h-3.5 w-3.5" />
                                {formatScheduledDate(email.scheduled_date)}
                            </div>
                        </div>

                        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 text-sm">
                            <Metric label="Delivered" value={email.delivered} />
                            <Metric label="Unique Opens" value={email.unique_opens} rate={`${email.open_rate}%`} />
                            <Metric label="Total Opens" value={email.total_opens} />
                            <Metric label="Unique Clicks" value={email.unique_clicks} rate={`${email.unique_click_through_rate}% CTR`} />
                            <Metric label="Hard Bounces" value={email.hard_bounces} rate={`${email.delivery_rate}% delivery`} />
                            <Metric label="Click-to-Open" value={`${email.click_to_open_ratio}%`} />
                            <Metric label="Total CTR" value={`${email.total_click_through_rate}%`} />
                            {email.segment && <Metric label="Segment" value={email.segment} />}
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

function Metric({ label, value, rate }: { label: string; value: string | number; rate?: string }) {
    return (
        <div className="rounded-lg bg-muted/40 px-3 py-2">
            <p className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">{label}</p>
            <p className="font-semibold mt-0.5">
                {typeof value === 'number' ? value.toLocaleString() : value}
            </p>
            {rate && <p className="text-[10px] text-muted-foreground mt-0.5">{rate}</p>}
        </div>
    );
}
