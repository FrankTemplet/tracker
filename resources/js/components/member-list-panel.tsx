import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Users, X, Eye, MousePointerClick, AlertTriangle, Send } from 'lucide-react';
import type { ComponentType } from 'react';
import type { MemberStatus } from '@/components/campaign-metrics';

export interface Member {
    member_id: string;
    first_name: string;
    last_name: string;
    email: string;
    company: string;
    status_update_date: string;
}

interface StatusConfig {
    label: string;
    colorClass: string;
    Icon: ComponentType<{ className?: string }>;
}

const STATUS_CONFIG: Record<string, StatusConfig> = {
    Opened: { label: 'Opened', colorClass: 'bg-sky-500/10 text-sky-600 dark:text-sky-400', Icon: Eye },
    Clicked: { label: 'Clicked', colorClass: 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400', Icon: MousePointerClick },
    Bounced: { label: 'Bounced', colorClass: 'bg-amber-500/10 text-amber-600 dark:text-amber-400', Icon: AlertTriangle },
    Sent: { label: 'Sent', colorClass: 'bg-muted text-foreground', Icon: Send },
};

interface MemberListPanelProps {
    members: Member[];
    status: MemberStatus;
    isLoading?: boolean;
    onClose: () => void;
}

export function MemberListPanel({ members, status, isLoading = false, onClose }: MemberListPanelProps) {
    const config = STATUS_CONFIG[status] ?? STATUS_CONFIG.Sent;
    const { Icon } = config;

    return (
        <Card>
            <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <div className={`rounded-lg p-1.5 ${config.colorClass}`}>
                            <Icon className="h-4 w-4" />
                        </div>
                        <CardTitle className="text-base font-semibold">{config.label} Members</CardTitle>
                        <Badge variant="secondary" className="text-xs">
                            {isLoading ? '…' : members.length.toLocaleString()}
                        </Badge>
                    </div>
                    <Button variant="ghost" size="sm" onClick={onClose} className="h-8 w-8 p-0">
                        <X className="h-4 w-4" />
                    </Button>
                </div>
            </CardHeader>
            <CardContent className="p-0">
                {isLoading ? (
                    <div className="p-4 space-y-3">
                        {Array.from({ length: 5 }).map((_, i) => (
                            <div key={i} className="flex gap-4">
                                <Skeleton className="h-4 w-32" />
                                <Skeleton className="h-4 w-48" />
                                <Skeleton className="h-4 w-32" />
                                <Skeleton className="h-4 w-24" />
                            </div>
                        ))}
                    </div>
                ) : members.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-10 gap-2">
                        <Users className="h-5 w-5 text-muted-foreground/50" />
                        <p className="text-xs text-muted-foreground">No members found for this status</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/30">
                                    <th className="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wider">Name</th>
                                    <th className="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wider">Email</th>
                                    <th className="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wider">Company</th>
                                    <th className="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground uppercase tracking-wider">Date</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {members.map((member) => (
                                    <tr key={member.member_id} className="hover:bg-muted/20 transition-colors">
                                        <td className="px-4 py-3 font-medium">
                                            {member.first_name} {member.last_name}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground font-mono text-xs">{member.email}</td>
                                        <td className="px-4 py-3 text-muted-foreground text-sm">{member.company}</td>
                                        <td className="px-4 py-3 text-muted-foreground text-xs">{member.status_update_date}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
