import type { ReactNode } from 'react';

type PageHeaderProps = {
    title: string;
    description: string;
    action?: ReactNode;
};

export function PageHeader({ title, description, action }: PageHeaderProps) {
    return (
        <div className="mb-8 flex flex-col gap-4 border-b border-mist/80 pb-6 sm:flex-row sm:items-end sm:justify-between">
            <div className="min-w-0">
                <h1 className="font-display text-[1.65rem] font-bold tracking-tight text-ink sm:text-3xl">
                    {title}
                </h1>
                <p className="mt-1.5 max-w-2xl text-sm leading-relaxed text-ink-soft/60">
                    {description}
                </p>
            </div>
            {action ? <div className="shrink-0">{action}</div> : null}
        </div>
    );
}
