/**
 * Aggregations behind the funnel, lead-source and aging panels.
 *
 * These run in the browser, over the same filtered lead array the cards and the
 * table read, so every panel responds to the country/period filters without a
 * round trip. Keep them pure — the components below assume no side effects.
 */

import type { Lead } from '@/components/leads-dashboard';

/**
 * Outcomes that mean the lead was thrown out rather than worked.
 *
 * The business rule is "disqualified, rejected or recycled". Which column
 * carries that outcome is not stable in this export: today `Disqualified` and
 * `Recycled` only ever appear in Lead Status, while `Rejected` appears in Lead
 * Status *and* in Lead Stage. So the check is by value across both columns
 * rather than by column — matching the rule's intent and surviving a change in
 * which field the export populates.
 *
 * "Rechazado" is the Spanish spelling of "Rejected" that a subset of the
 * Salesforce org writes; both forms are live in the data. `Recycled` is in the
 * rule but currently has zero rows in either column.
 */
const DISQUALIFIED_OUTCOMES = ['disqualified', 'rejected', 'recycled', 'rechazado'];

/** True when either the stage or the status carries a disqualifying outcome. */
function isDisqualified(lead: Lead): boolean {
    return DISQUALIFIED_OUTCOMES.includes(lead.lead_stage?.trim().toLowerCase())
        || DISQUALIFIED_OUTCOMES.includes(lead.lead_status?.trim().toLowerCase());
}

/** Slices past this fold into "Other" so the donut stays readable. */
const MAX_SOURCE_SLICES = 6;

/** Weeks of history the aging sparklines plot. */
const TREND_WEEKS = 26;

export interface LeadFunnel {
    received: number;
    disqualified: number;
    processed: number;
    /** Processed leads whose owner changed at least once. */
    reassigned: number;
    disqualifiedShare: number;
    processedShare: number;
    reassignedShare: number;
}

export interface SourceSlice {
    label: string;
    count: number;
    share: number;
    /** True for the "no source recorded" slice, which is drawn in neutral grey. */
    isUnknown: boolean;
}

export interface AgingBucket {
    label: string;
    count: number;
    share: number;
}

export interface AgingTrendPoint {
    /** ISO date of the Monday starting the cohort week. */
    weekStart: string;
    label: string;
    ageDays: number | null;
    firstTouchDays: number | null;
    count: number;
}

export interface AgingSummary {
    /** Leads carrying a parseable Create Date — the denominator for every figure here. */
    measured: number;
    avgAgeDays: number | null;
    avgFirstTouchDays: number | null;
    touched: number;
    untouched: number;
    untouchedShare: number;
    /**
     * Leads with no history event at all — the denominator for `ageBuckets` only.
     *
     * Distinct from `untouched`, which counts leads with no event strictly after
     * creation: a lead whose only events land on its creation day is untouched
     * but not never-reassigned.
     */
    neverReassigned: number;
    /** Age distribution of the never-reassigned leads only, not of every measured lead. */
    ageBuckets: AgingBucket[];
    firstTouchBuckets: AgingBucket[];
    trend: AgingTrendPoint[];
}

/** Parse the M/D/YYYY strings the lead tables return. */
export function parseLeadDate(dateStr: string): Date | null {
    if (!dateStr) {
        return null;
    }

    const parts = dateStr.split('/');

    if (parts.length !== 3) {
        return null;
    }

    const [m, d, y] = parts.map(Number);

    if (!Number.isFinite(m) || !Number.isFinite(d) || !Number.isFinite(y)) {
        return null;
    }

    return new Date(y, m - 1, d);
}

function share(part: number, whole: number): number {
    return whole > 0 ? (part / whole) * 100 : 0;
}

/**
 * Build the funnel.
 *
 * Only Disqualified subtracts: a reassigned lead is still a processed lead, so
 * counting it as a second drop-off would make the stages stop adding up. It is
 * reported alongside Processed as an annotation instead.
 *
 * "Converted" (leads that produced an opportunity) is deliberately absent —
 * '(raw) Oppty' carries no lead key, so there is nothing in the dataset to
 * measure it from. See the placeholder stage in the funnel component.
 */
export function buildFunnel(leads: Lead[]): LeadFunnel {
    const received = leads.length;
    let disqualified = 0;
    let reassigned = 0;

    for (const lead of leads) {
        if (isDisqualified(lead)) {
            disqualified++;
            continue;
        }

        if ((lead.aging?.[2] ?? 0) > 0) {
            reassigned++;
        }
    }

    const processed = received - disqualified;

    return {
        received,
        disqualified,
        processed,
        reassigned,
        disqualifiedShare: share(disqualified, received),
        processedShare: share(processed, received),
        reassignedShare: share(reassigned, received),
    };
}

/**
 * Resolve a lead's source, attributing one from the campaign when it is empty.
 *
 * A lead that arrives with no Lead Source is not unattributed in practice — the
 * Account Engagement campaign it came through carries the signal:
 *   - a campaign whose name starts with a digit is a web campaign
 *   - a campaign containing "_event_" is an event campaign
 *   - anything else came through email
 *
 * The digit test runs first on purpose: campaigns like "2026_event_launch"
 * satisfy both rules, and those are attributed to Web.
 *
 * Every branch returns a label, so a lead can no longer end up unattributed
 * once its source is empty — the "No source" slice now only appears for leads
 * whose campaign is empty too.
 */
export function resolveLeadSource(lead: Lead): string {
    const source = lead.lead_source?.trim();

    if (source) {
        return source;
    }

    const campaign = lead.campaign?.trim() ?? '';

    if (!campaign) {
        return '';
    }

    if (/^\d/.test(campaign)) {
        return 'Web';
    }

    if (campaign.toLowerCase().includes('_event_')) {
        return 'Event Lead';
    }

    return 'Email';
}

/** Group leads by Lead Source, folding the long tail into "Other". */
export function buildSourceBreakdown(leads: Lead[]): SourceSlice[] {
    const counts = new Map<string, number>();
    let unknown = 0;

    for (const lead of leads) {
        const source = resolveLeadSource(lead);

        if (!source) {
            unknown++;
            continue;
        }

        counts.set(source, (counts.get(source) ?? 0) + 1);
    }

    const ranked = [...counts.entries()].sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]));
    const head = ranked.slice(0, MAX_SOURCE_SLICES);
    const tail = ranked.slice(MAX_SOURCE_SLICES);
    const total = leads.length;

    const slices: SourceSlice[] = head.map(([label, count]) => ({
        label,
        count,
        share: share(count, total),
        isUnknown: false,
    }));

    if (tail.length > 0) {
        const rest = tail.reduce((sum, [, count]) => sum + count, 0);

        slices.push({ label: 'Other', count: rest, share: share(rest, total), isUnknown: false });
    }

    if (unknown > 0) {
        slices.push({ label: 'No source', count: unknown, share: share(unknown, total), isUnknown: true });
    }

    return slices;
}

/** Upper bound in days for each bucket; the last bucket is open-ended. */
const BUCKET_EDGES = [1, 3, 7, 30];
const BUCKET_LABELS = ['0–1d', '1–3d', '3–7d', '7–30d', '+30d'];

function bucketize(values: number[]): AgingBucket[] {
    const counts = new Array<number>(BUCKET_LABELS.length).fill(0);

    for (const days of values) {
        let index = BUCKET_EDGES.findIndex(edge => days < edge);

        if (index === -1) {
            index = BUCKET_LABELS.length - 1;
        }

        counts[index]++;
    }

    return BUCKET_LABELS.map((label, i) => ({
        label,
        count: counts[i],
        share: share(counts[i], values.length),
    }));
}

function mean(values: number[]): number | null {
    if (values.length === 0) {
        return null;
    }

    return values.reduce((sum, v) => sum + v, 0) / values.length;
}

/** Monday of the week containing `date`, as an ISO date string. */
function weekStartKey(date: Date): string {
    const monday = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    const weekday = (monday.getDay() + 6) % 7;

    monday.setDate(monday.getDate() - weekday);

    const month = String(monday.getMonth() + 1).padStart(2, '0');
    const day = String(monday.getDate()).padStart(2, '0');

    return `${monday.getFullYear()}-${month}-${day}`;
}

function weekLabel(key: string): string {
    const [, month, day] = key.split('-');
    const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    return `${day} ${months[Number(month) - 1]}`;
}

/**
 * Aggregate aging across the filtered leads.
 *
 * The two averages read every measured lead. The trend groups leads into weekly
 * creation cohorts: "age" therefore ramps upward for older cohorts by
 * construction, which is what a cohort chart is supposed to show.
 *
 * `ageBuckets` is the one exception: it reads only leads with no history event,
 * so the distribution answers "how long have untouched leads been waiting"
 * rather than "how old is the database". Measured over every lead it was
 * dominated by long-closed records, which pushed almost everything into the
 * +30d bucket and made the panel unreadable. Note that the lead history only
 * records owner reassignments — Salesforce status transitions are not in the
 * dataset — so "never reassigned" is the closest available stand-in for "nobody
 * has worked this lead".
 */
export function buildAgingSummary(leads: Lead[]): AgingSummary {
    const ageDays: number[] = [];
    const neverReassignedAgeDays: number[] = [];
    const firstTouchDays: number[] = [];
    const cohorts = new Map<string, { age: number[]; firstTouch: number[]; count: number }>();
    let untouched = 0;

    for (const lead of leads) {
        const aging = lead.aging;

        if (!aging) {
            continue;
        }

        const age = aging[0] / 24;
        const firstTouch = aging[1] === null ? null : aging[1] / 24;

        ageDays.push(age);

        if (aging[2] === 0) {
            neverReassignedAgeDays.push(age);
        }

        if (firstTouch === null) {
            untouched++;
        } else {
            firstTouchDays.push(firstTouch);
        }

        const created = parseLeadDate(lead.created_date);

        if (created === null) {
            continue;
        }

        const key = weekStartKey(created);
        const cohort = cohorts.get(key) ?? { age: [], firstTouch: [], count: 0 };

        cohort.age.push(age);
        cohort.count++;

        if (firstTouch !== null) {
            cohort.firstTouch.push(firstTouch);
        }

        cohorts.set(key, cohort);
    }

    const trend: AgingTrendPoint[] = [...cohorts.entries()]
        .sort((a, b) => a[0].localeCompare(b[0]))
        .slice(-TREND_WEEKS)
        .map(([weekStart, cohort]) => ({
            weekStart,
            label: weekLabel(weekStart),
            ageDays: mean(cohort.age),
            firstTouchDays: mean(cohort.firstTouch),
            count: cohort.count,
        }));

    const measured = ageDays.length;

    return {
        measured,
        avgAgeDays: mean(ageDays),
        avgFirstTouchDays: mean(firstTouchDays),
        touched: firstTouchDays.length,
        untouched,
        untouchedShare: share(untouched, measured),
        neverReassigned: neverReassignedAgeDays.length,
        ageBuckets: bucketize(neverReassignedAgeDays),
        firstTouchBuckets: bucketize(firstTouchDays),
        trend,
    };
}
