import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

export type E2eFixtures = {
    organization_id: string;
    organization_slug: string;
    owner_email: string;
    oauth_client_id: string;
    oauth_redirect_uri: string;
    oidc_user_email: string;
    oidc_user_password: string;
    oidc_user_id: string;
    oidc_reset_user_email: string;
    oidc_reset_user_password: string;
};

export function loadE2eFixtures(): E2eFixtures {
    const path = resolve(process.cwd(), 'storage/app/e2e-fixtures.json');
    const raw = readFileSync(path, 'utf8');
    const data = JSON.parse(raw) as E2eFixtures;

    if (!data.oauth_client_id || !data.organization_id) {
        throw new Error(`Invalid E2E fixtures at ${path}`);
    }

    return data;
}
