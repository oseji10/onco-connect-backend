<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }

        body {
            margin: 0;
            font-family: 'DejaVu Serif', Georgia, serif;
            color: #1f2937;
        }

        .sheet { padding: 22px; }

        .frame {
            position: relative;
            border: 6px solid #047857;
            padding: 10px;
            height: 530px;
        }

        .inner {
            border: 1px solid #10b981;
            height: 100%;
            padding: 34px 56px;
            box-sizing: border-box;
            text-align: center;
        }

        .eyebrow {
            letter-spacing: 6px;
            font-size: 12px;
            color: #047857;
            text-transform: uppercase;
        }

        .title {
            font-size: 34px;
            font-weight: bold;
            color: #065f46;
            margin: 6px 0 20px;
            text-transform: uppercase;
        }

        .subtitle { font-size: 13px; color: #6b7280; }

        .name {
            font-size: 40px;
            color: #111827;
            margin: 14px 0 6px;
        }

        .rule {
            width: 340px;
            border-bottom: 1px solid #d1d5db;
            margin: 0 auto 18px;
        }

        .body {
            font-size: 15px;
            color: #374151;
            line-height: 1.6;
            width: 660px;
            margin: 0 auto;
        }

        .event { font-weight: bold; color: #065f46; }

        .signatures {
            width: 100%;
            margin-top: 54px;
            border-collapse: collapse;
        }

        .sig {
            width: 33%;
            text-align: center;
            vertical-align: bottom;
            font-size: 12px;
            color: #6b7280;
        }

        .sig-line {
            border-top: 1px solid #9ca3af;
            margin: 0 28px 6px;
        }

        .meta {
            position: absolute;
            bottom: 14px;
            left: 0;
            right: 0;
            font-size: 10px;
            color: #9ca3af;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="frame">
            <div class="inner">
                <div class="eyebrow">{{ $eventName }}</div>
                <div class="title">{{ $typeLabel }}</div>

                <div class="subtitle">This certificate is proudly presented to</div>
                <div class="name">{{ $fullName }}</div>
                <div class="rule"></div>

                <div class="body">
                    in recognition of having {{ $bodyText }}
                    <span class="event">{{ $eventName }}</span>.
                </div>

                <table class="signatures">
                    <tr>
                        <td class="sig">
                            <div class="sig-line"></div>
                            Date<br>{{ $issuedDate }}
                        </td>
                        <td class="sig">&nbsp;</td>
                        <td class="sig">
                            <div class="sig-line"></div>
                            Authorised Signature
                        </td>
                    </tr>
                </table>

                <div class="meta">Certificate No: {{ $certificateNumber }}</div>
            </div>
        </div>
    </div>
</body>
</html>