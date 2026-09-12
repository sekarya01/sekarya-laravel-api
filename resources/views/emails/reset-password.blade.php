<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tautan reset kata sandi Sekarya</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,'Segoe UI',Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="max-width:480px;background-color:#ffffff;border-radius:16px;overflow:hidden;">
                <tr>
                    <td align="center" style="background-color:#ffffff;padding:28px 24px 8px;">
                        <img src="{{ $message->embed(public_path('images/logo-sekarya-wordmark.png')) }}"
                             alt="Sekarya" width="144"
                             style="display:block;width:144px;height:auto;border:0;">
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px 32px;color:#0f172a;font-size:15px;line-height:1.6;">
                        <p style="margin:0 0 12px;">Halo {{ $name }},</p>
                        <p style="margin:0 0 20px;">Kami menerima permintaan reset kata sandi untuk akun Anda. Tekan tombol di bawah untuk membuat kata sandi baru:</p>
                        <p align="center" style="margin:0 0 20px;">
                            <a href="{{ $url }}" style="display:inline-block;background-color:#f97316;color:#ffffff;text-decoration:none;font-weight:bold;font-size:15px;border-radius:12px;padding:14px 36px;">Buat Kata Sandi Baru</a>
                        </p>
                        <p style="margin:0 0 8px;color:#475569;">Tautan hanya bisa dipakai <strong>satu kali</strong> dan berlaku {{ $ttlMinutes }} menit.</p>
                        <p style="margin:0;color:#94a3b8;font-size:13px;">Jika Anda tidak merasa meminta reset, abaikan email ini — kata sandi Anda tidak berubah.</p>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:0 32px 24px;color:#94a3b8;font-size:12px;">
                        Bantuan lokal, kelar hari ini.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
