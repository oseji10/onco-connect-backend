<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Meal Tickets</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            margin: 15px;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
        }

        .header h1 {
            margin: 0;
            font-size: 20px;
        }

        .header p {
            margin: 4px 0;
            font-size: 12px;
            color: #555;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        td {
            vertical-align: top;
            width: 33.333%;
            padding: 6px;
        }

        .ticket {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 10px;
            text-align: center;
            height: 240px;
            box-sizing: border-box;
        }

        .meal-title {
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .meal-meta {
            font-size: 10px;
            color: #666;
            margin-bottom: 8px;
        }

        .qr {
            margin: 8px 0;
        }

        .qr img {
            width: 100px;
            height: 100px;
            object-fit: contain;
        }

        .serial {
            font-weight: bold;
            font-size: 11px;
            margin-top: 6px;
        }
    </style>
</head>

<body>

<div class="header">
    <h1>{{ $meal->title }}</h1>
    <p>
        {{ $meal->mealDate }}
        |
        {{ \Carbon\Carbon::parse($meal->startTime)->format('h:i A') }}
        -
        {{ \Carbon\Carbon::parse($meal->endTime)->format('h:i A') }}
    </p>
</div>

@php
    $rows = $tickets->chunk(3); // 3 per row
@endphp

@php
    $rows = $tickets->chunk(3);
    $counter = 1; // start numbering
@endphp

<table>
    <tbody>
        @foreach($rows as $row)
            <tr>
                @foreach($row as $ticket)
                    @php
                        $qrFile = public_path('storage/' . $ticket->qrPath);
                    @endphp

                    <td>
                        <div class="ticket">

                            <div class="serial">
                                #{{ $counter++ }}
                            </div>

                            <div class="meal-title">
                                {{ $meal->title }}
                            </div>

                            <div class="meal-meta">
                                {{ $meal->mealDate }} <br>
                                {{ \Carbon\Carbon::parse($meal->startTime)->format('h:i A') }}
                                -
                                {{ \Carbon\Carbon::parse($meal->endTime)->format('h:i A') }}
                            </div>

                            <div class="qr">
                                @if(!empty($ticket->qrPath) && file_exists($qrFile))
                                    <img src="{{ $qrFile }}" alt="QR Code">
                                @else
                                    <div style="font-size: 10px; color: red;">QR not found</div>
                                @endif
                            </div>

                        </div>
                    </td>
                @endforeach

                @for($i = $row->count(); $i < 3; $i++)
                    <td></td>
                @endfor
            </tr>
        @endforeach
    </tbody>
</table>

</body>
</html>