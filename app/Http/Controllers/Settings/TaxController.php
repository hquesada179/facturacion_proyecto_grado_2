<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Tax;
use Illuminate\Http\RedirectResponse;

class TaxController extends Controller
{
    public function toggle(Tax $tax): RedirectResponse
    {
        $this->authorize('update', $tax);

        $tax->update(['is_active' => ! $tax->is_active]);

        return redirect()->route('settings.billing')->with('status', 'Estado del impuesto actualizado correctamente.');
    }
}
