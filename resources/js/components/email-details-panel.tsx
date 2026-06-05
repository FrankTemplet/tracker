import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Mail, User, Send, Hash } from 'lucide-react';
import type { Email } from '@/components/email-list-item';

interface EmailDetailsPanelProps {
    email: Email | null;
}

export function EmailDetailsPanel({ email }: EmailDetailsPanelProps) {
    if (!email) {
        return (
            <Card className="border-dashed">
                <CardContent className="flex flex-col items-center justify-center py-10 gap-3">
                    <div className="rounded-full bg-muted p-4">
                        <Mail className="h-6 w-6 text-muted-foreground" />
                    </div>
                    <div className="text-center">
                        <p className="text-sm font-medium text-foreground">No email selected</p>
                        <p className="text-xs text-muted-foreground mt-1">Select an email to view details</p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    const sentDate = new Date(email.sent_at);

    return (
        <Card className="overflow-hidden">
            <CardHeader className="pb-3 border-b bg-muted/30">
                <div className="flex items-start justify-between gap-3">
                    <div className="flex items-center gap-2 min-w-0">
                        <div className="rounded-lg bg-primary/10 p-1.5 shrink-0">
                            <Mail className="h-4 w-4 text-primary" />
                        </div>
                        <div className="min-w-0">
                            <CardTitle className="text-sm font-semibold truncate">
                                {email.subject}
                            </CardTitle>
                        </div>
                    </div>
                    <Badge variant="secondary" className="shrink-0 text-xs">
                        {sentDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="p-4">
                <div className="grid grid-cols-1 gap-3">
                    <div className="flex items-start gap-3 rounded-lg bg-muted/40 p-3">
                        <div className="rounded-md bg-background p-1.5 shrink-0 border">
                            <User className="h-3.5 w-3.5 text-muted-foreground" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide mb-0.5">From</p>
                            <p className="text-sm truncate">{email.from}</p>
                        </div>
                    </div>

                    <div className="flex items-start gap-3 rounded-lg bg-muted/40 p-3">
                        <div className="rounded-md bg-background p-1.5 shrink-0 border">
                            <Send className="h-3.5 w-3.5 text-muted-foreground" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide mb-0.5">To</p>
                            <p className="text-sm truncate">{email.to}</p>
                        </div>
                    </div>

                    <div className="flex items-start gap-3 rounded-lg bg-muted/40 p-3">
                        <div className="rounded-md bg-background p-1.5 shrink-0 border">
                            <Hash className="h-3.5 w-3.5 text-muted-foreground" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide mb-0.5">Email ID</p>
                            <p className="text-xs font-mono text-muted-foreground truncate">{email.id}</p>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
