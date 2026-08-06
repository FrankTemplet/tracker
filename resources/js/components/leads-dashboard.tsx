import { useState, useMemo } from 'react';
import { TrendingUp, BarChart3, Target, ChevronLeft, ChevronRight, Filter, User, Clock, ArrowRightLeft, Tag, Activity } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
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
    created_date: string;
    country: string;
    lead_stage: string;
    created_by: string;
    created_alias: string;
}

export interface LeadsDashboardPageProps {
    variant: 'latam' | 'carib';
    summary: LeadSummaryLatam | LeadSummaryCarib;
    leads: Lead[];
    error?: string;
}

type CardKey = 'leads_created' | 'leads_assigned' | 'mqls' | 'sqls';

// ---------------------------------------------------------------------------
// Stat card
// ---------------------------------------------------------------------------

interface StatCardProps {
    label: string;
    value: number;
    icon: LucideIcon;
    colorClass: string;
    iconBgClass: string;
    onClick?: () => void;
}

function StatCard({ label, value, icon: Icon, colorClass, iconBgClass, onClick }: StatCardProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="rounded-xl border bg-card p-5 shadow-sm flex items-center gap-4 w-full text-left transition-colors hover:bg-muted/40 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
            <div className={`rounded-lg p-2.5 shrink-0 ${iconBgClass}`}>
                <Icon className={`h-5 w-5 ${colorClass}`} />
            </div>
            <div>
                <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">{label}</p>
                <p className={`text-3xl font-bold ${colorClass}`}>{value.toLocaleString()}</p>
            </div>
        </button>
    );
}

// ---------------------------------------------------------------------------
// Period helpers
// ---------------------------------------------------------------------------

const PAGE_SIZE = 20;

const PERIOD_OPTIONS = [
    { value: 'all', label: 'All time' },
    { value: 'this_month', label: 'This month' },
    { value: 'last_3_months', label: 'Last 3 months' },
    { value: 'last_6_months', label: 'Last 6 months' },
    { value: 'this_year', label: 'This year' },
    { value: 'last_year', label: 'Last year' },
];

function parseLeadDate(dateStr: string): Date | null {
    if (!dateStr) return null;
    const parts = dateStr.split('/');
    if (parts.length !== 3) return null;
    const [m, d, y] = parts.map(Number);
    return new Date(y, m - 1, d);
}

function filterByPeriod(leads: Lead[], period: string): Lead[] {
    if (period === 'all') return leads;
    const now = new Date();
    const thisYear = now.getFullYear();
    const thisMonth = now.getMonth();
    return leads.filter(lead => {
        const d = parseLeadDate(lead.created_date);
        if (!d) return false;
        if (period === 'this_month') return d.getFullYear() === thisYear && d.getMonth() === thisMonth;
        if (period === 'last_3_months') { const c = new Date(now); c.setMonth(c.getMonth() - 3); return d >= c; }
        if (period === 'last_6_months') { const c = new Date(now); c.setMonth(c.getMonth() - 6); return d >= c; }
        if (period === 'this_year') return d.getFullYear() === thisYear;
        if (period === 'last_year') return d.getFullYear() === thisYear - 1;
        return true;
    });
}

// ---------------------------------------------------------------------------
// Card drill-down helpers
// ---------------------------------------------------------------------------

function getLeadsByCard(leads: Lead[], card: CardKey): Lead[] {
    switch (card) {
        case 'leads_created': return leads.filter(l => l.created_by === 'Sales Outcomes Lead Triage');
        case 'leads_assigned': return leads.filter(l => l.created_alias === 'b2bmausr' || l.created_alias === 'LeadTrge');
        case 'mqls': return leads.filter(l => l.lead_stage === 'MQL');
        case 'sqls': return leads.filter(l => l.lead_stage === 'SQL');
    }
}

const CARD_LABELS: Record<CardKey, string> = {
    leads_created: "Leads Created",
    leads_assigned: "Leads Assigned",
    mqls: "MQL's",
    sqls: "SQL's",
};

// ---------------------------------------------------------------------------
// Lead detail modal
// ---------------------------------------------------------------------------

function LeadDetailModal({ lead, open, onClose }: { lead: Lead | null; open: boolean; onClose: () => void }) {
    if (!lead) return null;

    const stageColors: Record<string, string> = {
        MQL: 'bg-violet-500/10 text-violet-600 dark:text-violet-400 border-violet-200 dark:border-violet-800',
        SQL: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-800',
    };
    const stageBadge = lead.lead_stage ? (stageColors[lead.lead_stage] ?? 'bg-muted text-muted-foreground') : null;

    return (
        <Dialog open={open} onOpenChange={o => { if (!o) onClose(); }}>
            <DialogContent className="w-[520px] !max-w-none max-h-[85vh] flex flex-col gap-0 p-0 overflow-hidden">
                {/* Header */}
                <div className="px-6 pt-6 pb-4 border-b">
                    <div className="flex items-start justify-between gap-4 pr-6">
                        <div>
                            <DialogTitle className="text-lg font-semibold leading-tight">{lead.name || '—'}</DialogTitle>
                            <p className="text-sm text-muted-foreground mt-0.5">{lead.email || '—'}</p>
                        </div>
                        {stageBadge && (
                            <span className={`shrink-0 mt-0.5 inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold ${stageBadge}`}>
                                {lead.lead_stage}
                            </span>
                        )}
                    </div>
                </div>

                <div className="flex-1 overflow-y-auto px-6 py-5 flex flex-col gap-6">
                    {/* Basic info */}
                    <div className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">Company</p>
                            <p className="font-medium">{lead.company || '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">Country</p>
                            <p className="font-medium">{lead.country || '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">Create Date</p>
                            <p className="font-medium">{lead.created_date || '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">Created By</p>
                            <p className="font-medium">{lead.created_by || '—'}</p>
                        </div>
                    </div>

                    {/* Current owner */}
                    <div>
                        <div className="flex items-center gap-2 mb-3">
                            <User className="h-4 w-4 text-muted-foreground" />
                            <h3 className="text-sm font-semibold">Current Owner</h3>
                        </div>
                        <div className="rounded-lg border bg-muted/30 px-4 py-3 text-sm font-medium">
                            {lead.owner || '—'}
                        </div>
                    </div>

                    {/* Lead Source */}
                    <div>
                        <div className="flex items-center gap-2 mb-3">
                            <Tag className="h-4 w-4 text-muted-foreground" />
                            <h3 className="text-sm font-semibold">Lead Source</h3>
                        </div>
                        <div className="rounded-lg border border-dashed px-4 py-3 text-sm text-muted-foreground italic">
                            No data available yet
                        </div>
                    </div>

                    {/* Lead Status */}
                    <div>
                        <div className="flex items-center gap-2 mb-3">
                            <Activity className="h-4 w-4 text-muted-foreground" />
                            <h3 className="text-sm font-semibold">Lead Status</h3>
                        </div>
                        <div className="rounded-lg border border-dashed px-4 py-3 text-sm text-muted-foreground italic">
                            No data available yet
                        </div>
                    </div>

                    {/* Previous owners */}
                    <div>
                        <div className="flex items-center gap-2 mb-3">
                            <Clock className="h-4 w-4 text-muted-foreground" />
                            <h3 className="text-sm font-semibold">Previous Owners</h3>
                        </div>
                        <div className="rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground italic">
                            No data available yet
                        </div>
                    </div>

                    {/* Reassignment history */}
                    <div>
                        <div className="flex items-center gap-2 mb-3">
                            <ArrowRightLeft className="h-4 w-4 text-muted-foreground" />
                            <h3 className="text-sm font-semibold">Reassignment History</h3>
                        </div>
                        <div className="rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground italic">
                            No data available yet
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

// ---------------------------------------------------------------------------
// Shared lead row renderer
// ---------------------------------------------------------------------------

function LeadRows({ leads, onSelect }: { leads: Lead[]; onSelect: (lead: Lead) => void }) {
    return (
        <>
            {leads.length === 0 ? (
                <tr>
                    <td colSpan={6} className="px-4 py-8 text-center text-muted-foreground text-sm">
                        No leads found
                    </td>
                </tr>
            ) : (
                leads.map((lead, i) => (
                    <tr
                        key={i}
                        onClick={() => onSelect(lead)}
                        className="border-b last:border-0 hover:bg-muted/30 transition-colors cursor-pointer"
                    >
                        <td className="px-4 py-3 font-medium">{lead.name}</td>
                        <td className="px-4 py-3 text-muted-foreground">{lead.email}</td>
                        <td className="px-4 py-3 text-muted-foreground">{lead.company}</td>
                        <td className="px-4 py-3 text-muted-foreground">{lead.owner}</td>
                        <td className="px-4 py-3 text-muted-foreground">{lead.country}</td>
                        <td className="px-4 py-3 text-muted-foreground">{lead.created_date}</td>
                    </tr>
                ))
            )}
        </>
    );
}

const TABLE_HEADERS = ['Name', 'Email', 'Company / Account', 'Owner', 'Country', 'Create Date'];

// ---------------------------------------------------------------------------
// Drill-down modal table (with its own filters)
// ---------------------------------------------------------------------------

function FilteredLeadsTable({ leads, onSelectLead }: { leads: Lead[]; onSelectLead: (lead: Lead) => void }) {
    const [page, setPage] = useState(1);
    const [countryFilter, setCountryFilter] = useState('all');
    const [periodFilter, setPeriodFilter] = useState('all');

    const countryOptions = useMemo(
        () => [...new Set(leads.map(l => l.country).filter(Boolean))].sort(),
        [leads],
    );

    const filtered = useMemo(() => {
        let result = leads;
        if (countryFilter !== 'all') result = result.filter(l => l.country === countryFilter);
        result = filterByPeriod(result, periodFilter);
        return result;
    }, [leads, countryFilter, periodFilter]);

    const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
    const safePage = Math.min(page, totalPages);
    const start = (safePage - 1) * PAGE_SIZE;
    const pageLeads = filtered.slice(start, start + PAGE_SIZE);
    const hasActiveFilters = countryFilter !== 'all' || periodFilter !== 'all';

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center gap-2">
                <div className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                    <Filter className="h-3.5 w-3.5" />
                    Filters
                </div>
                <Select value={countryFilter} onValueChange={v => { setCountryFilter(v); setPage(1); }}>
                    <SelectTrigger className="h-8 w-44 text-xs">
                        <SelectValue placeholder="Country" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all" className="text-xs">All countries</SelectItem>
                        {countryOptions.map(c => (
                            <SelectItem key={c} value={c} className="text-xs">{c}</SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Select value={periodFilter} onValueChange={v => { setPeriodFilter(v); setPage(1); }}>
                    <SelectTrigger className="h-8 w-40 text-xs">
                        <SelectValue placeholder="Period" />
                    </SelectTrigger>
                    <SelectContent>
                        {PERIOD_OPTIONS.map(o => (
                            <SelectItem key={o.value} value={o.value} className="text-xs">{o.label}</SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {hasActiveFilters && (
                    <Button variant="ghost" size="sm" className="h-8 px-2 text-xs text-muted-foreground"
                        onClick={() => { setCountryFilter('all'); setPeriodFilter('all'); setPage(1); }}>
                        Clear
                    </Button>
                )}
                <span className="ml-auto text-xs text-muted-foreground">{filtered.length.toLocaleString()} records</span>
            </div>

            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b bg-muted/40">
                            {TABLE_HEADERS.map(h => (
                                <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">{h}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        <LeadRows leads={pageLeads} onSelect={onSelectLead} />
                    </tbody>
                </table>
            </div>

            {totalPages > 1 && (
                <div className="flex items-center justify-between px-1">
                    <span className="text-xs text-muted-foreground">
                        {start + 1}–{Math.min(start + PAGE_SIZE, filtered.length)} of {filtered.length.toLocaleString()}
                    </span>
                    <div className="flex items-center gap-1">
                        <Button variant="outline" size="sm" onClick={() => setPage(p => Math.max(1, p - 1))} disabled={safePage === 1} className="h-7 w-7 p-0">
                            <ChevronLeft className="h-4 w-4" />
                        </Button>
                        <span className="text-xs px-2 text-muted-foreground">{safePage} / {totalPages}</span>
                        <Button variant="outline" size="sm" onClick={() => setPage(p => Math.min(totalPages, p + 1))} disabled={safePage === totalPages} className="h-7 w-7 p-0">
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}

// ---------------------------------------------------------------------------
// Main table
// ---------------------------------------------------------------------------

function LeadsTable({ leads, onSelectLead }: { leads: Lead[]; onSelectLead: (lead: Lead) => void }) {
    const [page, setPage] = useState(1);
    const totalPages = Math.max(1, Math.ceil(leads.length / PAGE_SIZE));
    const start = (page - 1) * PAGE_SIZE;
    const pageLeads = leads.slice(start, start + PAGE_SIZE);

    return (
        <Card>
            <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                    <CardTitle className="text-base font-semibold">Leads</CardTitle>
                    <span className="text-xs text-muted-foreground">{leads.length.toLocaleString()} total</span>
                </div>
            </CardHeader>
            <CardContent className="p-0">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-t border-b bg-muted/40">
                                {TABLE_HEADERS.map(h => (
                                    <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            <LeadRows leads={pageLeads} onSelect={onSelectLead} />
                        </tbody>
                    </table>
                </div>

                {totalPages > 1 && (
                    <div className="flex items-center justify-between border-t px-6 py-3">
                        <span className="text-xs text-muted-foreground">
                            {start + 1}–{Math.min(start + PAGE_SIZE, leads.length)} of {leads.length.toLocaleString()}
                        </span>
                        <div className="flex items-center gap-1">
                            <Button variant="outline" size="sm" onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1} className="h-7 w-7 p-0">
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                            <span className="text-xs px-2 text-muted-foreground">{page} / {totalPages}</span>
                            <Button variant="outline" size="sm" onClick={() => setPage(p => Math.min(totalPages, p + 1))} disabled={page === totalPages} className="h-7 w-7 p-0">
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

// ---------------------------------------------------------------------------
// Dashboard
// ---------------------------------------------------------------------------

export function LeadsDashboard({ variant, summary, leads, error }: LeadsDashboardPageProps) {
    const isCarib = variant === 'carib';
    const caribSummary = summary as LeadSummaryCarib;

    const [countryFilter, setCountryFilter] = useState('all');
    const [periodFilter, setPeriodFilter] = useState('all');
    const [modalCard, setModalCard] = useState<CardKey | null>(null);
    const [selectedLead, setSelectedLead] = useState<Lead | null>(null);

    const countryOptions = useMemo(
        () => [...new Set(leads.map(l => l.country).filter(Boolean))].sort(),
        [leads],
    );

    const filteredLeads = useMemo(() => {
        let result = leads;
        if (countryFilter !== 'all') result = result.filter(l => l.country === countryFilter);
        result = filterByPeriod(result, periodFilter);
        return result;
    }, [leads, countryFilter, periodFilter]);

    const hasActiveFilters = countryFilter !== 'all' || periodFilter !== 'all';

    const modalLeads = useMemo(
        () => (modalCard ? getLeadsByCard(leads, modalCard) : []),
        [leads, modalCard],
    );

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

            {/* Main filters */}
            <div className="flex flex-wrap items-center gap-3">
                <div className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                    <Filter className="h-3.5 w-3.5" />
                    Filters
                </div>
                <Select value={countryFilter} onValueChange={v => setCountryFilter(v)}>
                    <SelectTrigger className="h-8 w-48 text-xs">
                        <SelectValue placeholder="Country" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all" className="text-xs">All countries</SelectItem>
                        {countryOptions.map(c => (
                            <SelectItem key={c} value={c} className="text-xs">{c}</SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Select value={periodFilter} onValueChange={v => setPeriodFilter(v)}>
                    <SelectTrigger className="h-8 w-44 text-xs">
                        <SelectValue placeholder="Period" />
                    </SelectTrigger>
                    <SelectContent>
                        {PERIOD_OPTIONS.map(o => (
                            <SelectItem key={o.value} value={o.value} className="text-xs">{o.label}</SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {hasActiveFilters && (
                    <Button variant="ghost" size="sm" className="h-8 px-2 text-xs text-muted-foreground"
                        onClick={() => { setCountryFilter('all'); setPeriodFilter('all'); }}>
                        Clear filters
                    </Button>
                )}
            </div>

            {isCarib ? (
                <div className="flex flex-col gap-6">
                    <div className="grid grid-cols-4 gap-4">
                        <StatCard label="Leads created" value={caribSummary.leads_created} icon={TrendingUp}
                            colorClass="text-emerald-600 dark:text-emerald-400" iconBgClass="bg-emerald-500/10"
                            onClick={() => setModalCard('leads_created')} />
                        <StatCard label="Leads assigned" value={caribSummary.leads_assigned} icon={TrendingUp}
                            colorClass="text-sky-600 dark:text-sky-400" iconBgClass="bg-sky-500/10"
                            onClick={() => setModalCard('leads_assigned')} />
                        <StatCard label="MQL's" value={summary.mqls} icon={Target}
                            colorClass="text-violet-600 dark:text-violet-400" iconBgClass="bg-violet-500/10"
                            onClick={() => setModalCard('mqls')} />
                        <StatCard label="SQL's" value={summary.sqls} icon={BarChart3}
                            colorClass="text-amber-600 dark:text-amber-400" iconBgClass="bg-amber-500/10"
                            onClick={() => setModalCard('sqls')} />
                    </div>
                    <LeadsTable leads={filteredLeads} onSelectLead={setSelectedLead} />
                </div>
            ) : (
                <div className="flex flex-col gap-6">
                    <div className="grid grid-cols-3 gap-4">
                        <StatCard label="Leads assigned" value={summary.leads_assigned} icon={TrendingUp}
                            colorClass="text-sky-600 dark:text-sky-400" iconBgClass="bg-sky-500/10"
                            onClick={() => setModalCard('leads_assigned')} />
                        <StatCard label="MQL's" value={summary.mqls} icon={Target}
                            colorClass="text-violet-600 dark:text-violet-400" iconBgClass="bg-violet-500/10"
                            onClick={() => setModalCard('mqls')} />
                        <StatCard label="SQL's" value={summary.sqls} icon={BarChart3}
                            colorClass="text-amber-600 dark:text-amber-400" iconBgClass="bg-amber-500/10"
                            onClick={() => setModalCard('sqls')} />
                    </div>
                    <LeadsTable leads={filteredLeads} onSelectLead={setSelectedLead} />
                </div>
            )}

            {/* Drill-down modal */}
            <Dialog open={modalCard !== null} onOpenChange={open => { if (!open) setModalCard(null); }}>
                <DialogContent className="w-[85vw] !max-w-none max-h-[85vh] flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>{modalCard ? CARD_LABELS[modalCard] : ''}</DialogTitle>
                    </DialogHeader>
                    <div className="flex-1 overflow-y-auto">
                        {modalCard && (
                            <FilteredLeadsTable
                                leads={modalLeads}
                                onSelectLead={lead => { setSelectedLead(lead); }}
                            />
                        )}
                    </div>
                </DialogContent>
            </Dialog>

            {/* Lead detail modal */}
            <LeadDetailModal
                lead={selectedLead}
                open={selectedLead !== null}
                onClose={() => setSelectedLead(null)}
            />
        </div>
    );
}
