import { useCallback, useContext, useEffect, useMemo, useSyncExternalStore } from 'react';
import de from '../../../lang/de.json';
import en from '../../../lang/en.json';
import es from '../../../lang/es.json';
import fr from '../../../lang/fr.json';
import hi from '../../../lang/hi.json';
import { WorkspaceContext } from '../workspace/WorkspaceContext';

type Messages = Record<string, string>;

const LOCALE_STORAGE_KEY = 'authzio_preferred_locale';
const supportedLocales = new Set(['en', 'fr', 'de', 'es', 'hi']);

const catalogs: Record<string, Messages> = {
    en: en as Messages,
    fr: fr as Messages,
    de: de as Messages,
    es: es as Messages,
    hi: hi as Messages,
};

type I18nSnapshot = {
    locale: string;
    messages: Messages;
    english: Messages;
    loading: boolean;
    version: number;
};

let snapshot: I18nSnapshot = {
    locale: 'en',
    messages: catalogs.en,
    english: catalogs.en,
    loading: false,
    version: 0,
};

const listeners = new Set<() => void>();

function emit(): void {
    snapshot = { ...snapshot, version: snapshot.version + 1 };
    for (const listener of listeners) {
        listener();
    }
}

function normalizeLocale(value: string | null | undefined): string {
    if (value && supportedLocales.has(value)) {
        return value;
    }

    try {
        const stored = localStorage.getItem(LOCALE_STORAGE_KEY);
        if (stored && supportedLocales.has(stored)) {
            return stored;
        }
    } catch {
        /* ignore */
    }

    const browser = (typeof navigator !== 'undefined' ? navigator.language : 'en')
        .slice(0, 2)
        .toLowerCase();

    return supportedLocales.has(browser) ? browser : 'en';
}

function lookup(messages: Messages, key: string): string | undefined {
    const value = messages[key];
    return value !== undefined && value !== '' ? value : undefined;
}

function messagesFor(locale: string): Messages {
    return catalogs[locale] ?? catalogs.en;
}

function applyLocale(locale: string): void {
    const target = normalizeLocale(locale);

    try {
        localStorage.setItem(LOCALE_STORAGE_KEY, target);
    } catch {
        /* ignore */
    }

    snapshot = {
        locale: target,
        messages: messagesFor(target),
        english: catalogs.en,
        loading: false,
        version: snapshot.version,
    };
    emit();
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);
    return () => {
        listeners.delete(listener);
    };
}

function getSnapshot(): I18nSnapshot {
    return snapshot;
}

let bootstrapped = false;

function bootstrap(locale: string): void {
    const target = normalizeLocale(locale);
    if (!bootstrapped) {
        bootstrapped = true;
        applyLocale(target);
        return;
    }

    if (snapshot.locale !== target) {
        applyLocale(target);
    }
}

export function useI18n() {
    const workspace = useContext(WorkspaceContext);
    const preferred = workspace?.userPreferences.preferred_locale;
    const locale = normalizeLocale(preferred);

    useEffect(() => {
        bootstrap(locale);
    }, [locale]);

    const current = useSyncExternalStore(subscribe, getSnapshot, getSnapshot);

    const t = useCallback(
        (key: string, replacements: Record<string, string | number> = {}): string => {
            // version forces re-bind when catalogs reload with stable object refs
            const catalogGeneration = current.version;
            let value =
                lookup(current.messages, key) ??
                lookup(current.english, key) ??
                lookup(catalogs.en, key) ??
                key;

            for (const [name, replacement] of Object.entries(replacements)) {
                value = value.replaceAll(`:${name}`, String(replacement));
                value = value.replaceAll(`{{${name}}}`, String(replacement));
            }

            return catalogGeneration >= 0 ? value : value;
        },
        [current.messages, current.english, current.version],
    );

    return useMemo(
        () => ({
            t,
            locale: current.locale,
            loading: current.loading,
            messages: current.messages,
        }),
        [t, current.locale, current.loading, current.messages],
    );
}
