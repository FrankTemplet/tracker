import { CalendarIcon, X } from 'lucide-react';
import { useState } from 'react';
import type { DateRange } from 'react-day-picker';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export interface StringDateRange {
    from: string;
    to: string;
}

interface DateRangePickerProps {
    value: StringDateRange;
    onChange: (range: StringDateRange) => void;
    placeholder?: string;
    ariaLabel?: string;
    className?: string;
}

const CALENDAR_START_YEAR = 2020;

const DISPLAY_FORMAT = new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
});

/** Parses a `YYYY-MM-DD` value as a local date, avoiding the UTC shift of `new Date(string)`. */
function parseIsoDate(value: string): Date | undefined {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);

    if (!match) {
        return undefined;
    }

    const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));

    return Number.isNaN(date.getTime()) ? undefined : date;
}

function toIsoDate(date: Date | undefined): string {
    if (!date) {
        return '';
    }

    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

function formatLabel(range: StringDateRange, placeholder: string): string {
    const from = parseIsoDate(range.from);
    const to = parseIsoDate(range.to);

    if (from && to) {
        return `${DISPLAY_FORMAT.format(from)} – ${DISPLAY_FORMAT.format(to)}`;
    }

    if (from) {
        return `From ${DISPLAY_FORMAT.format(from)}`;
    }

    if (to) {
        return `Until ${DISPLAY_FORMAT.format(to)}`;
    }

    return placeholder;
}

export function DateRangePicker({
    value,
    onChange,
    placeholder = 'Pick a date range',
    ariaLabel = 'Date range',
    className,
}: DateRangePickerProps) {
    const [open, setOpen] = useState(false);

    const selected: DateRange | undefined = value.from || value.to
        ? { from: parseIsoDate(value.from), to: parseIsoDate(value.to) }
        : undefined;

    const hasValue = Boolean(value.from || value.to);

    function handleSelect(range: DateRange | undefined) {
        onChange({ from: toIsoDate(range?.from), to: toIsoDate(range?.to) });
    }

    function handleClear(event: React.MouseEvent) {
        event.stopPropagation();
        onChange({ from: '', to: '' });
        setOpen(false);
    }

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    aria-label={ariaLabel}
                    className={cn(
                        'h-8 justify-start gap-2 px-2.5 text-xs font-normal',
                        !hasValue && 'text-muted-foreground',
                        className,
                    )}
                >
                    <CalendarIcon className="h-3.5 w-3.5 shrink-0 opacity-70" />
                    <span className="truncate">{formatLabel(value, placeholder)}</span>
                    {hasValue && (
                        <span
                            role="button"
                            tabIndex={-1}
                            aria-label="Clear date range"
                            onClick={handleClear}
                            className="ml-auto rounded-sm p-0.5 opacity-60 hover:bg-muted hover:opacity-100"
                        >
                            <X className="h-3 w-3" />
                        </span>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0" align="start">
                <Calendar
                    mode="range"
                    numberOfMonths={2}
                    captionLayout="dropdown"
                    startMonth={new Date(CALENDAR_START_YEAR, 0)}
                    endMonth={new Date(new Date().getFullYear() + 1, 11)}
                    defaultMonth={parseIsoDate(value.from) ?? parseIsoDate(value.to)}
                    selected={selected}
                    onSelect={handleSelect}
                    autoFocus
                />
            </PopoverContent>
        </Popover>
    );
}
