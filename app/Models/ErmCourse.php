<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Bunkhouse: a training course, required for some departments or everyone. */
class ErmCourse extends Model
{
    public const CATEGORIES = ['Compliance', 'Customer Care', 'Systems', 'Product', 'Safety', 'Leadership'];

    protected $table = 'erm_courses';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['required_for' => 'array', 'hours' => 'float'];
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(ErmEnrollment::class, 'course_id');
    }

    /** "Everyone", "3rd-party users", "Customer Service, Finance" or "Optional". */
    public function requiredLabel(): string
    {
        $r = $this->required_for ?? [];

        return match (true) {
            in_array('all', $r, true) => 'Everyone',
            ! $r => 'Optional',
            default => collect($r)->map(fn ($d) => $d === 'external' ? '3rd-party users' : $d)->implode(', '),
        };
    }

    public function requiredFor(User $u): bool
    {
        $r = $this->required_for ?? [];

        return in_array('all', $r, true) || ($u->company ? in_array('external', $r, true) : in_array($u->department, $r, true));
    }
}
