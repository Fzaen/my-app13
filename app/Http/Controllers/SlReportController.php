<?php

namespace App\Http\Controllers;

use App\Models\ExportModel\SlReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SlReportController extends Controller
{
    public function picker(Request $request): Response
    {
        $filters = $request->validate([
            'tglstart' => ['nullable', 'date_format:Y-m-d', 'required_with:tglend'],
            'tglend' => ['nullable', 'date_format:Y-m-d', 'required_with:tglstart', 'after_or_equal:tglstart'],
        ]);
        $filters = [
            'tglstart' => $filters['tglstart'] ?? now()->toDateString(),
            'tglend' => $filters['tglend'] ?? now()->toDateString(),
        ];
        $filters['tglend_exclusive'] = CarbonImmutable::parse($filters['tglend'])
            ->addDay()
            ->toDateString();

        return Inertia::render('Sl/Picker', [
            'filters' => [
                'tglstart' => $filters['tglstart'],
                'tglend' => $filters['tglend'],
            ],
            'rows' => SlReport::pickerSummary($filters),
        ]);
    }

    public function pickerDetails(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'tglstart' => ['required', 'date_format:Y-m-d'],
            'tglend' => ['required', 'date_format:Y-m-d', 'after_or_equal:tglstart'],
            'picker' => ['nullable', 'string', 'max:100'],
            'unassigned' => ['required', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $filters['tglend_exclusive'] = CarbonImmutable::parse($filters['tglend'])
            ->addDay()
            ->toDateString();
        $filters['unassigned'] = $request->boolean('unassigned');

        return response()->json(
            SlReport::pickerDetails($filters)
                ->simplePaginate(50)
                ->appends($request->query()),
        );
    }

    public function picking(): Response
    {
        return Inertia::render('Sl/Picking', [
            'statusRows' => SlReport::pickingStatusSummary(),
            'slRows' => SlReport::pickingSlSummary(),
        ]);
    }
}
