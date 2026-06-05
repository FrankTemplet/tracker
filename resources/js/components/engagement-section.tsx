import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { MetricCard } from '@/components/metric-card';
import { Badge } from '@/components/ui/badge';
import { Users, UserCheck, CalendarClock, CalendarCheck } from 'lucide-react';

export interface EngagementData {
    opened: number;
    registered: number;
    schedule_appointment: number;
    attended: number;
}

interface EngagementSectionProps {
    engagement: EngagementData | null;
    isLoading?: boolean;
}

export function EngagementSection({ engagement, isLoading = false }: EngagementSectionProps) {
    if (!engagement && !isLoading) {
        return null;
    }

    return (
        <Card>
            <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <div className="rounded-lg bg-indigo-500/10 p-1.5">
                            <Users className="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <CardTitle className="text-base font-semibold">Engagement</CardTitle>
                    </div>
                    <Badge variant="outline" className="text-xs font-normal text-muted-foreground">
                        Datos de prueba
                    </Badge>
                </div>
            </CardHeader>
            <CardContent>
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <MetricCard
                        title="Opened"
                        value={engagement?.opened ?? 0}
                        icon={Users}
                        variant="default"
                        isLoading={isLoading}
                    />
                    <MetricCard
                        title="Registered"
                        value={engagement?.registered ?? 0}
                        icon={UserCheck}
                        variant="success"
                        isLoading={isLoading}
                    />
                    <MetricCard
                        title="Schedule Appointment"
                        value={engagement?.schedule_appointment ?? 0}
                        icon={CalendarClock}
                        variant="default"
                        isLoading={isLoading}
                    />
                    <MetricCard
                        title="Attended"
                        value={engagement?.attended ?? 0}
                        icon={CalendarCheck}
                        variant="success"
                        isLoading={isLoading}
                    />
                </div>
            </CardContent>
        </Card>
    );
}
