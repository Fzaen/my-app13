<?php

namespace App\Http\Controllers;

use App\Models\FrontEnd\ObiOrder;
use Inertia\Inertia;
use Inertia\Response;

class PbAllController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Pb/All', [
            'rows' => ObiOrder::getDailySummaryForLastSevenDays(),
        ]);
    }
}
