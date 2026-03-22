<?php

namespace App\Models;

use App\Models\Traits\BelongsToStructure;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, BelongsToStructure, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'role', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Utilisateur {$eventName}");
    }

    public string $codeField = 'user_code';

    protected $fillable = [
        'name',
        'user_code',
        'email',
        'password',
        'role_id',
        'is_active',
        'avatar',
        'structure_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    // ── Role helpers ────────────────────────────────────────────────
    public function isAdmin(): bool     { return $this->role?->name === 'admin'; }
    public function isManager(): bool   { return $this->role?->name === 'manager'; }
    public function isCashier(): bool   { return $this->role?->name === 'cashier'; }
    public function isWarehouse(): bool { return $this->role?->name === 'warehouse'; }

    public function canManageStock(): bool
    {
        return $this->hasPermission('stock.manage');
    }

    // ── Permission helpers ──────────────────────────────────────────
    public function hasPermission(string $permission): bool
    {
        return $this->role?->permissions->contains('name', $permission) ?? false;
    }

    public function hasAnyPermission(string ...$permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    // ── Relations ─────────────────────────────────────────────────
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(StructureIncrementor::class, 'structure_id');
    }

    public function documentHeaders(): HasMany
    {
        return $this->hasMany(DocumentHeader::class, 'user_id');
    }

    public function stockMouvements(): HasMany
    {
        return $this->hasMany(StockMouvement::class, 'user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'user_id');
    }
}
