<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\School;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Partner schools for fee payments, and what is owed to each. */
class SchoolController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:Manage services');
    }

    public function index()
    {
        $schools = School::orderBy('name')->get();
        $owed = Transaction::where('category', 'education')->where('status', 'processing')->get()
            ->groupBy(fn ($t) => $t->meta['school_id'] ?? 0)->map(fn ($g) => ['n' => $g->count(), 'total' => $g->sum('amount')]);

        return view('admin.schools.index', ['pagetitle' => 'Schools', 'schools' => $schools, 'owed' => $owed, 'countries' => Country::ordered()->get()]);
    }

    public function save(Request $request, ?School $school = null)
    {
        $d = $request->validate([
            'name'           => 'required|string|max:150',
            'code'           => ['required', 'alpha_dash', 'max:30', Rule::unique('schools')->ignore($school?->id)],
            'country_code'   => 'required|exists:countries,code',
            'city'           => 'nullable|string|max:80',
            'contact_email'  => 'nullable|email|max:255',
            'contact_phone'  => 'nullable|string|max:30',
            'bank_name'      => 'nullable|string|max:80',
            'account_number' => 'nullable|string|max:20',
            'account_name'   => 'nullable|string|max:150',
        ]);
        $d['is_active'] = $request->boolean('is_active', true);
        ($school ?? new School())->fill($d)->save();

        return back()->with('success', 'School saved.');
    }
}
