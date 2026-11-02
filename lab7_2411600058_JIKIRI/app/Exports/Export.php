<?php

namespace App\Exports;

abstract class Export
{
    /** The filters used to build this export, shown under the title. */
    protected array $filters = [];

    public function withFilters(array $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    abstract public function title(): string;

    abstract public function headings(): array;

    /** Yields one array per row (streamed, so large exports don't load everything at once). */
    abstract public function rows(): iterable;

    /** Totals shown under the table: label => value. */
    abstract public function summary(): array;

    public function subtitle(): string
    {
        $parts = [];

        foreach ($this->filters as $key => $value) {
            if ($value === null || $value === '' || $value === 'all') {
                continue;
            }
            $parts[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . $value;
        }

        return $parts ? 'Filters: ' . implode(', ', $parts) : 'No filters applied';
    }
}