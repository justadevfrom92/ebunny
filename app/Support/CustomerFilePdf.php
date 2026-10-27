<?php

namespace App\Support;

use App\Models\Bill;
use App\Models\CustomerFile;

/**
 * An account document (EFL, YRAC, TOS, welcome packet, invoice) as a one-page PDF, in English or Spanish.
 * Invoices use the bill statement; the others lay out the CustomerDocument wording under a brand header.
 */
class CustomerFilePdf
{
    private const NAVY = [0.063, 0.133, 0.278];

    private const CYAN = [0, 0.682, 0.937];

    private const GREY = [0.42, 0.45, 0.5];

    private const SKY = [0.93, 0.96, 0.97];

    public static function make(CustomerFile $file, string $lang = 'en'): string
    {
        return self::build($file, $lang)->output(CustomerDocument::render($file, $lang)['title'].' - '.$file->customer->account);
    }

    /** The drawn page: write it out with ->output() for the PDF, or ->svg() to show it in the page. */
    public static function build(CustomerFile $file, string $lang = 'en'): Pdf
    {
        $c = $file->customer;
        if ($file->kind === 'invoice' && $bill = self::bill($file)) {
            return BillPdf::build($bill->load('customer.plan', 'customer.market'));
        }
        $doc = CustomerDocument::render($file, $lang);
        $es = $lang === 'es';
        $pdf = new Pdf;

        $pdf->rect(0, 0, Pdf::W, 86, self::NAVY)
            ->text(40, 40, strtoupper(config('brand.name')), 20, true, [1, 1, 1])
            ->text(40, 60, $doc['title'], 11, false, [0.75, 0.85, 0.95])
            ->text(572, 40, $file->name, 9, true, [1, 1, 1], 'right')
            ->text(572, 58, ($es ? 'Fecha ' : 'Date ').($file->generated_at ?? $file->created_at)->format($es ? 'j/n/Y' : 'F j, Y'), 10, false, [0.75, 0.85, 0.95], 'right');

        $y = 118;
        $pdf->text(40, $y, $c->name, 12, true)
            ->text(40, $y + 16, trim($c->address.($c->unit ? ' #'.$c->unit : '')), 10, false, self::GREY)
            ->text(40, $y + 30, trim(($c->city ? $c->city.', ' : '').'TX '.$c->zip), 10, false, self::GREY)
            ->text(572, $y, ($es ? 'Cuenta ' : 'Account ').$c->account, 10, true, self::NAVY, 'right')
            ->text(572, $y + 16, 'ESIID '.($c->esiid ?: '—'), 9, false, self::GREY, 'right');

        $y += 66;
        $pdf->text(40, $y, $doc['title'], 18, true)->rect(40, $y + 10, 60, 3, self::CYAN);
        $y += 40;
        foreach ($doc['paragraphs'] as $p) {
            foreach (explode("\n", wordwrap($p, 92)) as $line) {
                $pdf->text(40, $y, $line, 11);
                $y += 16;
            }
            $y += 8;
        }
        if ($doc['rows']) {
            $y += 6;
            foreach ($doc['rows'] as $k => $v) {
                $pdf->rect(40, $y - 14, 532, 24, self::SKY)
                    ->text(52, $y + 2, $k, 10, true, self::GREY)
                    ->text(560, $y + 2, $v, 11, true, self::NAVY, 'right');
                $y += 28;
            }
        }

        $pdf->line(40, 740, 572, 740)
            ->text(40, 758, config('brand.name').' · '.($es ? 'Muestra para demostración' : 'Sample document for demonstration'), 8, false, self::GREY)
            ->text(572, 758, ($es ? 'Página 1 de 1' : 'Page 1 of 1'), 8, false, self::GREY, 'right');

        return $pdf;
    }

    /** The bill an invoice file was made from (its name carries a hash of the bill reference), else the latest bill. */
    private static function bill(CustomerFile $file): ?Bill
    {
        $bills = $file->customer->bills()->orderByDesc('billed_on')->get();

        return $bills->first(fn ($b) => str_contains($file->name, substr(md5((string) $b->reference), 0, 24))) ?? $bills->first();
    }
}
