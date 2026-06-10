import { useMemo } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Mail } from 'lucide-react';
import type { EmailCampaignMetric, MetricDrilldownKey } from '@/components/campaign-metrics';

interface MetricEmailsModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    emails: EmailCampaignMetric[];
    metric: MetricDrilldownKey | null;
    title: string;
}

const METRIC_VALUE_LABEL: Record<MetricDrilldownKey, string> = {
    delivered: 'Delivered',
    'unique-opens': 'Unique Opens',
    'total-opens': 'Total Opens',
    'unique-clicks': 'Unique Clicks',
    'hard-bounces': 'Hard Bounces',
};

function filterEmailsByMetric(emails: EmailCampaignMetric[], metric: MetricDrilldownKey): EmailCampaignMetric[] {
    return emails.filter((email) => {
        switch (metric) {
            case 'delivered':
                return email.delivered > 0;
            case 'unique-opens':
                return email.unique_opens > 0;
            case 'total-opens':
                return email.total_opens > 0;
            case 'unique-clicks':
                return email.unique_clicks > 0;
            case 'hard-bounces':
                return email.hard_bounces > 0;
        }
    });
}

function getMetricValue(email: EmailCampaignMetric, metric: MetricDrilldownKey): number {
    switch (metric) {
        case 'delivered':
            return email.delivered;
        case 'unique-opens':
            return email.unique_opens;
        case 'total-opens':
            return email.total_opens;
        case 'unique-clicks':
            return email.unique_clicks;
        case 'hard-bounces':
            return email.hard_bounces;
    }
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

export function MetricEmailsModal({
    open,
    onOpenChange,
    emails,
    metric,
    title,
}: MetricEmailsModalProps) {
    const filteredEmails = useMemo(() => {
        if (!metric) {
            return [];
        }

        return filterEmailsByMetric(emails, metric);
    }, [emails, metric]);

    const valueLabel = metric ? METRIC_VALUE_LABEL[metric] : '';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-4xl max-h-[85vh] flex flex-col">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>
                        Sent emails from Email Campaign Metrics contributing to this total
                    </DialogDescription>
                </DialogHeader>

                <div className="flex-1 overflow-hidden -mx-6 px-6">
                    {filteredEmails.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 gap-2">
                            <Mail className="h-5 w-5 text-muted-foreground/50" />
                            <p className="text-sm text-muted-foreground">No sent emails found for this metric</p>
                        </div>
                    ) : (
                        <div className="overflow-auto max-h-[55vh] rounded-lg border">
                            <table className="w-full text-sm">
                                <thead className="sticky top-0 bg-muted/80 backdrop-blur-sm z-10">
                                    <tr className="border-b">
                                        <th className="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                            Subject
                                        </th>
                                        <th className="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                            Send Name
                                        </th>
                                        <th className="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                            Scheduled
                                        </th>
                                        <th className="px-4 py-2.5 text-right text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                            {valueLabel}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {filteredEmails.map((email) => (
                                        <tr key={email.id} className="hover:bg-muted/20">
                                            <td className="px-4 py-3 font-medium max-w-xs">
                                                <span className="line-clamp-2">{email.subject}</span>
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground text-xs max-w-xs">
                                                <span className="line-clamp-2">{email.name}</span>
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground text-xs whitespace-nowrap">
                                                {formatScheduledDate(email.scheduled_date)}
                                            </td>
                                            <td className="px-4 py-3 text-right font-semibold tabular-nums">
                                                {metric ? getMetricValue(email, metric).toLocaleString() : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {filteredEmails.length > 0 && metric && (
                    <p className="text-xs text-muted-foreground text-right">
                        {filteredEmails.length.toLocaleString()} sent email{filteredEmails.length === 1 ? '' : 's'}
                        {' · '}
                        {filteredEmails.reduce((sum, email) => sum + getMetricValue(email, metric), 0).toLocaleString()}{' '}
                        {valueLabel.toLowerCase()}
                    </p>
                )}
            </DialogContent>
        </Dialog>
    );
}
