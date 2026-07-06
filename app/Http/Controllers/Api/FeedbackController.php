<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeedbackSubmission;
use App\Models\FeedbackStaffRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FeedbackController extends Controller
{
    // ── POST /api/feedback ────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'overallRating'         => ['required', 'integer', 'between:1,5'],
            'organization'          => ['required', 'integer', 'between:1,5'],
            'communication'         => ['required', 'integer', 'between:1,5'],
            'respected'             => ['required', Rule::in(['yes', 'somewhat', 'no'])],
            'contributedToLearning' => ['required', Rule::in(['yes', 'somewhat', 'no'])],
            'wouldParticipateAgain' => ['required', Rule::in(['yes', 'maybe', 'no'])],
            'staff'                 => ['required', 'array', 'min:1'],
        ]);

        // ── Resolve active event ──────────────────────────────────────────────
        $activeEvent = $this->getActiveEvent();

        if (!$activeEvent) {
            return response()->json([
                'data' => null,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        $staffInput    = $validated['staff'];
        $validStaffIds = $this->resolveValidStaffUserIds();
        $staffErrors   = [];

        foreach ($staffInput as $staffId => $rating) {
            if (!in_array((int) $staffId, $validStaffIds)) {
                $staffErrors["staff.{$staffId}"] = "User {$staffId} is not a valid active staff member.";
                continue;
            }

            // Only performance + two open-text fields
            $v = validator($rating, [
                'performance' => ['required', 'integer', 'between:1,5'],
                'strength'    => ['nullable', 'string', 'max:2000'],
                'improvement' => ['nullable', 'string', 'max:2000'],
            ]);

            if ($v->fails()) {
                foreach ($v->errors()->toArray() as $field => $messages) {
                    $staffErrors["staff.{$staffId}.{$field}"] = $messages[0];
                }
            }
        }

        if (!empty($staffErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed for one or more staff ratings.',
                'errors'  => $staffErrors,
            ], 422);
        }

        DB::beginTransaction();
        try {
            $submission = FeedbackSubmission::create([
                'attendeeId'              => $request->user()?->attendeeId ?? null,
                'overall_rating'          => (int) $validated['overallRating'],
                'organization'            => (int) $validated['organization'],
                'communication'           => (int) $validated['communication'],
                'respected'               => $validated['respected'],
                'contributed_to_learning' => $validated['contributedToLearning'],
                'would_participate_again' => $validated['wouldParticipateAgain'],
                'ip_address'              => $request->ip(),
                'eventId'                 => $eventId,
            ]);

            foreach ($staffInput as $staffId => $rating) {
                FeedbackStaffRating::create([
                    'feedback_submission_id' => $submission->id,
                    'staff_id'               => (int) $staffId,
                    'performance'            => (int) $rating['performance'],
                    'strength'               => $rating['strength']    ?? null,
                    'improvement'            => $rating['improvement'] ?? null,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Feedback submitted successfully.',
                'data'    => [
                    'submissionId'  => $submission->id,
                    'staffReviewed' => count($staffInput),
                ],
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while saving your feedback. Please try again.',
            ], 500);
        }
    }

    // ── GET /api/feedback  (admin summary) ───────────────────────────────────

    public function summary(): JsonResponse
    {
        $activeEvent = $this->getActiveEvent();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        return response()->json([
            'success' => true,
            'data'    => [
                'event'    => [
                    'id'   => $eventId,
                    'name' => $activeEvent->name ?? 'ISSAM Residential Training',
                ],
                'general'  => $this->buildGeneralSummary($eventId),
                'staff'    => $this->buildStaffSummary($eventId),
                'comments' => $this->buildAllComments($eventId),
            ],
        ]);
    }

    // ── GET /api/feedback/download-pdf  (admin) ───────────────────────────────

    public function downloadPdf(): Response
    {
        $activeEvent = $this->getActiveEvent();

        if (!$activeEvent) {
            return response('No active event found.', 404);
        }

        $eventId      = $activeEvent->eventId ?? $activeEvent->id;
        $eventName    = $activeEvent->name ?? 'ISSAM Residential Training';
        
        $general  = $this->buildGeneralSummary($eventId);
        $staff    = $this->buildStaffSummary($eventId);
        $comments = $this->buildAllComments($eventId);
        
        $html     = $this->buildReportHtml(
            $eventName,
            now()->format('d M Y, H:i'),
            $general,
            $staff,
            $comments
        );

        $pdf = $this->wkhtmltopdfAvailable()
            ? $this->renderWithWkhtmltopdf($html)
            : $this->renderWithDomPdf($html);

        $filename = 'issam-feedback-report-' . now()->format('Y-m-d') . '.pdf';

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length'      => strlen($pdf),
        ]);
    }

    // ── Private — data builders ───────────────────────────────────────────────

    private function buildGeneralSummary(int $eventId): object
    {
        return DB::table('feedback_submissions')
            ->where('eventId', $eventId)
            ->select(
                DB::raw('COUNT(*) as totalSubmissions'),
                DB::raw('ROUND(AVG(overall_rating), 2) as avgOverallRating'),
                DB::raw('ROUND(AVG(organization),   2) as avgOrganization'),
                DB::raw('ROUND(AVG(communication),  2) as avgCommunication'),
                DB::raw('SUM(CASE WHEN respected = "yes"      THEN 1 ELSE 0 END) as respectedYes'),
                DB::raw('SUM(CASE WHEN respected = "somewhat" THEN 1 ELSE 0 END) as respectedSomewhat'),
                DB::raw('SUM(CASE WHEN respected = "no"       THEN 1 ELSE 0 END) as respectedNo'),
                DB::raw('SUM(CASE WHEN contributed_to_learning = "yes"      THEN 1 ELSE 0 END) as contributedYes'),
                DB::raw('SUM(CASE WHEN contributed_to_learning = "somewhat" THEN 1 ELSE 0 END) as contributedSomewhat'),
                DB::raw('SUM(CASE WHEN contributed_to_learning = "no"       THEN 1 ELSE 0 END) as contributedNo'),
                DB::raw('SUM(CASE WHEN would_participate_again = "yes"   THEN 1 ELSE 0 END) as participateYes'),
                DB::raw('SUM(CASE WHEN would_participate_again = "maybe" THEN 1 ELSE 0 END) as participateMaybe'),
                DB::raw('SUM(CASE WHEN would_participate_again = "no"    THEN 1 ELSE 0 END) as participateNo')
            )
            ->first();
    }

    private function buildStaffSummary(int $eventId): \Illuminate\Support\Collection
    {
        return DB::table('feedback_staff_ratings as fsr')
            ->join('feedback_submissions as fs', 'fs.id', '=', 'fsr.feedback_submission_id')
            ->join('users as u', 'u.id', '=', 'fsr.staff_id')
            ->join('roles as r', 'r.roleId', '=', 'u.role')
            ->where('fs.eventId', $eventId)
            ->select(
                'u.id as userId',
                DB::raw("TRIM(CONCAT(COALESCE(u.firstName,''), ' ', COALESCE(u.lastName,''))) as name"),
                'u.photo as image',
                'r.roleName as role',
                'u.portfolio as portfolio',
                DB::raw('COUNT(DISTINCT fsr.feedback_submission_id) as responseCount'),
                DB::raw('ROUND(AVG(fsr.performance), 2) as avgPerformance')
            )
            ->groupBy('u.id', 'u.firstName', 'u.lastName', 'u.photo', 'r.roleName', 'u.portfolio')
            ->orderByDesc('avgPerformance')
            ->get();
    }

    private function buildAllComments(int $eventId): array
    {
        $rows = DB::table('feedback_staff_ratings as fsr')
            ->join('feedback_submissions as fs', 'fs.id', '=', 'fsr.feedback_submission_id')
            ->join('users as u', 'u.id', '=', 'fsr.staff_id')
            ->join('roles as r', 'r.roleId', '=', 'u.role')
            ->where('fs.eventId', $eventId)
            ->where(function ($q) {
                $q->whereNotNull('fsr.strength')
                  ->orWhereNotNull('fsr.improvement');
            })
            ->select(
                'u.id as userId',
                DB::raw("TRIM(CONCAT(COALESCE(u.firstName,''), ' ', COALESCE(u.lastName,''))) as name"),
                'u.photo as image',
                'r.roleName as role',
                'fsr.strength',
                'fsr.improvement',
                'fsr.created_at'
            )
            ->orderBy('u.id')
            ->orderByDesc('fsr.created_at')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $uid = $row->userId;
            if (!isset($grouped[$uid])) {
                $grouped[$uid] = [
                    'userId'   => $uid,
                    'name'     => $row->name,
                    'role'     => $row->role,
                    'image'    => $row->image,
                    'comments' => [],
                ];
            }
            $grouped[$uid]['comments'][] = [
                'strength'    => $row->strength,
                'improvement' => $row->improvement,
                'submittedAt' => $row->created_at,
            ];
        }

        return array_values($grouped);
    }

    // ── PDF ───────────────────────────────────────────────────────────────────

    private function wkhtmltopdfAvailable(): bool
    {
        return !empty(shell_exec('which wkhtmltopdf 2>/dev/null'));
    }

    private function renderWithWkhtmltopdf(string $html): string
    {
        $tmpHtml = tempnam(sys_get_temp_dir(), 'fb_') . '.html';
        $tmpPdf  = tempnam(sys_get_temp_dir(), 'fb_') . '.pdf';
        file_put_contents($tmpHtml, $html);
        shell_exec(
            "wkhtmltopdf --quiet --page-size A4 --margin-top 15mm --margin-bottom 15mm " .
            "--margin-left 15mm --margin-right 15mm " .
            "--footer-center 'ISSAM Feedback Report  |  Page [page] of [topage]' --footer-font-size 8 " .
            escapeshellarg($tmpHtml) . ' ' . escapeshellarg($tmpPdf)
        );
        $pdf = file_get_contents($tmpPdf);
        unlink($tmpHtml);
        unlink($tmpPdf);
        return $pdf;
    }

    private function renderWithDomPdf(string $html): string
    {
        $dompdf = new \Dompdf\Dompdf(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => false]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf->output();
    }

    private function buildReportHtml(
        string $programName,
        string $generatedAt,
        object $general,
        \Illuminate\Support\Collection $staff,
        array $commentGroups
    ): string {
        $total      = $general->totalSubmissions ?? 0;
        $avgOverall = number_format($general->avgOverallRating ?? 0, 1);
        $avgOrg     = number_format($general->avgOrganization  ?? 0, 1);
        $avgComm    = number_format($general->avgCommunication ?? 0, 1);

        $rYes = $general->respectedYes ?? 0;
        $rSom = $general->respectedSomewhat ?? 0;
        $rNo  = $general->respectedNo ?? 0;
        $cYes = $general->contributedYes ?? 0;
        $cSom = $general->contributedSomewhat ?? 0;
        $cNo  = $general->contributedNo ?? 0;
        $pYes   = $general->participateYes ?? 0;
        $pMaybe = $general->participateMaybe ?? 0;
        $pNo    = $general->participateNo ?? 0;

        // Staff table rows — single performance column
        $staffRows = '';
        $rank = 1;
        foreach ($staff as $s) {
            $perf  = number_format($s->avgPerformance ?? 0, 1);
            $barW  = round(($s->avgPerformance ?? 0) / 5 * 100);
            $color = $barW >= 80 ? '#059669' : ($barW >= 60 ? '#f59e0b' : '#f43f5e');
            $medal = $rank === 1 ? '1st' : ($rank === 2 ? '2nd' : ($rank === 3 ? '3rd' : "#{$rank}"));

            $staffRows .= "
            <tr>
                <td style='padding:10px 8px;border-bottom:1px solid #f0f0f0;font-size:11px;color:#6b7280;'>{$medal}</td>
                <td style='padding:10px 8px;border-bottom:1px solid #f0f0f0;'>
                    <div style='font-size:12px;font-weight:700;color:#111;'>{$s->name}</div>
                    <div style='font-size:10px;color:#6b7280;margin-top:2px;'>{$s->role}</div>
                </td>
                <td style='padding:10px 8px;border-bottom:1px solid #f0f0f0;text-align:center;font-size:12px;'>{$s->responseCount}</td>
                <td style='padding:10px 8px;border-bottom:1px solid #f0f0f0;min-width:120px;'>
                    <div style='font-size:13px;font-weight:700;color:{$color};margin-bottom:4px;'>{$perf}/5</div>
                    <div style='background:#f3f4f6;border-radius:3px;height:6px;'>
                        <div style='background:{$color};width:{$barW}%;height:6px;border-radius:3px;'></div>
                    </div>
                </td>
            </tr>";
            $rank++;
        }

        // Comments
        $commentHtml = '';
        foreach ($commentGroups as $group) {
            $staffName = htmlspecialchars($group['name'], ENT_QUOTES);
            $role      = htmlspecialchars($group['role'], ENT_QUOTES);
            $count     = count($group['comments']);

            $commentHtml .= "
            <div style='margin-bottom:20px;page-break-inside:avoid;'>
                <div style='background:#f0fdf4;border-left:4px solid #059669;padding:8px 12px;margin-bottom:8px;border-radius:0 6px 6px 0;'>
                    <span style='font-size:12px;font-weight:700;color:#065f46;'>{$staffName}</span>
                    <span style='font-size:10px;color:#6b7280;margin-left:8px;'>{$role} &middot; {$count} response(s)</span>
                </div>";

            foreach ($group['comments'] as $c) {
                if ($c['strength']) {
                    $s = htmlspecialchars($c['strength'], ENT_QUOTES);
                    $commentHtml .= "<div style='margin:6px 0 6px 16px;padding:6px 10px;background:#f9fafb;border-radius:6px;'>
                        <div style='font-size:10px;font-weight:700;color:#059669;margin-bottom:3px;'>&#10003; What this staff member did well:</div>
                        <div style='font-size:11px;color:#374151;line-height:1.5;'>{$s}</div>
                    </div>";
                }
                if ($c['improvement']) {
                    $i = htmlspecialchars($c['improvement'], ENT_QUOTES);
                    $commentHtml .= "<div style='margin:6px 0 6px 16px;padding:6px 10px;background:#fffbeb;border-radius:6px;'>
                        <div style='font-size:10px;font-weight:700;color:#d97706;margin-bottom:3px;'>&#9651; Areas for improvement:</div>
                        <div style='font-size:11px;color:#374151;line-height:1.5;'>{$i}</div>
                    </div>";
                }
            }
            $commentHtml .= "</div>";
        }

        if (empty($commentHtml)) {
            $commentHtml = '<p style="font-size:12px;color:#9ca3af;">No written comments were submitted.</p>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<title>ISSAM Feedback Report</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:Arial,Helvetica,sans-serif;color:#111;background:#fff;font-size:13px;}
  .cover{background:#064e3b;color:#fff;padding:48px 40px 40px;}
  .cover h1{font-size:28px;font-weight:700;margin-bottom:8px;}
  .cover p{font-size:13px;opacity:.75;margin-bottom:4px;}
  .pill{display:inline-block;background:rgba(255,255,255,.15);border-radius:20px;padding:4px 12px;font-size:11px;margin-top:12px;margin-right:8px;}
  .section{padding:28px 40px;}
  .section-title{font-size:13px;font-weight:700;color:#065f46;text-transform:uppercase;letter-spacing:.07em;margin-bottom:16px;padding-bottom:8px;border-bottom:2px solid #d1fae5;}
  .kpi-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px;}
  .kpi{background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:14px 16px;border-left:4px solid #059669;}
  .kpi-label{font-size:10px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;}
  .kpi-val{font-size:22px;font-weight:700;color:#065f46;margin-top:4px;}
  .kpi-sub{font-size:10px;color:#9ca3af;margin-top:2px;}
  table{width:100%;border-collapse:collapse;}
  th{background:#f0fdf4;color:#065f46;font-size:10px;text-transform:uppercase;letter-spacing:.05em;padding:10px 8px;text-align:left;border-bottom:2px solid #d1fae5;}
  th.center{text-align:center;}
  .page-break{page-break-before:always;}
  .footer{margin-top:40px;padding:12px 40px;background:#f9fafb;border-top:1px solid #e5e7eb;font-size:10px;color:#9ca3af;}
</style>
</head>
<body>

<div class="cover">
  <div style="font-size:11px;opacity:.6;text-transform:uppercase;letter-spacing:.1em;margin-bottom:14px;">Confidential Feedback Report</div>
  <h1>{$programName}</h1>
  <p>Staff &amp; Programme Evaluation Analytics</p>
  <div>
    <span class="pill">Generated: {$generatedAt}</span>
    <span class="pill">Total Responses: {$total}</span>
  </div>
</div>

<div class="section">
  <div class="section-title">Programme Overview</div>
  <div class="kpi-grid">
    <div class="kpi"><div class="kpi-label">Total Submissions</div><div class="kpi-val">{$total}</div><div class="kpi-sub">Unique respondents</div></div>
    <div class="kpi"><div class="kpi-label">Overall Rating</div><div class="kpi-val">{$avgOverall}<span style="font-size:13px;color:#9ca3af;">/5</span></div><div class="kpi-sub">Management team</div></div>
    <div class="kpi"><div class="kpi-label">Organisation</div><div class="kpi-val">{$avgOrg}<span style="font-size:13px;color:#9ca3af;">/5</span></div></div>
    <div class="kpi"><div class="kpi-label">Communication</div><div class="kpi-val">{$avgComm}<span style="font-size:13px;color:#9ca3af;">/5</span></div></div>
    <div class="kpi"><div class="kpi-label">Felt Respected — Yes / Somewhat / No</div><div class="kpi-val">{$rYes}</div><div class="kpi-sub">Somewhat: {$rSom} &middot; No: {$rNo}</div></div>
    <div class="kpi"><div class="kpi-label">Contributed to Learning — Yes</div><div class="kpi-val">{$cYes}</div><div class="kpi-sub">Somewhat: {$cSom} &middot; No: {$cNo}</div></div>
  </div>
  <div class="kpi-grid" style="grid-template-columns:1fr;">
    <div class="kpi"><div class="kpi-label">Would Participate Again</div><div style="display:flex;gap:24px;margin-top:6px;"><span><strong style="font-size:18px;color:#059669;">{$pYes}</strong> <span style="font-size:11px;color:#6b7280;">Yes</span></span><span><strong style="font-size:18px;color:#f59e0b;">{$pMaybe}</strong> <span style="font-size:11px;color:#6b7280;">Maybe</span></span><span><strong style="font-size:18px;color:#f43f5e;">{$pNo}</strong> <span style="font-size:11px;color:#6b7280;">No</span></span></div></div>
  </div>
</div>

<div class="section" style="padding-top:0;">
  <div class="section-title">Staff Performance Rankings</div>
  <table>
    <thead>
      <tr>
        <th style="width:45px;">Rank</th>
        <th>Staff Member</th>
        <th class="center" style="width:80px;">Responses</th>
        <th style="width:140px;">Performance Rating</th>
      </tr>
    </thead>
    <tbody>{$staffRows}</tbody>
  </table>
</div>

<div class="section page-break">
  <div class="section-title">Qualitative Feedback — Written Comments</div>
  <p style="font-size:11px;color:#6b7280;margin-bottom:20px;">All written responses submitted by participants, grouped by staff member.</p>
  {$commentHtml}
</div>

<div class="footer">
  Confidential — authorised personnel only &nbsp;|&nbsp; {$programName} &nbsp;|&nbsp; Generated {$generatedAt}
</div>

</body>
</html>
HTML;
    }

    // ── Private — helpers ─────────────────────────────────────────────────────

    private function getActiveEvent(): ?object
    {
        return DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();
    }

    private function resolveValidStaffUserIds(): array
    {
        $roles = DB::table('roles')
            ->whereNotNull('table_name')
            ->where('table_name', '!=', '')
            ->get();

        if ($roles->isEmpty()) return [];

        $userIds = collect();
        foreach ($roles as $role) {
            if (!$this->tableExists($role->table_name)) continue;
            $ids = DB::table($role->table_name . ' as rp')
                ->join('users as u', 'u.id', '=', 'rp.userId')
                ->where('u.status', 'active')
                ->pluck('u.id')
                ->map(fn ($id) => (int) $id);
            $userIds = $userIds->merge($ids);
        }
        return $userIds->unique()->values()->all();
    }

    private function tableExists(string $table): bool
    {
        try {
            DB::select("SELECT 1 FROM `{$table}` LIMIT 1");
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}