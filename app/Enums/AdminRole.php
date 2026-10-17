<?php

namespace App\Enums;

enum AdminRole: string
{
    case SuperAdmin = 'super_admin';
    case Support = 'support';
    case Billing = 'billing';
    case ContentManager = 'content_manager';
    case PrivacyOfficer = 'privacy_officer';
    case Auditor = 'auditor';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',self::Support => 'Support',self::Billing => 'Billing',self::ContentManager => 'Content Manager',self::PrivacyOfficer => 'Privacy Officer',self::Auditor => 'Auditor (read only)'
        };
    }
}
