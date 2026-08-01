import { useSyncExternalStore } from 'react';

function subscribe(onStoreChange: () => void): () => void {
    const observer = new MutationObserver(onStoreChange);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
    return () => observer.disconnect();
}

function getSnapshot(): boolean {
    return document.documentElement.classList.contains('dark');
}

function getServerSnapshot(): boolean {
    return false;
}

/** True when `html.dark` is applied (console theme). */
export function useIsDarkMode(): boolean {
    return useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
}
