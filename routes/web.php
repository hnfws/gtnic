<?php

use App\Http\Controllers\Api\ScraperController;
use App\Http\Controllers\EventRadarController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

Route::post('/scraped-events', [ScraperController::class, 'store'])
    ->middleware('scraper.token'); // Menggunakan middleware token yang sudah kita buat

Route::get('/radar', [EventRadarController::class, 'index'])->name('radar');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
