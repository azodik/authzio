import { Copy } from 'lucide-react';
import { Link } from 'react-router';

export type CreatedCredentials = {
    client_id: string;
    client_secret: string | null;
    warning: string | null;
};

type ApplicationCredentialsCardProps = {
    credentials: CreatedCredentials;
    appHome: (applicationId: string) => string;
    clientIdLabel: string;
    clientSecretLabel: string;
    createdLabel: string;
    configureLabel: string;
};

function CopyButton({ value }: { value: string }) {
    return (
        <button
            type="button"
            className="text-ink-soft/50 hover:text-ink"
            aria-label="Copy"
            onClick={() => {
                void navigator.clipboard.writeText(value);
            }}
        >
            <Copy className="size-3.5" strokeWidth={1.75} />
        </button>
    );
}

export function ApplicationCredentialsCard({
    credentials,
    appHome,
    clientIdLabel,
    clientSecretLabel,
    createdLabel,
    configureLabel,
}: ApplicationCredentialsCardProps) {
    return (
        <div className="mb-6 border border-teal/40 bg-fog p-5">
            <p className="font-display text-base font-semibold text-ink">{createdLabel}</p>
            {credentials.warning !== null && (
                <p className="mt-1 text-sm text-teal-deep">{credentials.warning}</p>
            )}
            <dl className="mt-4 space-y-3 text-sm">
                <div>
                    <dt className="text-ink-soft/60">{clientIdLabel}</dt>
                    <dd className="mt-1 flex items-center gap-2 font-mono text-ink">
                        <span className="break-all">{credentials.client_id}</span>
                        <CopyButton value={credentials.client_id} />
                    </dd>
                </div>
                {credentials.client_secret !== null && (
                    <div>
                        <dt className="text-ink-soft/60">{clientSecretLabel}</dt>
                        <dd className="mt-1 flex items-center gap-2 font-mono text-ink">
                            <span className="break-all">{credentials.client_secret}</span>
                            <CopyButton value={credentials.client_secret} />
                        </dd>
                    </div>
                )}
            </dl>
            <Link
                to={appHome(credentials.client_id)}
                className="mt-4 inline-flex text-sm font-medium text-teal hover:text-teal-deep"
            >
                {configureLabel}
            </Link>
        </div>
    );
}
