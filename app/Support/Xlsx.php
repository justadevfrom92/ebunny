<?php

namespace App\Support;

/**
 * Writes a one-sheet Excel (.xlsx) file: a bold header row, then the data rows. No extra packages
 * and no PHP zip extension needed: the .xlsx (a zip of XML files) is put together here.
 */
class Xlsx
{
    public static function build(array $header, iterable $rows, string $sheet = 'Report'): string
    {
        $xml = '';
        $r = 1;
        foreach ([$header, ...collect($rows)->map(fn ($row) => array_values((array) $row))->all()] as $i => $cells) {
            $xml .= '<row r="'.$r.'">';
            foreach (array_values($cells) as $c => $v) {
                $ref = self::col($c).$r;
                $style = $i === 0 ? ' s="1"' : '';
                if (is_int($v) || is_float($v)) {
                    $xml .= '<c r="'.$ref.'"'.$style.'><v>'.$v.'</v></c>';
                } elseif ($v !== null && $v !== '') {
                    $xml .= '<c r="'.$ref.'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.self::esc((string) $v).'</t></is></c>';
                }
            }
            $xml .= '</row>';
            $r++;
        }

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.self::esc(mb_substr($sheet, 0, 31)).'" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="1"><fill><patternFill patternType="none"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf xfId="0"/><xf xfId="0" fontId="1" applyFont="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetData>'.$xml.'</sheetData></worksheet>',
        ];

        return self::zip($files);
    }

    /** A zip archive of name => contents (deflated when zlib is there, which it almost always is; stored otherwise). */
    public static function zip(array $files): string
    {
        $out = '';
        $central = '';
        [$time, $date] = [0, (2026 - 1980) << 9 | 1 << 5 | 1];   // a fixed timestamp keeps files identical run to run
        foreach ($files as $name => $content) {
            $crc = crc32($content);
            $deflated = function_exists('gzdeflate') ? gzdeflate($content, 6) : false;
            [$method, $data] = $deflated !== false ? [8, $deflated] : [0, $content];
            $head = pack('vvvvvVVVvv', 20, 0x0800, $method, $time, $date, $crc, strlen($data), strlen($content), strlen($name), 0);
            $offset = strlen($out);
            $out .= "PK\x03\x04".$head.$name.$data;
            $central .= "PK\x01\x02".pack('vvvvvvVVVvvvvvVV', 20, 20, 0x0800, $method, $time, $date, $crc, strlen($data), strlen($content), strlen($name), 0, 0, 0, 0, 0, $offset).$name;
        }

        return $out.$central."PK\x05\x06".pack('vvvvVVv', 0, 0, count($files), count($files), strlen($central), strlen($out), 0);
    }

    private static function col(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26).$s;
        }

        return $s;
    }

    private static function esc(string $v): string
    {
        // Drop control characters XML can't hold
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
