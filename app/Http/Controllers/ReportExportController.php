<?php

namespace App\Http\Controllers;

use App\Models\ReportExport;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportExportController extends Controller
{
    public function index()
    {
        $exports = auth()->user()->reportExports()
            ->orderByDesc('created_at')
            ->paginate(15);

        return Inertia::render('Reports/Index', [
            'exports' => $exports,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'format' => 'required|in:pdf,csv',
            'range_start' => 'required|date',
            'range_end' => 'required|date|after_or_equal:range_start',
            'include_sections' => 'nullable|array',
            'include_sections.profile_summary' => 'nullable|boolean',
            'include_sections.glucose_trends' => 'nullable|boolean',
            'include_sections.meals_summary' => 'nullable|boolean',
            'include_sections.medications' => 'nullable|boolean',
        ]);

        $export = auth()->user()->reportExports()->create([
            'format' => $validated['format'],
            'range_start' => $validated['range_start'],
            'range_end' => $validated['range_end'],
            'include_sections_json' => $validated['include_sections'] ?? null,
            'status' => 'pending',
        ]);

        AuditService::log(auth()->user(), 'report_export.created', 'report_export', $export->id);

        // Dispatch export job (for now, just mark as completed with placeholder)
        // TODO: Implement ExportReportJob dispatched to queue
        $this->generateExport($export);

        return redirect()->route('reports.index')->with('success', 'Export started.');
    }

    /**
     * Download a completed export.
     */
    public function download(ReportExport $reportExport)
    {
        if ($reportExport->user_id !== auth()->id()) {
            abort(404);
        }

        if ($reportExport->status !== 'completed' || !$reportExport->file_path_enc) {
            return back()->with('error', 'Export not ready.');
        }

        AuditService::log(auth()->user(), 'report_export.downloaded', 'report_export', $reportExport->id);

        return response()->download(storage_path('app/' . $reportExport->file_path_enc));
    }

    /**
     * Generate export synchronously (MVP approach, move to queue later).
     */
    private function generateExport(ReportExport $export): void
    {
        $user = $export->user;
        $readings = $user->glucoseReadings()
            ->whereBetween('measured_at_utc', [$export->range_start, $export->range_end])
            ->orderBy('measured_at_utc')
            ->get();

        if ($export->format === 'csv') {
            $this->generateCsv($export, $readings);
        } else {
            // PDF generation will be added with dompdf
            $this->generateCsv($export, $readings); // Fallback to CSV for now
        }
    }

    private function generateCsv(ReportExport $export, $readings): void
    {
        $filename = 'exports/glucose_' . $export->public_id . '.csv';
        $path = storage_path('app/' . $filename);

        // Ensure directory exists
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $handle = fopen($path, 'w');
        fputcsv($handle, [
            'Date/Time (UTC)', 'Value', 'Unit', 'Value (mg/dL)',
            'Time of Day', 'Fasting', 'Meal Type', 'Insulin Taken',
            'Meds Taken', 'Symptoms', 'Notes',
        ]);

        foreach ($readings as $reading) {
            fputcsv($handle, [
                $reading->measured_at_utc->format('Y-m-d H:i:s'),
                $reading->value_raw,
                $reading->unit,
                $reading->value_mgdl,
                $reading->time_of_day,
                $reading->is_fasting ? 'Yes' : 'No',
                $reading->meal_type,
                $reading->insulin_taken ? 'Yes' : 'No',
                $reading->meds_taken ? 'Yes' : 'No',
                is_array($reading->symptoms_json) ? implode(', ', $reading->symptoms_json) : '',
                $reading->notes_enc ?? '',
            ]);
        }

        fclose($handle);

        $export->update([
            'status' => 'completed',
            'file_path_enc' => $filename,
            'expires_at' => now()->addDays(7),
        ]);
    }
}
