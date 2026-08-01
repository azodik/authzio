<?php

namespace App\Services\Mail;

/**
 * Shared HTML chrome for transactional emails (platform + organization).
 */
final class EmailHtml
{
    public static function logoUrl(bool $dark = false): string
    {
        $path = $dark ? '/images/email-logo-dark.png' : '/images/email-logo.png';

        return rtrim((string) config('app.url'), '/').$path;
    }

    public static function productName(): string
    {
        return (string) config('app.name', 'Authzio');
    }

    public static function button(string $url, string $label): string
    {
        $safeUrl = e($url);
        $safeLabel = e($label);

        return <<<HTML
<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:24px 0 8px;">
  <tr>
    <td class="email-btn" style="border-radius:8px;background:#0B6E6E;">
      <a href="{$safeUrl}" class="email-btn-link" style="display:inline-block;padding:12px 20px;font-size:15px;font-weight:600;line-height:1.2;color:#ffffff;text-decoration:none;border-radius:8px;">{$safeLabel}</a>
    </td>
  </tr>
</table>
HTML;
    }

    public static function code(string $code): string
    {
        $safe = e($code);
        // Short OTP codes keep wide tracking; long placeholders (e.g. {{verification_code}}) wrap safely.
        $isShortCode = strlen(html_entity_decode($code, ENT_QUOTES | ENT_HTML5)) <= 8;
        $tracking = $isShortCode ? '0.28em' : '0.04em';
        $size = $isShortCode ? '28px' : '18px';

        return <<<HTML
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:20px 0;max-width:100%;table-layout:fixed;">
  <tr>
    <td class="email-code" align="center" style="padding:18px 16px;border-radius:10px;background:#F0F5F4;border:1px solid #d8e0de;overflow:hidden;word-break:break-word;">
      <span class="email-code-value" style="display:inline-block;max-width:100%;box-sizing:border-box;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:{$size};font-weight:700;letter-spacing:{$tracking};line-height:1.35;color:#0B6E6E;overflow-wrap:anywhere;word-break:break-word;">{$safe}</span>
    </td>
  </tr>
</table>
HTML;
    }

    public static function muted(string $html): string
    {
        return '<p class="email-muted" style="margin:16px 0 0;font-size:13px;line-height:1.55;color:#66706c;">'.$html.'</p>';
    }

    public static function paragraph(string $html): string
    {
        return '<p class="email-text" style="margin:0 0 14px;font-size:16px;line-height:1.65;color:#14201E;">'.$html.'</p>';
    }

    public static function heading(string $html): string
    {
        return '<h1 class="email-heading" style="margin:0 0 16px;font-size:22px;line-height:1.3;font-weight:700;letter-spacing:-0.02em;color:#14201E;">'.$html.'</h1>';
    }

    /**
     * Full document wrapper with light + dark theme support.
     *
     * Platform emails may include the Authzio logo. Organization / client-facing
     * emails use a text brand header only — never the Authzio logo.
     */
    public static function wrap(
        string $bodyHtml,
        ?string $brand = null,
        bool $includeLogo = true,
        string $locale = 'en',
    ): string {
        $brand ??= self::productName();
        $safeBrand = e($brand);
        $safeLocale = e($locale !== '' ? $locale : 'en');
        $year = date('Y');
        $product = e(self::productName());
        $footerBrand = $includeLogo ? $product : $safeBrand;
        $homeUrl = e(rtrim((string) config('app.url'), '/'));

        $header = $includeLogo
            ? self::logoHeader($homeUrl, $product)
            : self::textBrandHeader($safeBrand);

        $logoStyles = $includeLogo ? <<<'CSS'
  .logo-dark { display: none !important; max-height: 0 !important; overflow: hidden !important; width: 0 !important; height: 0 !important; }
  @media (prefers-color-scheme: dark) {
    .logo-light { display: none !important; max-height: 0 !important; overflow: hidden !important; width: 0 !important; height: 0 !important; }
    .logo-dark { display: inline-block !important; max-height: none !important; overflow: visible !important; width: auto !important; height: 32px !important; }
  }
CSS
            : <<<'CSS'
  @media (prefers-color-scheme: dark) {
    .email-brand { color: #e8eeec !important; }
  }
CSS;

        return <<<HTML
<!DOCTYPE html>
<html lang="{$safeLocale}" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title>{$safeBrand}</title>
<style>
  :root { color-scheme: light dark; }
  body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
  img { border: 0; line-height: 100%; outline: none; text-decoration: none; }
  {$logoStyles}
  @media (prefers-color-scheme: dark) {
    .email-bg { background: #0c1211 !important; }
    .email-card { background: #151c1a !important; border-color: #2a3532 !important; }
    .email-header { border-color: #2a3532 !important; }
    .email-footer { border-color: #2a3532 !important; color: #8b9a95 !important; }
    .email-heading, .email-text { color: #e8eeec !important; }
    .email-muted { color: #8b9a95 !important; }
    .email-code { background: #1c2623 !important; border-color: #2a3532 !important; }
    .email-code span { color: #5ecfcf !important; }
    .email-btn { background: #0d8a8a !important; }
    .email-text a { color: #5ecfcf !important; }
  }
</style>
<!--[if mso]>
<style type="text/css">
  body, table, td { font-family: Arial, Helvetica, sans-serif !important; }
</style>
<![endif]-->
</head>
<body class="email-bg" style="margin:0;padding:0;background:#F4F7F6;color:#14201E;font-family:'Source Sans 3',Segoe UI,Helvetica,Arial,sans-serif;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{$safeBrand}</div>
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" class="email-bg" style="background:#F4F7F6;padding:32px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" class="email-card" style="max-width:560px;background:#ffffff;border:1px solid #d8e0de;border-radius:12px;overflow:hidden;">
          <tr>
            <td class="email-header" style="padding:24px 28px 18px;border-bottom:1px solid #eef2f1;">
              {$header}
            </td>
          </tr>
          <tr>
            <td style="padding:28px;font-size:16px;line-height:1.65;">
              {$bodyHtml}
            </td>
          </tr>
          <tr>
            <td class="email-footer" style="padding:16px 28px 24px;font-size:12px;line-height:1.5;color:#66706c;border-top:1px solid #eef2f1;">
              © {$year} {$footerBrand}
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    private static function logoHeader(string $homeUrl, string $product): string
    {
        $logoLight = e(self::logoUrl(false));
        $logoDark = e(self::logoUrl(true));

        return <<<HTML
              <a href="{$homeUrl}" style="text-decoration:none;">
                <img class="logo-light" src="{$logoLight}" width="140" height="32" alt="{$product}" style="display:block;height:32px;width:auto;max-width:160px;">
                <!--[if !mso]><!-->
                <img class="logo-dark" src="{$logoDark}" width="140" height="32" alt="{$product}" style="display:none;height:32px;width:auto;max-width:160px;">
                <!--<![endif]-->
              </a>
HTML;
    }

    private static function textBrandHeader(string $safeBrand): string
    {
        return <<<HTML
              <span class="email-brand" style="display:inline-block;font-size:18px;font-weight:700;letter-spacing:-0.02em;color:#14201E;">{$safeBrand}</span>
HTML;
    }
}
