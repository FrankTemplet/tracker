import { cn } from '@/lib/utils';
import { Mail, Clock } from 'lucide-react';

export interface Email {
    id: string;
    campaign_id: string;
    subject: string;
    from: string;
    to: string;
    sent_at: string;
}

interface EmailListItemProps {
    email: Email;
    isSelected: boolean;
    onClick: () => void;
}

export function EmailListItem({
    email,
    isSelected,
    onClick,
}: EmailListItemProps) {
    const sentDate = new Date(email.sent_at);
    const formattedDate = sentDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    const formattedTime = sentDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

    return (
        <button
            type="button"
            className={cn(
                'w-full text-left rounded-xl border p-4 cursor-pointer transition-all duration-200',
                'hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                isSelected
                    ? 'border-primary/50 bg-primary/5 shadow-sm ring-1 ring-primary/20'
                    : 'border-border bg-card hover:border-muted-foreground/30 hover:bg-muted/30'
            )}
            onClick={onClick}
        >
            <div className="flex items-start gap-3">
                <div
                    className={cn(
                        'rounded-lg p-2 shrink-0 mt-0.5',
                        isSelected
                            ? 'bg-primary/15'
                            : 'bg-muted'
                    )}
                >
                    <Mail
                        className={cn(
                            'h-4 w-4',
                            isSelected ? 'text-primary' : 'text-muted-foreground'
                        )}
                    />
                </div>
                <div className="flex-1 min-w-0">
                    <p
                        className={cn(
                            'font-semibold text-sm truncate leading-snug',
                            isSelected ? 'text-primary' : 'text-foreground'
                        )}
                    >
                        {email.subject}
                    </p>
                    <p className="text-xs text-muted-foreground truncate mt-1">
                        {email.from}
                    </p>
                    <div className="flex items-center gap-1 mt-2">
                        <Clock className="h-3 w-3 text-muted-foreground/70 shrink-0" />
                        <span className="text-xs text-muted-foreground/70">
                            {formattedDate} · {formattedTime}
                        </span>
                    </div>
                </div>
                {isSelected && (
                    <div className="h-2 w-2 rounded-full bg-primary shrink-0 mt-1.5" />
                )}
            </div>
        </button>
    );
}
