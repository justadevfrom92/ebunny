<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/** One choice made in the website's cookie banner. The latest choice for a person (or browser) is the one in force. */
class CookieConsent extends Model
{
    public const UPDATED_AT = null;

    /** Bump when the cookie policy changes: everyone is asked again. */
    public const POLICY_VERSION = '2026-10';

    /** The website's cookies by category: name => [category, purpose, lasts]. Necessary ones are always on. */
    public const COOKIES = [
        'site_session' => ['necessary', 'Keeps you signed in and your cart between pages', 'Session'],
        'XSRF-TOKEN' => ['necessary', 'Protects forms from cross-site requests', 'Session'],
        'cookie_consent' => ['necessary', 'Remembers this cookie choice', '1 year'],
        '_ga' => ['analytics', 'Google Analytics: tells visits apart', '2 years'],
        '_ga_ID' => ['analytics', 'Google Analytics: keeps the visit going', '2 years'],
        '_clck' => ['analytics', 'Clarity: heat maps of clicks on pages', '1 year'],
        '_fbp' => ['marketing', 'Meta: measures ads that brought you here', '3 months'],
        '_gcl_au' => ['marketing', 'Google Ads: measures ad conversions', '3 months'],
        'li_fat_id' => ['marketing', 'LinkedIn: measures business ads', '1 month'],
    ];

    /** The cookies this choice allows. */
    public function cookies(): array
    {
        return array_keys(array_filter(self::COOKIES, fn ($c) => $c[0] === 'necessary' || ($c[0] === 'analytics' && $this->analytics) || ($c[0] === 'marketing' && $this->marketing)));
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['analytics' => 'boolean', 'marketing' => 'boolean', 'created_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The choice in force for this request: a signed-in customer's own (from any device), else this browser's. */
    public static function current(Request $request, ?string $visitor): ?self
    {
        $q = self::where('policy_version', self::POLICY_VERSION)->latest('id');
        if ($c = auth('customer')->user()) {
            $mine = (clone $q)->where('customer_id', $c->id)->first();
            if ($mine) {
                return $mine;
            }
        }

        return $visitor ? $q->where('visitor', $visitor)->first() : null;
    }

    public function label(): string
    {
        return ['all' => 'Accepted all', 'necessary' => 'Necessary only', 'custom' => 'Chose'][$this->choice] ?? $this->choice;
    }
}
