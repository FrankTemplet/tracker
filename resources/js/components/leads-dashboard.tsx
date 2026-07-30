import { useState } from 'react';
import { TrendingUp, BarChart3, Target, ChevronLeft, ChevronRight } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
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
    owner: string;
    email: string;
    company: string;
}

export interface LeadsDashboardPageProps {
    variant: 'latam' | 'carib';
    summary: LeadSummaryLatam | LeadSummaryCarib;
    leads: Lead[];
    error?: string;
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

const PAGE_SIZE = 20;

function LeadsTable({ leads }: { leads: Lead[] }) {
    const [page, setPage] = useState(1);

    const totalPages = Math.max(1, Math.ceil(leads.length / PAGE_SIZE));
    const start = (page - 1) * PAGE_SIZE;
    const pageLeads = leads.slice(start, start + PAGE_SIZE);

    return (
        <Card>
            <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                    <CardTitle className="text-base font-semibold">Leads</CardTitle>
                    <span className="text-xs text-muted-foreground">
                        {leads.length.toLocaleString()} total
                    </span>
                </div>
            </CardHeader>
            <CardContent className="p-0">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-t border-b bg-muted/40">
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Name</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Email</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Company / Account</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Owner</th>
                            </tr>
                        </thead>
                        <tbody>
                            {leads.length === 0 ? (
                                <tr>
                                    <td colSpan={4} className="px-4 py-8 text-center text-muted-foreground text-sm">
                                        No leads found
                                    </td>
                                </tr>
                            ) : (
                                pageLeads.map((lead, i) => (
                                    <tr
                                        key={start + i}
                                        className="border-b last:border-0 hover:bg-muted/30 transition-colors"
                                    >
                                        <td className="px-4 py-3 font-medium">{lead.name}</td>
                                        <td className="px-4 py-3 text-muted-foreground">{lead.email}</td>
                                        <td className="px-4 py-3 text-muted-foreground">{lead.company}</td>
                                        <td className="px-4 py-3 text-muted-foreground">{lead.owner}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {totalPages > 1 && (
                    <div className="flex items-center justify-between border-t px-6 py-3">
                        <span className="text-xs text-muted-foreground">
                            {start + 1}–{Math.min(start + PAGE_SIZE, leads.length)} of {leads.length.toLocaleString()}
                        </span>
                        <div className="flex items-center gap-1">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setPage(p => Math.max(1, p - 1))}
                                disabled={page === 1}
                                className="h-7 w-7 p-0"
                            >
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                            <span className="text-xs px-2 text-muted-foreground">
                                {page} / {totalPages}
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setPage(p => Math.min(totalPages, p + 1))}
                                disabled={page === totalPages}
                                className="h-7 w-7 p-0"
                            >
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export function LeadsDashboard({ variant, summary, leads, error }: LeadsDashboardPageProps) {
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

            {error && (
                <div className="rounded-xl border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                    {error}
                </div>
            )}

            {isCarib ? (
                /* CARIB: 4 cards in a row above, table full-width below */
                <div className="flex flex-col gap-6">
                    <div className="grid grid-cols-4 gap-4">
                        <StatCard
                            label="Leads created"
                            value={caribSummary.leads_created}
                            icon={TrendingUp}
                            colorClass="text-emerald-600 dark:text-emerald-400"
                            iconBgClass="bg-emerald-500/10"
                        />
                        <StatCard
                            label="Leads assigned"
                            value={caribSummary.leads_assigned}
                            icon={TrendingUp}
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
            ) : (
                /* LATAM: 3 cards in a row, table full-width below */
                <div className="flex flex-col gap-6">
                    <div className="grid grid-cols-3 gap-4">
                        <StatCard
                            label="Leads assigned"
                            value={summary.leads_assigned}
                            icon={TrendingUp}
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
