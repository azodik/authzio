import type { LoginLayout, LoginTheme, PasswordPolicy, SecurityPolicy } from '../types';

type LoginPreviewProps = {
    appName: string;
    logoUrl: string;
    primaryColor: string;
    backgroundColor: string;
    headline: string;
    description: string;
    buttonLabel: string;
    showSignupLink: boolean;
    showForgotPasswordLink: boolean;
    loginLayout: LoginLayout;
    loginTheme: LoginTheme;
    termsUrl: string;
    privacyUrl: string;
    requireLegalAccept: boolean;
    passwordPolicy: PasswordPolicy;
    securityPolicy: SecurityPolicy;
};

/** Hosted-login preview: mirrors authorize layout; follows selected login theme, not console theme. */
export function LoginBoxPreview(props: LoginPreviewProps) {
    const initial = (props.appName.charAt(0) || 'A').toUpperCase();
    const primary = props.primaryColor || '#0F766E';
    const background = props.backgroundColor || '#F3F4F6';
    const dark = props.loginTheme === 'dark';
    const ink = dark ? '#edf2ef' : '#111827';
    const muted = dark ? '#a8b3ac' : '#5b6572';
    const faint = dark ? '#7a8680' : '#8b95a1';
    const line = dark ? '#2a3531' : '#e5e7eb';
    const field = dark ? '#1c2622' : '#f9fafb';
    const paper = dark ? '#151c19' : '#ffffff';
    const layout = props.loginLayout || 'form_right';
    const centered = layout === 'centered';
    const formLeft = layout === 'form_left';

    const brandPanel = (
        <aside
            className="relative flex min-h-[220px] flex-col justify-between p-5 text-white"
            style={{
                background: `linear-gradient(160deg, color-mix(in srgb, ${primary} 88%, #000) 0%, ${primary} 55%, color-mix(in srgb, ${primary} 75%, #fff) 100%)`,
            }}
        >
            <div>
                {props.logoUrl !== '' ? (
                    <img
                        src={props.logoUrl}
                        alt=""
                        className="size-10 rounded-xl bg-white object-contain"
                    />
                ) : (
                    <div
                        className="grid size-10 place-items-center rounded-xl text-base font-bold"
                        style={{
                            background: 'rgba(255,255,255,0.16)',
                            border: '1px solid rgba(255,255,255,0.22)',
                            fontFamily: 'Space Grotesk, Plus Jakarta Sans, sans-serif',
                        }}
                    >
                        {initial}
                    </div>
                )}
                <h3
                    className="mt-5 text-xl font-semibold leading-tight tracking-tight"
                    style={{ fontFamily: 'Space Grotesk, Plus Jakarta Sans, sans-serif' }}
                >
                    {props.appName}
                </h3>
                <p className="mt-2 max-w-[22ch] text-xs leading-relaxed text-white/80">
                    {props.description || 'Sign in to continue to your account securely.'}
                </p>
            </div>
            <p className="text-[11px] text-white/70">Hosted authentication</p>
        </aside>
    );

    const formPanel = (
        <div
            className="flex items-center justify-center p-5"
            style={{
                background: dark
                    ? `linear-gradient(180deg, ${paper} 0%, color-mix(in srgb, ${background} 55%, ${paper}) 100%)`
                    : `linear-gradient(180deg, ${paper} 0%, color-mix(in srgb, ${background} 70%, ${paper}) 100%)`,
            }}
        >
            <div className="w-full max-w-[280px]">
                {centered && (
                    <div className="mb-5 flex items-center gap-3">
                        {props.logoUrl !== '' ? (
                            <img
                                src={props.logoUrl}
                                alt=""
                                className="size-9 rounded-[10px] object-contain"
                            />
                        ) : (
                            <div
                                className="grid size-9 place-items-center rounded-[10px] text-sm font-bold text-white"
                                style={{ background: primary }}
                            >
                                {initial}
                            </div>
                        )}
                        <span className="text-sm font-semibold">{props.appName}</span>
                    </div>
                )}

                <h3
                    className="text-xl font-semibold tracking-tight"
                    style={{ fontFamily: 'Space Grotesk, Plus Jakarta Sans, sans-serif' }}
                >
                    {props.headline || 'Sign in'}
                </h3>
                <p className="mt-2 text-sm leading-relaxed" style={{ color: muted }}>
                    {props.description || `Continue to ${props.appName}`}
                </p>

                <div className="mt-5 space-y-3">
                    <label className="block text-[12px] font-semibold">
                        Email
                        <input
                            readOnly
                            value="alex@example.com"
                            className="mt-1.5 w-full rounded-lg px-3 py-2 text-sm outline-none"
                            style={{ border: `1px solid ${line}`, background: field, color: ink }}
                        />
                    </label>
                    <label className="block text-[12px] font-semibold">
                        Password
                        <input
                            readOnly
                            type="password"
                            value="passwordpassword"
                            className="mt-1.5 w-full rounded-lg px-3 py-2 text-sm outline-none"
                            style={{ border: `1px solid ${line}`, background: field, color: ink }}
                        />
                    </label>
                </div>

                {props.showForgotPasswordLink && (
                    <p
                        className="mt-2 text-right text-[12px] font-semibold"
                        style={{ color: primary }}
                    >
                        Forgot password?
                    </p>
                )}

                {props.requireLegalAccept && (
                    <label
                        className="mt-3 flex items-start gap-2 text-[11px] leading-relaxed"
                        style={{ color: muted }}
                    >
                        <input type="checkbox" checked readOnly className="mt-0.5" />
                        <span>
                            I agree to the{' '}
                            {props.termsUrl !== '' ? (
                                <a href={props.termsUrl} style={{ color: primary }}>
                                    Terms
                                </a>
                            ) : (
                                'Terms'
                            )}{' '}
                            and{' '}
                            {props.privacyUrl !== '' ? (
                                <a href={props.privacyUrl} style={{ color: primary }}>
                                    Privacy Policy
                                </a>
                            ) : (
                                'Privacy Policy'
                            )}
                        </span>
                    </label>
                )}

                <button
                    type="button"
                    className="mt-5 w-full rounded-lg py-2.5 text-sm font-bold text-white"
                    style={{ background: primary }}
                >
                    {props.buttonLabel || 'Continue'}
                </button>

                {props.showSignupLink && (
                    <p className="mt-4 text-sm" style={{ color: muted }}>
                        Need an account?{' '}
                        <span className="font-semibold" style={{ color: primary }}>
                            Sign up
                        </span>
                    </p>
                )}

                <p className="mt-3 text-[10px]" style={{ color: faint }}>
                    Password min {props.passwordPolicy.min_length}
                    {props.passwordPolicy.require_mixed_case ? ' · mixed case' : ''}
                    {props.securityPolicy.mfa_required ? ' · MFA required' : ''}
                </p>
            </div>
        </div>
    );

    return (
        <div
            className={
                centered
                    ? 'grid min-h-[480px] grid-cols-1 overflow-hidden'
                    : 'grid min-h-[480px] grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] overflow-hidden'
            }
            style={{ colorScheme: dark ? 'dark' : 'light', color: ink, background }}
        >
            {centered ? (
                formPanel
            ) : formLeft ? (
                <>
                    {formPanel}
                    {brandPanel}
                </>
            ) : (
                <>
                    {brandPanel}
                    {formPanel}
                </>
            )}
        </div>
    );
}
