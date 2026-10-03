<?php

namespace App\Http\Controllers;

use App\Models\InvoiceEvent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        $recentActivity = InvoiceEvent::query()
            ->whereHas('invoice')
            ->where('user_id', $user->id)
            ->with('invoice')
            ->latest()
            ->limit(5)
            ->get();

        return view('profile.show', [
            'user' => $user,
            'recentActivity' => $recentActivity,
        ]);
    }
}
