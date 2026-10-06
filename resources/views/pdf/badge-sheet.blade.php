{{-- Save as resources/views/pdf/badge-sheet.blade.php
     8 passes per A4 page (2 columns x 4 rows), black and white only, to save ink. --}}
@php
    // Re-flow whatever the controller sends into pages of 8 passes, so the
    // controller does not need to change (it may still chunk by 4).
    $pages = collect($sheets)->flatMap(fn ($s) => $s)->values()->chunk(8)->map->values();
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page { margin: 8mm 5mm; }
  body { margin: 0; font-family: "DejaVu Sans", sans-serif; color: #000000; }

  table.sheet { width: 198mm; border-collapse: collapse; table-layout: fixed; }
  table.sheet.break { page-break-after: always; }

  /* 2 columns x 4 rows of 99 x 68 mm passes = one A4 page */
  td.cell { width: 99mm; height: 68mm; padding: 0; border: 0.2mm dashed #888888; vertical-align: top; }

  /* Row heights add up to 68 mm: 8 + 43 + 17 */
  table.badge { width: 99mm; height: 68mm; border-collapse: collapse; table-layout: fixed; }
  td.head { height: 8mm; padding: 0 4mm; text-align: center; vertical-align: middle; font-size: 6.5pt; font-weight: bold; letter-spacing: 0.3mm; text-transform: uppercase; border-bottom: 0.3mm solid #000000; }
  td.txt  { width: 59mm; height: 43mm; padding: 0 3mm 0 5mm; text-align: left; vertical-align: middle; }
  td.qr   { width: 40mm; height: 43mm; text-align: center; vertical-align: middle; }
  td.qr img { width: 35mm; height: 35mm; }
  td.idc  { width: 59mm; height: 17mm; padding: 0 3mm 0 5mm; text-align: left; vertical-align: middle; border-top: 0.3mm solid #000000; }
  td.lab  { width: 40mm; height: 17mm; text-align: center; vertical-align: middle; border-top: 0.3mm solid #000000; font-size: 11pt; font-weight: bold; letter-spacing: 0.5mm; text-transform: uppercase; }

  .name   { font-weight: bold; line-height: 1.15; }
  .org    { margin-top: 2mm; font-size: 8.5pt; font-weight: bold; line-height: 1.2; text-transform: uppercase; }
  .id     { font-size: 8.5pt; font-weight: bold; font-family: "DejaVu Sans Mono", monospace; }
  .serial { font-size: 6pt; color: #333333; margin-top: 0.6mm; }
</style>
</head>
<body>
@foreach($pages as $sheet)
  <table class="sheet {{ $loop->last ? '' : 'break' }}">
    @for($r = 0; $r < 4; $r++)
      <tr>
        @for($c = 0; $c < 2; $c++)
          @php
              $b = $sheet->get($r * 2 + $c);
              if ($b) {
                  $len = mb_strlen($b['name']);
                  $nameSize = $len <= 16 ? 17 : ($len <= 26 ? 14 : ($len <= 38 ? 12 : 10));
                  $isGuest = preg_match('/-G\d+$/', (string) ($b['uniqueId'] ?? '')) === 1;
                  $label = $isGuest ? 'VIP GUEST' : $b['label'];
              }
          @endphp
          <td class="cell">
            @if($b)
              <table class="badge">
                <tr><td class="head" colspan="2">{{ $eventName }}</td></tr>
                <tr>
                  <td class="txt">
                    <div class="name" style="font-size: {{ $nameSize }}pt;">{{ $b['name'] }}</div>
                    @if($b['org'])<div class="org">{{ $b['org'] }}</div>@endif
                  </td>
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
                  <td class="lab">{{ $label }}</td>
                </tr>
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