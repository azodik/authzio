import { mailpitApiBase } from './env';

type MailpitMessageSummary = {
    ID: string;
    To: Array<{ Address: string }>;
    Subject: string;
    Created: string;
};

type MailpitSearchResponse = {
    messages: MailpitMessageSummary[];
    total: number;
};

type MailpitMessage = {
    ID: string;
    Subject: string;
    HTML: string;
    Text: string;
};

/** Messages created at/after this ISO time are eligible for wait/count (set by clearMailbox). */
let mailboxEpochIso: string | null = null;

async function sleep(ms: number): Promise<void> {
    await new Promise((resolve) => setTimeout(resolve, ms));
}

function keepMailpit(): boolean {
    return process.env.E2E_KEEP_MAILPIT === '1' || process.env.E2E_KEEP_MAILPIT === 'true';
}

function isAfterEpoch(created: string): boolean {
    if (mailboxEpochIso === null) {
        return true;
    }

    return Date.parse(created) >= Date.parse(mailboxEpochIso);
}

/**
 * Clears Mailpit unless E2E_KEEP_MAILPIT=1.
 * Always advances the local epoch so waitForEmail only matches newer messages.
 */
export async function clearMailbox(): Promise<void> {
    mailboxEpochIso = new Date().toISOString();

    if (keepMailpit()) {
        return;
    }

    const response = await fetch(`${mailpitApiBase()}/api/v1/messages`, {
        method: 'DELETE',
    });
    if (!response.ok && response.status !== 200) {
        throw new Error(`Mailpit clear failed: ${response.status}`);
    }
}

export async function waitForEmail(
    to: string,
    options: { subjectIncludes?: string; bodyIncludes?: string; timeoutMs?: number } = {},
): Promise<MailpitMessage> {
    const timeoutMs = options.timeoutMs ?? 30_000;
    const deadline = Date.now() + timeoutMs;
    const query = encodeURIComponent(`to:${to}`);

    while (Date.now() < deadline) {
        const search = await fetch(`${mailpitApiBase()}/api/v1/search?query=${query}`);
        if (!search.ok) {
            throw new Error(`Mailpit search failed: ${search.status}`);
        }

        const payload = (await search.json()) as MailpitSearchResponse;
        const candidates = (payload.messages ?? [])
            .filter((message) => isAfterEpoch(message.Created))
            .filter((message) => {
                if (!options.subjectIncludes) {
                    return true;
                }
                return message.Subject.toLowerCase().includes(options.subjectIncludes.toLowerCase());
            })
            .sort((a, b) => Date.parse(b.Created) - Date.parse(a.Created));

        for (const candidate of candidates) {
            const detail = await fetch(`${mailpitApiBase()}/api/v1/message/${candidate.ID}`);
            if (!detail.ok) {
                throw new Error(`Mailpit message fetch failed: ${detail.status}`);
            }
            const message = (await detail.json()) as MailpitMessage;
            if (options.bodyIncludes) {
                const haystack = `${message.HTML ?? ''}\n${message.Text ?? ''}`.toLowerCase();
                if (!haystack.includes(options.bodyIncludes.toLowerCase())) {
                    continue;
                }
            }
            return message;
        }

        await sleep(500);
    }

    throw new Error(
        `Timed out waiting for email to ${to}`
        + (options.subjectIncludes ? ` subject~${options.subjectIncludes}` : '')
        + (options.bodyIncludes ? ` body~${options.bodyIncludes}` : ''),
    );
}

export async function countEmails(
    to: string,
    options: { subjectIncludes?: string } = {},
): Promise<number> {
    const query = encodeURIComponent(`to:${to}`);
    const search = await fetch(`${mailpitApiBase()}/api/v1/search?query=${query}`);
    if (!search.ok) {
        throw new Error(`Mailpit search failed: ${search.status}`);
    }

    const payload = (await search.json()) as MailpitSearchResponse;
    return (payload.messages ?? []).filter((message) => {
        if (!isAfterEpoch(message.Created)) {
            return false;
        }
        if (!options.subjectIncludes) {
            return true;
        }
        return message.Subject.toLowerCase().includes(options.subjectIncludes.toLowerCase());
    }).length;
}

export function extractHref(html: string, pathFragment: string): string {
    const pattern = new RegExp(`href=["']([^"']*${pathFragment}[^"']*)["']`, 'i');
    const match = html.match(pattern);
    if (match?.[1]) {
        return match[1].replace(/&amp;/g, '&');
    }

    const bare = html.match(
        new RegExp(`https?://[^\\s"'<>]*${pathFragment}[^\\s"'<>]*`, 'i'),
    );
    if (bare?.[0]) {
        return bare[0].replace(/&amp;/g, '&');
    }

    throw new Error(`Could not find link containing ${pathFragment}`);
}

export function extractVerificationCode(html: string): string {
    const match = html.match(/\b(\d{6})\b/);
    if (!match?.[1]) {
        throw new Error('Could not find 6-digit verification code in email');
    }
    return match[1];
}
