<?php

namespace App\Support;

/**
 * A small one-page PDF writer (Letter size, Helvetica) for documents like bill statements.
 * Coordinates are points from the top-left corner (612 × 792), which this flips for PDF.
 */
class Pdf
{
    private array $ops = [];

    public const W = 612;

    public const H = 792;

    /** @param  array{0: float, 1: float, 2: float}  $rgb  0–1 */
    public function text(float $x, float $y, string $text, float $size = 10, bool $bold = false, array $rgb = [0.1, 0.13, 0.28], string $align = 'left'): self
    {
        $s = self::latin($text);
        if ($align !== 'left') {
            $w = $this->width($s, $size, $bold);
            $x -= $align === 'right' ? $w : $w / 2;
        }
        $this->ops[] = sprintf('BT %s rg /%s %.2F Tf %.2F %.2F Td (%s) Tj ET', self::rgb($rgb), $bold ? 'F2' : 'F1', $size, $x, self::H - $y, self::escape($s));

        return $this;
    }

    public function rect(float $x, float $y, float $w, float $h, array $rgb): self
    {
        $this->ops[] = sprintf('%s rg %.2F %.2F %.2F %.2F re f', self::rgb($rgb), $x, self::H - $y - $h, $w, $h);

        return $this;
    }

    public function line(float $x1, float $y1, float $x2, float $y2, array $rgb = [0.85, 0.87, 0.9], float $width = 0.8): self
    {
        $this->ops[] = sprintf('%s RG %.2F w %.2F %.2F m %.2F %.2F l S', self::rgb($rgb), $width, $x1, self::H - $y1, $x2, self::H - $y2);

        return $this;
    }

    /** The finished PDF file. */
    public function output(string $title = 'Document'): string
    {
        $stream = implode("\n", $this->ops);
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::W.' '.self::H.'] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream",
            '<< /Title ('.self::escape(self::latin($title)).') /Producer (Laravel) >>',
        ];
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$body."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }

        return $pdf.'trailer'."\n<< /Size ".(count($objects) + 1).' /Root 1 0 R /Info '.count($objects)." 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }

    /** Approximate text width (Helvetica averages ~0.5em; bold a little wider), for right/center alignment. */
    private function width(string $s, float $size, bool $bold): float
    {
        $w = 0;
        foreach (str_split($s) as $ch) {
            $w += match (true) {
                str_contains('il.,:;|\'!I', $ch) => 0.25,
                str_contains('fjrt()- ', $ch) => 0.33,
                ctype_digit($ch), $ch === '$' => 0.556,
                ctype_upper($ch), str_contains('mw', $ch) => 0.68,
                default => 0.5,
            };
        }

        return $w * $size * ($bold ? 1.06 : 1);
    }

    private static function latin(string $s): string
    {
        $s = strtr($s, ['–' => '-', '—' => '-', '’' => "'", '“' => '"', '”' => '"', '…' => '...', '★' => '*']);

        return (string) @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);
    }

    private static function escape(string $s): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $s);
    }

    private static function rgb(array $c): string
    {
        return sprintf('%.3F %.3F %.3F', ...$c);
    }
}
