<?php

namespace App\Observers;

use App\Models\UserBook;
use Illuminate\Support\Facades\Storage;

class UserBookObserver
{
    public function deleted(UserBook $userBook): void
    {
        $book = $userBook->book;

        if (! $book || $book->userBooks()->exists()) {
            return;
        }

        if ($book->getRawOriginal('cover_url') && Storage::disk('public')->exists("covers/{$book->id}.jpg")) {
            Storage::disk('public')->delete("covers/{$book->id}.jpg");
        }

        $book->delete();
    }
}
