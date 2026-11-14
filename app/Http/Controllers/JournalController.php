<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The general journal: every entry any module posted, read-only. */
class JournalController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));

        return view('journals.index', [
            'term' => $term,
            'entries' => JournalEntry::withSum('lines as total', 'base_debit')
                ->when(query_date('from'), fn ($q, $date) => $q->where('date', '>=', $date))
                ->when(query_date('until'), fn ($q, $date) => $q->where('date', '<=', $date))
                ->when($term !== '', fn ($q) => $q->where(fn ($inner) => $inner->where('description', 'like', '%'.$term.'%')->orWhere('reference', 'like', '%'.$term.'%')->orWhere('number', $term)))
                ->orderByDesc('date')->orderByDesc('id')
                ->paginate(per_page(25))->withQueryString(),
        ]);
    }

    public function show(Request $request): View
    {
        return view('journals.show', [
            'entry' => JournalEntry::with('lines.account', 'creator', 'reversalOf', 'reversedBy')->findOrFail($request->route('id')),
        ]);
    }
}
