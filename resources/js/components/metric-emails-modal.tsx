import { Mail, Download, ChevronRight } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { EmailCampaignMetric, MetricDrilldownKey } from '@/components/campaign-metrics';
import { EmailRecipientsModal } from '@/components/email-recipients-modal';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { exportToExcel } from '@/lib/export-to-excel';

interface MetricEmailsModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    emails: EmailCampaignMetric[];
    metric: MetricDrilldownKey | null;
    title: string;
    campaignId?: string;
    /** Recipient engagement lives in Dataverse, which only holds Carib data for now. */
    region?: string;
}

const METRIC_VALUE_LABEL: Record<MetricDrilldownKey, string> = {
    delivered: 'Delivered',
    'unique-opens': 'Unique Opens',
    'total-opens': 'Total Opens',
    'unique-clicks': 'Unique Clicks',
    'hard-bounces': 'Hard Bounces',
    'registered-appointment': 'Registered / Schedule Appointment',
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
            default:
                return false;
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
        default:
            return 0;
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
    campaignId,
    region,
}: MetricEmailsModalProps) {
    const [selectedEmail, setSelectedEmail] = useState<EmailCampaignMetric | null>(null);
    const filteredEmails = useMemo(() => {
        if (!metric) {
            return [];
        }

        return filterEmailsByMetric(emails, metric);
    }, [emails, metric]);

    const valueLabel = metric ? METRIC_VALUE_LABEL[metric] : '';

    // Drilling into recipients is only wired for Carib deliveries so far
    const canDrillIntoRecipients =
        metric === 'delivered' && region?.toLowerCase() === 'carib' && !!campaignId;

    const handleDownload = () => {
        if (!metric) {
return;
}

        exportToExcel(
            filteredEmails.map((email) => ({
                Subject: email.subject,
                'Send Name': email.name,
                Scheduled: formatScheduledDate(email.scheduled_date),
                [valueLabel]: getMetricValue(email, metric),
            })),
            title,
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-4xl max-h-[85vh] flex flex-col">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>
                        Sent emails from Email Campaign Metrics contributing to this total
                        {canDrillIntoRecipients && ' · select an email to see its recipients'}
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
                                        <tr
                                            key={email.id}
                                            onClick={canDrillIntoRecipients ? () => setSelectedEmail(email) : undefined}
                                            className={
                                                canDrillIntoRecipients
                                                    ? 'hover:bg-muted/40 cursor-pointer'
                                                    : 'hover:bg-muted/20'
                                            }
                                        >
                                            <td className="px-4 py-3 font-medium max-w-xs">
                                                <span className="flex items-start gap-1.5">
                                                    {canDrillIntoRecipients && (
                                                        <ChevronRight className="h-3.5 w-3.5 mt-0.5 shrink-0 text-muted-foreground" />
                                                    )}
                                                    <span className="line-clamp-2">{email.subject}</span>
                                                </span>
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
                    <div className="flex items-center justify-between gap-2">
                        <Button variant="outline" size="sm" onClick={handleDownload} className="gap-1.5">
                            <Download className="h-3.5 w-3.5" />
                            Download Excel
                        </Button>
                        <p className="text-xs text-muted-foreground text-right">
                            {filteredEmails.length.toLocaleString()} sent email{filteredEmails.length === 1 ? '' : 's'}
                            {' · '}
                            {filteredEmails.reduce((sum, email) => sum + getMetricValue(email, metric), 0).toLocaleString()}{' '}
                            {valueLabel.toLowerCase()}
                        </p>
                    </div>
                )}

                <p className="text-[11px] leading-relaxed text-muted-foreground/80 border-t pt-3">
                    * Note: Any discrepancy in unique opens may be due to data synchronization delays or
                    Pardot tracking variations.
                </p>
            </DialogContent>

            <EmailRecipientsModal
                open={selectedEmail !== null}
                onOpenChange={(isOpen) => !isOpen && setSelectedEmail(null)}
                campaignId={campaignId ?? ''}
                emailName={selectedEmail?.name ?? null}
                emailSubject={selectedEmail?.subject}
            />
        </Dialog>
    );
}
