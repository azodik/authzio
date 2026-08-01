import { toast } from 'sonner';
import { ApiError } from '@/lib/api';

export function toastSuccess(message: string): void {
    toast.success(message);
}

export function toastError(error: unknown, fallback = 'Something went wrong.'): void {
    if (error instanceof ApiError) {
        const fieldError = Object.values(error.errors)
            .flat()
            .find((value): value is string => typeof value === 'string' && value.trim() !== '');
        toast.error(fieldError ?? error.message);
        return;
    }

    if (error instanceof Error && error.message.trim() !== '') {
        toast.error(error.message);
        return;
    }

    toast.error(fallback);
}

export function toastInfo(message: string): void {
    toast.message(message);
}
