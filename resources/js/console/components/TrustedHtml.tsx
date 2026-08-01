import { useEffect, useRef } from 'react';

type TrustedHtmlProps = {
    html: string;
    className?: string;
};

/** Renders trusted HTML from our own API (e.g. MFA QR SVG). */
export function TrustedHtml({ html, className }: TrustedHtmlProps) {
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (ref.current) {
            ref.current.innerHTML = html;
        }
    }, [html]);

    return <div ref={ref} className={className} />;
}
