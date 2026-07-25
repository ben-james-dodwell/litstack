<?php

namespace App\Http\Controllers;

use App\Models\OwnershipStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LibraryExportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'ownership' => ['nullable', 'integer', 'exists:ownership_statuses,id'],
        ]);

        $ownershipId = $validated['ownership'] ?? null;

        $userBooks = Auth::user()
            ->userBooks()
            ->with('book')
            ->when($ownershipId, fn ($query) => $query->where('ownership_status_id', $ownershipId))
            ->get()
            ->sortBy([['book.author', 'asc'], ['book.title', 'asc']])
            ->values();

        $scopeLabel = match (OwnershipStatus::find($ownershipId)?->name) {
            'owned' => 'Owned Books',
            'wishlist' => 'Wishlist',
            default => 'All Books',
        };

        $filename = 'litstack-'.Str::slug($scopeLabel).'-'.now()->format('Y-m-d').'.pdf';

        return Pdf::loadView('pdf.library-export', [
            'userBooks' => $userBooks,
            'scopeLabel' => $scopeLabel,
            'generatedAt' => now(),
        ])->download($filename);
    }
}
