{{--
    Kerangka email HTML: sederhana, tanpa gambar, gaya inline (banyak aplikasi email mengabaikan <style>).
    Warna dari token desain: ink #1C1F1E, ink-soft #4A504D, line #E3E5E1, canvas #F6F6F3, brand #1F5A47.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#F6F6F3;font-family:Arial,Helvetica,sans-serif;color:#1C1F1E;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F6F6F3;">
        <tr>
            <td align="center" style="padding:24px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;">
                    <tr>
                        <td style="padding:0 0 16px 0;font-size:18px;font-weight:bold;color:#1F5A47;">
                            SIM-KER <span style="font-weight:normal;color:#4A504D;font-size:14px;">Distrik Ransiki</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#FFFFFF;border:1px solid #E3E5E1;border-radius:10px;padding:24px;font-size:15px;line-height:1.6;">
                            {{ $slot }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 0 0 0;font-size:13px;line-height:1.5;color:#4A504D;">
                            Email ini dikirim otomatis oleh SIM-KER, Sistem Informasi dan Monitoring Kelembagaan Ekonomi Distrik Ransiki. Jangan membalas email ini.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
