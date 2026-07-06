<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sub Community Lead Tags</title>

    <style>
        @page {
            size: A4;
            margin: 8mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            margin: 0;
            color: #222;
        }

        .passes-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 8px;
            table-layout: fixed;
        }

        .passes-table td {
            width: 50%;
            vertical-align: top;
        }

        .ticket {
            border-radius: 10px;
            padding: 12px;
            height: 460px;
            box-sizing: border-box;
            page-break-inside: avoid;
            text-align: center;
            border: 1px solid rgba(0,0,0,0.08);
        }

        .event-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
            line-height: 1.3;
        }

        .event-meta {
            font-size: 10px;
            margin-bottom: 10px;
            line-height: 1.5;
        }

        .front-box {
            width: 280px;
            height: 320px;
            margin: 0 auto 10px auto;
            box-sizing: border-box;
            border-radius: 10px;
            padding: 14px;
            text-align: center;
        }

        .front-box-inner {
            width: 100%;
            height: 100%;
            border-radius: 10px;
            box-sizing: border-box;
            padding: 16px 14px;
        }

        .front-logo-wrap {
            margin: 0 auto 12px auto;
            text-align: center;
        }

        .front-logo-wrap img {
            max-width: 140px;
            max-height: 140px;
            display: inline-block;
        }

        .front-main-title {
            font-size: 22px;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.2;
            margin-bottom: 16px;
        }

        .front-subtitle {
            font-size: 11px;
            line-height: 1.45;
            margin-bottom: 14px;
        }

        .name-write-box {
            background: #ffffff;
            color: #000000;
            padding: 28px 20px;
            border-radius: 8px;
            font-size: 11px;
            margin-bottom: 16px;
            border: 1px solid #e5e5e5;
            min-height: 60px;
            text-align: center;
            font-style: italic;
            color: #888;
        }

        .front-footer {
            font-size: 9px;
            line-height: 1.4;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .color-label {
            margin-top: 8px;
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>

@php
    // Define the 8 colors
    $colors = [
        ['name' => 'Red', 'bg' => '#EF4444', 'text' => '#FFFFFF'],
        ['name' => 'Purple', 'bg' => '#8B5CF6', 'text' => '#FFFFFF'],
        ['name' => 'Green', 'bg' => '#22C55E', 'text' => '#FFFFFF'],
        ['name' => 'Blue', 'bg' => '#3B82F6', 'text' => '#FFFFFF'],
        ['name' => 'Yellow', 'bg' => '#FACC15', 'text' => '#000000'],
        ['name' => 'Pink', 'bg' => '#EC4899', 'text' => '#FFFFFF'],
        ['name' => 'Orange', 'bg' => '#F59E0B', 'text' => '#000000'],
        ['name' => 'Brown', 'bg' => '#964B00', 'text' => '#FFFFFF'],
    ];

    $logoPath = public_path('storage/images/wima-base.png');
    
    // Create 3 sub community leads per color
    $allTags = [];
    foreach ($colors as $color) {
        for ($i = 1; $i <= 3; $i++) {
            $allTags[] = $color;
        }
    }
@endphp

@foreach($allTags as $tagIndex => $color)
    <table class="passes-table">
        <tr>
            <!-- LEFT SIDE: FRONT -->
            <td>
                <div class="ticket" style="background-color: {{ $color['bg'] }}; color: {{ $color['text'] }};">
                    
                    <div class="event-title">
                        {{ $event->title ?? 'EVENT TITLE' }}
                    </div>

                    <div class="event-meta">
                        {{ $event->startDate ?? 'START DATE' }} - {{ $event->endDate ?? 'END DATE' }}<br>
                    </div>

                    <div class="front-box">
                        <div class="front-box-inner">

                            <div class="front-logo-wrap">
                                <img src="{{ $logoPath }}">
                            </div>

                            <div class="front-main-title">
                                Sub Community Lead
                            </div>

                            <!-- <div class="name-write-box">
                                (Write participant name here)
                            </div> -->

                            <div class="color-label">
                                {{ $color['name'] }} Team
                            </div>

                            <!-- <div class="front-footer">
                                Valid for accredited participant use only
                            </div> -->

                        </div>
                    </div>

                </div>
            </td>

            <!-- RIGHT SIDE: BACK (Identical to Front) -->
            <td>
                <div class="ticket" style="background-color: {{ $color['bg'] }}; color: {{ $color['text'] }};">
                    
                    <div class="event-title">
                        {{ $event->title ?? 'EVENT TITLE' }}
                    </div>

                    <div class="event-meta">
                        {{ $event->startDate ?? 'START DATE' }} - {{ $event->endDate ?? 'END DATE' }}<br>
                    </div>

                    <div class="front-box">
                        <div class="front-box-inner">

                            <div class="front-logo-wrap">
                                <img src="{{ $logoPath }}">
                            </div>

                            <div class="front-main-title">
                                Sub Community Lead
                            </div>

                            <!-- <div class="name-write-box">
                                (Write participant name here)
                            </div> -->

                            <div class="color-label">
                                {{ $color['name'] }} Team
                            </div>

                            <!-- <div class="front-footer">
                                Valid for accredited participant use only
                            </div> -->

                        </div>
                    </div>

                </div>
            </td>
        </tr>
    </table>

    @if($tagIndex < count($allTags) - 1)
        <div class="page-break"></div>
    @endif
@endforeach

</body>
</html>