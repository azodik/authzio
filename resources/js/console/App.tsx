import { Route, Routes } from 'react-router';
import { AuthProvider } from '@/auth/AuthContext';
import { RequireAuth } from '@/auth/RequireAuth';
import { ConsoleLayout } from '@/components/ConsoleLayout';
import { OnboardingPage } from '@/routes/account/onboarding/OnboardingPage';
import { OrganizationsPage } from '@/routes/account/organizations/OrganizationsPage';
import { SettingsPage } from '@/routes/account/settings/SettingsPage';
import { WorkspaceHomeRedirect } from '@/routes/account/workspace-home/WorkspaceHomeRedirect';
import { AcceptInvitePage } from '@/routes/auth/accept-invite/AcceptInvitePage';
import { ForgotPasswordPage } from '@/routes/auth/forgot-password/ForgotPasswordPage';
import { LoginPage } from '@/routes/auth/login/LoginPage';
import { MfaChallengePage } from '@/routes/auth/mfa/MfaChallengePage';
import { RegisterPage } from '@/routes/auth/register/RegisterPage';
import { ResetPasswordPage } from '@/routes/auth/reset-password/ResetPasswordPage';
import { VerifyEmailPage } from '@/routes/auth/verify-email/VerifyEmailPage';
import { NotFoundPage } from '@/routes/NotFoundPage';
import { ApplicationsPage } from '@/routes/org/applications/ApplicationsPage';
import { ApplicationDetailPage } from '@/routes/org/applications/detail/ApplicationDetailPage';
import { OidcPage } from '@/routes/org/applications/oidc/OidcPage';
import { AuditLogsPage } from '@/routes/org/audit-logs/AuditLogsPage';
import { BillingPage } from '@/routes/org/billing/BillingPage';
import { DomainsPage } from '@/routes/org/domains/DomainsPage';
import { EmailProviderPage } from '@/routes/org/email-provider/EmailProviderPage';
import { EmailTemplatesPage } from '@/routes/org/email-templates/EmailTemplatesPage';
import { MembersPage } from '@/routes/org/members/MembersPage';
import { OverviewPage } from '@/routes/org/overview/OverviewPage';
import { RolesPage } from '@/routes/org/roles/RolesPage';
import { SocialProvidersPage } from '@/routes/org/social-providers/SocialProvidersPage';
import { SsoPage } from '@/routes/org/sso/SsoPage';
import { UsersPage } from '@/routes/org/users/UsersPage';
import { WorkspaceProvider } from '@/workspace/WorkspaceContext';
import { WorkspaceUrlSync } from '@/workspace/WorkspaceUrlSync';

export function App() {
    return (
        <AuthProvider>
            <Routes>
                <Route path="/login" element={<LoginPage />} />
                <Route path="/mfa" element={<MfaChallengePage />} />
                <Route path="/register" element={<RegisterPage />} />
                <Route path="/forgot-password" element={<ForgotPasswordPage />} />
                <Route path="/reset-password" element={<ResetPasswordPage />} />
                <Route path="/invites/:token" element={<AcceptInvitePage />} />
                <Route path="/verify-email" element={<VerifyEmailPage />} />

                <Route element={<RequireAuth />}>
                    <Route
                        element={
                            <WorkspaceProvider>
                                <ConsoleLayout />
                            </WorkspaceProvider>
                        }
                    >
                        <Route index element={<WorkspaceHomeRedirect />} />
                        <Route path="onboarding" element={<OnboardingPage />} />
                        <Route path="organizations" element={<OrganizationsPage />} />
                        <Route path="settings" element={<SettingsPage />} />

                        <Route path=":orgId" element={<WorkspaceUrlSync />}>
                            <Route index element={<OverviewPage />} />
                            <Route path="applications" element={<ApplicationsPage />} />
                            <Route path="members" element={<MembersPage />} />
                            <Route path="roles" element={<RolesPage />} />
                            <Route path="domains" element={<DomainsPage />} />
                            <Route path="email-templates" element={<EmailTemplatesPage />} />
                            <Route path="email-provider" element={<EmailProviderPage />} />
                            <Route path="social-providers" element={<SocialProvidersPage />} />
                            <Route path="sso" element={<SsoPage />} />
                            <Route path="billing" element={<BillingPage />} />
                            <Route path="users" element={<UsersPage />} />
                            <Route path="audit-logs" element={<AuditLogsPage />} />
                            <Route path=":appId" element={<ApplicationDetailPage />} />
                            <Route path=":appId/oidc" element={<OidcPage />} />
                            <Route path="*" element={<NotFoundPage />} />
                        </Route>
                    </Route>
                </Route>

                <Route path="*" element={<NotFoundPage standalone />} />
            </Routes>
        </AuthProvider>
    );
}
