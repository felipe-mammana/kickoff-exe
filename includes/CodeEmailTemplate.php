<?php
declare(strict_types=1);

final class CodeEmailTemplate
{
    public static function render(string $title, string $code, string $description, string $validity): string
    {
        $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $title = $escape($title);
        $code = $escape($code);
        $description = $escape($description);
        $validity = $escape($validity);
        return <<<HTML
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{$title}</title></head>
<body style="margin:0;padding:0;background:#eef2f6;color:#202b39;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f6;"><tr><td align="center" style="padding:28px 12px;">
<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="width:100%;max-width:560px;background:#ffffff;border:1px solid #dce3eb;border-radius:8px;">
<tr><td style="padding:28px 28px 24px;border-bottom:3px solid #0052a1;">
<img src="cid:exe-logo" width="188" alt="EXE Soluções Estratégicas em TI" style="display:block;width:188px;max-width:100%;height:auto;border:0;">
</td></tr>
<tr><td style="padding:28px;">
<p style="margin:0 0 12px;font-size:12px;font-weight:bold;color:#0052a1;">SEGURANÇA DA CONTA</p>
<h1 style="margin:0 0 16px;font-size:24px;line-height:32px;">{$title}</h1>
<p style="margin:0 0 24px;font-size:15px;line-height:24px;color:#526071;">{$description}</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 8px;background:#edf5ff;border:1px solid #cadff5;border-radius:6px;">
<span style="font-family:Consolas,'Courier New',monospace;font-size:32px;line-height:40px;font-weight:bold;color:#0052a1;">{$code}</span>
</td></tr></table>
<p style="margin:12px 0 26px;text-align:center;font-size:13px;line-height:20px;color:#526071;">{$validity}</p>
<p style="margin:0 0 8px;font-size:15px;line-height:22px;font-weight:bold;">Não compartilhe este código com ninguém.</p>
<p style="margin:0;font-size:14px;line-height:22px;color:#526071;">Nem mesmo com pessoas que se apresentem como suporte da EXE. Digite o código somente no sistema onde você iniciou a solicitação.</p>
<p style="margin:24px 0 0;padding-top:20px;border-top:1px solid #e4e9ef;font-size:13px;line-height:21px;color:#526071;">Não reconhece esta solicitação? Não use nem encaminhe o código. Se as mensagens continuarem, procure o administrador por um canal conhecido.</p>
</td></tr>
<tr><td style="padding:18px 28px;background:#f7f9fb;font-size:12px;line-height:20px;color:#647183;">EXE Kickoff · Mensagem automática de segurança</td></tr>
</table></td></tr></table></body></html>
HTML;
    }

    public static function logo(): string
    {
        return (string) file_get_contents(BASE_PATH . '/public/assets/brand/exe-logo-email.png');
    }
}
