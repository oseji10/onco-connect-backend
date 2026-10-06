{{-- Save as resources/views/pdf/badge-sheet.blade.php --}}
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page { margin: 10mm 5mm; }
  body { margin: 0; font-family: "DejaVu Sans", sans-serif; color: #111827; }

  table.sheet { width: 198mm; border-collapse: collapse; table-layout: fixed; }
  table.sheet.break { page-break-after: always; }

  /* 2 columns x 2 rows of 99 x 135 mm portrait badges = one A4 page */
  td.cell { width: 99mm; height: 135mm; padding: 0; border: 0.25mm dotted #9ca3af; vertical-align: top; }

  /* Row heights add up to 135 mm: 14 + 32 + 14 + 47 + 14 + 14 */
  table.badge { width: 99mm; height: 135mm; border-collapse: collapse; }
  td.head { height: 14mm; padding: 0 5mm; text-align: center; vertical-align: middle; color: #ffffff; font-size: 7.5pt; font-weight: bold; letter-spacing: 0.4mm; text-transform: uppercase; }
  td.nm   { height: 32mm; padding: 0 5mm; text-align: center; vertical-align: middle; }
  td.og   { height: 14mm; padding: 0 5mm; text-align: center; vertical-align: top; }
  td.qr   { height: 47mm; text-align: center; vertical-align: middle; }
  td.qr img { width: 41mm; height: 41mm; }
  td.idc  { height: 14mm; text-align: center; vertical-align: top; }
  td.foot { height: 14mm; text-align: center; vertical-align: middle; color: #ffffff; font-weight: bold; font-size: 11pt; letter-spacing: 0.7mm; text-transform: uppercase; }

  .name   { font-weight: bold; line-height: 1.15; }
  .org    { font-size: 11pt; font-weight: bold; line-height: 1.2; text-transform: uppercase; }
  .id     { font-size: 10.5pt; font-weight: bold; font-family: "DejaVu Sans Mono", monospace; }
  .serial { font-size: 6.5pt; color: #6b7280; margin-top: 0.8mm; }
</style>
</head>
<body>
@foreach($sheets as $sheet)
  <table class="sheet {{ $loop->last ? '' : 'break' }}">
    @for($r = 0; $r < 2; $r++)
      <tr>
        @for($c = 0; $c < 2; $c++)
          @php $b = $sheet->get($r * 2 + $c); @endphp
          <td class="cell">
            @if($b)
              <table class="badge">
                <tr><td class="head" style="background: {{ $b['color'] }};">{{ $eventName }}</td></tr>
                <tr><td class="nm"><div class="name" style="font-size: {{ $b['size'] }}pt;">{{ $b['name'] }}</div></td></tr>
                <tr><td class="og">@if($b['org'])<div class="org">{{ $b['org'] }}</div>@endif</td></tr>
                <tr>
                  <td class="qr">
                    @if($b['qr'])
                      <img src="{{ $b['qr'] }}">
                    @else
                      <div class="serial">QR unavailable</div>
                    @endif
                  </td>
                </tr>
                <tr>
                  <td class="idc">
                    <div class="id">{{ $b['uniqueId'] }}</div>
                    @if($b['serial'])<div class="serial">Pass {{ $b['serial'] }}</div>@endif
                  </td>
                </tr>
                <tr><td class="foot" style="background: {{ $b['color'] }};">{{ $b['label'] }}</td></tr>
              </table>
            @endif
          </td>
        @endfor
      </tr>
    @endfor
  </table>
@endforeach
</body>
</html>