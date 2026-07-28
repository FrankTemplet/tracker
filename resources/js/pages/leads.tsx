import { Head } from '@inertiajs/react';
import { leads } from '@/routes';
import { LeadsDashboard, type LeadsDashboardPageProps } from '@/components/leads-dashboard';

export default function Leads(props: LeadsDashboardPageProps) {
    return (
        <>
            <Head title="Lead Management" />
            <LeadsDashboard {...props} />
        </>
    );
}

Leads.layout = {
    breadcrumbs: [
        {
            title: 'Lead Management',
            href: leads(),
        },
    ],
};
