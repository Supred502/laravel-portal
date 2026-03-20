<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RestToolController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/rest-tool', [RestToolController::class, 'index'])->name('rest-tool.index');
    Route::post('/rest-tool/fetch', [RestToolController::class, 'fetch'])->name('rest-tool.fetch');
    Route::post('/rest-tool/post', [RestToolController::class, 'postXml'])->name('rest-tool.post');
    Route::post('/rest-tool/buves/load', [RestToolController::class, 'loadBuves'])->name('rest-tool.buves.load');
    Route::post('/rest-tool/buves/update', [RestToolController::class, 'updateBuves'])->name('rest-tool.buves.update');
    Route::get('/rest-tool/logs', [RestToolController::class, 'logs'])->name('rest-tool.logs');
    Route::get('/rest-tool/logs/{id}', [RestToolController::class, 'logShow'])->name('rest-tool.logs.show');
});

require __DIR__ . '/auth.php';
