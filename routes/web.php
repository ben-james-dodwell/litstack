<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/debug-headers', function (\Illuminate\Http\Request $request) {
    return response()->json([
        'session_token'   => session()->token(),
        'csrf_token'      => csrf_token(),
        'x_csrf_header'   => $request->header('X-CSRF-TOKEN'),
        'content_type'    => $request->header('Content-Type'),
        'is_json'         => $request->isJson(),
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', '/books/shelf')->name('dashboard');

    Route::livewire('books/search', 'pages::books.search')->name('books.search');
    Route::livewire('books/shelf', 'pages::books.shelf')->name('books.shelf');
    Route::livewire('books/{userBook}', 'pages::books.show')->name('books.show');
});

require __DIR__.'/settings.php';
