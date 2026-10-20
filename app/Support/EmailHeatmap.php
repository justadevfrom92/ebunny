<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\EmailTemplate;

/**
 * The links in an email as sent (its header, the template body and its footer — Rodeo → Headers &
 * Footers), each with a key (header-1, body-2, footer-3…), so clicks can be counted per link and
 * drawn over the email as a heat map.
 */
class EmailHeatmap
{
    /** @return array<string, array{label: string, url: string}> in the order they appear */
    public static function links(?EmailTemplate $t): array
    {
        $links = [];
        foreach (self::sections($t) as $section => $html) {
            if (preg_match_all('/<a\s[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/is', $html, $m, PREG_SET_ORDER)) {
                foreach ($m as $i => $a) {
                    $text = trim(preg_replace('/\s+/', ' ', strip_tags($a[2])));
                    $links[$section.'-'.($i + 1)] = ['label' => ucfirst($section).': '.($text !== '' ? $text : 'Link '.($i + 1)), 'url' => $a[1]];
                }
            }
        }

        return $links;
    }

    /** The email as sent, with data-link="key" on each link (for the heat map overlay). */
    public static function html(?EmailTemplate $t, ?Customer $sample): string
    {
        $out = '';
        foreach (self::sections($t, $sample) as $section => $html) {
            $i = 0;
            $out .= '<div class="hm-'.$section.'">'.preg_replace_callback('/<a\s/i', function () use (&$i, $section) {
                $i++;

                return '<a data-link="'.$section.'-'.$i.'" ';
            }, $html).'</div>';
        }

        return '<div class="hm-email">'.$out.'</div>';
    }

    /** header / body / footer HTML (filled in for $sample when given). */
    private static function sections(?EmailTemplate $t, ?Customer $sample = null): array
    {
        if (! $t) {
            return [];
        }
        $parts = ['header' => $t->headerPart()?->html ?? '', 'body' => $t->body, 'footer' => $t->footerPart()?->html ?? ''];
        if ($sample) {
            // Fill in each part on its own, so the link keys stay per part
            $parts = array_map(fn ($html) => (new EmailTemplate(['body' => $html]))->renderBody($sample), $parts);
        }

        return $parts;
    }
}
