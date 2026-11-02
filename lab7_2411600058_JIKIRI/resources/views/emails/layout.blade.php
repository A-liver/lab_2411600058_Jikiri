<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
</head>
<body style="margin:0;padding:0;background:#FEFAE0;font-family:'Segoe UI',Tahoma,Arial,sans-serif;color:#283618;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#FEFAE0;padding:24px 0;">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;">
                <tr>
                    <td style="background:#606C38;padding:20px 28px;color:#ffffff;font-size:20px;font-weight:bold;">
                        {{ config('app.name') }}
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px;font-size:15px;line-height:1.5;">
                        @yield('content')
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 28px;background:#f5f1d8;font-size:12px;color:#606C38;">
                        This is an automated message from {{ config('app.name') }}.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>