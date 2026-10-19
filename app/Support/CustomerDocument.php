<?php

namespace App\Support;

use App\Models\CustomerFile;

/**
 * The account documents the billing system produces (EFL, YRAC, TOS, welcome letter, invoice),
 * written out for one customer in English or Spanish, for the file page, printing and email.
 * Sample wording only.
 */
class CustomerDocument
{
    public const LANGUAGES = ['en' => 'English', 'es' => 'Spanish'];

    /** @return array{title: string, paragraphs: array<int, string>, rows: array<string, string>} */
    public static function render(CustomerFile $file, string $lang): array
    {
        $c = $file->customer;
        $es = $lang === 'es';
        $brand = config('brand.name');
        $plan = $c->plan?->name ?? ($es ? 'su plan' : 'your plan');
        $bill = $c->bills()->latest('billed_on')->first();
        $money = fn ($v) => '$'.number_format((float) $v, 2);
        $date = fn ($d) => $d ? $d->format($es ? 'j/n/Y' : 'n/j/Y') : '—';

        return match ($file->kind) {
            'efl' => ['title' => $es ? 'Etiqueta de Datos de Electricidad' : 'Electricity Facts Label',
                'paragraphs' => [$es ? "Plan: {$plan}. Precio promedio por kWh según su uso mensual:" : "Plan: {$plan}. Average price per kWh at your monthly usage:"],
                'rows' => [($es ? 'Uso de 500 kWh' : '500 kWh use') => '18.4¢', ($es ? 'Uso de 1,000 kWh' : '1,000 kWh use') => '15.9¢', ($es ? 'Uso de 2,000 kWh' : '2,000 kWh use') => '15.1¢',
                    ($es ? 'Plazo' : 'Term') => ($c->plan?->term ?? 12).($es ? ' meses' : ' months'), ($es ? 'Cargo por cancelación' : 'Cancellation fee') => $money($c->plan?->etf ?? 150)]],
            'yrac' => ['title' => $es ? 'Sus Derechos como Cliente' : 'Your Rights as a Customer',
                'paragraphs' => $es
                    ? ['Usted tiene derecho a recibir una copia de su contrato.', 'Usted puede cancelar su contrato dentro de los tres días hábiles después de recibirlo, sin cargo.', 'Usted puede presentar una queja ante la Comisión de Servicios Públicos.']
                    : ['You have the right to a copy of your contract.', 'You may cancel within three business days of receiving your contract, at no charge.', 'You may file a complaint with the Public Utility Commission.'],
                'rows' => []],
            'tos' => ['title' => $es ? 'Términos de Servicio' : 'Terms of Service',
                'paragraphs' => $es
                    ? ["Este acuerdo es entre {$c->name} (cuenta {$c->account}) y {$brand}.", "Plan: {$plan}. Las facturas vencen 16 días después de la fecha de emisión.", 'Los pagos atrasados pueden tener un cargo de 5%.']
                    : ["This agreement is between {$c->name} (account {$c->account}) and {$brand}.", "Plan: {$plan}. Bills are due 16 days after they are issued.", 'Late payments may carry a 5% fee.'],
                'rows' => []],
            'welcome' => ['title' => $es ? 'Carta de Bienvenida' : 'Welcome Letter',
                'paragraphs' => $es
                    ? ["¡Bienvenido/a, {$c->first_name}!", "Su servicio en {$c->address}, {$c->city} comienza pronto. Su plan: {$plan}.", "Su número de cuenta es {$c->account}. Administre su cuenta en Mi Cuenta."]
                    : ["Welcome, {$c->first_name}!", "Your service at {$c->address}, {$c->city} starts soon. Your plan: {$plan}.", "Your account number is {$c->account}. Manage your account in My Account."],
                'rows' => []],
            default => ['title' => $es ? 'Factura' : 'Invoice',
                'paragraphs' => [$es ? "Factura para {$c->name}, cuenta {$c->account}." : "Invoice for {$c->name}, account {$c->account}."],
                'rows' => $bill ? [($es ? 'Factura' : 'Invoice') => (string) ($bill->invoice ?? $bill->reference), ($es ? 'Periodo' : 'Period') => $date($bill->period_start).' – '.$date($bill->period_end),
                    'kWh' => number_format($bill->kwh), ($es ? 'Monto' : 'Amount') => $money($bill->amount), ($es ? 'Vence' : 'Due') => $date($bill->due_on)] : []],
        };
    }

    /** The customer's preferred language as a code ("Spanish" → es). */
    public static function preferred(?string $language): string
    {
        return str_starts_with(strtolower((string) $language), 'span') ? 'es' : 'en';
    }
}
