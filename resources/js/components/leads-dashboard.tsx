import { useState, useMemo, useEffect } from 'react';
import { TrendingUp, BarChart3, Target, ChevronLeft, ChevronRight, Filter, User, Users, Clock, ArrowRightLeft, Tag, Activity, Hourglass } from 'lucide-react';
import { LeadAgingCard, LeadFunnelCard, LeadSourceCard } from '@/components/leads-analytics';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { LucideIcon } from 'lucide-react';
import { formatDuration, agingSeverity, AGING_TEXT_CLASS, AGING_BADGE_CLASS } from '@/lib/format-duration';
import { buildAgingSummary, buildFunnel, buildSourceBreakdown, parseLeadDate } from '@/lib/lead-analytics';

export interface LeadSummaryLatam {
    leads_assigned: number;
    mqls: number;
    sqls: number;
}

export interface LeadSummaryCarib extends LeadSummaryLatam {
    leads_created: number;
}

export interface LeadAging {
    created_at: string | null;
    first_touch_at: string | null;
    first_touch_by: string | null;
    first_touch_to: string | null;
    age_hours: number | null;
    time_to_first_touch_hours: number | null;
    idle_hours: number | null;
    untouched: boolean;
    event_count: number;
    /** 'date' means Create Date carries no wall time, so durations from it are +/- a day. */
    created_precision: string;
}

export interface LeadHistoryEvent {
    at: string | null;
    at_display: string;
    field: string;
    old_value: string;
    new_value: string;
    edited_by: string;
    delta_hours: number | null;
}

/**
 * Aging for one lead, reduced to the three numbers the aggregate panel plots:
 * `[age_hours, hours_to_first_touch, owner_change_count]`. Null when the lead
 * has no parseable Create Date. The lead detail modal still fetches the full
 * LeadAging object on demand — see useLeadHistory.
 */
export type LeadAgingTuple = [number, number | null, number];

export interface Lead {
    lead_id: string;
    name: string;
    owner: string;
    email: string;
    company: string;
    created_date: string;
    country: string;
    lead_stage: string;
    created_by: string;
    created_alias: string;
    lead_source: string;
    lead_status: string;
    aging: LeadAgingTuple | null;
}

export interface LeadsDashboardPageProps {
    variant: 'latam' | 'carib';
    summary: LeadSummaryLatam | LeadSummaryCarib;
    leads: Lead[];
    error?: string;
}

type CardKey = 'total' | 'leads_created' | 'leads_assigned' | 'mqls' | 'sqls';

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
        case 'total': return leads;
        case 'leads_created': return leads.filter(l => l.created_by === 'Sales Outcomes Lead Triage');
        case 'leads_assigned': return leads.filter(l => l.created_alias === 'b2bmausr' || l.created_alias === 'LeadTrge');
        case 'mqls': return leads.filter(l => l.lead_stage === 'MQL');
        case 'sqls': return leads.filter(l => l.lead_stage === 'SQL');
    }
}

function countLeadsByCard(leads: Lead[]): Record<CardKey, number> {
    return {
        total: leads.length,
        leads_created: getLeadsByCard(leads, 'leads_created').length,
        leads_assigned: getLeadsByCard(leads, 'leads_assigned').length,
        mqls: getLeadsByCard(leads, 'mqls').length,
        sqls: getLeadsByCard(leads, 'sqls').length,
    };
}

const CARD_LABELS: Record<CardKey, string> = {
    total: "Total Leads",
    leads_created: "Leads Created",
    leads_assigned: "Leads Assigned",
    mqls: "MQL's",
    sqls: "SQL's",
};

// ---------------------------------------------------------------------------
// Lead detail modal
// ---------------------------------------------------------------------------

/**
 * Load a lead's owner-reassignment timeline.
 *
 * The backend serves this from the same cached history payload the page already
 * uses, so this is a cheap call, but it stays out of the Inertia props because
 * shipping every history row for every lead would bloat the page payload.
 */
function useLeadHistory(leadId: string | null, enabled: boolean) {
    // The fetched leadId is kept alongside the result so that switching leads
    // reads as "loading" without having to reset state from inside the effect.
    const [result, setResult] = useState<{ leadId: string; aging: LeadAging | null; events: LeadHistoryEvent[]; error: boolean } | null>(null);

    useEffect(() => {
        if (!enabled || !leadId) {
            return;
        }

        let cancelled = false;

        fetch(`/api/powerbi/leads/${encodeURIComponent(leadId)}/history`, {
            headers: { Accept: 'application/json' },
        })
            .then(res => (res.ok ? res.json() : Promise.reject(new Error(String(res.status)))))
            .then(body => {
                if (cancelled) {
                    return;
                }

                setResult({
                    leadId,
                    aging: body.success ? (body.data.aging as LeadAging | null) : null,
                    events: body.success ? (body.data.events as LeadHistoryEvent[]) : [],
                    error: !body.success,
                });
            })
            .catch(() => {
                if (cancelled) {
                    return;
                }

                setResult({ leadId, aging: null, events: [], error: true });
            });

        return () => {
            cancelled = true;
        };
    }, [leadId, enabled]);

    const ready = result !== null && result.leadId === leadId;

    return {
        aging: ready ? result.aging : null,
        events: ready ? result.events : null,
        loading: enabled && !!leadId && !ready,
        error: ready ? result.error : false,
    };
}

/** One labelled duration inside the Aging block. */
function AgingMetric({ label, hours, hint, muted }: { label: string; hours: number | null; hint?: string; muted?: boolean }) {
    const severity = agingSeverity(hours);

    return (
        <div>
            <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">{label}</p>
            <p className={`text-lg font-bold ${muted ? 'text-muted-foreground' : AGING_TEXT_CLASS[severity]}`}>
                {formatDuration(hours)}
            </p>
            {hint && <p className="text-xs text-muted-foreground mt-0.5">{hint}</p>}
        </div>
    );
}

/** Aging summary: how old the lead is and how long it went unattended. */
function LeadAgingSection({ aging, loading, error }: { aging: LeadAging | null; loading: boolean; error: boolean }) {
    if (loading || error || !aging) {
        const message = loading ? 'Loading aging…' : error ? 'Could not load aging' : 'No create date on this lead';

        return (
            <div>
                <div className="flex items-center gap-2 mb-3">
                    <Hourglass className="h-4 w-4 text-muted-foreground" />
                    <h3 className="text-sm font-semibold">Aging</h3>
                </div>
                <div className="rounded-lg border border-dashed px-4 py-3 text-sm text-muted-foreground italic">
                    {message}
                </div>
            </div>
        );
    }

    const untouchedSeverity = agingSeverity(aging.age_hours);

    return (
        <div>
            <div className="flex items-center justify-between gap-2 mb-3">
                <div className="flex items-center gap-2">
                    <Hourglass className="h-4 w-4 text-muted-foreground" />
                    <h3 className="text-sm font-semibold">Aging</h3>
                </div>
                {aging.untouched && (
                    <span className={`shrink-0 inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold ${AGING_BADGE_CLASS[untouchedSeverity]}`}>
                        Never touched
                    </span>
                )}
            </div>

            <div className="rounded-lg border bg-muted/30 px-4 py-3 grid grid-cols-3 gap-4">
                <AgingMetric label="Lead age" hours={aging.age_hours} muted />
                {aging.untouched ? (
                    <AgingMetric label="Unattended for" hours={aging.age_hours} hint="No owner change yet" />
                ) : (
                    <AgingMetric
                        label="To first touch"
                        hours={aging.time_to_first_touch_hours}
                        hint={aging.first_touch_to ? `→ ${aging.first_touch_to}` : undefined}
                    />
                )}
                <AgingMetric label="Since last move" hours={aging.idle_hours} muted />
            </div>

            {aging.created_precision === 'date' && (
                <p className="text-xs text-muted-foreground mt-2">
                    Create Date has no time of day, so durations measured from it are accurate to within a day.
                </p>
            )}
        </div>
    );
}

/**
 * Everyone who held the lead before its current owner, oldest first.
 *
 * Derived from the handoff chain: each event's Old Value is who held the lead
 * until that moment, so the distinct Old Values are the previous owners.
 */
function PreviousOwners({ events, loading, error, currentOwner }: { events: LeadHistoryEvent[] | null; loading: boolean; error: boolean; currentOwner: string }) {
    if (loading) {
        return <div className="rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground italic">Loading history…</div>;
    }

    if (error) {
        return <div className="rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground italic">Could not load history</div>;
    }

    const owners: string[] = [];

    for (const event of events ?? []) {
        const owner = event.old_value?.trim();

        if (owner && owner !== currentOwner && !owners.includes(owner)) {
            owners.push(owner);
        }
    }

    if (owners.length === 0) {
        return <div className="rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground italic">No previous owners</div>;
    }

    return (
        <div className="flex flex-wrap gap-2">
            {owners.map(owner => (
                <span key={owner} className="inline-flex items-center rounded-full border bg-muted/30 px-3 py-1 text-sm font-medium">
                    {owner}
                </span>
            ))}
        </div>
    );
}

/** Chronological list of owner handoffs. */
function ReassignmentTimeline({ events, loading, error }: { events: LeadHistoryEvent[] | null; loading: boolean; error: boolean }) {
    if (loading) {
        return <div className="rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground italic">Loading history…</div>;
    }

    if (error) {
        return <div className="rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground italic">Could not load history</div>;
    }

    if (!events || events.length === 0) {
        return <div className="rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground italic">No reassignments recorded</div>;
    }

    return (
        <ol className="flex flex-col gap-2">
            {events.map((event, i) => (
                <li key={i} className="rounded-lg border px-4 py-3 text-sm">
                    <div className="flex items-baseline justify-between gap-3">
                        <p className="font-medium">
                            {event.old_value || '—'} <span className="text-muted-foreground">→</span> {event.new_value || '—'}
                        </p>
                        <span className="shrink-0 text-xs text-muted-foreground">{event.at_display || '—'}</span>
                    </div>
                    <p className="text-xs text-muted-foreground mt-1">
                        by {event.edited_by || '—'}
                        {event.delta_hours !== null && <> · {formatDuration(event.delta_hours)} after the previous move</>}
                    </p>
                </li>
            ))}
        </ol>
    );
}

function LeadDetailModal({ lead, open, onClose }: { lead: Lead | null; open: boolean; onClose: () => void }) {
    const { aging, events, loading, error } = useLeadHistory(lead?.lead_id ?? null, open && !!lead);

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

                    {/* Aging */}
                    <LeadAgingSection aging={aging} loading={loading} error={error} />

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
                        {lead.lead_source ? (
                            <div className="rounded-lg border bg-muted/30 px-4 py-3 text-sm font-medium">{lead.lead_source}</div>
                        ) : (
                            <div className="rounded-lg border border-dashed px-4 py-3 text-sm text-muted-foreground italic">
                                No lead source recorded
                            </div>
                        )}
                    </div>

                    {/* Lead Status */}
                    <div>
                        <div className="flex items-center gap-2 mb-3">
                            <Activity className="h-4 w-4 text-muted-foreground" />
                            <h3 className="text-sm font-semibold">Lead Status</h3>
                        </div>
                        {lead.lead_status ? (
                            <div className="rounded-lg border bg-muted/30 px-4 py-3 text-sm font-medium">{lead.lead_status}</div>
                        ) : (
                            <div className="rounded-lg border border-dashed px-4 py-3 text-sm text-muted-foreground italic">
                                No lead status recorded
                            </div>
                        )}
                    </div>

                    {/* Previous owners */}
                    <div>
                        <div className="flex items-center gap-2 mb-3">
                            <Clock className="h-4 w-4 text-muted-foreground" />
                            <h3 className="text-sm font-semibold">Previous Owners</h3>
                        </div>
                        <PreviousOwners events={events} loading={loading} error={error} currentOwner={lead.owner} />
                    </div>

                    {/* Reassignment history */}
                    <div>
                        <div className="flex items-center gap-2 mb-3">
                            <ArrowRightLeft className="h-4 w-4 text-muted-foreground" />
                            <h3 className="text-sm font-semibold">Reassignment History</h3>
                        </div>
                        <ReassignmentTimeline events={events} loading={loading} error={error} />
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
// Drill-down modal table (inherits the dashboard filters)
// ---------------------------------------------------------------------------

function ModalLeadsTable({ leads, onSelectLead }: { leads: Lead[]; onSelectLead: (lead: Lead) => void }) {
    const [page, setPage] = useState(1);

    const totalPages = Math.max(1, Math.ceil(leads.length / PAGE_SIZE));
    const safePage = Math.min(page, totalPages);
    const start = (safePage - 1) * PAGE_SIZE;
    const pageLeads = leads.slice(start, start + PAGE_SIZE);

    return (
        <div className="flex flex-col gap-3">
            <div className="flex items-center justify-end">
                <span className="text-xs text-muted-foreground">{leads.length.toLocaleString()} records</span>
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
                        {start + 1}–{Math.min(start + PAGE_SIZE, leads.length)} of {leads.length.toLocaleString()}
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

export function LeadsDashboard({ variant, leads, error }: LeadsDashboardPageProps) {
    const isCarib = variant === 'carib';

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

    // Cards, table and drill-down modal all read from the same filtered set.
    const counts = useMemo(() => countLeadsByCard(filteredLeads), [filteredLeads]);

    const funnel = useMemo(() => buildFunnel(filteredLeads), [filteredLeads]);
    const sourceSlices = useMemo(() => buildSourceBreakdown(filteredLeads), [filteredLeads]);
    const agingSummary = useMemo(() => buildAgingSummary(filteredLeads), [filteredLeads]);

    const modalLeads = useMemo(
        () => (modalCard ? getLeadsByCard(filteredLeads, modalCard) : []),
        [filteredLeads, modalCard],
    );

    const activeFilterLabel = useMemo(() => {
        const parts: string[] = [];
        if (countryFilter !== 'all') parts.push(countryFilter);
        if (periodFilter !== 'all') {
            parts.push(PERIOD_OPTIONS.find(o => o.value === periodFilter)?.label ?? periodFilter);
        }
        return parts.join(' · ');
    }, [countryFilter, periodFilter]);

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
                    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                        <StatCard label="Total leads" value={counts.total} icon={Users}
                            colorClass="text-foreground" iconBgClass="bg-muted"
                            onClick={() => setModalCard('total')} />
                        <StatCard label="Leads assigned" value={counts.leads_assigned} icon={TrendingUp}
                            colorClass="text-sky-600 dark:text-sky-400" iconBgClass="bg-sky-500/10"
                            onClick={() => setModalCard('leads_assigned')} />
                        <StatCard label="Leads created" value={counts.leads_created} icon={TrendingUp}
                            colorClass="text-emerald-600 dark:text-emerald-400" iconBgClass="bg-emerald-500/10"
                            onClick={() => setModalCard('leads_created')} />
                        <StatCard label="MQL's" value={counts.mqls} icon={Target}
                            colorClass="text-violet-600 dark:text-violet-400" iconBgClass="bg-violet-500/10"
                            onClick={() => setModalCard('mqls')} />
                        <StatCard label="SQL's" value={counts.sqls} icon={BarChart3}
                            colorClass="text-amber-600 dark:text-amber-400" iconBgClass="bg-amber-500/10"
                            onClick={() => setModalCard('sqls')} />
                    </div>
                    <div className="grid gap-6 lg:grid-cols-3">
                        <div className="lg:col-span-2">
                            <LeadFunnelCard funnel={funnel} />
                        </div>
                        <LeadSourceCard slices={sourceSlices} total={filteredLeads.length} />
                    </div>
                    <LeadAgingCard aging={agingSummary} />
                    <LeadsTable leads={filteredLeads} onSelectLead={setSelectedLead} />
                </div>
            ) : (
                <div className="flex flex-col gap-6">
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <StatCard label="Total leads" value={counts.total} icon={Users}
                            colorClass="text-foreground" iconBgClass="bg-muted"
                            onClick={() => setModalCard('total')} />
                        <StatCard label="Leads assigned" value={counts.leads_assigned} icon={TrendingUp}
                            colorClass="text-sky-600 dark:text-sky-400" iconBgClass="bg-sky-500/10"
                            onClick={() => setModalCard('leads_assigned')} />
                        <StatCard label="MQL's" value={counts.mqls} icon={Target}
                            colorClass="text-violet-600 dark:text-violet-400" iconBgClass="bg-violet-500/10"
                            onClick={() => setModalCard('mqls')} />
                        <StatCard label="SQL's" value={counts.sqls} icon={BarChart3}
                            colorClass="text-amber-600 dark:text-amber-400" iconBgClass="bg-amber-500/10"
                            onClick={() => setModalCard('sqls')} />
                    </div>
                    <div className="grid gap-6 lg:grid-cols-3">
                        <div className="lg:col-span-2">
                            <LeadFunnelCard funnel={funnel} />
                        </div>
                        <LeadSourceCard slices={sourceSlices} total={filteredLeads.length} />
                    </div>
                    <LeadAgingCard aging={agingSummary} />
                    <LeadsTable leads={filteredLeads} onSelectLead={setSelectedLead} />
                </div>
            )}

            {/* Drill-down modal */}
            <Dialog open={modalCard !== null} onOpenChange={open => { if (!open) setModalCard(null); }}>
                <DialogContent className="w-[85vw] !max-w-none max-h-[85vh] flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>{modalCard ? CARD_LABELS[modalCard] : ''}</DialogTitle>
                        {activeFilterLabel && (
                            <p className="text-xs text-muted-foreground">Filtered by {activeFilterLabel}</p>
                        )}
                    </DialogHeader>
                    <div className="flex-1 overflow-y-auto">
                        {modalCard && (
                            <ModalLeadsTable
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
