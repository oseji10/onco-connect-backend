@php
    /*
    |--------------------------------------------------------------------------
    | Certificate Background
    |--------------------------------------------------------------------------
    | Each certificate type has its own approved background/design.
    |
    | Files:
    |   storage/app/public/images/certificate-attendance.jpg
    |   storage/app/public/images/certificate-oral.jpg
    |   storage/app/public/images/certificate-poster.jpg
    |
    | The CertificateService passes $backgroundPath according to the
    | certificate type.
    |--------------------------------------------------------------------------
    */

    $backgroundFile = $backgroundPath ?? null;

    if ($backgroundFile && file_exists($backgroundFile)) {
        $extension = strtolower(pathinfo($backgroundFile, PATHINFO_EXTENSION));

        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            default       => 'image/jpeg',
        };

        $bg = 'data:' . $mime . ';base64,' .
            base64_encode(file_get_contents($backgroundFile));
    } else {
        $bg = null;
    }

    // ── Fonts ─────────────────────────────────────────────────────────────

    $titleTtf = storage_path('fonts/certificate-title.ttf');
    $nameTtf  = storage_path('fonts/certificate-name.ttf');

    $hasTitleFont = file_exists($titleTtf);
    $hasNameFont  = file_exists($nameTtf);

    // ── Text fitting ──────────────────────────────────────────────────────

    $displayName = mb_convert_case(
        mb_strtolower(trim($fullName)),
        MB_CASE_TITLE,
        'UTF-8'
    );

    $nameLen  = mb_strlen($displayName);
    $nameSize = $nameLen > 28
        ? max(16, round(30 * 28 / $nameLen, 1))
        : 30;

    $titleLen  = mb_strlen($typeLabel);
    $titleSize = $titleLen > 26
        ? max(24, round(40 * 26 / $titleLen, 1))
        : 40;
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <style>
        @page {
            margin: 0;
        }

        @if($hasTitleFont)
        @font-face {
            font-family: 'CertTitle';
            font-style: normal;
            font-weight: normal;
            src: url('{{ $titleTtf }}') format('truetype');
        }
        @endif

        @if($hasNameFont)
        @font-face {
            font-family: 'CertName';
            font-style: normal;
            font-weight: normal;
            src: url('{{ $nameTtf }}') format('truetype');
        }
        @endif

        html,
        body {
            margin: 0;
            padding: 0;
        }

        .page {
            position: relative;
            width: 841pt;
            height: 594pt;
            overflow: hidden;
        }

        .bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 841pt;
            height: 594pt;
        }

        .line {
            position: absolute;
            left: 0;
            width: 841pt;
            text-align: center;
            white-space: nowrap;
        }

        /*
        |--------------------------------------------------------------------------
        | Certificate Title
        |--------------------------------------------------------------------------
        */

        .title {
            top: 127pt;

            font-family:
                @if($hasTitleFont)
                    'CertTitle',
                @endif
                Helvetica,
                Arial,
                sans-serif;

            font-size: {{ $titleSize }}pt;
            line-height: 48pt;
            color: #ed114e;
        }

        /*
        |--------------------------------------------------------------------------
        | Recipient Name
        |--------------------------------------------------------------------------
        */

        .name {
            top: 230pt;

            font-family:
                @if($hasNameFont)
                    'CertName',
                @else
                    'Times New Roman',
                @endif
                serif;

            @if(!$hasNameFont)
                font-style: italic;
            @endif

            font-size: {{ $nameSize }}pt;
            line-height: 36pt;
            color: #111111;
        }

        /*
        |--------------------------------------------------------------------------
        | Action Text
        |--------------------------------------------------------------------------
        */

        .action {
            top: 271pt;

            font-family:
                Helvetica,
                Arial,
                sans-serif;

            font-size: 11.5pt;
            line-height: 14pt;
            color: #ed114e;
        }
    </style>
</head>

<body>

<div class="page">

    @if($bg)
        <img
            class="bg"
            src="{{ $bg }}"
            alt=""
        >
    @endif

    <div class="line title">
        <!-- {{ $typeLabel }} -->
    </div>

    <div class="line name">
        {{ $displayName }}
    </div>

    <div class="line action">
        <!-- @yield('action') -->
    </div>

</div>

</body>
</html>