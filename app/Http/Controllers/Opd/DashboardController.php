<?php

namespace App\Http\Controllers\Opd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('opd.dashboard', [
            'user' => $request->user()->load('opd'),
        ]);
    }
}
