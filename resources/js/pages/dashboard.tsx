import { CampaignDashboard, type CampaignDashboardPageProps } from '@/components/campaign-dashboard';
import { dashboard } from '@/routes';

export default function Dashboard(props: CampaignDashboardPageProps) {
    return (
        <CampaignDashboard
            {...props}
            title="Dashboard"
            heading="Email Send Report"
            description="Monitor your email campaigns and analytics"
            currentRoute={dashboard}
        />
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
