<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ManualBookController extends Controller
{
    public function baOnline(): Response
    {
        $manual = json_decode(
            file_get_contents(resource_path('manual-book/ba-online.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        return Inertia::render('ManualBook/BaOnline', [
            'manual' => $manual,
        ]);
    }
}
