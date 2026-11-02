{{--
    Table-based, inline-styled email markup on purpose: Gmail/Outlook/
    mobile mail clients strip <style> blocks and modern CSS (flex/grid/
    custom properties) unpredictably, so every rule that matters here is
    either an inline style= attribute or a plain table/cellpadding
    layout — the one combination every major client renders consistently.
    This view is rendered directly as the Mailable's full HTML body (see
    EmailVerificationCodeMail::content(), a plain `view:`, not
    `markdown:`), so it owns the entire <html> document itself.
--}}
<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>Verifica tu correo — Fiscora</title>
<!--[if mso]>
<noscript>
<xml>
<o:OfficeDocumentSettings>
<o:PixelsPerInch>96</o:PixelsPerInch>
</o:OfficeDocumentSettings>
</xml>
</noscript>
<![endif]-->
</head>
<body style="margin:0; padding:0; background-color:#ece8dc; font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#ece8dc;">
<tr>
<td align="center" style="padding:32px 16px;">

<table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="width:560px; max-width:560px; background-color:#ffffff; border-radius:12px; overflow:hidden;">

{{-- Gold hairline, same accent the app's brand panel/sidebar use --}}
<tr>
<td style="background-color:#c79a46; height:3px; line-height:3px; font-size:0;">&nbsp;</td>
</tr>

{{-- Navy header: logo + wordmark --}}
<tr>
<td style="background-color:#040c1f; padding:28px 32px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td style="width:48px; vertical-align:middle;">
<img src="{{ asset('branding/fiscora-icon-transparent.png') }}" width="40" height="40" alt="Fiscora" style="display:block; width:40px; height:40px; border:0;">
</td>
<td style="vertical-align:middle; padding-left:12px;">
<p style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:18px; font-weight:bold; letter-spacing:1px; color:#ffffff;">FISCORA</p>
<p style="margin:2px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:11px; letter-spacing:1px; text-transform:uppercase; color:#c79a46;">Plataforma de Facturación</p>
</td>
</tr>
</table>
</td>
</tr>

{{-- Body --}}
<tr>
<td style="background-color:#ffffff; padding:36px 32px 28px;">
<h1 style="margin:0 0 16px; font-family:Arial,Helvetica,sans-serif; font-size:22px; line-height:28px; font-weight:bold; color:#0b1c30;">Verifica tu correo</h1>

<p style="margin:0 0 16px; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:22px; color:#0b1c30;">Hola {{ $recipientName }},</p>

<p style="margin:0 0 24px; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:22px; color:#444651;">
Para confirmar tu correo y continuar con la configuración de tu cuenta en Fiscora, usa el siguiente código de verificación:
</p>

{{-- OTP code --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
<tr>
<td align="center" style="background-color:#f3f1ea; border:1.5px solid #d8d3c6; border-radius:10px; padding:20px 0;">
<span style="font-family:'Courier New',Courier,monospace; font-size:34px; font-weight:bold; letter-spacing:10px; color:#00236f;">{{ $code }}</span>
</td>
</tr>
</table>

<p style="margin:0 0 20px; font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:20px; color:#444651;">
Este código vence en 10 minutos.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:4px;">
<tr>
<td style="background-color:#fdf6b2; border-radius:8px; padding:14px 16px; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:19px; color:#723b13;">
Si no creaste una cuenta en Fiscora, puedes ignorar este mensaje: no se realizará ninguna acción adicional.
</td>
</tr>
</table>
</td>
</tr>

{{-- Footer --}}
<tr>
<td style="background-color:#f6f4ee; padding:20px 32px; border-top:1px solid #ece8dc;">
<p style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:12px; line-height:18px; color:#757682;">
Fiscora — Prototipo académico de facturación electrónica. Este mensaje y los documentos que genera la plataforma no tienen validez tributaria real ante la DIAN.
</p>
</td>
</tr>

</table>

</td>
</tr>
</table>
</body>
</html>
