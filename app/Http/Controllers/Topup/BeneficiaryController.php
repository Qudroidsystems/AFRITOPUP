<?php

namespace App\Http\Controllers\Topup;

use App\Http\Controllers\Controller;
use App\Models\Beneficiary;
use Illuminate\Http\Request;

/** Saved numbers / meters / smartcards (web: JSON for the buy form, plus rename and remove). */
class BeneficiaryController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:Buy services');
    }

    public function index(Request $request)
    {
        $q = $request->user()->beneficiaries()->with('service:id,code,name,short_name,color')
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->orderByDesc('last_used_at')->limit(30);

        return response()->json(['beneficiaries' => $q->get()->map->present()]);
    }

    public function update(Request $request, Beneficiary $beneficiary)
    {
        abort_unless($beneficiary->user_id === $request->user()->id, 404);
        $beneficiary->update($request->validate(['label' => 'nullable|string|max:80']));

        return $request->expectsJson() ? response()->json(['beneficiary' => $beneficiary->present()]) : back()->with('success', 'Saved.');
    }

    public function destroy(Request $request, Beneficiary $beneficiary)
    {
        abort_unless($beneficiary->user_id === $request->user()->id, 404);
        $beneficiary->delete();

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('success', 'Removed.');
    }
}
