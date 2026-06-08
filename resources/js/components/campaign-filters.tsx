import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Globe, Calendar } from 'lucide-react';

export type Region = 'carib' | 'latam';

interface CampaignFiltersProps {
    selectedRegion?: Region;
    selectedYear?: string;
    onRegionChange: (region: Region) => void;
    onYearChange: (year: string) => void;
    availableYears?: string[];
}

export function CampaignFilters({
    selectedRegion,
    selectedYear,
    onRegionChange,
    onYearChange,
    availableYears = [],
}: CampaignFiltersProps) {
    // If no years provided, generate last 5 years
    const years = availableYears.length > 0 
        ? availableYears 
        : Array.from({ length: 5 }, (_, i) => {
            const year = new Date().getFullYear() - i;
            return year.toString();
        });

    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {/* Region Filter */}
            <div className="flex items-center gap-3 rounded-xl border bg-card px-4 py-3 shadow-sm">
                <div className="rounded-lg bg-primary/10 p-1.5 shrink-0">
                    <Globe className="h-4 w-4 text-primary" />
                </div>
                <div className="flex-1 min-w-0">
                    <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                        Region
                    </p>
                    <Select
                        value={selectedRegion}
                        onValueChange={onRegionChange}
                    >
                        <SelectTrigger className="h-7 border-0 p-0 shadow-none bg-transparent font-medium focus:ring-0 focus:ring-offset-0 text-sm">
                            <SelectValue placeholder="Select region" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="carib">Carib</SelectItem>
                            <SelectItem value="latam">LATAM</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            {/* Year Filter */}
            <div className="flex items-center gap-3 rounded-xl border bg-card px-4 py-3 shadow-sm">
                <div className="rounded-lg bg-primary/10 p-1.5 shrink-0">
                    <Calendar className="h-4 w-4 text-primary" />
                </div>
                <div className="flex-1 min-w-0">
                    <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                        Year
                    </p>
                    <Select
                        value={selectedYear}
                        onValueChange={onYearChange}
                    >
                        <SelectTrigger className="h-7 border-0 p-0 shadow-none bg-transparent font-medium focus:ring-0 focus:ring-offset-0 text-sm">
                            <SelectValue placeholder="Select year" />
                        </SelectTrigger>
                        <SelectContent>
                            {years.map((year) => (
                                <SelectItem key={year} value={year}>
                                    {year}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>
        </div>
    );
}
