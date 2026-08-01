import { FileQuestion } from 'lucide-react';
import { Link } from 'react-router';
import { useAuth } from '@/auth/AuthContext';
import { EmptyState } from '@/components/EmptyState';
import { useI18n } from '@/hooks/useI18n';

export function NotFoundPage({ standalone = false }: { standalone?: boolean }) {
    const { user } = useAuth();
    const { t } = useI18n();

    const content = (
        <EmptyState
            icon={FileQuestion}
            title={t('console.page.not_found.title')}
            description={t('console.page.not_found.description')}
            action={
                <div className="flex flex-wrap justify-center gap-3">
                    <Link
                        to={user ? '/' : '/login'}
                        className="bg-ink px-4 py-2.5 text-sm font-semibold text-paper hover:bg-ink-soft"
                    >
                        {user
                            ? t('console.page.not_found.back_console')
                            : t('console.page.not_found.sign_in')}
                    </Link>
                    <a
                        href="/"
                        className="border border-mist px-4 py-2.5 text-sm font-medium text-ink hover:bg-fog"
                    >
                        {t('console.page.not_found.marketing_home')}
                    </a>
                </div>
            }
        />
    );

    if (!standalone) {
        return content;
    }

    return (
        <div className="flex min-h-screen items-center justify-center bg-paper px-4 py-16">
            <div className="w-full max-w-lg">
                <div className="mb-6 flex items-center justify-center gap-2.5">
                    <img src="/images/logo.svg" alt="" className="size-7" width={40} height={40} />
                    <span className="font-display text-xl font-bold tracking-tight">Authzio</span>
                </div>
                {content}
            </div>
        </div>
    );
}
