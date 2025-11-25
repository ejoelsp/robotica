<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia; 
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ProfileController;

Route::get('/', function () {
    return Inertia::render('Home'); 
});

// Pantallas de autenticación 
Route::get('/login', function () {
    return Inertia::render('Auth/Login');
})->name('login');

Route::get('/register', function () {
    return Inertia::render('Auth/Register');
})->name('register');

// Registro real (POST)
Route::post('/register', [RegisterController::class, 'store'])
    ->name('register.store');

// Login
Route::post('/login', [LoginController::class, 'store'])
    ->name('login.store');

// RUTAS PROTEGIDAS
Route::middleware('auth')->group(function () {

    // Dashboard del competidor después de login
    Route::get('/dashboard', fn () => Inertia::render('DashboardCompetidor'))
        ->name('dashboard');

    // Perfil (si ya lo tienes)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Logout
    Route::post('/logout', [LoginController::class, 'destroy'])
        ->name('logout');
});
