<?php

namespace App\Http\Controllers\Kelompok;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('kelompok.dashboard', [
            'user' => $request->user()->load('organization'),
        ]);
    }
}
