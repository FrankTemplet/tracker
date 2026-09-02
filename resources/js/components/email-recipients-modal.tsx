import { useHttp } from '@inertiajs/react';
import { Users, Loader2, Download, AlertTriangle } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { emailLogs } from '@/actions/App/Http/Controllers/EmailEngagementController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { exportToExcel } from '@/lib/export-to-excel';
import { ENGAGEMENT_DESCRIPTION, ENGAGEMENT_EMPTY_STATE } from '@/lib/recipient-engagement';
import type { RecipientEngagementFilter } from '@/lib/recipient-engagement';

const PAGE_SIZE = 100;

export interface EmailEngagementLog {
    id: string;
    recipient_email: string;
    email_name: string;
    email_subject: string;
    campaign_id: string;
    campaign_name: string;
    list_email_id: string;
    prospect_id: string;
    date_sent: string | null;
    delivered: number;
    opens: number;
    clicks: number;
    hard_bounced: number;
    soft_bounced: number;
}

interface EmailRecipientsModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    campaignId: string;
    emailName: string | null;
    emailSubject?: string;
    /** Restrict the recipients to one engagement subset; omit for every recipient. */
    engagement?: RecipientEngagementFilter | null;
}

interface LogsResponse {
    success: boolean;
    data?: EmailEngagementLog[];
    next_cursor?: string | null;
    total?: number | null;
    message?: string;
}

function formatSentDate(value: string | null): string {
    if (!value) {
        return '—';
    }

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

function statusOf(log: EmailEngagementLog): string {
    if (log.hard_bounced > 0) {
        return 'Hard bounced';
    }

    if (log.soft_bounced > 0) {
        return 'Soft bounced';
    }

    if (log.delivered > 0) {
        return 'Delivered';
    }

    return 'Not delivered';
}

export function EmailRecipientsModal({
    open,
    onOpenChange,
    campaignId,
    emailName,
    emailSubject,
    engagement = null,
}: EmailRecipientsModalProps) {
    const { submit } = useHttp();
    const [logs, setLogs] = useState<EmailEngagementLog[]>([]);
    const [cursor, setCursor] = useState<string | null>(null);
    const [total, setTotal] = useState<number | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [isLoadingMore, setIsLoadingMore] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchPage = useCallback(
        async (nextCursor: string | null): Promise<LogsResponse | null> => {
            if (!campaignId || !emailName) {
                return null;
            }

            return (await submit(
                emailLogs(campaignId, {
                    query: {
                        email_name: emailName,
                        page_size: PAGE_SIZE,
                        ...(engagement ? { engagement } : {}),
                        ...(nextCursor ? { cursor: nextCursor } : {}),
                    },
                }),
            )) as LogsResponse;
        },
        [campaignId, emailName, engagement, submit],
    );

    // First page whenever a different send is opened
    useEffect(() => {
        if (!open || !campaignId || !emailName) {
            return;
        }

        let isMounted = true;

        const loadFirstPage = async () => {
            setIsLoading(true);
            setError(null);
            setLogs([]);
            setCursor(null);
            setTotal(null);

            try {
                const response = await fetchPage(null);

                if (!isMounted) {
                    return;
                }

                if (response?.success) {
                    setLogs(response.data ?? []);
                    setCursor(response.next_cursor ?? null);
                    setTotal(response.total ?? null);
                } else {
                    setError(response?.message ?? 'Failed to load recipients.');
                }
            } catch (err) {
                if (isMounted) {
                    setError('An error occurred while fetching recipients.');
                    console.error(err);
                }
            } finally {
                if (isMounted) {
                    setIsLoading(false);
                }
            }
        };

        loadFirstPage();

        return () => {
            isMounted = false;
        };
    }, [open, campaignId, emailName, fetchPage]);

    const handleLoadMore = async () => {
        if (!cursor || isLoadingMore) {
            return;
        }

        setIsLoadingMore(true);

        try {
            const response = await fetchPage(cursor);

            if (response?.success) {
                setLogs((current) => [...current, ...(response.data ?? [])]);
                setCursor(response.next_cursor ?? null);
            } else {
                setError(response?.message ?? 'Failed to load more recipients.');
            }
        } catch (err) {
            setError('An error occurred while fetching recipients.');
            console.error(err);
        } finally {
            setIsLoadingMore(false);
        }
    };

    const handleDownload = () => {
        exportToExcel(
            logs.map((log) => ({
                Recipient: log.recipient_email,
                Status: statusOf(log),
                Opens: log.opens,
                Clicks: log.clicks,
                Sent: formatSentDate(log.date_sent),
            })),
            emailName ?? 'recipients',
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-5xl max-h-[85vh] flex flex-col">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Users className="h-5 w-5 text-sky-600 dark:text-sky-400" />
                        {emailSubject || emailName || 'Recipients'}
                    </DialogTitle>
                    <DialogDescription className="break-all">
                        {engagement ? ENGAGEMENT_DESCRIPTION[engagement] : 'Recipient engagement'} · {emailName}
                    </DialogDescription>
                </DialogHeader>

                <div className="flex-1 overflow-hidden -mx-6 px-6">
                    {isLoading ? (
                        <div className="p-4 space-y-3">
                            <div className="flex items-center justify-center py-8 gap-2 text-muted-foreground text-sm">
                                <Loader2 className="h-4 w-4 animate-spin text-primary" />
                                Loading recipients...
                            </div>
                            {Array.from({ length: 5 }).map((_, i) => (
                                <div key={i} className="flex gap-4">
                                    <Skeleton className="h-4 w-1/3" />
                                    <Skeleton className="h-4 w-1/6" />
                                    <Skeleton className="h-4 w-1/6" />
                                    <Skeleton className="h-4 w-1/4" />
                                </div>
                            ))}
                        </div>
                    ) : error ? (
                        <div className="flex flex-col items-center justify-center py-12 gap-2">
                            <AlertTriangle className="h-5 w-5 text-amber-500" />
                            <p className="text-sm text-muted-foreground text-center">{error}</p>
                        </div>
                    ) : logs.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 gap-2">
                            <Users className="h-5 w-5 text-muted-foreground/50" />
                            <p className="text-sm text-muted-foreground text-center">
                                {engagement
                                    ? ENGAGEMENT_EMPTY_STATE[engagement]
                                    : 'No recipients found for this email'}
                            </p>
                            <p className="text-xs text-muted-foreground/70 text-center max-w-md">
                                The engagement log is loaded separately from the campaign metrics and
                                covers only part of the catalogue, so a send can report a total here
                                with no recipient rows behind it yet.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-auto max-h-[55vh] rounded-lg border">
                            <table className="w-full text-sm">
                                <thead className="sticky top-0 bg-muted/80 backdrop-blur-sm z-10">
                                    <tr className="border-b">
                                        <th className="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                            Recipient
                                        </th>
                                        <th className="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th className="px-4 py-2.5 text-right text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                            Opens
                                        </th>
                                        <th className="px-4 py-2.5 text-right text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                            Clicks
                                        </th>
                                        <th className="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                            Sent
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {logs.map((log) => (
                                        <tr key={log.id} className="hover:bg-muted/20">
                                            <td className="px-4 py-3 font-medium break-all">{log.recipient_email}</td>
                                            <td className="px-4 py-3 text-muted-foreground text-xs whitespace-nowrap">
                                                {statusOf(log)}
                                            </td>
                                            <td className="px-4 py-3 text-right font-semibold tabular-nums">
                                                {log.opens.toLocaleString()}
                                            </td>
                                            <td className="px-4 py-3 text-right font-semibold tabular-nums">
                                                {log.clicks.toLocaleString()}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground text-xs whitespace-nowrap">
                                                {formatSentDate(log.date_sent)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>

                            {cursor && (
                                <div className="flex justify-center border-t p-3">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={handleLoadMore}
                                        disabled={isLoadingMore}
                                        className="gap-1.5"
                                    >
                                        {isLoadingMore && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                                        Load more
                                    </Button>
                                </div>
                            )}
                        </div>
                    )}
                </div>

                {logs.length > 0 && (
                    <div className="flex items-center justify-between gap-2">
                        <Button variant="outline" size="sm" onClick={handleDownload} className="gap-1.5">
                            <Download className="h-3.5 w-3.5" />
                            Download Excel
                        </Button>
                        <p className="text-xs text-muted-foreground text-right">
                            {logs.length.toLocaleString()} shown
                            {total !== null && total > logs.length ? ` of ${total.toLocaleString()}` : ''}
                            {' · '}
                            {logs.reduce((sum, log) => sum + log.opens, 0).toLocaleString()} opens
                            {' · '}
                            {logs.reduce((sum, log) => sum + log.clicks, 0).toLocaleString()} clicks
                        </p>
                    </div>
                )}

                <p className="text-[11px] leading-relaxed text-muted-foreground/80 border-t pt-3">
                    * Note: Any discrepancy in unique opens may be due to data synchronization delays or
                    Pardot tracking variations.
                </p>
            </DialogContent>
        </Dialog>
    );
}
