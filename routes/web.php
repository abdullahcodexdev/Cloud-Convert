<?php

use App\Http\Controllers\AuthPageController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\ConversionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MyFilesController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/whatsapp-chat', [HomeController::class, 'whatsapp'])->name('whatsapp.chat');
Route::post('/ai-chat', [HomeController::class, 'aiChat'])->name('ai.chat');
Route::get('/signin', [AuthPageController::class, 'signin'])->name('signin');
Route::get('/signup', [AuthPageController::class, 'signup'])->name('signup');
Route::get('/forgot-password', [AuthPageController::class, 'forgot'])->name('password.forgot');
Route::post('/forgot-password', [AuthPageController::class, 'sendReset'])->name('password.email');
Route::get('/reset-code', [AuthPageController::class, 'resetCode'])->name('password.code');
Route::post('/reset-code', [AuthPageController::class, 'verifyResetCode'])->name('password.code.verify');
Route::get('/reset-password/{token}', [AuthPageController::class, 'reset'])->name('password.reset');
Route::post('/reset-password', [AuthPageController::class, 'updatePassword'])->name('password.update');
Route::get('/my-files', [MyFilesController::class, 'index'])->name('my-files');
Route::get('/profile', [AccountController::class, 'profile'])->name('profile');
Route::post('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
Route::post('/profile/password', [AccountController::class, 'updatePassword'])->name('profile.password');
Route::get('/settings', [AccountController::class, 'settings'])->name('settings');
Route::post('/settings/email', [AccountController::class, 'updateEmail'])->name('settings.email');
Route::post('/settings/two-step', [AccountController::class, 'updateTwoStep'])->name('settings.two-step');
Route::post('/signin', [AuthPageController::class, 'localSignin'])->name('signin.post');
Route::post('/signup', [AuthPageController::class, 'localSignup'])->name('signup.post');
Route::get('/two-step', [AuthPageController::class, 'twoStep'])->name('two-step');
Route::post('/two-step', [AuthPageController::class, 'verifyTwoStep'])->name('two-step.verify');
Route::get('/signout', [AuthPageController::class, 'signout'])->name('signout');
Route::get('/auth/{provider}', [AuthPageController::class, 'social'])->name('auth.social');
Route::get('/auth/{provider}/callback', [AuthPageController::class, 'callback'])->name('auth.callback');

Route::get('/api/highlights', [HomeController::class, 'highlights'])->name('api.highlights');
Route::get('/api/history', [ConversionController::class, 'history'])->name('api.history');
Route::post('/api/convert', [ConversionController::class, 'convert'])->middleware('throttle:30,1')->name('api.convert');
Route::post('/api/merge-pdf', [ConversionController::class, 'mergePdf'])->middleware('throttle:30,1')->name('api.merge-pdf');
Route::post('/api/merge', [ConversionController::class, 'merge'])->middleware('throttle:30,1')->name('api.merge');
Route::get('/api/download/{fileId}', [ConversionController::class, 'download'])->name('api.download');
Route::get('/download/{fileId}', [ConversionController::class, 'download'])->name('download');
Route::post('/api/download-all', [ConversionController::class, 'downloadAll'])->middleware('throttle:30,1')->name('api.download-all');
