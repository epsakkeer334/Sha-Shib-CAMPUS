{{--
    Branded layout of every CAMP email (App\Services\EmailService::render). Table layout + inline styles so it
    looks the same in Gmail, Outlook and phone mail apps. $bodyHtml / $signatureHtml are already escaped.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:0;background:#EEF0F6;-webkit-text-size-adjust:100%;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $subject }}</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#EEF0F6;">
        <tr>
            <td align="center" style="padding:28px 12px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">

                    {{-- Header --}}
                    <tr>
                        <td style="background:#1D1C4C;border-radius:12px 12px 0 0;padding:22px 32px;border-bottom:4px solid #F26522;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <img src="{{ asset('brand/logo-white.png') }}" width="230" alt="{{ $siteName }}" style="display:block;width:230px;max-width:100%;height:auto;border:0;">
                                    </td>
                                    @if($institute)
                                        <td align="right" style="vertical-align:middle;padding-left:12px;color:#FBB866;font-size:12px;line-height:16px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;">
                                            {{ $institute->name }}
                                        </td>
                                    @endif
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="background:#FFFFFF;padding:32px 32px 12px;">
                            <h1 style="margin:0 0 20px;color:#1D1C4C;font-size:20px;line-height:28px;font-weight:700;">{{ $subject }}</h1>
                            {!! $bodyHtml !!}

                            @if($button)
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 20px;">
                                    <tr>
                                        <td style="border-radius:8px;background:#F26522;">
                                            <a href="{{ $button['url'] }}" target="_blank" style="display:inline-block;padding:12px 26px;color:#FFFFFF;font-size:15px;font-weight:600;text-decoration:none;border-radius:8px;">{{ $button['text'] }}</a>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            @if($signatureHtml)
                                <div style="margin-top:8px;padding-top:16px;border-top:1px solid #E6E7F0;">{!! $signatureHtml !!}</div>
                            @else
                                <p style="margin:8px 0 16px;color:#2F2E5A;font-size:15px;line-height:24px;">Regards,<br><strong style="color:#1D1C4C;">{{ $institute->name ?? $organisation }}</strong></p>
                            @endif
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background:#F7F8FC;border-radius:0 0 12px 12px;padding:18px 32px 22px;border-top:1px solid #E6E7F0;color:#6B6E8F;font-size:12px;line-height:18px;">
                            @if($institute)
                                <strong style="color:#1D1C4C;">{{ $institute->name }}</strong>
                                @if($institute->address)<br>{{ $institute->address }}@if($institute->city), {{ $institute->city }}@endif @endif
                                @if($institute->email || $institute->phone)<br>{{ collect([$institute->email, $institute->phone])->filter()->implode(' · ') }}@endif
                            @else
                                <strong style="color:#1D1C4C;">{{ $organisation }}</strong>
                                @if($supportEmail)<br>{{ $supportEmail }}@endif
                            @endif
                            <br><br>
                            {{ $footerText ?: 'This email was sent by ' . $siteName . '.' }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
