<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * User Model
 * 
 * @property int $id
 * @property int|null $external_id
 * @property string $provider
 * @property string $username
 * @property string|null $name
 * @property string $email
 * @property \Carbon\Carbon|null $email_verified_at
 * @property string|null $avatar_url
 * @property string|null $bio
 * @property string|null $location
 * @property string|null $company
 * @property string|null $website_url
 * @property string|null $password
 * @property string|null $remember_token
 * @property string|null $oauth_token
 * @property string|null $oauth_refresh_token
 * @property \Carbon\Carbon|null $oauth_expires_at
 * @property string $role
 * @property array|null $permissions
 * @property array|null $preferences
 * @property string $timezone
 * @property bool $email_notifications
 * @property bool $slack_notifications
 * @property string|null $slack_webhook_url
 * @property \Carbon\Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property \Carbon\Carbon|null $last_activity_at
 * @property bool $is_active
 * @property \Carbon\Carbon|null $suspended_at
 * @property string|null $suspension_reason
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'external_id',
        'provider',
        'username',
        'name',
        'email',
        'email_verified_at',
        'avatar_url',
        'bio',
        'location',
        'company',
        'website_url',
        'password',
        'oauth_token',
        'oauth_refresh_token',
        'oauth_expires_at',
        'role',
        'permissions',
        'preferences',
        'timezone',
        'email_notifications',
        'slack_notifications',
        'slack_webhook_url',
        'last_login_at',
        'last_login_ip',
        'last_activity_at',
        'is_active',
        'suspended_at',
        'suspension_reason',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'oauth_token',
        'oauth_refresh_token',
        'slack_webhook_url',
    ];

    protected $casts = [
        'external_id' => 'integer',
        'email_verified_at' => 'datetime',
        'oauth_expires_at' => 'datetime',
        'permissions' => 'array',
        'preferences' => 'array',
        'email_notifications' => 'boolean',
        'slack_notifications' => 'boolean',
        'last_login_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'is_active' => 'boolean',
        'suspended_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user is an admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }
}