<?php

namespace App\Reports;

use App\Models\ReportColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * One report: what it reads (its model), the filters it takes, and its rows.
 * Corral's report screens and Walker both use these classes; the list of
 * reports is config('walker.reports').
 */
abstract class Report
{
    /** Filters: name => [label, type (date|text|select|multi), options for select/multi]. */
    abstract public function filters(): array;

    /** @return Collection the records the report covers */
    abstract public function query(array $f): Collection;

    abstract public function columns(): array;

    abstract public function row($record): array;

    /** Summary output: [header, rows]. Default: count by the first column. */
    public function summary(Collection $records): array
    {
        $first = $this->columns()[0];

        return [[$first, 'Rows'], $records->map(fn ($r) => $this->row($r)[0])->countBy()->map(fn ($n, $v) => [$v, $n])->values()];
    }

    /** Validation rules for the filters. */
    public function rules(): array
    {
        $rules = [];
        foreach ($this->filters() as $name => [$label, $type]) {
            $options = $this->filters()[$name][2] ?? [];
            $rules[$name] = match ($type) {
                'date' => ['nullable', 'date'],
                'select' => ['nullable', Rule::in(array_keys($options))],
                'multi' => ['nullable', 'array'],
                default => ['nullable', 'string', 'max:100'],
            };
            if ($type === 'multi') {
                $rules[$name.'.*'] = [Rule::in(array_keys($options))];
            }
        }
        if (isset($rules['end'], $rules['start'])) {
            $rules['end'][] = 'after_or_equal:start';
        }

        return $rules;
    }

    /** Filter values used when none are given. */
    public function defaults(): array
    {
        return ['start' => now()->startOfYear()->toDateString(), 'end' => today()->toDateString()];
    }

    /**
     * [header, rows] for the file outputs. When the report has export columns (Walker → a report →
     * Export Columns) each column is a model key on the record, like "customer.name" or
     * "customer.payments.*.amount", given the title chosen for it, in the running order.
     */
    public function detail(Collection $records, ?string $key = null): array
    {
        $map = $key ? ReportColumn::where('report', $key)->value('columns') : null;
        if (! $map) {
            return [$this->columns(), $records->map(fn ($r) => $this->row($r))->values()];
        }

        return [array_column($map, 'title'), $records->map(fn ($r) => array_map(fn ($c) => self::cell(data_get($r, $c['key'])), $map))->values()];
    }

    /**
     * A nested key => title array, in running order, as export columns:
     * ['customer' => ['name' => 'Name', 'email' => 'Email'], 'status' => 'Status']
     * → [['key' => 'customer.name', 'title' => 'Name'], ['key' => 'customer.email', 'title' => 'Email'], ['key' => 'status', 'title' => 'Status']].
     */
    public static function flattenColumns(array $nested, string $prefix = ''): array
    {
        $out = [];
        foreach ($nested as $k => $v) {
            $key = $prefix.($k === '_all' ? '*' : $k);
            if (is_array($v)) {
                array_push($out, ...self::flattenColumns($v, $key.'.'));
            } elseif (preg_match('/^[A-Za-z0-9_.*]+$/', $key)) {
                $out[] = ['key' => $key, 'title' => (string) $v !== '' ? (string) $v : $key];
            }
        }

        return $out;
    }

    /** One exported value: lists are joined, dates formatted, models shown by name or id. */
    public static function cell(mixed $v): mixed
    {
        return match (true) {
            $v instanceof \DateTimeInterface => $v->format('Y-m-d H:i:s'),
            $v instanceof Collection, is_array($v) => collect($v)->flatten()->map(fn ($x) => self::cell($x))->implode(', '),
            $v instanceof Model => $v->getAttribute('name') ?? $v->getAttribute('account') ?? $v->getKey(),
            is_bool($v) => $v ? 'Yes' : 'No',
            default => $v,
        };
    }

    /** Model keys a report's records offer for export columns (their fields and their related records' fields). */
    public function fields(array $f = []): array
    {
        $first = $this->query($f + $this->defaults())->first();
        if (! $first instanceof Model) {
            return [];
        }
        $keys = array_keys($first->getAttributes());
        foreach ($first->getRelations() as $name => $rel) {
            $one = $rel instanceof Collection ? $rel->first() : $rel;
            if ($one instanceof Model) {
                foreach (array_keys($one->getAttributes()) as $k) {
                    $keys[] = $name.($rel instanceof Collection ? '.*.' : '.').$k;
                }
            }
        }

        return array_values(array_filter($keys, fn ($k) => ! preg_match('/(password|remember_token|ssn|secret|token)/i', $k)));
    }

    public static function make(string $key): static
    {
        $def = config('walker.reports.'.$key);
        abort_unless($def, 404);

        return app($def['class']);
    }

    public static function definition(string $key): array
    {
        return config('walker.reports.'.$key) + ['key' => $key];
    }
}
