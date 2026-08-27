import { Info } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { AGING_TEXT_CLASS, agingSeverity, formatDuration } from '@/lib/format-duration';
import type { AgingBucket, AgingSummary, AgingTrendPoint, LeadFunnel, SourceSlice } from '@/lib/lead-analytics';

/**
 * Categorical slots, assigned in fixed order and never cycled.
 *
 * A source keeps its slot for as long as it stays in the top six, so changing a
 * filter cannot repaint the surviving slices. The tail folds into "Otros" rather
 * than generating a ninth hue.
 */
const SOURCE_COLORS = [
    'var(--viz-cat-1)',
    'var(--viz-cat-2)',
    'var(--viz-cat-3)',
    'var(--viz-cat-4)',
    'var(--viz-cat-5)',
    'var(--viz-cat-6)',
    'var(--viz-cat-7)',
];

/** Ordinal ramp: one hue, monotone lightness, light end clears the surface. */
const RAMP = [
    'var(--viz-ramp-1)',
    'var(--viz-ramp-2)',
    'var(--viz-ramp-3)',
    'var(--viz-ramp-4)',
    'var(--viz-ramp-5)',
];

function formatPercent(value: number): string {
    return `${value.toFixed(1)}%`;
}

function formatDays(days: number | null): string {
    if (days === null) {
        return '—';
    }

    return days.toFixed(1);
}

/** A short explanatory note behind an info icon, matching the card headers elsewhere. */
function InfoHint({ children }: { children: React.ReactNode }) {
    return (
        <TooltipProvider delayDuration={150}>
            <Tooltip>
                <TooltipTrigger asChild>
                    <button type="button" aria-label="Cómo se calcula" className="text-muted-foreground hover:text-foreground transition-colors">
                        <Info className="h-4 w-4" />
                    </button>
                </TooltipTrigger>
                <TooltipContent className="max-w-xs text-xs leading-relaxed">{children}</TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}

// ---------------------------------------------------------------------------
// Funnel
// ---------------------------------------------------------------------------

interface FunnelBarProps {
    label: string;
    value: number;
    share: number;
    color: string;
    /** Drop-offs and annotations are indented and drawn thinner than a main stage. */
    branch?: boolean;
    hint?: string;
}

function FunnelBar({ label, value, share, color, branch, hint }: FunnelBarProps) {
    return (
        <div className={branch ? 'pl-6' : ''}>
            <div className="flex items-baseline justify-between gap-3 mb-1">
                <span className={`text-xs ${branch ? 'text-muted-foreground' : 'font-semibold'}`}>
                    {branch && <span className="mr-1.5 text-muted-foreground">↳</span>}
                    {label}
                </span>
                <span className="shrink-0 text-xs tabular-nums">
                    <span className={branch ? 'text-muted-foreground' : 'font-semibold'}>{value.toLocaleString()}</span>
                    <span className="text-muted-foreground ml-1.5">{formatPercent(share)}</span>
                </span>
            </div>
            <div className={`w-full rounded-[4px] bg-muted/50 ${branch ? 'h-2' : 'h-4'}`}>
                <div
                    className="h-full rounded-[4px] transition-[width] duration-300"
                    style={{ width: `${Math.max(share, 0.5)}%`, background: color }}
                />
            </div>
            {hint && <p className="text-xs text-muted-foreground mt-1">{hint}</p>}
        </div>
    );
}

export function LeadFunnelCard({ funnel }: { funnel: LeadFunnel }) {
    return (
        <Card className="h-full">
            <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                    <CardTitle className="text-base font-semibold">Funnel de Leads</CardTitle>
                    <InfoHint>
                        Solo Disqualified resta: un lead reasignado sigue siendo un lead procesado, así que se reporta
                        como anotación y no como segunda fuga. Porcentajes sobre Request Received.
                    </InfoHint>
                </div>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                <FunnelBar label="Request Received" value={funnel.received} share={100} color={RAMP[1]} />
                <FunnelBar
                    label="Disqualified"
                    value={funnel.disqualified}
                    share={funnel.disqualifiedShare}
                    color="var(--viz-danger)"
                    branch
                    hint="Stage o Status: Disqualified, Rejected o Recycled"
                />
                <FunnelBar label="Processed" value={funnel.processed} share={funnel.processedShare} color={RAMP[3]} />
                <FunnelBar
                    label="Reasignados"
                    value={funnel.reassigned}
                    share={funnel.reassignedShare}
                    color="var(--viz-unknown)"
                    branch
                    hint="Procesados que cambiaron de dueño al menos una vez"
                />

                {/* Converted is intentionally unmeasured: '(raw) Oppty' carries no
                    lead key, so there is nothing in the dataset to join on. The
                    stage is drawn empty rather than dropped so the gap is visible
                    to whoever adds the column upstream. */}
                <div>
                    <div className="flex items-baseline justify-between gap-3 mb-1">
                        <span className="text-xs font-semibold text-muted-foreground">Converted</span>
                        <span className="shrink-0 text-xs text-muted-foreground">Sin dato</span>
                    </div>
                    <div className="w-full h-4 rounded-[4px] border border-dashed" />
                    <p className="text-xs text-muted-foreground mt-1">
                        Pendiente: <code className="text-[11px]">(raw) Oppty</code> no expone el lead que la originó.
                    </p>
                </div>
            </CardContent>
        </Card>
    );
}

// ---------------------------------------------------------------------------
// Lead source
// ---------------------------------------------------------------------------

const DONUT_RADIUS = 62;
const DONUT_STROKE = 22;
const DONUT_CIRCUMFERENCE = 2 * Math.PI * DONUT_RADIUS;

/** Surface-coloured gap between adjacent arcs, in user units. */
const ARC_GAP = 2;

function sourceColor(slice: SourceSlice, index: number): string {
    if (slice.isUnknown) {
        return 'var(--viz-unknown)';
    }

    return SOURCE_COLORS[index % SOURCE_COLORS.length];
}

export function LeadSourceCard({ slices, total }: { slices: SourceSlice[]; total: number }) {
    const [hovered, setHovered] = useState<number | null>(null);

    // Each arc starts where the previous one ended, so its offset is the sum of
    // every preceding arc. Written as a prefix sum rather than a running
    // accumulator to keep the render body free of mutation; there are at most
    // eight slices, so the quadratic walk is irrelevant.
    const arcs = useMemo(() => {
        const lengths = slices.map(slice => (slice.share / 100) * DONUT_CIRCUMFERENCE);

        return slices.map((slice, i) => ({
            slice,
            color: sourceColor(slice, i),
            dash: Math.max(lengths[i] - ARC_GAP, 0.5),
            offset: lengths.slice(0, i).reduce((sum, length) => sum + length, 0),
        }));
    }, [slices]);

    return (
        <Card className="h-full">
            <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                    <CardTitle className="text-base font-semibold">Lead Source</CardTitle>
                    <InfoHint>
                        La mayoría de los leads llega sin Lead Source registrado en Salesforce; esos se agrupan como
                        &ldquo;Sin fuente&rdquo; en gris. Del séptimo lugar en adelante se agrupan en &ldquo;Otros&rdquo;.
                    </InfoHint>
                </div>
            </CardHeader>
            <CardContent>
                {total === 0 ? (
                    <div className="rounded-lg border border-dashed px-4 py-8 text-center text-sm text-muted-foreground italic">
                        No hay leads en el filtro actual
                    </div>
                ) : (
                    <div className="flex flex-wrap items-center justify-center gap-6">
                        <div className="relative shrink-0 mx-auto">
                            <svg viewBox="0 0 160 160" className="h-40 w-40" role="img" aria-label="Distribución de leads por fuente">
                                <g transform="rotate(-90 80 80)">
                                    {arcs.map((arc, i) => (
                                        <circle
                                            key={arc.slice.label}
                                            cx="80"
                                            cy="80"
                                            r={DONUT_RADIUS}
                                            fill="none"
                                            stroke={arc.color}
                                            strokeWidth={hovered === i ? DONUT_STROKE + 4 : DONUT_STROKE}
                                            strokeDasharray={`${arc.dash} ${DONUT_CIRCUMFERENCE - arc.dash}`}
                                            strokeDashoffset={-arc.offset}
                                            className="transition-[stroke-width] duration-150"
                                            onMouseEnter={() => setHovered(i)}
                                            onMouseLeave={() => setHovered(null)}
                                        >
                                            <title>{`${arc.slice.label}: ${arc.slice.count.toLocaleString()} (${formatPercent(arc.slice.share)})`}</title>
                                        </circle>
                                    ))}
                                </g>
                            </svg>
                            <div className="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                <span className="text-2xl font-bold tabular-nums">{total.toLocaleString()}</span>
                                <span className="text-xs text-muted-foreground">Leads</span>
                            </div>
                        </div>

                        {/* The legend carries the label, share and count for every
                            slice, which is also what satisfies the relief rule for
                            the lighter hues on the light surface. */}
                        <ul className="flex-1 min-w-[190px] flex flex-col gap-2">
                            {arcs.map((arc, i) => (
                                <li
                                    key={arc.slice.label}
                                    className={`flex items-center gap-2.5 text-xs rounded px-1 py-0.5 -mx-1 transition-colors ${hovered === i ? 'bg-muted/60' : ''}`}
                                    onMouseEnter={() => setHovered(i)}
                                    onMouseLeave={() => setHovered(null)}
                                >
                                    <span className="h-2.5 w-2.5 shrink-0 rounded-[3px]" style={{ background: arc.color }} />
                                    <span className={`flex-1 truncate ${arc.slice.isUnknown ? 'text-muted-foreground italic' : ''}`}>
                                        {arc.slice.label}
                                    </span>
                                    <span className="shrink-0 tabular-nums font-medium">{formatPercent(arc.slice.share)}</span>
                                    <span className="shrink-0 tabular-nums text-muted-foreground">
                                        ({arc.slice.count.toLocaleString()})
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

// ---------------------------------------------------------------------------
// Aging
// ---------------------------------------------------------------------------

const SPARK_WIDTH = 320;
const SPARK_HEIGHT = 56;
const SPARK_PAD = 6;

interface SparklineProps {
    points: AgingTrendPoint[];
    metric: 'ageDays' | 'firstTouchDays';
    color: string;
    label: string;
}

/**
 * Weekly cohort trend for one aging metric.
 *
 * One series, so there is no legend — the panel heading names it. Weeks with no
 * measurement for the metric are skipped rather than plotted as zero, which
 * would read as "answered instantly".
 */
function Sparkline({ points, metric, color, label }: SparklineProps) {
    const [hovered, setHovered] = useState<number | null>(null);

    const plotted = points
        .map((point, index) => ({ point, index, value: point[metric] }))
        .filter((entry): entry is { point: AgingTrendPoint; index: number; value: number } => entry.value !== null);

    if (plotted.length < 2) {
        return (
            <div className="h-14 flex items-center justify-center rounded-lg border border-dashed text-xs text-muted-foreground italic">
                Sin historial suficiente
            </div>
        );
    }

    const max = Math.max(...plotted.map(entry => entry.value));
    const innerW = SPARK_WIDTH - SPARK_PAD * 2;
    const innerH = SPARK_HEIGHT - SPARK_PAD * 2;

    const coords = plotted.map((entry, i) => ({
        ...entry,
        x: SPARK_PAD + (i / (plotted.length - 1)) * innerW,
        y: SPARK_PAD + innerH - (max > 0 ? entry.value / max : 0) * innerH,
    }));

    const path = coords.map((c, i) => `${i === 0 ? 'M' : 'L'}${c.x.toFixed(1)},${c.y.toFixed(1)}`).join(' ');
    const active = hovered === null ? null : coords[hovered];

    function handleMove(event: React.MouseEvent<SVGSVGElement>) {
        const rect = event.currentTarget.getBoundingClientRect();
        const fraction = (event.clientX - rect.left) / rect.width;
        const index = Math.round(fraction * (coords.length - 1));

        setHovered(Math.min(Math.max(index, 0), coords.length - 1));
    }

    return (
        <div className="relative">
            <svg
                viewBox={`0 0 ${SPARK_WIDTH} ${SPARK_HEIGHT}`}
                className="w-full h-14"
                preserveAspectRatio="none"
                role="img"
                aria-label={`${label}: tendencia por semana de creación`}
                onMouseMove={handleMove}
                onMouseLeave={() => setHovered(null)}
            >
                <line
                    x1={SPARK_PAD}
                    y1={SPARK_HEIGHT - SPARK_PAD}
                    x2={SPARK_WIDTH - SPARK_PAD}
                    y2={SPARK_HEIGHT - SPARK_PAD}
                    stroke="var(--viz-grid)"
                    strokeWidth="1"
                    vectorEffect="non-scaling-stroke"
                />
                <path d={path} fill="none" stroke={color} strokeWidth="2" strokeLinejoin="round" strokeLinecap="round" vectorEffect="non-scaling-stroke" />
                {active && (
                    <>
                        <line
                            x1={active.x}
                            y1={SPARK_PAD}
                            x2={active.x}
                            y2={SPARK_HEIGHT - SPARK_PAD}
                            stroke="var(--viz-grid)"
                            strokeWidth="1"
                            vectorEffect="non-scaling-stroke"
                        />
                    </>
                )}
            </svg>

            {active && (
                <span
                    className="pointer-events-none absolute z-10 block h-2 w-2 -translate-x-1/2 -translate-y-1/2 rounded-full ring-2"
                    style={{
                        left: `${(active.x / SPARK_WIDTH) * 100}%`,
                        top: `${(active.y / SPARK_HEIGHT) * 100}%`,
                        background: color,
                        // A surface-coloured ring keeps the marker legible where
                        // it overlaps the line.
                        ['--tw-ring-color' as string]: 'var(--viz-surface)',
                    }}
                />
            )}

            {active && (
                <div
                    className="pointer-events-none absolute -top-1 z-10 -translate-x-1/2 -translate-y-full rounded-md border bg-popover px-2 py-1 text-xs shadow-md whitespace-nowrap"
                    style={{ left: `${(active.x / SPARK_WIDTH) * 100}%` }}
                >
                    <span className="font-medium">{active.point.label}</span>
                    <span className="text-muted-foreground"> · {formatDays(active.value)} d</span>
                    <span className="text-muted-foreground"> · {active.point.count.toLocaleString()} leads</span>
                </div>
            )}

            <div className="flex justify-between text-[10px] text-muted-foreground mt-0.5">
                <span>{coords[0].point.label}</span>
                <span>{coords[coords.length - 1].point.label}</span>
            </div>
        </div>
    );
}

/** Horizontal bucket distribution, coloured on the ordinal ramp. */
function BucketBars({ buckets }: { buckets: AgingBucket[] }) {
    return (
        <ul className="flex flex-col gap-1.5">
            {buckets.map((bucket, i) => (
                <li key={bucket.label} className="flex items-center gap-2.5 text-xs">
                    <span className="w-12 shrink-0 text-muted-foreground tabular-nums">{bucket.label}</span>
                    <span className="flex-1 h-2.5 rounded-[4px] bg-muted/50">
                        <span
                            className="block h-full rounded-[4px]"
                            style={{ width: `${Math.max(bucket.share, bucket.count > 0 ? 1 : 0)}%`, background: RAMP[i] }}
                        />
                    </span>
                    <span className="w-10 shrink-0 text-right tabular-nums font-medium">{bucket.count.toLocaleString()}</span>
                    <span className="w-12 shrink-0 text-right tabular-nums text-muted-foreground">{formatPercent(bucket.share)}</span>
                </li>
            ))}
        </ul>
    );
}

interface AgingMetricBlockProps {
    title: string;
    average: number | null;
    subtitle: string;
    color: string;
    metric: 'ageDays' | 'firstTouchDays';
    trend: AgingTrendPoint[];
    buckets: AgingBucket[];
}

function AgingMetricBlock({ title, average, subtitle, color, metric, trend, buckets }: AgingMetricBlockProps) {
    const severity = agingSeverity(average === null ? null : average * 24);

    return (
        <div className="flex flex-col gap-3">
            <div className="flex items-baseline justify-between gap-3">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{title}</p>
                    <p className="text-xs text-muted-foreground mt-0.5">{subtitle}</p>
                </div>
                <div className="shrink-0 text-right">
                    <p className={`text-2xl font-bold tabular-nums ${AGING_TEXT_CLASS[severity]}`}>{formatDays(average)}</p>
                    <p className="text-xs text-muted-foreground">
                        días · {formatDuration(average === null ? null : average * 24)}
                    </p>
                </div>
            </div>
            <Sparkline points={trend} metric={metric} color={color} label={title} />
            <BucketBars buckets={buckets} />
        </div>
    );
}

export function LeadAgingCard({ aging }: { aging: AgingSummary }) {
    return (
        <Card className="h-full">
            <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                    <CardTitle className="text-base font-semibold">Aging de Leads</CardTitle>
                    <InfoHint>
                        El primer toque es el primer cambio de dueño registrado en el historial del lead. Create Date no
                        trae hora, así que las duraciones medidas desde ahí tienen precisión de un día. La tendencia
                        agrupa por semana de creación, por lo que la edad sube en las cohortes más viejas por definición.
                    </InfoHint>
                </div>
            </CardHeader>
            <CardContent className="flex flex-col gap-6">
                {aging.measured === 0 ? (
                    <div className="rounded-lg border border-dashed px-4 py-8 text-center text-sm text-muted-foreground italic">
                        Ningún lead del filtro actual trae fecha de creación
                    </div>
                ) : (
                    <>
                        <div className="grid gap-6 lg:grid-cols-2">
                            <AgingMetricBlock
                                title="Tiempo desde creación"
                                subtitle={`${aging.measured.toLocaleString()} leads medidos`}
                                average={aging.avgAgeDays}
                                color="var(--viz-cat-1)"
                                metric="ageDays"
                                trend={aging.trend}
                                buckets={aging.ageBuckets}
                            />
                            <AgingMetricBlock
                                title="Tiempo hasta primer toque"
                                subtitle={`${aging.touched.toLocaleString()} leads con al menos un toque`}
                                average={aging.avgFirstTouchDays}
                                color="var(--viz-cat-3)"
                                metric="firstTouchDays"
                                trend={aging.trend}
                                buckets={aging.firstTouchBuckets}
                            />
                        </div>

                        <div className="flex items-center justify-between rounded-lg border bg-muted/30 px-4 py-3">
                            <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Nunca tocados
                            </span>
                            <span className="text-sm">
                                <span className={`font-bold tabular-nums ${AGING_TEXT_CLASS[aging.untouchedShare > 50 ? 'late' : aging.untouchedShare > 25 ? 'warn' : 'ok']}`}>
                                    {aging.untouched.toLocaleString()}
                                </span>
                                <span className="text-muted-foreground ml-1.5">
                                    de {aging.measured.toLocaleString()} ({formatPercent(aging.untouchedShare)})
                                </span>
                            </span>
                        </div>
                    </>
                )}
            </CardContent>
        </Card>
    );
}
