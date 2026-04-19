<?php

use App\Http\Controllers\DemoLoginController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/demo-login', DemoLoginController::class)
    ->middleware('throttle:10,1')
    ->name('demo.login');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', '/books/shelf')->name('dashboard');

    Route::livewire('books/search', 'pages::books.search')->name('books.search');
    Route::livewire('books/shelf', 'pages::books.shelf')->name('books.shelf');
    Route::livewire('books/{userBook}', 'pages::books.show')->name('books.show');
});

require __DIR__.'/settings.php';
