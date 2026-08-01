<?php

namespace App\Enums;

enum AuditAction: string
{
    case Login = 'login';
    case Logout = 'logout';
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserDeleted = 'user.deleted';
    case OrganizationCreated = 'organization.created';
    case OrganizationUpdated = 'organization.updated';
    case OrganizationDeleted = 'organization.deleted';
    case MemberInvited = 'member.invited';
    case MemberJoined = 'member.joined';
    case MemberRemoved = 'member.removed';
    case OauthClientCreated = 'oauth_client.created';
    case OauthClientUpdated = 'oauth_client.updated';
    case OauthClientRevoked = 'oauth_client.revoked';
    case RoleCreated = 'role.created';
    case RoleUpdated = 'role.updated';
    case RoleDeleted = 'role.deleted';
    case PermissionGranted = 'permission.granted';
    case PermissionRevoked = 'permission.revoked';
    case MfaEnabled = 'mfa.enabled';
    case MfaDisabled = 'mfa.disabled';
    case DomainAdded = 'domain.added';
    case DomainVerified = 'domain.verified';
    case DomainRemoved = 'domain.removed';
    case EmailTemplateUpdated = 'email_template.updated';
}
