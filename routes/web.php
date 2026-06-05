<?php

use App\Http\Controllers\Auth\GithubLoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OrgController;
use Illuminate\Support\Facades\Route;

// Auth
Route::get('/login', [GithubLoginController::class, 'showLogin'])->name('login');
Route::get('/auth/github', [GithubLoginController::class, 'redirect'])->name('auth.github');
Route::get('/auth/github/callback', [GithubLoginController::class, 'callback'])->name('auth.github.callback');
Route::post('/auth/logout', [GithubLoginController::class, 'logout'])->name('auth.logout');

// Personal dashboard
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
});

// Org admin views
Route::middleware(['auth', \App\Http\Middleware\EnsureAdmin::class])->prefix('org')->name('org.')->group(function () {
    Route::get('/', [OrgController::class, 'index'])->name('index');
    Route::get('/members/{login}', [MemberController::class, 'show'])->name('member');
});
