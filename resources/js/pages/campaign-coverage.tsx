import { Head } from '@inertiajs/react';
import { CampaignCoverage } from '@/components/campaign-coverage';
import type { CampaignCoveragePageProps } from '@/components/campaign-coverage';
import { campaignCoverage } from '@/routes';

export default function CampaignCoveragePage(props: CampaignCoveragePageProps) {
    return (
        <>
            <Head title="Catálogo de campañas" />
            <CampaignCoverage {...props} />
        </>
    );
}

CampaignCoveragePage.layout = {
    breadcrumbs: [
        {
            title: 'Catálogo de campañas',
            href: campaignCoverage(),
        },
    ],
};
