import { CampaignDashboard, type CampaignDashboardPageProps } from '@/components/campaign-dashboard';
import { webinars } from '@/routes';

export default function Webinars(props: CampaignDashboardPageProps) {
    return (
        <CampaignDashboard
            {...props}
            title="Webinars"
            heading="Webinars Report"
            description="Monitor your webinar campaigns and analytics"
            currentRoute={webinars}
        />
    );
}

Webinars.layout = {
    breadcrumbs: [
        {
            title: 'Webinars',
            href: webinars(),
        },
    ],
};
