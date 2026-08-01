import { createHmac } from 'node:crypto';

/** Decode RFC 4648 base32 (Google Authenticator secrets). */
function base32Decode(input: string): Buffer {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    const cleaned = input.replace(/=+$/g, '').replace(/\s+/g, '').toUpperCase();
    let bits = '';

    for (const char of cleaned) {
        const value = alphabet.indexOf(char);
        if (value < 0) {
            throw new Error(`Invalid base32 character: ${char}`);
        }
        bits += value.toString(2).padStart(5, '0');
    }

    const bytes: number[] = [];
    for (let i = 0; i + 8 <= bits.length; i += 8) {
        bytes.push(Number.parseInt(bits.slice(i, i + 8), 2));
    }

    return Buffer.from(bytes);
}

/** RFC 6238 TOTP (SHA-1, 30s step, 6 digits). */
export function generateTotp(secretBase32: string, atMs = Date.now()): string {
    const key = base32Decode(secretBase32);
    const counter = Math.floor(atMs / 1000 / 30);
    const buffer = Buffer.alloc(8);
    buffer.writeBigUInt64BE(BigInt(counter));

    const digest = createHmac('sha1', key).update(buffer).digest();
    const offset = digest[digest.length - 1]! & 0x0f;
    const code =
        ((digest[offset]! & 0x7f) << 24)
        | ((digest[offset + 1]! & 0xff) << 16)
        | ((digest[offset + 2]! & 0xff) << 8)
        | (digest[offset + 3]! & 0xff);

    return (code % 1_000_000).toString().padStart(6, '0');
}
