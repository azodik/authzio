import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { type ReactNode, useState } from 'react';
import { Toaster } from 'sonner';
import { ApiError } from '@/lib/api';

function createQueryClient(): QueryClient {
    return new QueryClient({
        defaultOptions: {
            queries: {
                staleTime: 30_000,
                retry: (failureCount, error) => {
                    if (error instanceof ApiError && error.status >= 400 && error.status < 500) {
                        return false;
                    }
                    return failureCount < 2;
                },
                refetchOnWindowFocus: false,
            },
            mutations: {
                retry: false,
            },
        },
    });
}

type AppProvidersProps = {
    children: ReactNode;
};

export function AppProviders({ children }: AppProvidersProps) {
    const [queryClient] = useState(createQueryClient);

    return (
        <QueryClientProvider client={queryClient}>
            {children}
            <Toaster
                position="top-right"
                closeButton
                toastOptions={{
                    classNames: {
                        toast: 'border border-mist bg-paper-elevated text-ink shadow-none',
                        title: 'text-ink font-medium',
                        description: 'text-ink-soft/80',
                        success: 'border-success/30',
                        error: 'border-danger/30',
                        closeButton: 'border-mist bg-paper text-ink',
                    },
                }}
            />
        </QueryClientProvider>
    );
}
