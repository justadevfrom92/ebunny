<?php

namespace App\Support;

use Cron\CronExpression;
use Illuminate\Support\Carbon;

/** Cron schedules in plain words, and when they next run. */
class Cron
{
    public static function next(string $expr): ?Carbon
    {
        return CronExpression::isValidExpression($expr) ? Carbon::instance((new CronExpression($expr))->getNextRunDate(now())) : null;
    }

    /** "Every 15 minutes", "Every hour", "Daily at 6:30am", or the expression itself for anything less common. */
    public static function describe(string $expr): string
    {
        $p = preg_split('/\s+/', trim($expr));
        if (count($p) !== 5) {
            return $expr;
        }
        [$min, $hour, $dom, $mon, $dow] = $p;
        $everyDay = $dom === '*' && $mon === '*' && $dow === '*';
        if ($everyDay && $hour === '*' && preg_match('#^\*/(\d+)$#', $min, $m)) {
            return 'Every '.$m[1].' minutes';
        }
        if ($everyDay && $hour === '*' && ctype_digit($min)) {
            return $min === '0' ? 'Every hour' : 'Every hour at :'.str_pad($min, 2, '0', STR_PAD_LEFT);
        }
        if ($everyDay && ctype_digit($min) && preg_match('#^\*/(\d+)$#', $hour, $m)) {
            return 'Every '.$m[1].' hours';
        }
        if ($dom === '*' && $mon === '*' && ctype_digit($min) && ctype_digit($hour)) {
            $at = Carbon::createFromTime((int) $hour, (int) $min)->format('g:ia');
            $days = ['1-5' => 'Weekdays', '0' => 'Sundays', '1' => 'Mondays', '6' => 'Saturdays', '*' => 'Daily'][$dow] ?? null;

            return $days ? $days.' at '.$at : $expr;
        }

        return $expr;
    }
}
