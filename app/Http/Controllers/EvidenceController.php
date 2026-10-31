<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadEvidenceRequest;
use App\Models\Evidence;
use App\Models\Report;
use App\Services\EvidenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvidenceController extends Controller
{
    public function store(UploadEvidenceRequest $request, Report $report, EvidenceService $evidenceService): RedirectResponse
    {
        $evidenceService->store($report, $request->user(), $request->file('evidence'));

        return back()->with('status', 'La evidencia quedó adjunta al reporte.');
    }

    public function download(Report $report, Evidence $evidence): StreamedResponse
    {
        $this->authorize('viewEvidence', $report);
        abort_unless($evidence->report_id === $report->id, 404);
        abort_unless(str_starts_with($evidence->file_path, "reports/{$report->id}/evidence/"), 404);
        abort_unless(Storage::disk('local')->exists($evidence->file_path), 404);

        return Storage::disk('local')->download($evidence->file_path, $evidence->original_name);
    }
}