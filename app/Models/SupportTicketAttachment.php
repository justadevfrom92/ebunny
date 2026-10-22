<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** A file on a ticket, sent with a My Account message or an email (by the customer or the team). */
class SupportTicketAttachment extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    /** What can be attached (max 10 MB each, 5 per message). */
    public const RULES = ['nullable', 'array', 'max:5'];

    public const FILE_RULES = ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,gif,webp,heic,txt,csv,doc,docx,xls,xlsx'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'from_customer' => 'boolean'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportTicketMessage::class, 'support_ticket_message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Save an upload (or raw content) to the ticket's folder on the private disk. */
    public static function store(SupportTicketMessage $m, UploadedFile|array $file, ?User $by, bool $fromCustomer): self
    {
        [$name, $content, $mime] = $file instanceof UploadedFile ? [$file->getClientOriginalName(), $file->get(), $file->getMimeType() ?: 'application/octet-stream'] : $file;
        $path = 'ranger/'.$m->support_ticket_id.'/'.Str::random(8).'-'.Str::of($name)->basename()->replaceMatches('/[^\w.\-]+/', '-');
        Storage::disk('local')->put($path, $content);

        return self::create(['support_ticket_id' => $m->support_ticket_id, 'support_ticket_message_id' => $m->id, 'user_id' => $by?->id, 'from_customer' => $fromCustomer,
            'name' => Str::limit($name, 190, ''), 'path' => $path, 'mime' => $mime, 'size' => strlen($content), 'created_at' => $m->created_at ?? now()]);
    }

    public function isImage(): bool
    {
        return in_array($this->mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true);
    }

    public function isPdf(): bool
    {
        return $this->mime === 'application/pdf';
    }

    public function isText(): bool
    {
        return in_array($this->mime, ['text/plain', 'text/csv'], true);
    }

    public function sizeLabel(): string
    {
        return $this->size >= 1048576 ? round($this->size / 1048576, 1).' MB' : max(1, round($this->size / 1024)).' KB';
    }

    public function icon(): string
    {
        return $this->isImage() ? '🖼' : ($this->isPdf() ? '📄' : '📎');
    }
}
