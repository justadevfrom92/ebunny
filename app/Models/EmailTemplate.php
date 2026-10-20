<?php

namespace App\Models;

use App\Models\Concerns\RecordsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

/** An email customers get (Rodeo → Email Templates). {{first_name}}, {{account}}, {{balance}}, {{plan}} and {{survey:slug}} are filled in. */
class EmailTemplate extends Model
{
    use RecordsHistory;

    protected $guarded = ['id'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(EmailCategory::class, 'email_category_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function header(): BelongsTo
    {
        return $this->belongsTo(EmailPart::class, 'email_header_id');
    }

    public function footer(): BelongsTo
    {
        return $this->belongsTo(EmailPart::class, 'email_footer_id');
    }

    /** The header and footer this template uses: its own picks, or the defaults. */
    public function headerPart(): ?EmailPart
    {
        return $this->header ?? EmailPart::where('kind', 'header')->where('is_default', true)->first();
    }

    public function footerPart(): ?EmailPart
    {
        return $this->footer ?? EmailPart::where('kind', 'footer')->where('is_default', true)->first();
    }

    /** The whole email as the customer gets it: header, body, footer, with the fill-ins. */
    public function render(Customer $c): string
    {
        return $this->fillIns(($this->headerPart()?->html ?? '').$this->body.($this->footerPart()?->html ?? ''), $c);
    }

    /** Just the body, with the fill-ins. */
    public function renderBody(Customer $c): string
    {
        return $this->fillIns($this->body, $c);
    }

    /** The subject with its fill-ins; plain text, so nothing is HTML-escaped. */
    public function renderSubject(Customer $c): string
    {
        return html_entity_decode($this->fillIns($this->subject, $c), ENT_QUOTES);
    }

    public function testSends(): HasMany
    {
        return $this->hasMany(EmailTestSend::class);
    }

    private function fillIns(string $text, Customer $c): string
    {
        return preg_replace_callback('/\{\{\s*(first_name|account|balance|plan|survey:([a-z0-9-]+))\s*\}\}/', fn ($m) => match (true) {
            $m[1] === 'first_name' => e($c->first_name),
            $m[1] === 'account' => e($c->account),
            $m[1] === 'balance' => '$'.number_format($c->balance, 2),
            $m[1] === 'plan' => e($c->plan?->name ?? ''),
            // Signed, so a response is only tied to the account the email was sent to
            default => URL::signedRoute('survey.show', ['survey' => $m[2], 'c' => $c->account]),
        }, $text);
    }
}
