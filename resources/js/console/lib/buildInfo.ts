export type AuthzioBuildInfo = {
    name: string;
    version: string;
    build: string;
    commit: string;
    display: string;
};

declare global {
    interface Window {
        __AUTHZIO__?: AuthzioBuildInfo;
    }
}

const fallback: AuthzioBuildInfo = {
    name: 'Authzio',
    version: '0.0.0',
    build: 'dev',
    commit: 'unknown',
    display: 'Authzio 0.0.0',
};

export function readBuildInfo(): AuthzioBuildInfo {
    const raw = typeof window !== 'undefined' ? window.__AUTHZIO__ : undefined;
    if (!raw || typeof raw !== 'object') {
        return fallback;
    }

    const name = typeof raw.name === 'string' && raw.name.trim() !== '' ? raw.name : fallback.name;
    const version =
        typeof raw.version === 'string' && raw.version.trim() !== ''
            ? raw.version
            : fallback.version;
    const build =
        typeof raw.build === 'string' && raw.build.trim() !== '' ? raw.build : fallback.build;
    const commit =
        typeof raw.commit === 'string' && raw.commit.trim() !== '' ? raw.commit : fallback.commit;
    const display =
        typeof raw.display === 'string' && raw.display.trim() !== ''
            ? raw.display
            : build.toLowerCase() === 'dev'
              ? `${name} ${version}`
              : `${name} ${version} (Build ${build})`;

    return { name, version, build, commit, display };
}
