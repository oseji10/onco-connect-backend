{{-- Save as resources/views/pdf/badge-sheet.blade.php
     10 passes per A4 page (2 columns x 5 rows), black and white only, to save ink.
     Every pass is placed with absolute positions (no table layout), so a long name
     can never stretch a row and push passes onto extra pages. --}}
@php
    // Collect every pass from whatever the controller sends (any nesting of
    // collections / arrays), then re-flow them into pages of 10.
    $flat = [];
    $walk = function ($node) use (&$walk, &$flat) {
        if ($node instanceof \Illuminate\Support\Collection) {
            $node = $node->all();
        }
        if (!is_array($node)) {
            return;
        }
        if (array_key_exists('uniqueId', $node) || array_key_exists('name', $node)) {
            $flat[] = $node;           // a single pass
            return;
        }
        foreach ($node as $child) {
            $walk($child);
        }
    };
    $walk($sheets);
    $pages = array_chunk($flat, 10);
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page { margin: 8mm 5mm; }
  body { margin: 0; font-family: "DejaVu Sans", sans-serif; color: #000000; }

  .page { position: relative; width: 198mm; height: 280mm; }
  .page.break { page-break-after: always; }

  /* one pass = 99 x 56 mm (border included) */
  .pass { position: absolute; width: 98.6mm; height: 55.6mm; border: 0.2mm dashed #888888; overflow: hidden; }

  .head   { position: absolute; left: 0; top: 0; width: 98.6mm; height: 6.2mm; line-height: 6.2mm; text-align: center; border-bottom: 0.3mm solid #000000; font-size: 6pt; font-weight: bold; letter-spacing: 0.3mm; text-transform: uppercase; }
  .text   { position: absolute; left: 5mm; top: 9mm; width: 54mm; height: 30mm; overflow: hidden; }
  .qr     { position: absolute; left: 64mm; top: 8.5mm; width: 30mm; height: 30mm; }
  .qr img { width: 30mm; height: 30mm; }
  .rule   { position: absolute; left: 0; top: 40.5mm; width: 98.6mm; border-top: 0.3mm solid #000000; height: 0; }
  .idbox  { position: absolute; left: 5mm; top: 43mm; width: 54mm; }
  .label  { position: absolute; left: 61mm; top: 41mm; width: 35mm; height: 14mm; line-height: 14mm; text-align: center; font-size: 10pt; font-weight: bold; letter-spacing: 0.4mm; text-transform: uppercase; }

  .name   { font-weight: bold; line-height: 1.15; }
  .org    { margin-top: 1.5mm; font-size: 8pt; font-weight: bold; line-height: 1.2; text-transform: uppercase; }
  .id     { font-size: 8pt; font-weight: bold; font-family: "DejaVu Sans Mono", monospace; }
  .serial { font-size: 6pt; color: #333333; margin-top: 0.5mm; }
</style>
</head>
<body>
@foreach($pages as $page)
  <div class="page {{ $loop->last ? '' : 'break' }}">
    @foreach($page as $i => $b)
      @php
          $col = $i % 2;
          $row = intdiv($i, 2);

          $len = mb_strlen($b['name'] ?? '');
          $nameSize = $len <= 16 ? 15 : ($len <= 26 ? 12.5 : ($len <= 38 ? 11 : 9.5));

          $isGuest = preg_match('/-G\d+$/', (string) ($b['uniqueId'] ?? '')) === 1;
          $label = $isGuest ? 'VIP GUEST' : ($b['label'] ?? '');
      @endphp
      <div class="pass" style="left: {{ $col * 99 }}mm; top: {{ $row * 56 }}mm;">
        <div class="head">{{ $eventName }}</div>

        <div class="text">
          <div class="name" style="font-size: {{ $nameSize }}pt;">{{ $b['name'] ?? '' }}</div>
          @if(!empty($b['org']))<div class="org">{{ $b['org'] }}</div>@endif
        </div>

        <div class="qr">
          @if(!empty($b['qr']))
            <img src="{{ $b['qr'] }}">
          @else
            <div class="serial">QR unavailable</div>
          @endif
        </div>

        <div class="rule"></div>

        <div class="idbox">
          <div class="id">{{ $b['uniqueId'] ?? '' }}</div>
          @if(!empty($b['serial']))<div class="serial">Pass {{ $b['serial'] }}</div>@endif
        </div>

        <div class="label">{{ $label }}</div>
      </div>
    @endforeach
  </div>
@endforeach
</body>
</html>