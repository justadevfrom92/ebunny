<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Reads an uploaded spreadsheet into rows of strings: CSV, tab-separated text, or Excel (.xlsx, first sheet).
 * Blank rows are dropped.
 */
class SheetReader
{
    /** @return array<int, array<int, string>> */
    public static function read(string $path, string $name): array
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $rows = $ext === 'xlsx' ? self::xlsx($path) : self::text((string) file_get_contents($path));

        return array_values(array_filter(array_map(fn ($r) => array_map(fn ($v) => trim((string) $v), $r), $rows),
            fn ($r) => collect($r)->filter(fn ($v) => $v !== '')->isNotEmpty()));
    }

    private static function text(string $s): array
    {
        $s = preg_replace('/^\xEF\xBB\xBF/', '', $s);   // byte-order mark from Excel's "CSV UTF-8"
        $lines = preg_split('/\r\n|\r|\n/', trim($s));
        $tab = substr_count($lines[0] ?? '', "\t") > substr_count($lines[0] ?? '', ',');

        return array_map(fn ($l) => str_getcsv($l, $tab ? "\t" : ','), $lines);
    }

    private static function xlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('The file is not a readable Excel workbook.');
        }
        $strings = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            foreach (simplexml_load_string($xml)->si as $si) {
                $strings[] = isset($si->t) ? (string) $si->t : implode('', array_map(fn ($r) => (string) $r->t, iterator_to_array($si->r, false)));
            }
        }
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheet === false) {
            throw new RuntimeException('The workbook has no first sheet.');
        }
        $rows = [];
        foreach (simplexml_load_string($sheet)->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $col = self::column(preg_replace('/\d+/', '', (string) $c['r']));
                $type = (string) $c['t'];
                $v = match ($type) {
                    's' => $strings[(int) $c->v] ?? '',
                    'inlineStr' => (string) $c->is->t,
                    default => (string) $c->v,
                };
                $cells[$col] = $v;
            }
            if ($cells) {
                $rows[] = array_replace(array_fill(0, max(array_keys($cells)) + 1, ''), $cells);
            }
        }

        return $rows;
    }

    /** "A" → 0, "AB" → 27. */
    private static function column(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return max(0, $n - 1);
    }
}
