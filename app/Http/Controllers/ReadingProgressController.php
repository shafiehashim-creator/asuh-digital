<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Services\ChildMode;
use App\Services\ReadingLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Heartbeat from the reader in Mod Anak (see ReadingLog). */
class ReadingProgressController extends Controller
{
    public function store(Request $request, Book $book, ChildMode $childMode, ReadingLog $log): JsonResponse
    {
        $child = $childMode->child($request->user());
        abort_unless($child && $childMode->allows($child, $book), 403);

        $data = $request->validate([
            'page' => ['required', 'integer', 'min:1', 'max:5000'],
            'tick' => ['sometimes', 'boolean'],
        ]);

        $session = $log->record($child, $book, (int) $data['page'], (bool) ($data['tick'] ?? false));

        return response()->json(['minutes' => $session->minutes]);
    }
}
