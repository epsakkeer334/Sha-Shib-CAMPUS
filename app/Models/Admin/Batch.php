<?php

namespace App\Models\Admin;

use App\Traits\BelongsToInstitute;
use Illuminate\Support\Str;

/**
 * A batch of an institute course (e.g. "B1.1 — June 2026", code SHA-B11-2026).
 * Managed by Super Admin / Institute Admin; students pick one when they register.
 */
class Batch extends BaseModel
{
    use BelongsToInstitute;

    const CODE_PATTERN = '/^[A-Z0-9][A-Z0-9\/\-_.]{1,29}$/';

    protected $fillable = ['institute_id', 'course_id', 'name', 'code', 'start_date', 'end_date', 'capacity', 'status', 'remarks', 'created_by', 'updated_by'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'capacity' => 'integer',
        'status' => 'boolean',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function scopeActive($query)
    {
        return $query->where($this->qualifyColumn('status'), true);
    }

    /** Active batches of an institute course, for the registration dropdowns. */
    public function scopeOpenFor($query, $instituteId, $courseId)
    {
        return $query->active()->where('institute_id', $instituteId)->where('course_id', $courseId)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', today()));
    }

    /** Students holding a seat (everyone except rejected applications). */
    public function seatsTaken(): int
    {
        return $this->students()->where('status', '!=', 'rejected')->count();
    }

    public function isFull(): bool
    {
        return $this->capacity !== null && $this->seatsTaken() >= $this->capacity;
    }

    /** "SHA-B11-2026 · B1.1 June 2026 (12 seats left)" for dropdowns. */
    public function optionLabel(): string
    {
        $label = "{$this->code} · {$this->name}";
        if ($this->capacity !== null) {
            $left = max(0, $this->capacity - $this->seatsTaken());
            $label .= $left ? " ({$left} " . Str::plural('seat', $left) . ' left)' : ' (full)';
        }

        return $label;
    }

    public static function normalizeCode(?string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '-', trim((string) $code)));
    }

    /**
     * Suggested code: institute prefix - course code - start year (e.g. SHA-B11-2026), made unique.
     */
    public static function suggestCode(?Institute $institute, ?Course $course, $startDate = null): ?string
    {
        if (!$institute || !$course) {
            return null;
        }
        $prefix = $institute->code_prefix ?: strtok((string) $institute->code, '/');
        $courseCode = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $course->code));
        $year = $startDate ? \Carbon\Carbon::parse($startDate)->year : now()->year;
        $base = substr(trim("{$prefix}-{$courseCode}-{$year}", '-'), 0, 26);

        $code = $base;
        for ($n = 2; static::withTrashed()->withoutGlobalScopes()->where('code', $code)->exists(); $n++) {
            $code = "{$base}-{$n}";
        }

        return $code;
    }
}
