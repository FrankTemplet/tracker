import { AlertTriangle, CheckCircle2, Filter, Search, XCircle } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

export interface CampaignCoverageRow {
    campaign_id: string;
    campaign_name: string;
    business_unit: string;
    start_date: string;
    /** Sends this campaign has in '(raw) Email Campaign Metrics'. */
    email_rows: number;
    /** People '(raw) Engagement' recorded for this campaign. */
    engagement_members: number;
    /** Whether any of those people got there through an email. */
    email_activity: boolean;
    in_catalogue: boolean;
}

export interface CampaignCoveragePageProps {
    campaigns: CampaignCoverageRow[];
    allowedRegions: string[];
    /** Campaigns in '(raw) Engagement' before the region filter. */
    datasetTotal: number;
    /** Of those, how many made it into the catalogue. */
    datasetWithMetrics: number;
    error?: string;
}

type CatalogueFilter = 'in' | 'missing' | 'not-email' | 'all';

const CATALOGUE_OPTIONS: { value: CatalogueFilter; label: string }[] = [
    { value: 'in', label: 'En el catálogo' },
    { value: 'missing', label: 'Reporte faltante' },
    { value: 'not-email', label: 'No son de email' },
    { value: 'all', label: 'Todas' },
];

/**
 * What a campaign is, relative to the catalogue.
 *
 * '(raw) Email Campaign Metrics' is the universe of email campaigns.
 * '(raw) Engagement' records who those campaigns reached, so a campaign that
 * only appears there is either an email campaign whose send report never
 * arrived, or something that was never emailed at all.
 */
function campaignKind(row: CampaignCoverageRow): CatalogueFilter {
    if (row.in_catalogue) {
        return 'in';
    }

    return row.email_activity ? 'missing' : 'not-email';
}

/** Years the campaign pages offer, mirroring PowerBiController::ALLOWED_YEARS. */
const YEARS = ['2026', '2025'];

/** Campaign names are prefixed with their region, e.g. "CARIB_JAM_…". */
function campaignRegion(name: string): string {
    return name.split('_')[0]?.toUpperCase() || '—';
}

/**
 * Which page a campaign lands on.
 *
 * Mirrors PowerBiController::campaignMatchesPage — "event" wins over "web", and
 * everything else falls through to Dashboard.
 */
function campaignPage(name: string): string {
    const lower = name.toLowerCase();

    if (lower.includes('event')) {
        return 'Events';
    }

    if (lower.includes('web')) {
        return 'Webinars';
    }

    return 'Dashboard';
}

/** The year the campaign filter would match on, taken from the name. */
function campaignYear(name: string): string {
    return YEARS.find(year => name.includes(year)) ?? '—';
}

function formatStartDate(value: string): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
}

interface StatProps {
    label: string;
    value: string;
    hint?: string;
    accent?: string;
}

function Stat({ label, value, hint, accent }: StatProps) {
    return (
        <div className="rounded-xl border bg-card p-4 shadow-sm">
            <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">{label}</p>
            <p className={`text-2xl font-bold tabular-nums ${accent ?? ''}`}>{value}</p>
            {hint && <p className="text-xs text-muted-foreground mt-0.5">{hint}</p>}
        </div>
    );
}

export function CampaignCoverage({ campaigns, allowedRegions, datasetTotal, datasetWithMetrics, error }: CampaignCoveragePageProps) {
    const [catalogue, setCatalogue] = useState<CatalogueFilter>('in');
    const [region, setRegion] = useState('all');
    const [year, setYear] = useState('all');
    const [search, setSearch] = useState('');

    const total = campaigns.length;
    const inCatalogue = useMemo(() => campaigns.filter(c => c.in_catalogue), [campaigns]);
    const missingReport = useMemo(() => campaigns.filter(c => campaignKind(c) === 'missing'), [campaigns]);
    const notEmail = total - inCatalogue.length - missingReport.length;

    // The email report barely covers 2025, so a year breakdown of the catalogue
    // is the fastest way to see what the year filter will actually return.
    const byYear = useMemo(() => {
        const counts = new Map<string, number>();

        for (const campaign of inCatalogue) {
            const key = campaignYear(campaign.campaign_name);

            counts.set(key, (counts.get(key) ?? 0) + 1);
        }

        return counts;
    }, [inCatalogue]);

    const regionOptions = useMemo(
        () => [...new Set(campaigns.map(c => campaignRegion(c.campaign_name)))].sort(),
        [campaigns],
    );

    const rows = useMemo(() => {
        const needle = search.trim().toLowerCase();

        return campaigns
            .filter(c => {
                if (catalogue !== 'all' && campaignKind(c) !== catalogue) {
                    return false;
                }

                if (region !== 'all' && campaignRegion(c.campaign_name) !== region) {
                    return false;
                }

                if (year !== 'all' && campaignYear(c.campaign_name) !== year) {
                    return false;
                }

                if (needle && !c.campaign_name.toLowerCase().includes(needle) && !c.business_unit.toLowerCase().includes(needle)) {
                    return false;
                }

                return true;
            })
            .sort((a, b) => b.email_rows - a.email_rows || b.engagement_members - a.engagement_members || a.campaign_name.localeCompare(b.campaign_name));
    }, [campaigns, catalogue, region, year, search]);

    const hasActiveFilters = catalogue !== 'in' || region !== 'all' || year !== 'all' || search !== '';

    return (
        <div className="flex h-full flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 className="text-xl font-bold tracking-tight">Catálogo de campañas y su cobertura</h1>
                <p className="text-xs text-muted-foreground mt-0.5">
                    Página de diagnóstico · <code>(raw) Email Campaign Metrics</code> es el universo de campañas de
                    email y de ahí sale el catálogo que ofrecen Dashboard, Events y Webinars.
                    <code> (raw) Engagement</code> es el registro de las personas a las que esas campañas llegaron.
                </p>
                <p className="text-xs text-muted-foreground mt-1">
                    Una campaña que solo aparece en Engagement es una de dos cosas: <strong>sí se envió por email</strong>{' '}
                    (tiene gente en Sent, Opened o Clicked) y lo que falta es su reporte de envíos; o{' '}
                    <strong>nunca se envió</strong> — es un evento o contenido, con gente solo en Registered, Attended o
                    Form Submission. La columna Estado las separa.
                </p>
                <p className="text-xs text-muted-foreground mt-1">
                    Tu usuario ve {total.toLocaleString()} de las {datasetTotal.toLocaleString()} campañas conocidas
                    ({datasetWithMetrics.toLocaleString()} en el catálogo completo): el filtro exige que el nombre
                    empiece con una de tus regiones ({allowedRegions.join(', ')}), y las que arrancan con otro prefijo
                    no le aparecen a nadie.
                </p>
            </div>

            {error && (
                <div className="rounded-xl border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                    {error}
                </div>
            )}

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Stat
                    label="En el catálogo"
                    value={inCatalogue.length.toLocaleString()}
                    hint="Seleccionables, todas con métricas"
                    accent="text-emerald-600 dark:text-emerald-400"
                />
                <Stat
                    label="Reporte faltante"
                    value={missingReport.length.toLocaleString()}
                    hint="Se enviaron, pero no están en el reporte"
                    accent="text-amber-600 dark:text-amber-400"
                />
                <Stat
                    label="No son de email"
                    value={notEmail.toLocaleString()}
                    hint="Eventos y contenido, sin envíos"
                />
                <Stat
                    label="Catálogo por año"
                    value={YEARS.map(y => `${y}: ${byYear.get(y) ?? 0}`).join('  ·  ')}
                    hint="Lo que devolvería cada filtro de año"
                />
            </div>

            <div className="flex flex-wrap items-center gap-3">
                <div className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                    <Filter className="h-3.5 w-3.5" />
                    Filtros
                </div>

                <Select value={catalogue} onValueChange={v => setCatalogue(v as CatalogueFilter)}>
                    <SelectTrigger className="h-8 w-48 text-xs">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {CATALOGUE_OPTIONS.map(o => (
                            <SelectItem key={o.value} value={o.value} className="text-xs">{o.label}</SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <Select value={region} onValueChange={setRegion}>
                    <SelectTrigger className="h-8 w-40 text-xs">
                        <SelectValue placeholder="Región" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all" className="text-xs">Todas las regiones</SelectItem>
                        {regionOptions.map(r => (
                            <SelectItem key={r} value={r} className="text-xs">{r}</SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <Select value={year} onValueChange={setYear}>
                    <SelectTrigger className="h-8 w-32 text-xs">
                        <SelectValue placeholder="Año" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all" className="text-xs">Todos los años</SelectItem>
                        {YEARS.map(y => (
                            <SelectItem key={y} value={y} className="text-xs">{y}</SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <div className="relative">
                    <Search className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={e => setSearch(e.target.value)}
                        placeholder="Buscar campaña o business unit"
                        className="h-8 w-64 pl-8 text-xs"
                    />
                </div>

                {hasActiveFilters && (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-8 px-2 text-xs text-muted-foreground"
                        onClick={() => {
                            setCatalogue('in');
                            setRegion('all');
                            setYear('all');
                            setSearch('');
                        }}
                    >
                        Limpiar filtros
                    </Button>
                )}

                <span className="ml-auto text-xs text-muted-foreground">
                    {rows.length.toLocaleString()} {rows.length === 1 ? 'campaña' : 'campañas'}
                </span>
            </div>

            <Card>
                <CardContent className="p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/40">
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">#</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Campaña</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Página</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Región</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Año</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Business Unit</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Inicio</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground">Envíos</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground">Personas</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Estado</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Campaign ID</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 ? (
                                    <tr>
                                        <td colSpan={11} className="px-4 py-8 text-center text-sm text-muted-foreground">
                                            No hay campañas con estos filtros
                                        </td>
                                    </tr>
                                ) : (
                                    rows.map((row, i) => (
                                        <tr key={row.campaign_id} className="border-b last:border-0 hover:bg-muted/30 transition-colors">
                                            <td className="px-4 py-3 text-xs text-muted-foreground tabular-nums">{i + 1}</td>
                                            <td className="px-4 py-3 font-medium">{row.campaign_name || '—'}</td>
                                            <td className="px-4 py-3 text-muted-foreground text-xs">{campaignPage(row.campaign_name)}</td>
                                            <td className="px-4 py-3 text-muted-foreground text-xs">{campaignRegion(row.campaign_name)}</td>
                                            <td className="px-4 py-3 text-muted-foreground text-xs tabular-nums">{campaignYear(row.campaign_name)}</td>
                                            <td className="px-4 py-3 text-muted-foreground">{row.business_unit || '—'}</td>
                                            <td className="px-4 py-3 text-muted-foreground text-xs whitespace-nowrap">{formatStartDate(row.start_date)}</td>
                                            <td className="px-4 py-3 text-right tabular-nums font-medium">{row.email_rows.toLocaleString()}</td>
                                            <td className="px-4 py-3 text-right tabular-nums text-muted-foreground">{row.engagement_members.toLocaleString()}</td>
                                            <td className="px-4 py-3">
                                                {campaignKind(row) === 'in' && (
                                                    <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-500/10 px-2.5 py-0.5 text-xs font-semibold text-emerald-600 dark:border-emerald-800 dark:text-emerald-400">
                                                        <CheckCircle2 className="h-3.5 w-3.5" />
                                                        En el catálogo
                                                    </span>
                                                )}
                                                {campaignKind(row) === 'missing' && (
                                                    <span className="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-500/10 px-2.5 py-0.5 text-xs font-semibold text-amber-600 dark:border-amber-800 dark:text-amber-400">
                                                        <AlertTriangle className="h-3.5 w-3.5" />
                                                        Reporte faltante
                                                    </span>
                                                )}
                                                {campaignKind(row) === 'not-email' && (
                                                    <span className="inline-flex items-center gap-1.5 rounded-full border bg-muted/50 px-2.5 py-0.5 text-xs font-semibold text-muted-foreground">
                                                        <XCircle className="h-3.5 w-3.5" />
                                                        No es de email
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 font-mono text-[11px] text-muted-foreground">{row.campaign_id}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
