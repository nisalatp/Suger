<?php

namespace App\Http\Controllers;

use App\Models\LabReport;
use App\Models\LabReportItem;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LabReportController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $reports = $user->labReports()
            ->with('items')
            ->orderByDesc('reported_on')
            ->paginate(20)
            ->withQueryString();

        // Trend data: last 20 values for a chosen test_key
        $trendKey  = $request->input('trend_key', null);
        $trendData = [];
        if ($trendKey) {
            $trendData = LabReportItem::whereHas('labReport', fn($q) => $q->where('user_id', $user->id)->whereNull('deleted_at'))
                ->where('test_key', $trendKey)
                ->join('lab_reports', 'lab_reports.id', '=', 'lab_report_items.lab_report_id')
                ->orderBy('lab_reports.reported_on')
                ->select('lab_report_items.value', 'lab_report_items.unit', 'lab_report_items.status', 'lab_reports.reported_on')
                ->limit(30)
                ->get();
        }

        // All distinct test_keys for the trend picker
        $availableKeys = LabReportItem::whereHas('labReport', fn($q) => $q->where('user_id', $user->id)->whereNull('deleted_at'))
            ->join('lab_reports', 'lab_reports.id', '=', 'lab_report_items.lab_report_id')
            ->whereNotNull('lab_report_items.test_key')
            ->selectRaw('lab_report_items.test_key, lab_report_items.test_name, lab_report_items.unit')
            ->distinct()
            ->orderBy('lab_report_items.test_name')
            ->get();

        return Inertia::render('LabReports/Index', [
            'reports'       => $reports,
            'trendData'     => $trendData,
            'trendKey'      => $trendKey,
            'availableKeys' => $availableKeys,
        ]);
    }

    public function create()
    {
        return Inertia::render('LabReports/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'reported_on'  => 'required|date',
            'lab_name'     => 'nullable|string|max:200',
            'panel_name'   => 'nullable|string|max:200',
            'ordered_by'   => 'nullable|string|max:200',
            'notes'        => 'nullable|string|max:3000',
            'items'        => 'required|array|min:1',
            'items.*.test_name' => 'required|string|max:200',
            'items.*.test_key'  => 'nullable|string|max:100',
            'items.*.value'     => 'required|numeric',
            'items.*.unit'      => 'nullable|string|max:50',
            'items.*.ref_min'   => 'nullable|numeric',
            'items.*.ref_max'   => 'nullable|numeric',
            'items.*.notes'     => 'nullable|string|max:500',
        ]);

        $user = auth()->user();

        $report = $user->labReports()->create([
            'reported_on' => $validated['reported_on'],
            'lab_name'    => $validated['lab_name']   ?? null,
            'panel_name'  => $validated['panel_name'] ?? null,
            'ordered_by'  => $validated['ordered_by'] ?? null,
            'notes_enc'   => $validated['notes']      ?? null,
        ]);

        foreach ($validated['items'] as $item) {
            $refMin = isset($item['ref_min']) ? (float) $item['ref_min'] : null;
            $refMax = isset($item['ref_max']) ? (float) $item['ref_max'] : null;
            $value  = (float) $item['value'];

            $report->items()->create([
                'test_name' => $item['test_name'],
                'test_key'  => $item['test_key']  ?? \Illuminate\Support\Str::slug($item['test_name']),
                'value'     => $value,
                'unit'      => $item['unit']       ?? null,
                'ref_min'   => $refMin,
                'ref_max'   => $refMax,
                'status'    => LabReportItem::computeStatus($value, $refMin, $refMax),
                'notes'     => $item['notes']      ?? null,
            ]);
        }

        AuditService::log($user, 'lab_report.created', 'lab_report', $report->id);

        return redirect()->route('lab-reports.index')->with('success', 'Lab report saved.');
    }

    public function edit(LabReport $labReport)
    {
        if ($labReport->user_id !== auth()->id()) abort(404);

        return Inertia::render('LabReports/Edit', [
            'report' => $labReport->load('items'),
        ]);
    }

    public function update(Request $request, LabReport $labReport)
    {
        if ($labReport->user_id !== auth()->id()) abort(404);

        $validated = $request->validate([
            'reported_on'  => 'required|date',
            'lab_name'     => 'nullable|string|max:200',
            'panel_name'   => 'nullable|string|max:200',
            'ordered_by'   => 'nullable|string|max:200',
            'notes'        => 'nullable|string|max:3000',
            'items'        => 'required|array|min:1',
            'items.*.test_name' => 'required|string|max:200',
            'items.*.test_key'  => 'nullable|string|max:100',
            'items.*.value'     => 'required|numeric',
            'items.*.unit'      => 'nullable|string|max:50',
            'items.*.ref_min'   => 'nullable|numeric',
            'items.*.ref_max'   => 'nullable|numeric',
            'items.*.notes'     => 'nullable|string|max:500',
        ]);

        $labReport->update([
            'reported_on' => $validated['reported_on'],
            'lab_name'    => $validated['lab_name']   ?? null,
            'panel_name'  => $validated['panel_name'] ?? null,
            'ordered_by'  => $validated['ordered_by'] ?? null,
            'notes_enc'   => $validated['notes']      ?? null,
        ]);

        // Replace all items
        $labReport->items()->delete();
        foreach ($validated['items'] as $item) {
            $refMin = isset($item['ref_min']) ? (float) $item['ref_min'] : null;
            $refMax = isset($item['ref_max']) ? (float) $item['ref_max'] : null;
            $value  = (float) $item['value'];

            $labReport->items()->create([
                'test_name' => $item['test_name'],
                'test_key'  => $item['test_key']  ?? \Illuminate\Support\Str::slug($item['test_name']),
                'value'     => $value,
                'unit'      => $item['unit']       ?? null,
                'ref_min'   => $refMin,
                'ref_max'   => $refMax,
                'status'    => LabReportItem::computeStatus($value, $refMin, $refMax),
                'notes'     => $item['notes']      ?? null,
            ]);
        }

        AuditService::log(auth()->user(), 'lab_report.updated', 'lab_report', $labReport->id);

        return redirect()->route('lab-reports.index')->with('success', 'Lab report updated.');
    }

    public function destroy(LabReport $labReport)
    {
        if ($labReport->user_id !== auth()->id()) abort(404);

        $labReport->delete();

        AuditService::log(auth()->user(), 'lab_report.deleted', 'lab_report', $labReport->id);

        return redirect()->route('lab-reports.index')->with('success', 'Lab report deleted.');
    }
}
