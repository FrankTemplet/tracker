import { Skeleton } from '@/components/ui/skeleton';
import { Card } from '@/components/ui/card';

export function DashboardSkeleton() {
    return (
        <div className="flex h-full gap-4">
            {/* Email List Skeleton */}
            <div className="w-1/3 space-y-3">
                <Skeleton className="h-8 w-32 mb-4" />
                {Array.from({ length: 5 }).map((_, i) => (
                    <Card key={i} className="p-4">
                        <div className="flex items-start gap-3">
                            <Skeleton className="h-10 w-10 rounded-full" />
                            <div className="flex-1 space-y-2">
                                <Skeleton className="h-4 w-3/4" />
                                <Skeleton className="h-3 w-full" />
                                <Skeleton className="h-3 w-1/2" />
                            </div>
                        </div>
                    </Card>
                ))}
            </div>

            {/* Details Panel Skeleton */}
            <div className="flex-1 space-y-4">
                <Card className="h-1/2 p-6">
                    <Skeleton className="h-6 w-32 mb-4" />
                    <div className="space-y-4">
                        <Skeleton className="h-4 w-full" />
                        <Skeleton className="h-4 w-3/4" />
                        <Skeleton className="h-4 w-1/2" />
                    </div>
                </Card>

                <Card className="h-1/2 p-6">
                    <Skeleton className="h-6 w-40 mb-4" />
                    <div className="grid grid-cols-3 gap-4">
                        {Array.from({ length: 3 }).map((_, i) => (
                            <Card key={i} className="p-4">
                                <Skeleton className="h-4 w-20 mb-2" />
                                <Skeleton className="h-8 w-16 mb-1" />
                                <Skeleton className="h-3 w-24" />
                            </Card>
                        ))}
                    </div>
                </Card>
            </div>
        </div>
    );
}
