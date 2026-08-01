import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

type EmptyStateProps = {
    title: string;
    description: string;
    icon?: LucideIcon;
    action?: ReactNode;
};

export function EmptyState({ title, description, icon: Icon, action }: EmptyStateProps) {
    return (
        <div className="flex min-h-64 flex-col items-center justify-center border border-dashed border-mist bg-paper-elevated px-6 py-16 text-center">
            {Icon ? (
                <span className="mb-4 inline-flex size-12 items-center justify-center rounded-lg bg-teal/10 text-teal">
                    <Icon className="size-6" strokeWidth={1.75} />
                </span>
            ) : null}
            <p className="font-display text-base font-semibold text-ink">{title}</p>
            <p className="mt-2 max-w-md text-sm leading-relaxed text-ink-soft/60">{description}</p>
            {action ? <div className="mt-6">{action}</div> : null}
        </div>
    );
}
