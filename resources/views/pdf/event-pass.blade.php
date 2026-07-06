<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    width: 255pt;
    height: 405pt;
    font-family: 'DejaVu Sans', sans-serif;
    background: #ffffff;
    color: #1a1a2e;
  }

  .pass {
    width: 255pt;
    height: 405pt;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1pt solid #dee2e6;
  }

  /* ── Header band ── */
  .pass-header {
    background: #0d3b6e;
    padding: 14pt 12pt 10pt;
    text-align: center;
  }

  .pass-header .conference-label {
    font-size: 6pt;
    letter-spacing: 2pt;
    color: #90caf9;
    text-transform: uppercase;
    margin-bottom: 4pt;
  }

  .pass-header .event-name {
    font-size: 11pt;
    font-weight: bold;
    color: #ffffff;
    line-height: 1.3;
  }

  .pass-header .event-meta {
    font-size: 6.5pt;
    color: #bbdefb;
    margin-top: 5pt;
  }

  /* ── Category ribbon ── */
  .category-ribbon {
    background: #e63946;
    padding: 5pt 12pt;
    text-align: center;
  }

  .category-ribbon span {
    font-size: 7.5pt;
    font-weight: bold;
    letter-spacing: 1.5pt;
    color: #ffffff;
    text-transform: uppercase;
  }

  /* ── Body ── */
  .pass-body {
    flex: 1;
    padding: 14pt 14pt 10pt;
    display: flex;
    flex-direction: column;
    align-items: center;
  }

  .attendee-name {
    font-size: 14pt;
    font-weight: bold;
    color: #0d3b6e;
    text-align: center;
    line-height: 1.25;
    margin-bottom: 4pt;
  }

  .divider {
    width: 40pt;
    height: 1.5pt;
    background: #e63946;
    margin: 6pt auto 12pt;
  }

  /* ── QR block ── */
  .qr-block {
    background: #f8f9fa;
    border: 1pt solid #e0e0e0;
    border-radius: 4pt;
    padding: 8pt;
    text-align: center;
    margin-bottom: 12pt;
  }

  .qr-block img {
    width: 110pt;
    height: 110pt;
  }

  .qr-label {
    font-size: 5.5pt;
    color: #888;
    margin-top: 5pt;
    letter-spacing: 0.5pt;
    text-transform: uppercase;
  }

  /* ── Details grid ── */
  .detail-row {
    width: 100%;
    margin-bottom: 6pt;
  }

  .detail-label {
    font-size: 5.5pt;
    color: #888;
    text-transform: uppercase;
    letter-spacing: 0.8pt;
    margin-bottom: 1.5pt;
  }

  .detail-value {
    font-size: 8pt;
    font-weight: bold;
    color: #1a1a2e;
  }

  /* ── Footer ── */
  .pass-footer {
    background: #0d3b6e;
    padding: 8pt 12pt;
    text-align: center;
  }

  .serial-number {
    font-size: 8.5pt;
    font-weight: bold;
    color: #ffffff;
    letter-spacing: 2pt;
  }

  .footer-note {
    font-size: 5pt;
    color: #90caf9;
    margin-top: 3pt;
  }
</style>
</head>
<body>
<div class="pass">

  {{-- Header --}}
  <div class="pass-header">
    <div class="conference-label">Official Event Pass</div>
    <div class="event-name">{{ $event->title }}</div>
    @if($event->startDate)
    <div class="event-meta">
      {{ \Carbon\Carbon::parse($event->startDate)->format('F j, Y') }}
      @if($event->location) &nbsp;·&nbsp; {{ $event->location }} @endif
    </div>
    @endif
  </div>

  {{-- Category ribbon --}}
  <div class="category-ribbon">
    <span>{{ $categoryLabel }}</span>
  </div>

  {{-- Body --}}
  <div class="pass-body">

    <div class="attendee-name">{{ $fullName }}</div>
    <div class="divider"></div>

    {{-- QR Code --}}
    <div class="qr-block">
      <img src="{{ $qrDataUri }}" alt="QR Pass">
      <div class="qr-label">Scan at check-in</div>
    </div>

    {{-- Details --}}
    <div class="detail-row">
      <div class="detail-label">Participation Type</div>
      <div class="detail-value">{{ $attendee->participationType }}</div>
    </div>

    @if($attendee->organizationName)
    <div class="detail-row">
      <div class="detail-label">Organization</div>
      <div class="detail-value">{{ $attendee->organizationName }}</div>
    </div>
    @endif

  </div>

  {{-- Footer with serial --}}
  <div class="pass-footer">
    <div class="serial-number">{{ $pass->serialNumber }}</div>
    <div class="footer-note">Present this pass at the venue entrance</div>
  </div>

</div>
</body>
</html>