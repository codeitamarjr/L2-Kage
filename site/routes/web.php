<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountSecurityController;
use App\Http\Controllers\AccountSessionController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\StoreController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');
// The EN | PT switch: remembers the choice for a year and goes back to the same page.
Route::get('/locale/{locale}', fn (string $locale) => back()->withCookie(cookie()->forever(SetLocale::COOKIE, $locale)))
    ->whereIn('locale', SetLocale::LOCALES)
    ->name('locale');
Route::get('/robots.txt', [SeoController::class, 'robots']);
Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);
Route::get('/lore', [PageController::class, 'lore'])->name('lore');
Route::get('/items', [ItemController::class, 'index'])->name('items.index');
Route::get('/items/{id}', [ItemController::class, 'show'])->whereNumber('id')->name('items.show');
Route::get('/map', [PageController::class, 'map'])->name('map');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/news', [NewsController::class, 'index'])->name('news.index');
Route::get('/news/{slug}', [NewsController::class, 'show'])->name('news.show');
Route::get('/store', [StoreController::class, 'index'])->name('store');
Route::get('/store/{slug}', [StoreController::class, 'show'])->name('store.show');
Route::post('/store/{slug}', [StoreController::class, 'buy'])->middleware('throttle:10,1')->name('store.buy');
// The Store's old addresses. /treasury itself shows the page instead of redirecting: /store used to redirect permanently to
// /treasury, browsers may remember that, and sending them back would loop.
Route::get('/treasury', [StoreController::class, 'index']);
Route::permanentRedirect('/treasury/{slug}', '/store/{slug}');
Route::permanentRedirect('/passes', '/store/kage-pass');
Route::permanentRedirect('/packages', '/store?category=packages#store');

Route::get('/account', [AccountController::class, 'show'])->name('account');
Route::get('/account/orders', [AccountController::class, 'show'])->defaults('tab', 'orders')->name('account.orders');
Route::permanentRedirect('/account/store', '/account/orders');
Route::post('/account/orders/{order}/redeem', [StoreController::class, 'redeem'])->whereNumber('order')->middleware('throttle:10,1')->name('account.orders.redeem');
Route::get('/account/security', [AccountController::class, 'show'])->defaults('tab', 'security')->name('account.security');
Route::post('/account/login', [AccountSessionController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('account.login');
// Game accounts: register (confirmed by email), forgot / reset password, change password and email.
Route::get('/register', [RegistrationController::class, 'create'])->name('register');
Route::post('/register', [RegistrationController::class, 'store'])->middleware('throttle:10,10')->name('register.store');
Route::get('/register/confirm/{token}', [RegistrationController::class, 'confirm'])->middleware('throttle:20,1')->name('register.confirm');
Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'store'])->middleware('throttle:5,10')->name('password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->middleware('throttle:20,1')->name('password.reset');
Route::post('/reset-password/{token}', [PasswordResetController::class, 'update'])->middleware('throttle:10,1')->name('password.update');
Route::post('/account/password', [AccountSecurityController::class, 'password'])->middleware('throttle:10,10')->name('account.password');
Route::post('/account/email', [AccountSecurityController::class, 'email'])->middleware('throttle:5,10')->name('account.email');
Route::get('/account/email/confirm/{token}', [AccountSecurityController::class, 'confirmEmail'])->middleware('throttle:20,1')->name('account.email.confirm');
Route::post('/account/logout', [AccountSessionController::class, 'destroy'])->name('account.logout');
