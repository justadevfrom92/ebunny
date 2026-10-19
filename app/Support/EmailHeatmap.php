<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\EmailTemplate;

/**
 * The links in an email as sent (the brand header, the template's own links, the button and the
 * footer), each with a key, so clicks can be counted per link and drawn over the email as a heat map.
 */
class EmailHeatmap
{
    /** @return array<string, array{label: string, url: string}> in the order they appear */
    public static function links(?EmailTemplate $t): array
    {
        $links = ['logo' => ['label' => 'Logo', 'url' => '/']];
        if ($t && preg_match_all('/<a\s[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is', $t->body, $m, PREG_SET_ORDER)) {
            foreach ($m as $i => $a) {
                $links['body-'.($i + 1)] = ['label' => trim(strip_tags($a[2])) ?: 'Link '.($i + 1), 'url' => $a[1]];
            }
        }

        return $links + [
            'cta' => ['label' => 'Button: Go to My Account', 'url' => '/myaccount'],
            'footer-pay' => ['label' => 'Footer: Pay My Bill', 'url' => '/myaccount/pay'],
            'footer-plans' => ['label' => 'Footer: See Plans', 'url' => '/plans'],
            'footer-unsubscribe' => ['label' => 'Footer: Unsubscribe', 'url' => '/unsubscribe'],
            'footer-privacy' => ['label' => 'Footer: Privacy', 'url' => '/privacy-policy'],
        ];
    }

    /** The email as sent, with data-link="key" on each link (for the heat map overlay). */
    public static function html(?EmailTemplate $t, ?Customer $sample, ?string $text = null): string
    {
        $body = $t ? ($sample ? $t->render($sample) : $t->body) : '<p>'.e((string) $text).'</p>';
        $i = 0;
        $body = preg_replace_callback('/<a\s/i', function () use (&$i) {
            $i++;

            return '<a data-link="body-'.$i.'" ';
        }, $body);
        $brand = e(config('brand.name'));

        return <<<HTML
<div class="hm-email">
  <div class="hm-head"><a data-link="logo" href="/">{$brand}</a></div>
  <div class="hm-body">{$body}</div>
  <div class="hm-cta"><a data-link="cta" href="/myaccount">Go to My Account</a></div>
  <div class="hm-foot"><a data-link="footer-pay" href="/myaccount/pay">Pay My Bill</a> · <a data-link="footer-plans" href="/plans">See Plans</a> · <a data-link="footer-unsubscribe" href="/unsubscribe">Unsubscribe</a> · <a data-link="footer-privacy" href="/privacy-policy">Privacy</a></div>
</div>
HTML;
    }
}
