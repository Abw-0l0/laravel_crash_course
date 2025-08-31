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
use Lab404\Impersonate\Models\Impersonate;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, HasUuids;
    use LogsActivity, Impersonate, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'avatar',
        'status',
        'timezone',
        'locale',
        'two_factor_enabled',
        'social_accounts',
        'last_login_at',
        'last_login_ip',
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
        'two_factor_enabled' => 'boolean',
        'social_accounts' => 'array',
        'last_login_at' => 'datetime',
        'password_changed_at' => 'datetime',
        'locked_until' => 'datetime',
    ];

    // Activity Log Configuration
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['first_name', 'last_name', 'email', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // Accessors & Mutators
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getInitialsAttribute(): string
    {
        return strtoupper(substr($this->first_name, 0, 1) . substr($this->last_name, 0, 1));
    }

    public function getAvatarUrlAttribute(): string
    {
        return $this->avatar 
            ? asset('storage/' . $this->avatar)
            : 'https://ui-avatars.com/api/?name=' . urlencode($this->full_name) . '&size=200&background=0D8ABC&color=fff';
    }

    // Relationships
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_users')
                    ->withPivot(['role', 'permissions', 'invited_at', 'joined_at'])
                    ->withTimestamps();
    }

    public function loginAttempts(): HasMany
    {
        return $this->hasMany(LoginAttempt::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    public function scopeWithTwoFactor($query)
    {
        return $query->where('two_factor_enabled', true);
    }

    // Business Logic Methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled && !empty($this->two_factor_secret);
    }

    public function recordLogin(string $ip): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
            'login_attempts' => 0,
        ]);
    }

    public function incrementLoginAttempts(): void
    {
        $this->increment('login_attempts');
        
        if ($this->login_attempts >= 5) {
            $this->update(['locked_until' => now()->addMinutes(30)]);
        }
    }

    public function resetLoginAttempts(): void
    {
        $this->update(['login_attempts' => 0, 'locked_until' => null]);
    }

    // Impersonation
    public function canImpersonate(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function canBeImpersonated(): bool
    {
        return !$this->hasRole('super_admin');
    }

    // Tenant Management
    public function joinTenant(Tenant $tenant, string $role = 'user', array $permissions = []): void
    {
        $this->tenants()->attach($tenant->id, [
            'role' => $role,
            'permissions' => json_encode($permissions),
            'joined_at' => now(),
        ]);
    }

    public function leaveTenant(Tenant $tenant): void
    {
        $this->tenants()->detach($tenant->id);
    }

    public function hasAccessToTenant(Tenant $tenant): bool
    {
        return $this->tenants()->where('tenant_id', $tenant->id)->exists();
    }

    // Two-Factor Authentication
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
            config('app.name'),
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

    public function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtolower(str_replace(['-', '_'], '', \Str::uuid()));
        }
        
        $this->update([
            'two_factor_recovery_codes' => encrypt(json_encode($codes)),
        ]);
        
        return $codes;
    }
}