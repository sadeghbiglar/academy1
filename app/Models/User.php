<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use App\Enums\Section;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'first_name', 'last_name', 'mobile', 'is_active', 'teacher_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function isTeacher(): bool
    {
        return $this->role === Role::Teacher;
    }

    public function canSee(string|Section $section): bool
    {
        return $this->is_admin || ($this->role?->canSee($section) ?? false);
    }

    public function hasRole(Role|string ...$roles): bool
    {
        $roles = collect($roles)
            ->map(fn ($r) => $r instanceof Role ? $r : Role::tryFrom($r))
            ->filter();

        return $roles->contains($this->role);
    }

    /** نام نمایشی: نام و نام خانوادگی اگر هست، وگرنه name، وگرنه ایمیل. */
    public function getDisplayNameAttribute(): string
    {
        $full = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        return $full !== '' ? $full : ($this->name ?: $this->email);
    }

    public function getIsAdminAttribute(): bool
    {
        return $this->role === Role::Admin;
    }

    public function getRoleLabelAttribute(): string
    {
        return $this->role?->label() ?? '';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeWithRole(Builder $query, Role ...$roles): Builder
    {
        return $query->whereIn('role', array_map(fn ($r) => $r->value, $roles));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }
}
