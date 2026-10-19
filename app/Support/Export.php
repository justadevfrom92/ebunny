<?php

namespace App\Support;

/** Writes a report's header and rows in the format chosen. */
class Export
{
    /** format => [label, extension, content type] */
    public const FORMATS = [
        'xlsx' => ['Excel (.xlsx)', 'xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'csv' => ['CSV (.csv)', 'csv', 'text/csv'],
        'txt' => ['Tab-separated (.txt)', 'txt', 'text/plain'],
        'json' => ['JSON (.json)', 'json', 'application/json'],
    ];

    public static function build(string $format, array $header, iterable $rows, string $title = 'Report'): string
    {
        return match ($format) {
            'csv' => self::delimited($header, $rows, ','),
            'txt' => self::delimited($header, $rows, "\t"),
            'json' => json_encode(collect($rows)->map(fn ($r) => array_combine($header, array_pad(array_values((array) $r), count($header), null)))->values(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            default => Xlsx::build($header, $rows, $title),
        };
    }

    private static function delimited(array $header, iterable $rows, string $sep): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, $header, $sep);
        foreach ($rows as $row) {
            // Keep spreadsheet apps from running cell text as a formula
            fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v, (array) $row), $sep);
        }
        rewind($out);
        $s = stream_get_contents($out);
        fclose($out);

        return $s;
    }
}
