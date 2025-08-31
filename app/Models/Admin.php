<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, HasUuids;
    use LogsActivity, SoftDeletes;

    protected $guard = 'admin';

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'role',
        'permissions',
        'is_active',
        'two_factor_enabled',
        'last_login_at',
        'last_login_ip',
        'created_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'id' => 'string',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'permissions' => 'array',
        'is_active' => 'boolean',
        'two_factor_enabled' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    // Activity Log Configuration
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'role', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // Relationships
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function createdAdmins(): HasMany
    {
        return $this->hasMany(Admin::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSuperAdmins($query)
    {
        return $query->where('role', 'super_admin');
    }

    public function scopeRegularAdmins($query)
    {
        return $query->whereIn('role', ['admin', 'moderator']);
    }

    // Accessors
    public function getInitialsAttribute(): string
    {
        return strtoupper(substr($this->name, 0, 2));
    }

    public function getAvatarUrlAttribute(): string
    {
        return $this->avatar 
            ? asset('storage/' . $this->avatar)
            : 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&size=200&background=DC2626&color=fff';
    }

    // Business Logic Methods
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isModerator(): bool
    {
        return $this->role === 'moderator';
    }

    public function canManageAdmins(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canManageTenants(): bool
    {
        return in_array($this->role, ['super_admin', 'admin']);
    }

    public function canAccessAnalytics(): bool
    {
        return $this->is_active && !$this->isModerator();
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($permission, $this->permissions ?? []);
    }

    public function grantPermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        
        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->update(['permissions' => $permissions]);
        }
    }

    public function revokePermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        
        $this->update([
            'permissions' => array_values(array_diff($permissions, [$permission]))
        ]);
    }

    public function recordLogin(string $ip): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);
    }

    // Two-Factor Authentication (similar to User model)
    public function generateTwoFactorSecret(): string
    {
        $google2fa = app('pragmarx.google2fa');
        $secret = $google2fa->generateSecretKey();
        
        $this->update([
            'two_factor_secret' => encrypt($secret),
        ]);
        
        return $secret;
    }

    public function getTwoFactorQrCodeUrl(): string
    {
        $google2fa = app('pragmarx.google2fa');
        $secret = decrypt($this->two_factor_secret);
        
        return $google2fa->getQRCodeUrl(
            config('app.name') . ' Admin',
            $this->email,
            $secret
        );
    }

    public function verifyTwoFactorCode(string $code): bool
    {
        $google2fa = app('pragmarx.google2fa');
        $secret = decrypt($this->two_factor_secret);
        
        return $google2fa->verifyKey($secret, $code);
    }

    // Admin Hierarchy Methods
    public function canManageAdmin(Admin $admin): bool
    {
        if ($this->id === $admin->id) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        return false;
    }

    public function getManageableRoles(): array
    {
        return match($this->role) {
            'super_admin' => ['admin', 'moderator'],
            'admin' => ['moderator'],
            default => [],
        };
    }
}