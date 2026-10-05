<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\InviteRequestController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\Operator;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TeaserController;
use Illuminate\Support\Facades\Route;

// JSON for the Nuxt site. Auth is a Sanctum personal access token sent as a Bearer token.

// Public.
Route::get('/me', MeController::class)->name('me');
Route::get('/teasers', TeaserController::class)->name('teasers');
Route::get('/photos/{file}', PhotoController::class)->name('photos.show');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login');
Route::post('/invite-requests', [InviteRequestController::class, 'store'])->middleware('throttle:5,1')->name('invite-requests.store');
Route::get('/invite/{token}', [InvitationController::class, 'show'])->name('invite.show');
Route::post('/invite/{token}', [InvitationController::class, 'store'])->middleware('throttle:10,1')->name('invite.accept');

// Any signed-in user, including invited members waiting to be activated.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/account', AccountController::class)->name('account');
    Route::put('/settings', [SettingsController::class, 'update'])->middleware('throttle:10,1')->name('settings.update');

    // Active members and the operator. Drafts return 404 to members.
    Route::middleware('board')->group(function () {
        Route::get('/board', BoardController::class)->name('board');
        Route::get('/deals/{deal}', [DealController::class, 'show'])->name('deals.show');
        Route::get('/deals/{deal}/pack', [DealController::class, 'pack'])->name('deals.pack');
    });

    // The operator only. Members get 403.
    Route::middleware('operator')->prefix('operator')->name('operator.')->group(function () {
        Route::get('/deals', [Operator\DealController::class, 'index'])->name('deals.index');
        Route::post('/deals', [Operator\DealController::class, 'store'])->name('deals.store');
        Route::get('/deals/{deal}', [Operator\DealController::class, 'show'])->name('deals.show');
        Route::put('/deals/{deal}', [Operator\DealController::class, 'update'])->name('deals.update');
        Route::post('/deals/{deal}/photo', [Operator\DealController::class, 'uploadPhoto'])->name('deals.photo');
        Route::post('/deals/{deal}/publish', [Operator\DealController::class, 'publish'])->name('deals.publish');
        Route::post('/deals/{deal}/unpublish', [Operator\DealController::class, 'unpublish'])->name('deals.unpublish');
        Route::post('/deals/{deal}/pack', [Operator\DealController::class, 'generatePack'])->name('deals.pack');
        Route::delete('/deals/{deal}/pack', [Operator\DealController::class, 'removePack'])->name('deals.pack.remove');

        Route::get('/members', [Operator\MemberController::class, 'index'])->name('members.index');
        Route::post('/members', [Operator\MemberController::class, 'store'])->name('members.store');
        Route::post('/members/{user}/invited', [Operator\MemberController::class, 'markInvited'])->name('members.invited');
        Route::post('/members/{user}/active', [Operator\MemberController::class, 'markActive'])->name('members.active');
        Route::post('/members/{user}/revoke', [Operator\MemberController::class, 'revoke'])->name('members.revoke');
    });
});
