<?php

namespace App\Http\Controllers;

use App\Support\PrototypeScreens;
use Illuminate\View\View;

class PrototypeController extends Controller
{
    public function show(string $screen): View
    {
        $page = PrototypeScreens::find($screen);

        abort_unless($page, 404);

        return view($page['view'] ?? 'prototype.screen', [
            'page' => $page,
        ]);
    }
}
