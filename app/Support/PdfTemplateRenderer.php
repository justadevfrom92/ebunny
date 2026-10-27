<?php

namespace App\Support;

use App\Models\Customer;

/**
 * Turns a PDF template (Rodeo → PDFs) into a PDF for one account. The body is plain text with a little markup,
 * one block per line:
 *   # Heading            ## Smaller heading         - A bullet point
 *   | Label | Value |    a shaded row of two cells  ---  a divider line
 *   **A bold line**      a blank line starts a new paragraph; anything else is wrapped text (** inside a line is dropped)
 * Fill-ins like {{first_name}} or {{balance}} are replaced with the account's details (see FIELDS).
 * Long documents run onto more pages; every page gets the footer and "Page X of Y".
 */
class PdfTemplateRenderer
{
    public const FIELDS = [
        'first_name' => 'First name', 'last_name' => 'Last name', 'name' => 'Full name', 'account' => 'Account number',
        'address' => 'Service address', 'city' => 'City', 'zip' => 'ZIP code', 'esiid' => 'ESIID', 'plan' => 'Plan name',
        'balance' => 'Balance due', 'due_date' => 'Latest bill due date', 'last_bill' => 'Latest bill amount', 'today' => "Today's date", 'brand' => 'Company name', 'phone' => 'Customer service phone',
    ];

    private const NAVY = [0.063, 0.133, 0.278];

    private const CYAN = [0, 0.682, 0.937];

    private const GREY = [0.42, 0.45, 0.5];

    private const SKY = [0.93, 0.96, 0.97];

    private const LEFT = 48;

    private const RIGHT = 564;

    private const BOTTOM = 712;   // body stops here; the footer sits below

    /** Replace {{fill_ins}} with the account's details (left as-is for unknown names). */
    public static function fill(string $text, ?Customer $c): string
    {
        $bill = $c?->bills()->latest('billed_on')->first();
        $v = [
            'first_name' => $c?->first_name, 'last_name' => $c?->last_name, 'name' => $c?->name, 'account' => $c?->account,
            'address' => $c ? trim($c->address.($c->unit ? ' #'.$c->unit : '')) : null, 'city' => $c?->city, 'zip' => $c?->zip, 'esiid' => $c?->esiid,
            'plan' => $c?->plan?->name, 'balance' => $c ? '$'.number_format((float) $c->balance, 2) : null,
            'due_date' => $bill?->due_on?->format('F j, Y'), 'last_bill' => $bill ? '$'.number_format((float) $bill->amount, 2) : null,
            'today' => now()->format('F j, Y'), 'brand' => config('brand.name'), 'phone' => config('brand.phone', '1-800-555-0100'),
        ];

        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', fn ($m) => array_key_exists($m[1], $v) ? (string) ($v[$m[1]] ?? '—') : $m[0], $text);
    }

    /** @param  array{title: string, body: string, footer: ?string, show_address: bool}  $doc */
    public static function build(array $doc, ?Customer $c): Pdf
    {
        $pdf = new Pdf;
        $title = self::fill($doc['title'], $c);
        $footer = trim(self::fill((string) ($doc['footer'] ?? ''), $c));

        // First page: brand band, the account's name and address, the title
        $pdf->rect(0, 0, Pdf::W, 80, self::NAVY)
            ->text(self::LEFT, 40, strtoupper(config('brand.name')), 20, true, [1, 1, 1])
            ->text(self::LEFT, 58, $title, 10, false, [0.75, 0.85, 0.95])
            ->text(self::RIGHT, 40, now()->format('F j, Y'), 10, false, [0.75, 0.85, 0.95], 'right');
        $y = 112;
        if ($doc['show_address'] ?? true) {
            if ($c) {
                $pdf->text(self::LEFT, $y, $c->name, 11, true)
                    ->text(self::LEFT, $y + 15, trim($c->address.($c->unit ? ' #'.$c->unit : '')), 10, false, self::GREY)
                    ->text(self::LEFT, $y + 29, trim(($c->city ? $c->city.', ' : '').'TX '.$c->zip), 10, false, self::GREY)
                    ->text(self::RIGHT, $y, 'Account '.$c->account, 10, true, self::NAVY, 'right');
            }
            $y += 58;
        }
        $pdf->text(self::LEFT, $y, $title, 17, true)->rect(self::LEFT, $y + 9, 56, 3, self::CYAN);
        $y += 36;

        $room = function (float $need) use (&$y, $pdf) {
            if ($y + $need > self::BOTTOM) {
                $pdf->newPage()->rect(0, 0, Pdf::W, 6, self::NAVY);
                $y = 52;
            }
        };
        $wrap = function (string $text, float $size, bool $bold, float $width) use ($pdf): array {
            $lines = [];
            $line = '';
            foreach (preg_split('/\s+/', trim($text)) as $word) {
                $try = $line === '' ? $word : $line.' '.$word;
                if ($line !== '' && $pdf->measure($try, $size, $bold) > $width) {
                    $lines[] = $line;
                    $line = $word;
                } else {
                    $line = $try;
                }
            }

            return $line === '' ? $lines : [...$lines, $line];
        };
        $paragraph = function (string $text, float $size = 10.5, bool $bold = false, float $indent = 0, array $rgb = [0.1, 0.13, 0.28]) use (&$y, $pdf, $room, $wrap) {
            foreach ($wrap($text, $size, $bold, self::RIGHT - self::LEFT - $indent) as $line) {
                $room($size + 5);
                $pdf->text(self::LEFT + $indent, $y, $line, $size, $bold, $rgb);
                $y += $size + 5;
            }
        };

        $buffer = [];
        $flush = function () use (&$buffer, &$y, $paragraph) {
            if ($buffer) {
                $paragraph(implode(' ', $buffer));
                $y += 7;
                $buffer = [];
            }
        };
        foreach (preg_split('/\R/', self::fill($doc['body'], $c)) as $raw) {
            $line = trim($raw);
            if ($line === '') {
                $flush();

                continue;
            }
            if (preg_match('/^(#{1,2})\s+(.*)$/', $line, $m)) {
                $flush();
                $size = $m[1] === '#' ? 14 : 12;
                $room($size + 30);
                $y += 6;
                $paragraph($m[2], $size, true, 0, self::NAVY);
                $y += 4;
            } elseif (preg_match('/^[-*]\s+(.*)$/', $line, $m)) {
                $flush();
                $room(16);
                $pdf->rect(self::LEFT + 4, $y - 4, 3.5, 3.5, self::CYAN);
                $paragraph(str_replace('**', '', $m[1]), 10.5, false, 16);
                $y += 2;
            } elseif (preg_match('/^\|(.+)\|$/', $line, $m)) {
                $flush();
                $cells = array_map('trim', explode('|', $m[1]));
                $room(26);
                $pdf->rect(self::LEFT, $y - 13, self::RIGHT - self::LEFT, 22, self::SKY)
                    ->text(self::LEFT + 10, $y + 2, $cells[0] ?? '', 10, true, self::GREY)
                    ->text(self::RIGHT - 10, $y + 2, $cells[1] ?? '', 10.5, true, self::NAVY, 'right');
                $y += 26;
            } elseif ($line === '---') {
                $flush();
                $room(16);
                $pdf->line(self::LEFT, $y, self::RIGHT, $y);
                $y += 14;
            } elseif (preg_match('/^\*\*(.+)\*\*$/', $line, $m)) {
                $flush();
                $paragraph($m[1], 10.5, true);
                $y += 7;
            } else {
                $buffer[] = str_replace('**', '', $line);   // bold inside a line isn't supported: keep the words
            }
        }
        $flush();

        // Footer and page numbers on every page
        $total = $pdf->pageCount();
        for ($i = 0; $i < $total; $i++) {
            $pdf->onPage($i)->line(self::LEFT, 732, self::RIGHT, 732)
                ->text(self::RIGHT, 750, 'Page '.($i + 1).' of '.$total, 8, false, self::GREY, 'right');
            $fy = 750;
            foreach (array_slice($wrap($footer ?: config('brand.name'), 7.5, false, 420), 0, 3) as $fl) {
                $pdf->text(self::LEFT, $fy, $fl, 7.5, false, self::GREY);
                $fy += 10;
            }
        }

        return $pdf;
    }
}
