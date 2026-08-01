type Translate = (key: string, replacements?: Record<string, string | number>) => string;

export function emailTemplateLabel(slug: string, fallback: string, t: Translate): string {
    const key = `console.page.email_templates.slug.${slug}`;
    const translated = t(key);
    return translated === key ? fallback : translated;
}
