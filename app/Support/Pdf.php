<?php

namespace App\Support;

/**
 * A small PDF writer (Letter size, Helvetica) for documents like bill statements and PDF templates.
 * Coordinates are points from the top-left corner (612 × 792), which this flips for PDF.
 * Drawing goes on the current page; newPage() starts another and onPage() goes back to one.
 */
class Pdf
{
    /** Per page: PDF drawing operators. */
    private array $ops = [[]];

    /** Per page: the same drawing as SVG elements, for showing the page in a browser that can't display PDFs. */
    private array $svg = [[]];

    private int $page = 0;

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
        $this->ops[$this->page][] = sprintf('BT %s rg /%s %.2F Tf %.2F %.2F Td (%s) Tj ET', self::rgb($rgb), $bold ? 'F2' : 'F1', $size, $x, self::H - $y, self::escape($s));
        $this->svg[$this->page][] = sprintf('<text x="%.2F" y="%.2F" font-size="%.2F"%s fill="%s">%s</text>', $x, $y, $size, $bold ? ' font-weight="bold"' : '', self::hex($rgb),
            htmlspecialchars((string) @iconv('Windows-1252', 'UTF-8', $s), ENT_XML1));

        return $this;
    }

    public function rect(float $x, float $y, float $w, float $h, array $rgb): self
    {
        $this->ops[$this->page][] = sprintf('%s rg %.2F %.2F %.2F %.2F re f', self::rgb($rgb), $x, self::H - $y - $h, $w, $h);
        $this->svg[$this->page][] = sprintf('<rect x="%.2F" y="%.2F" width="%.2F" height="%.2F" fill="%s"/>', $x, $y, $w, $h, self::hex($rgb));

        return $this;
    }

    public function line(float $x1, float $y1, float $x2, float $y2, array $rgb = [0.85, 0.87, 0.9], float $width = 0.8): self
    {
        $this->ops[$this->page][] = sprintf('%s RG %.2F w %.2F %.2F m %.2F %.2F l S', self::rgb($rgb), $width, $x1, self::H - $y1, $x2, self::H - $y2);
        $this->svg[$this->page][] = sprintf('<line x1="%.2F" y1="%.2F" x2="%.2F" y2="%.2F" stroke="%s" stroke-width="%.2F"/>', $x1, $y1, $x2, $y2, self::hex($rgb), $width);

        return $this;
    }

    public function newPage(): self
    {
        $this->ops[] = [];
        $this->svg[] = [];
        $this->page = count($this->ops) - 1;

        return $this;
    }

    /** Go back to a page (0-based), e.g. to add page numbers once the page count is known. */
    public function onPage(int $page): self
    {
        $this->page = max(0, min($page, count($this->ops) - 1));

        return $this;
    }

    public function pageCount(): int
    {
        return count($this->ops);
    }

    /** A page as an SVG image (white Letter page, Helvetica/Arial); the first page unless one is given. */
    public function svg(int $page = 0): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.self::W.' '.self::H.'" font-family="Helvetica, Arial, sans-serif" role="img">'
            .'<rect width="'.self::W.'" height="'.self::H.'" fill="#fff"/>'.implode('', $this->svg[$page] ?? []).'</svg>';
    }

    /** Every page as SVG. @return array<int, string> */
    public function svgs(): array
    {
        return array_map(fn ($i) => $this->svg($i), array_keys($this->svg));
    }

    /** Width of a line of text in points, for wrapping. */
    public function measure(string $text, float $size, bool $bold = false): float
    {
        return $this->width(self::latin($text), $size, $bold);
    }

    /** The finished PDF file. */
    public function output(string $title = 'Document'): string
    {
        // 1 catalog, 2 pages, 3–4 fonts, then a page object and its content stream per page, then the info
        $n = count($this->ops);
        $kids = implode(' ', array_map(fn ($i) => (5 + 2 * $i).' 0 R', range(0, $n - 1)));
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids ['.$kids.'] /Count '.$n.' >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        foreach ($this->ops as $i => $ops) {
            $stream = implode("\n", $ops);
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::W.' '.self::H.'] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.(6 + 2 * $i).' 0 R >>';
            $objects[] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream";
        }
        $objects[] = '<< /Title ('.self::escape(self::latin($title)).') /Producer (Laravel) >>';
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

    private static function hex(array $c): string
    {
        return sprintf('#%02x%02x%02x', ...array_map(fn ($v) => (int) round($v * 255), $c));
    }

    private static function rgb(array $c): string
    {
        return sprintf('%.3F %.3F %.3F', ...$c);
    }
}
