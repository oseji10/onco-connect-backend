@php
    // ── Assets ────────────────────────────────────────────────────────────
    // Background = the approved design (frame, logos, ICW block, signatures,
    // seal) with title / name / wording removed. Put the file at
    // public/images/certificate-bg.jpg
   $bg = 'data:image/jpeg;base64,' . base64_encode(file_get_contents(storage_path('app/public/images/certificate-bg.jpg')));

    // Optional exact fonts. Drop TTFs here and they are picked up automatically:
    //   storage/fonts/certificate-title.ttf   (title font)
    //   storage/fonts/certificate-name.ttf    (name script font, Monotype Corsiva in the original)
    $titleTtf = storage_path('fonts/certificate-title.ttf');
    $nameTtf  = storage_path('fonts/certificate-name.ttf');
    $hasTitleFont = file_exists($titleTtf);
    $hasNameFont  = file_exists($nameTtf);

    // ── Text fitting ──────────────────────────────────────────────────────
    $displayName = mb_convert_case(mb_strtolower(trim($fullName)), MB_CASE_TITLE, 'UTF-8');
    $nameLen     = mb_strlen($displayName);
    $nameSize    = $nameLen > 28 ? max(16, round(30 * 28 / $nameLen, 1)) : 30;

    $titleLen    = mb_strlen($typeLabel);
    $titleSize   = $titleLen > 26 ? max(24, round(40 * 26 / $titleLen, 1)) : 40;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }

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

        html, body { margin: 0; padding: 0; }

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

        .title {
            top: 127pt;
            font-family: @if($hasTitleFont) 'CertTitle', @endif Helvetica, Arial, sans-serif;
            font-size: {{ $titleSize }}pt;
            line-height: 48pt;
            color: #ed114e;
        }

        .name {
            top: 230pt;
            font-family: @if($hasNameFont) 'CertName', @endif 'Times New Roman', serif;
            @if(!$hasNameFont) font-style: italic; @endif
            font-size: {{ $nameSize }}pt;
            line-height: 36pt;
            color: #111111;
        }

        .action {
            top: 271pt;
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11.5pt;
            line-height: 14pt;
            color: #ed114e;
        }
    </style>
</head>
<body>
    <div class="page">
        <img class="bg" src="{{ $bg }}">

        <div class="line title">{{ $typeLabel }}</div>
        <div class="line name">{{ $displayName }}</div>
        <div class="line action">@yield('action')</div>
    </div>
</body>
</html>