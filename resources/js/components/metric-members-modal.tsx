import { useEffect, useState } from 'react';
import { useHttp } from '@inertiajs/react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Users, Mail, Loader2 } from 'lucide-react';
import { campaignMembers } from '@/actions/App/Http/Controllers/PowerBiController';
import { Skeleton } from '@/components/ui/skeleton';

export interface Member {
    member_id: string;
    first_name: string;
    last_name: string;
    email: string;
    company: string;
    status_update_date: string;
}

interface MetricMembersModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    campaignId: string;
    metric: string | null;
    title: string;
}

function formatStatusDate(value: string): string {
    if (!value) return '—';
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

export function MetricMembersModal({
    open,
    onOpenChange,
    campaignId,
    metric,
    title,
}: MetricMembersModalProps) {
    const { submit } = useHttp();
    const [members, setMembers] = useState<Member[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!open || !campaignId || !metric) {
            setMembers([]);
            return;
        }

        let isMounted = true;
        const fetchMembers = async () => {
            setIsLoading(true);
            setError(null);
            try {
                const response = (await submit(
                    campaignMembers({ campaignId, status: metric })
                )) as { success: boolean; data: Member[] };

                if (isMounted) {
                    if (response && response.success) {
                        setMembers(response.data);
                    } else {
                        setError('Failed to load members.');
                    }
                }
            } catch (err) {
                if (isMounted) {
                    setError('An error occurred while fetching members.');
                    console.error(err);
                }
            } finally {
                if (isMounted) {
                    setIsLoading(false);
                }
            }
        };

        fetchMembers();

        return () => {
            isMounted = false;
        };
    }, [open, campaignId, metric, submit]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-4xl max-h-[85vh] flex flex-col">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Users className="h-5 w-5 text-sky-600 dark:text-sky-400" />
                        {title}
                    </DialogTitle>
                    <DialogDescription>
                        Campaign members from the Engagement table contributing to this total
                    </DialogDescription>
                </DialogHeader>

                <div className="flex-1 overflow-hidden -mx-6 px-6">
                    {isLoading ? (
                        <div className="p-4 space-y-3">
                            <div className="flex items-center justify-center py-8 gap-2 text-muted-foreground text-sm">
                                <Loader2 className="h-4 w-4 animate-spin text-primary" />
                                Loading members data...
                            </div>
                            {Array.from({ length: 5 }).map((_, i) => (
                                <div key={i} className="flex gap-4">
                                    <Skeleton className="h-4 w-1/4" />
                                    <Skeleton className="h-4 w-1/3" />
                                    <Skeleton className="h-4 w-1/4" />
                                    <Skeleton className="h-4 w-1/6" />
                                </div>
                            ))}
                        </div>
                    ) : error ? (
                        <div className="flex flex-col items-center justify-center py-12 gap-2 text-destructive">
                            <p className="text-sm font-semibold">{error}</p>
                        </div>
                    ) : members.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 gap-2">
                            <Mail className="h-5 w-5 text-muted-foreground/50" />
                            <p className="text-sm text-muted-foreground">No members found for this status</p>
                        </div>
                    ) : (
                        <div className="overflow-auto max-h-[55vh] rounded-lg border">
                            <table className="w-full text-sm">
                                <thead className="sticky top-0 bg-muted/80 backdrop-blur-sm z-10">
                                    <tr className="border-b">
                                        <th className="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                            Name
                                        </th>
                                        <th className="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                            Email
                                        </th>
                                        <th className="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                            Company
                                        </th>
                                        <th className="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                            Date
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {members.map((member) => (
                                        <tr key={member.member_id} className="hover:bg-muted/20 transition-colors">
                                            <td className="px-4 py-3 font-medium">
                                                {member.first_name} {member.last_name}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground font-mono text-xs">
                                                {member.email}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground text-sm">
                                                {member.company}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground text-xs whitespace-nowrap">
                                                {formatStatusDate(member.status_update_date)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {!isLoading && !error && members.length > 0 && (
                    <p className="text-xs text-muted-foreground text-right mt-2">
                        {members.length.toLocaleString()} member{members.length === 1 ? '' : 's'} found
                    </p>
                )}
            </DialogContent>
        </Dialog>
    );
}
