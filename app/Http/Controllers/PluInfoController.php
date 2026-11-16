<?php

namespace App\Http\Controllers;

use App\Models\FrontEnd\PluInfo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PluInfoController extends Controller
{
    public function index(Request $request, PluInfo $pluInfo): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'plu' => ['nullable', 'string', 'max:32'],
        ]);
        $search = trim($validated['search'] ?? '');
        $descriptionSearch = $search;
        $selectedPlu = trim($validated['plu'] ?? '');
        $input = $selectedPlu !== '' ? $selectedPlu : $search;
        $pluSearch = $selectedPlu;
        $product = null;
        $searchResults = collect();
        $message = null;
        if ($input !== '') {
            if ($selectedPlu !== '' || ctype_digit($input)) {
                $product = $pluInfo->findByInput($input);
                $pluSearch = $product->plu ?? $input;
                $descriptionSearch = '';
                $search = $product->plu ?? $input;

                if ($product === null) {
                    $message = 'PLU atau barcode tidak ditemukan.';
                }
            } else {
                $search = $input;
                $descriptionSearch = $input;
                $searchResults = $pluInfo->searchByDescription($input);

                if ($searchResults->isEmpty()) {
                    $message = 'Tidak ada produk dengan deskripsi tersebut.';
                }
            }
        }

        return Inertia::render('Data/PluInfo/Index', [
            'search' => $search,
            'descriptionSearch' => $descriptionSearch,
            'pluSearch' => $pluSearch,
            'product' => $product,
            'searchResults' => $searchResults,
            'panelOptions' => PluInfo::panelOptions(),
            'message' => $message,
        ]);
    }

    public function suggestions(Request $request, PluInfo $pluInfo): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['required', 'string', 'min:2', 'max:80'],
        ]);

        return response()->json([
            'suggestions' => $pluInfo->suggestProducts(trim($validated['search']))->values(),
        ]);
    }

    public function panel(Request $request, PluInfo $pluInfo): JsonResponse
    {
        $validated = $request->validate([
            'plu' => ['required', 'string', 'regex:/^\d{6}0$/'],
            'panel' => ['required', 'string', Rule::in(array_column(PluInfo::panelOptions(), 'value'))],
        ]);
        abort_unless($pluInfo->hasProduct($validated['plu']), 404);

        $panel = collect(PluInfo::panelOptions())->firstWhere('value', $validated['panel']);

        return response()->json([
            'label' => $panel['label'],
            'rows' => $pluInfo->panelData($validated['panel'], $validated['plu'])->values(),
        ]);
    }

    public function summary(Request $request, PluInfo $pluInfo): JsonResponse
    {
        $validated = $request->validate([
            'plu' => ['required', 'string', 'regex:/^\d{6}0$/'],
        ]);
        abort_unless($pluInfo->hasProduct($validated['plu']), 404);

        return response()->json([
            'cashbacks' => $pluInfo->panelData('cashbacks', $validated['plu'])->values(),
            'gifts' => $pluInfo->panelData('gifts', $validated['plu'])->values(),
            'priceMargin' => $pluInfo->panelData('price-margin', $validated['plu'])->values(),
        ]);
    }
}
