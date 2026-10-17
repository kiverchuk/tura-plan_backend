<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Panel;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'public_id', 'locale', 'region'])]
#[Hidden(['password', 'remember_token', 'admin_mfa_secret'])]
class User extends Authenticatable implements HasAppAuthentication, HasAppAuthenticationRecovery, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, MustVerifyEmailTrait, Notifiable;

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if (! $user->public_id) {
                $user->public_id = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'erasure_requested_at' => 'datetime', 'password' => 'hashed'];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasVerifiedEmail() && in_array($this->admin_role, ['super_admin', 'support', 'billing', 'content_manager', 'privacy_officer', 'auditor'], true);
    }
}
