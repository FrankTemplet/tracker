import { useEffect, useState } from 'react';
import { RefreshCw } from 'lucide-react';
import { cn } from '@/lib/utils';

interface RefreshIndicatorProps {
    lastUpdated?: Date;
    isRefreshing?: boolean;
}

export function RefreshIndicator({
    lastUpdated,
    isRefreshing = false,
}: RefreshIndicatorProps) {
    const [timeAgo, setTimeAgo] = useState<string>('');

    useEffect(() => {
        if (!lastUpdated) {
            setTimeAgo('Never');
            return;
        }

        const updateTimeAgo = () => {
            const seconds = Math.floor(
                (new Date().getTime() - lastUpdated.getTime()) / 1000
            );

            if (seconds < 10) {
                setTimeAgo('Just now');
            } else if (seconds < 60) {
                setTimeAgo(`${seconds}s ago`);
            } else if (seconds < 3600) {
                const minutes = Math.floor(seconds / 60);
                setTimeAgo(`${minutes}m ago`);
            } else {
                const hours = Math.floor(seconds / 3600);
                setTimeAgo(`${hours}h ago`);
            }
        };

        updateTimeAgo();
        const interval = setInterval(updateTimeAgo, 1000);

        return () => clearInterval(interval);
    }, [lastUpdated]);

    return (
        <div className={cn(
            'flex items-center gap-2 rounded-lg border bg-muted/40 px-3 py-1.5 text-xs text-muted-foreground transition-colors',
            isRefreshing && 'bg-primary/5 border-primary/20 text-primary'
        )}>
            <RefreshCw
                className={cn('h-3 w-3', isRefreshing && 'animate-spin')}
            />
            <span className="font-medium">{isRefreshing ? 'Updating...' : `Updated ${timeAgo}`}</span>
        </div>
    );
}
