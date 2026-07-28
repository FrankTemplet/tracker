import { Users, TrendingUp, BarChart3, Target } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CampaignFilters, type Region } from '@/components/campaign-filters';
import type { LucideIcon } from 'lucide-react';

export interface LeadSummaryLatam {
    leads_assigned: number;
    mqls: number;
    sqls: number;
}

export interface LeadSummaryCarib extends LeadSummaryLatam {
    leads_created: number;
}

export interface Lead {
    name: string;
    created_date: string;
    owner: string;
}

export interface LeadsDashboardPageProps {
    variant: 'latam' | 'carib';
    summary: LeadSummaryLatam | LeadSummaryCarib;
    leads: Lead[];
    availableRegions?: Region[];
    selectedRegion?: Region;
    selectedYear?: string;
}

interface StatCardProps {
    label: string;
    value: number;
    icon: LucideIcon;
    colorClass: string;
    iconBgClass: string;
}

function StatCard({ label, value, icon: Icon, colorClass, iconBgClass }: StatCardProps) {
    return (
        <div className="rounded-xl border bg-card p-5 shadow-sm flex items-center gap-4">
            <div className={`rounded-lg p-2.5 shrink-0 ${iconBgClass}`}>
                <Icon className={`h-5 w-5 ${colorClass}`} />
            </div>
            <div>
                <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                    {label}
                </p>
                <p className={`text-3xl font-bold ${colorClass}`}>{value.toLocaleString()}</p>
            </div>
        </div>
    );
}

function LeadsTable({ leads }: { leads: Lead[] }) {
    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="text-base font-semibold">Leads</CardTitle>
            </CardHeader>
            <CardContent className="p-0">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-t border-b bg-muted/40">
                                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                    Name
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                    Created Date
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                    Owner
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {leads.map((lead, i) => (
                                <tr
                                    key={i}
                                    className="border-b last:border-0 hover:bg-muted/30 transition-colors"
                                >
                                    <td className="px-6 py-3 font-medium">{lead.name}</td>
                                    <td className="px-6 py-3 text-muted-foreground">
                                        {new Date(lead.created_date).toLocaleDateString('en-US', {
                                            year: 'numeric',
                                            month: 'short',
                                            day: 'numeric',
                                        })}
                                    </td>
                                    <td className="px-6 py-3 text-muted-foreground">{lead.owner}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    );
}

export function LeadsDashboard({
    variant,
    summary,
    leads,
    availableRegions,
    selectedRegion,
    selectedYear,
}: LeadsDashboardPageProps) {
    const isCarib = variant === 'carib';
    const caribSummary = summary as LeadSummaryCarib;

    return (
        <div className="flex h-full flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 className="text-xl font-bold tracking-tight">Lead Management</h1>
                <p className="text-xs text-muted-foreground mt-0.5">
                    {isCarib ? 'Caribbean region leads overview' : 'LATAM & Networks region leads overview'}
                </p>
            </div>

            <div className="flex flex-wrap items-stretch gap-3">
                <CampaignFilters
                    selectedRegion={selectedRegion}
                    selectedYear={selectedYear}
                    onRegionChange={() => {}}
                    onYearChange={() => {}}
                    availableRegions={availableRegions}
                />
            </div>

            {isCarib ? (
                /* CARIB: 2×2 cards on the left, table on the right */
                <div className="flex gap-6 items-start">
                    <div className="grid grid-cols-2 gap-4 shrink-0 w-fit">
                        <StatCard
                            label="Leads created"
                            value={caribSummary.leads_created}
                            icon={TrendingUp}
                            colorClass="text-emerald-600 dark:text-emerald-400"
                            iconBgClass="bg-emerald-500/10"
                        />
                        <StatCard
                            label="Leads assigned"
                            value={summary.leads_assigned}
                            icon={Users}
                            colorClass="text-sky-600 dark:text-sky-400"
                            iconBgClass="bg-sky-500/10"
                        />
                        <StatCard
                            label="MQL's"
                            value={summary.mqls}
                            icon={Target}
                            colorClass="text-violet-600 dark:text-violet-400"
                            iconBgClass="bg-violet-500/10"
                        />
                        <StatCard
                            label="SQL's"
                            value={summary.sqls}
                            icon={BarChart3}
                            colorClass="text-amber-600 dark:text-amber-400"
                            iconBgClass="bg-amber-500/10"
                        />
                    </div>
                    <div className="flex-1 min-w-0">
                        <LeadsTable leads={leads} />
                    </div>
                </div>
            ) : (
                /* LATAM: 3 cards in a row, table full-width below */
                <div className="flex flex-col gap-6">
                    <div className="grid grid-cols-3 gap-4">
                        <StatCard
                            label="Leads assigned"
                            value={summary.leads_assigned}
                            icon={Users}
                            colorClass="text-sky-600 dark:text-sky-400"
                            iconBgClass="bg-sky-500/10"
                        />
                        <StatCard
                            label="MQL's"
                            value={summary.mqls}
                            icon={Target}
                            colorClass="text-violet-600 dark:text-violet-400"
                            iconBgClass="bg-violet-500/10"
                        />
                        <StatCard
                            label="SQL's"
                            value={summary.sqls}
                            icon={BarChart3}
                            colorClass="text-amber-600 dark:text-amber-400"
                            iconBgClass="bg-amber-500/10"
                        />
                    </div>
                    <LeadsTable leads={leads} />
                </div>
            )}
        </div>
    );
}
