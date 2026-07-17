import { CampaignDashboard, type CampaignDashboardPageProps } from '@/components/campaign-dashboard';
import { events } from '@/routes';

export default function Events(props: CampaignDashboardPageProps) {
    return (
        <CampaignDashboard
            {...props}
            title="Events"
            heading="Events Report"
            description="Monitor your event campaigns and analytics"
            currentRoute={events}
        />
    );
}

Events.layout = {
    breadcrumbs: [
        {
            title: 'Events',
            href: events(),
        },
    ],
};
