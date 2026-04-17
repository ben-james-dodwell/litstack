<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('books/search', 'pages::books.search')->name('books.search');
    Route::livewire('books/shelf', 'pages::books.shelf')->name('books.shelf');
    Route::livewire('books/{userBook}', 'pages::books.show')->name('books.show');
});

require __DIR__.'/settings.php';
