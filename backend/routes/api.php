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
use App\Http\Controllers\Scout;
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

    // Scout bots, with a service token from `php artisan board:scout-token`. Everyone else gets 403.
    Route::middleware(['service', 'abilities:scout:write', 'throttle:120,1'])->prefix('scout')->name('scout.')->group(function () {
        Route::post('/candidates', [Scout\CandidateController::class, 'store'])->name('candidates.store');
        Route::post('/seen', [Scout\SeenController::class, 'store'])->name('seen.store');
        Route::post('/seen/check', [Scout\SeenController::class, 'check'])->name('seen.check');
        Route::get('/sources', [Scout\SourceController::class, 'index'])->name('sources.index');
    });

    // The operator only. Members get 403.
    Route::middleware('operator')->prefix('operator')->name('operator.')->group(function () {
        Route::get('/deals', [Operator\DealController::class, 'index'])->name('deals.index');
        Route::post('/deals', [Operator\DealController::class, 'store'])->name('deals.store');
        Route::get('/deals/{deal}', [Operator\DealController::class, 'show'])->name('deals.show');
        Route::put('/deals/{deal}', [Operator\DealController::class, 'update'])->name('deals.update');
        Route::post('/deals/{deal}/photo', [Operator\DealController::class, 'uploadPhoto'])->name('deals.photo');
        // The pipeline. Moves that are not allowed from the deal's status return 422.
        Route::post('/deals/bulk/reject', [Operator\DealController::class, 'bulkReject'])->name('deals.bulk.reject');
        Route::post('/deals/bulk/send-to-analyst', [Operator\DealController::class, 'bulkSendToAnalyst'])->name('deals.bulk.analyst');
        Route::post('/deals/{deal}/reject', [Operator\DealController::class, 'reject'])->name('deals.reject');
        Route::post('/deals/{deal}/send-to-analyst', [Operator\DealController::class, 'sendToAnalyst'])->name('deals.analyst');
        Route::post('/deals/{deal}/analyst-result', [Operator\DealController::class, 'analystResult'])->name('deals.analyst.result');
        Route::post('/deals/{deal}/awaiting-review', [Operator\DealController::class, 'moveToReview'])->name('deals.review');
        Route::post('/deals/{deal}/publish', [Operator\DealController::class, 'publish'])->name('deals.publish');
        Route::post('/deals/{deal}/unpublish', [Operator\DealController::class, 'unpublish'])->name('deals.unpublish');
        Route::post('/deals/{deal}/pack', [Operator\DealController::class, 'generatePack'])->name('deals.pack');
        Route::delete('/deals/{deal}/pack', [Operator\DealController::class, 'removePack'])->name('deals.pack.remove');

        Route::get('/members', [Operator\MemberController::class, 'index'])->name('members.index');
        Route::post('/members', [Operator\MemberController::class, 'store'])->name('members.store');
        Route::post('/members/{user}/invited', [Operator\MemberController::class, 'markInvited'])->name('members.invited');
        Route::post('/members/{user}/active', [Operator\MemberController::class, 'markActive'])->name('members.active');
        Route::post('/members/{user}/revoke', [Operator\MemberController::class, 'revoke'])->name('members.revoke');

        Route::get('/sources', [Operator\SourceController::class, 'index'])->name('sources.index');
        Route::post('/sources', [Operator\SourceController::class, 'store'])->name('sources.store');
        Route::put('/sources/{source}', [Operator\SourceController::class, 'update'])->name('sources.update');
        Route::delete('/sources/{source}', [Operator\SourceController::class, 'destroy'])->name('sources.destroy');
    });
});
