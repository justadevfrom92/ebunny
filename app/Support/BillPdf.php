<?php

namespace App\Support;

use App\Models\Bill;

/**
 * A customer's bill as a one-page PDF statement: account and service address, amount due,
 * charges (energy, delivery, taxes and fees), payments since the last bill and 12 months of usage.
 * Charges are split from the bill total with typical Texas proportions (the bill rows store the total).
 */
class BillPdf
{
    private const NAVY = [0.063, 0.133, 0.278];

    private const CYAN = [0, 0.682, 0.937];

    private const GREY = [0.42, 0.45, 0.5];

    private const SKY = [0.93, 0.96, 0.97];

    public static function make(Bill $b): string
    {
        return self::build($b)->output(config('brand.name').' bill '.($b->invoice ?: $b->reference));
    }

    /** The drawn page, before it's written out as a PDF (or shown as SVG). */
    public static function build(Bill $b): Pdf
    {
        $c = $b->customer;
        $pdf = new Pdf;
        $money = fn ($v) => '$'.number_format((float) $v, 2);
        $amount = (float) $b->amount;
        $energy = round($amount * 0.64, 2);
        $delivery = round($amount * 0.29, 2);
        $taxes = round($amount - $energy - $delivery, 2);
        $prev = $c->bills()->where('billed_on', '<', $b->billed_on)->orderByDesc('billed_on')->first();
        $paid = $prev ? (float) $c->payments()->whereBetween('paid_on', [$prev->billed_on, $b->billed_on])->where('status', '!=', 'Failed')->sum('amount') : 0;
        // Balance carried from the last bill (a credit when more was paid), then this bill's charges
        $forward = round(($prev ? (float) $prev->amount : 0) - $paid, 2);
        $due = max(0, round($forward + $amount, 2));

        // Header band
        $pdf->rect(0, 0, Pdf::W, 86, self::NAVY)
            ->text(40, 40, strtoupper(config('brand.name')), 20, true, [1, 1, 1])
            ->text(40, 60, 'Electricity Bill', 11, false, [0.75, 0.85, 0.95])
            ->text(572, 40, 'Invoice '.($b->invoice ?: $b->reference), 11, true, [1, 1, 1], 'right')
            ->text(572, 58, 'Bill date '.$b->billed_on->format('F j, Y'), 10, false, [0.75, 0.85, 0.95], 'right');

        // Customer and account
        $y = 118;
        $pdf->text(40, $y, $c->name, 12, true)
            ->text(40, $y + 16, trim($c->address.($c->unit ? ' #'.$c->unit : '')), 10, false, self::GREY)
            ->text(40, $y + 30, trim(($c->city ? $c->city.', ' : '').'TX '.$c->zip), 10, false, self::GREY)
            ->text(40, $y + 50, 'Account '.$c->account, 10, true)
            ->text(40, $y + 64, 'ESIID '.($c->esiid ?: '—'), 9, false, self::GREY)
            ->text(40, $y + 78, 'Plan: '.($c->plan?->name ?? '—'), 9, false, self::GREY);

        // Amount due box
        $pdf->rect(340, $y - 16, 232, 106, self::SKY)
            ->text(356, $y + 4, 'AMOUNT DUE', 9, true, self::GREY)
            ->text(356, $y + 36, $money($due), 28, true)
            ->text(356, $y + 58, 'Due '.($b->due_on?->format('F j, Y') ?? 'on receipt'), 11, true, self::CYAN)
            ->text(356, $y + 76, $b->paid_on ? 'Paid '.$b->paid_on->format('M j, Y').' - thank you' : 'Pay in My Account, by phone or AutoPay', 9, false, self::GREY);

        // Summary
        $y = 250;
        $pdf->text(40, $y, 'ACCOUNT SUMMARY', 10, true, self::CYAN)->line(40, $y + 8, 572, $y + 8, self::NAVY, 1.2);
        $rows = [['Previous bill', $prev ? $money($prev->amount) : '$0.00'], ['Payments received - thank you', $paid ? '-'.$money($paid) : '$0.00'],
            [$forward < 0 ? 'Credit carried forward' : 'Balance forward', ($forward < 0 ? '-' : '').$money(abs($forward))], ['New charges this period', $money($amount)]];
        foreach ($rows as $i => [$label, $v]) {
            $ry = $y + 26 + $i * 18;
            $pdf->text(40, $ry, $label, 10)->text(572, $ry, $v, 10, false, self::NAVY, 'right');
        }
        $pdf->line(40, $y + 94, 572, $y + 94)->text(40, $y + 110, 'Amount due', 11, true)->text(572, $y + 110, $money($due), 11, true, self::NAVY, 'right');

        // Charges
        $y = 386;
        $period = ($b->period_start?->format('M j') ?? '').' - '.($b->period_end?->format('M j, Y') ?? '');
        $pdf->text(40, $y, 'CHARGES FOR '.strtoupper($period), 10, true, self::CYAN)->line(40, $y + 8, 572, $y + 8, self::NAVY, 1.2);
        $kwh = max(1, (int) $b->kwh);
        $charges = [
            ['Energy charge', number_format($b->kwh).' kWh x '.number_format($energy / $kwh * 100, 2).'¢', $energy],
            ['Delivery charges ('.($c->market?->short ?? 'TDSP').')', 'Set by your utility and passed through', $delivery],
            ['Taxes and fees', 'Gross receipts, PUC assessment and sales tax', $taxes],
        ];
        foreach ($charges as $i => [$label, $note, $v]) {
            $ry = $y + 28 + $i * 30;
            $pdf->text(40, $ry, $label, 10, true)->text(40, $ry + 12, $note, 8.5, false, self::GREY)->text(572, $ry, $money($v), 10, false, self::NAVY, 'right');
        }
        $pdf->line(40, $y + 112, 572, $y + 112)->text(40, $y + 128, 'Total new charges', 11, true)->text(572, $y + 128, $money($amount), 11, true, self::NAVY, 'right')
            ->text(40, $y + 146, 'Average price this period: '.number_format($amount / $kwh * 100, 1).'¢ per kWh', 9, false, self::GREY);

        // Usage history
        $y = 556;
        $pdf->text(40, $y, 'YOUR USAGE (kWh)', 10, true, self::CYAN)->line(40, $y + 8, 572, $y + 8, self::NAVY, 1.2);
        $hist = $c->bills()->where('billed_on', '<=', $b->billed_on)->orderByDesc('billed_on')->limit(12)->get()->reverse()->values();
        $max = max(1, (int) $hist->max('kwh'));
        $slot = 532 / 12;
        foreach ($hist as $i => $h) {
            $bh = round($h->kwh / $max * 90, 1);
            $x = 40 + $i * $slot + 6;
            $pdf->rect($x, $y + 116 - $bh, $slot - 12, $bh, $h->is($b) ? self::CYAN : [0.75, 0.82, 0.9])
                ->text($x + ($slot - 12) / 2, $y + 112 - $bh, number_format($h->kwh), 7, $h->is($b), self::NAVY, 'center')
                ->text($x + ($slot - 12) / 2, $y + 130, $h->billed_on->format('M'), 7.5, false, self::GREY, 'center');
        }

        // Footer
        $pdf->line(40, 712, 572, 712)
            ->text(40, 730, 'Questions? Call '.config('brand.phone').' or chat in My Account.', 9, true)
            ->text(40, 744, 'Outages and emergencies: call your utility ('.($c->market?->short ?? 'TDSP').').', 8.5, false, self::GREY)
            ->text(40, 758, config('brand.name').' · PUCT Cert. No. '.config('brand.puct'), 8.5, false, self::GREY);

        return $pdf;
    }
}
