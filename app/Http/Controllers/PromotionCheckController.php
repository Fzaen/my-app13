<?php

namespace App\Http\Controllers;

use App\Exports\PromotionCheckTabExport;
use App\Models\ExportModel\PromotionCheck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PromotionCheckController extends Controller
{
    public function export(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'kode' => ['required', 'string', 'max:500'],
            'tab' => ['required', 'in:descriptions,recipients,registered,details'],
        ]);
        $codes = PromotionCheck::normalizeCodes($validated['kode']);

        if ($codes === []) {
            throw ValidationException::withMessages([
                'kode' => 'Pilih setidaknya satu kode promo untuk diekspor.',
            ]);
        }

        $rows = match ($validated['tab']) {
            'descriptions' => PromotionCheck::promoDescriptions($codes),
            'recipients' => PromotionCheck::recipients($codes),
            'registered' => PromotionCheck::registeredMembers($codes),
            'details' => PromotionCheck::promoDetails($codes),
        };

        return Excel::download(
            new PromotionCheckTabExport($rows, $validated['tab']),
            "detail-promo-{$validated['tab']}.xlsx",
        );
    }

    public function suggestions(Request $request): JsonResponse
    {
        $search = $request->input('search');

        if (is_string($search)) {
            $request->merge(['search' => trim($search)]);
        }

        $validated = $request->validate([
            'search' => ['required', 'string', 'min:2', 'max:80'],
        ]);

        return response()->json([
            'suggestions' => PromotionCheck::suggestPromotions($validated['search'])->values(),
        ]);
    }

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'kode' => ['nullable', 'string', 'max:500'],
        ]);

        $codes = PromotionCheck::normalizeCodes((string) ($validated['kode'] ?? ''));

        return Inertia::render('Cek/Promo', [
            'codes' => $codes,
            'promoDescriptions' => $codes === [] ? [] : PromotionCheck::promoDescriptions($codes),
            'recipients' => $codes === [] ? [] : PromotionCheck::recipients($codes),
            'registeredMembers' => $codes === [] ? [] : PromotionCheck::registeredMembers($codes),
            'promoDetails' => $codes === [] ? [] : PromotionCheck::promoDetails($codes),
        ]);
    }
}
