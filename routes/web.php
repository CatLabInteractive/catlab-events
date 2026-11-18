<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use App\Tools\SlackNotifier;


// Do we have catlab client id? (my own personal single sign on service)
if (config('services.catlab.client_id')) {
    // CatLab accounts login (same paths as LoginController::setRoutes()),
    // with remember-me always on: see App\Http\Controllers\Auth\CatLabLoginController.
    Route::get('/login', '\CatLab\Accounts\Client\Controllers\LoginController@login')->name('login');
    Route::get('/login/callback', '\App\Http\Controllers\Auth\CatLabLoginController@postLogin');
    Route::post('/logout', '\CatLab\Accounts\Client\Controllers\LoginController@logout')->name('logout');
} else {
    // Not set? Use default laravel authentication.
    Auth::routes();
}

//Route::resource('organisations', 'OrganisationController');

// The 'live' route
Route::get('/livestreams', 'LiveStreamController@index');
Route::get('/livestreams/{identifier}', 'LiveStreamController@view')->name('livestream_view');
Route::get('/livestreams/{identifier}/poll', 'LiveStreamController@poll');
Route::get('/livestreams/{identifier}/login', 'LiveStreamController@viewLogin')->middleware('auth');
Route::get('/livestreams/{identifier}/rocketchat', 'LiveStreamController@getRocketAuthToken')->middleware('auth');
Route::get('/livestreams/{identifier}/uitpas', 'LiveStreamController@uitPASCheckin');
Route::post('/livestreams/{identifier}/uitpas', 'LiveStreamController@processUitPASCheckin');

Route::domain('live.{domain}')->group(function() {
    Route::get('/', 'LiveStreamController@index')->where('domain', '.*');
    Route::get('/{identifier}', 'LiveStreamController@view')->where('domain', '.*');

    Route::get('{any}', 'LiveStreamController@index')
        ->where('any', '.*')
        ->where('domain', '.*');
});

Route::get('/', 'EventController@index');
Route::get('/events', 'EventController@index');

Route::get('press', 'HomeController@press');

Route::get('donate', 'DonateController@donate');
Route::get('doneer', 'DonateController@donate');
Route::post('donate/callback', 'DonateController@callback');

Route::group([

    'middleware' => [ 'auth' ]

], function() {

    Route::get('/home', 'HomeController@home');
    Route::get('/admin', 'HomeController@admin');

    Route::group([

            'prefix' => 'admin',
            'middleware' => [ 'admin' ],

        ],
        function() {
            \App\Http\Controllers\Admin\OrganisationController::routes('organisations', 'Admin\OrganisationController', 'organisation');
            \App\Http\Controllers\Admin\CompetitionController::routes('competitions', 'Admin\CompetitionController', 'competition');
            \App\Http\Controllers\Admin\SeriesController::routes('series', 'Admin\SeriesController', 'series');
            \App\Http\Controllers\Admin\EventController::routes('events', 'Admin\EventController', 'event');

            \App\Http\Controllers\Admin\VenueController::routes('venues', 'Admin\VenueController', 'venue');
            \App\Http\Controllers\Admin\PeopleController::routes('people', 'Admin\PeopleController', 'person');
            \App\Http\Controllers\Admin\LiveStreamController::routes('livestreams', 'Admin\LiveStreamController', 'livestream');
            \App\Http\Controllers\Admin\OrderController::routes('orders', 'Admin\OrderController', 'order');

            \App\Http\Controllers\Admin\TicketCategoryController::routes('events/{event}/ticketCategories', 'Admin\TicketCategoryController', 'ticketCategory');
            \App\Http\Controllers\Admin\EventDateController::routes('events/{event}/eventDates', 'Admin\EventDateController', 'id');
            \App\Http\Controllers\Admin\SeriesPhotoController::routes('series/{series}/photos', 'Admin\SeriesPhotoController', 'id');

            Route::get('/livestreams/{id}/generateUrls', 'Admin\LiveStreamController@generateUrlsForm');
            Route::post('/livestreams/{id}/generateUrls', 'Admin\LiveStreamController@processGenerateUrls');

            Route::get('assets', 'Admin\AssetController@index');
            Route::post('assets', 'Admin\AssetController@upload');

            // CMS pages and blog posts, each with its translations (the content per language)
            \App\Http\Controllers\Admin\PageController::routes('pages', 'Admin\PageController');
            \App\Http\Controllers\Admin\PageTranslationController::routes('pages/{page}/translations', 'Admin\PageTranslationController');
            \App\Http\Controllers\Admin\PostController::routes('posts', 'Admin\PostController');
            \App\Http\Controllers\Admin\PostTranslationController::routes('posts/{post}/translations', 'Admin\PostTranslationController');

            // Image picker and uploads of the CMS widgets (json)
            \App\Http\Controllers\Admin\CmsAssetController::routes();

            Route::get('uitdb', 'Admin\UitDbController@index');
            Route::get('uitdb/connect', 'Admin\UitDbController@link');
            Route::get('uitdb/disconnect', 'Admin\UitDbController@unlink');
            Route::get('uitdb/connect/next', 'Admin\UitDbController@afterLink');

            Route::get('orders/{id}/refund', 'Admin\RefundController@refund');
            Route::post('orders/{id}/refund', 'Admin\RefundController@processRefund');

            Route::get('events/{event}/waitinglist', 'Admin\WaitingListController@index');
            Route::get('events/{event}/waitinglist/invite/{user}', 'Admin\WaitingListController@invite');
            Route::post('events/{event}/waitinglist/invite/{user}', 'Admin\WaitingListController@sendInvite');
            Route::post('events/{event}/waitinglist/invite/{user}/generate', 'Admin\WaitingListController@generateInvite');
            Route::get('events/{event}/waitinglist/invite/{user}/edit', 'Admin\WaitingListController@editInvite');
            Route::get('events/{event}/waitinglist/mass-invite', 'Admin\WaitingListController@massInvite');
            Route::post('events/{event}/waitinglist/mass-invite', 'Admin\WaitingListController@sendMassInvite');

            // Route constraints for the mail types, from one list.
            $allEmailTypes = \App\Services\EmailTemplates::routePattern();
            $eventEmailTypes = \App\Services\EmailTemplates::routePattern(\App\Services\EmailTemplates::EVENT_TYPES);

            Route::get('emails', 'Admin\EmailTemplateController@index');

            // The library of named templates. Before the `emails/{type}` routes.
            Route::get('emails/templates', 'Admin\EmailLibraryController@index');
            Route::get('emails/templates/create', 'Admin\EmailLibraryController@create');
            Route::post('emails/templates', 'Admin\EmailLibraryController@store');
            Route::match([ 'get', 'post' ], 'emails/templates/create/preview', 'Admin\EmailLibraryController@previewNew');
            Route::get('emails/templates/{id}/edit', 'Admin\EmailLibraryController@edit');
            Route::match([ 'get', 'post' ], 'emails/templates/{id}/preview', 'Admin\EmailLibraryController@preview');
            Route::post('emails/templates/{id}/delete', 'Admin\EmailLibraryController@destroy');
            Route::post('emails/templates/{id}', 'Admin\EmailLibraryController@update');

            // The mail layout. Before the `emails/{type}` routes.
            Route::get('emails/layout', 'Admin\EmailLayoutController@edit');
            Route::post('emails/layout', 'Admin\EmailLayoutController@update');
            Route::match([ 'get', 'post' ], 'emails/layout/preview', 'Admin\EmailLayoutController@preview');
            Route::post('emails/layout/reset', 'Admin\EmailLayoutController@reset');

            Route::get('emails/{type}', 'Admin\EmailTemplateController@show')->where('type', $allEmailTypes);
            Route::match([ 'get', 'post' ], 'emails/{type}/preview', 'Admin\EmailTemplateController@preview')->where('type', $allEmailTypes);
            Route::get('emails/{type}/edit', 'Admin\EmailTemplateController@edit')->where('type', $allEmailTypes);
            Route::post('emails/{type}', 'Admin\EmailTemplateController@update')->where('type', $allEmailTypes);
            Route::post('emails/{type}/reset', 'Admin\EmailTemplateController@reset')->where('type', $allEmailTypes);

            // One event's own choice per mail.
            Route::get('events/{event}/emails', 'Admin\EventEmailController@index');
            Route::get('events/{event}/emails/{type}', 'Admin\EventEmailController@edit')->where('type', $eventEmailTypes);
            Route::post('events/{event}/emails/{type}', 'Admin\EventEmailController@update')->where('type', $eventEmailTypes);
            Route::post('events/{event}/emails/{type}/template', 'Admin\EventEmailController@template')->where('type', $eventEmailTypes);
            Route::post('events/{event}/emails/{type}/reset', 'Admin\EventEmailController@reset')->where('type', $eventEmailTypes);
            Route::match([ 'get', 'post' ], 'events/{event}/emails/{type}/preview', 'Admin\EventEmailController@preview')->where('type', $eventEmailTypes);

            Route::get('events/{event}/guests', 'Admin\GuestRegistrationController@index');
            Route::post('events/{event}/guests', 'Admin\GuestRegistrationController@store');
            Route::post('events/{event}/guests/{order}/cancel', 'Admin\GuestRegistrationController@cancel');

            Route::get('events/{id}/export/members', 'Admin\EventController@exportMembers');
            Route::get('events/{id}/export/sales', 'Admin\EventController@exportSales');
            Route::get('events/{id}/export/clearing', 'Admin\EventController@exportClearing');
            Route::get('events/{id}/export/salesovertime', 'Admin\EventController@exportSalesTimeline');
        }
    );

    \App\Http\Controllers\GroupController::routes('groups', 'GroupController', 'group');
    Route::get('groups/{id}/merge', 'GroupController@mergeGroup');
    Route::post('groups/{id}/merge', 'GroupController@confirmMergeGroup');
    Route::post('groups/{id}/merge/confirm', 'GroupController@processMergeGroup');

    \App\Http\Controllers\GroupMemberController::routes('groups/{group}/members', 'GroupMemberController', 'member');

    Route::get('invitations/{id}/{token}/accept', 'GroupController@acceptInvitation');

});

Route::get('invitations/{id}/{token}', 'GroupController@viewInvitation');

// Public routes
Route::get('organisations/{organisationId}', 'EventController@fromPublisher');
Route::get('events/register', 'EventController@registerIndex');
Route::get('archive', 'EventController@archive');
Route::get('calendar', 'EventController@calendar');
Route::get('events/{id}', 'EventController@view');
// Readable event links: /events/86/quiz-in-cafe-sekuurne. The slug is cosmetic; the
// lookahead keeps the other /events/{event}/... pages (scores, register, ...) theirs.
Route::get('events/{id}/{slug}', 'EventController@view')
    ->where('slug', '(?!(scores|waitinglist|register|register-auth)$)[a-z0-9-]+')
    ->name('event_view');
Route::get('s/{series}/{slug?}', 'SeriesController@view');
Route::get('e/{event}/{slug?}', 'EventController@view');
Route::get('events/{event}/scores', 'EventController@scores');
Route::get('venues/{id}', 'EventController@fromVenue');
Route::get('competitions', 'CompetitionController@index');
Route::get('competitions/{id}', 'CompetitionController@show');
Route::get('author/{id}/{slug?}', 'AuthorController@view');

Route::get('events/{event}/waitinglist', 'WaitingListController@waitingList');
Route::get('events/{event}/waitinglist/subscribe', 'WaitingListController@addToWaitinglist')->middleware([ 'auth' ]);

// One-page register form: public, guest checkout (spec 2026-11-17).
Route::get('events/{event}/register', 'EventController@selectTicketCategory');
Route::post('events/{event}/register', 'EventController@storeRegistration')
    ->middleware([ 'throttle:10,1' ]);

// Old per-category links: the one-page form with the category preselected.
Route::get('events/{event}/register/{ticketCategoryId}', 'EventController@register');
// The old team-select step posted here; a page still open at deploy lands
// on the new form instead of a 405.
Route::post('events/{event}/register/{ticketCategoryId}', 'EventController@register');

Route::get('status', 'StatusController@status')
    ->name('status');

Route::group([
    'middleware' => [ 'auth' ],
], function() {

    Route::get('events/{event}/register-auth', 'EventController@authSelectTicketCategory');
    Route::post('events/{event}/register/{ticketCategoryId}/process', 'EventController@processRegister');

    Route::get('orders', 'OrderController@index');
    Route::get('orders/{id}', 'OrderController@view');
    Route::get('orders/{id}/claim', 'OrderController@claim');

    Route::get('catlabaccount/{remotePath?}', 'CatLabAccountController@redirect')
        ->where('remotePath', '(.*)');

});

// Session-less by design (accounts' callback / the payment return URL that is
// often opened on another device); both carry a per-order signature.
Route::get('orders/{id}/sync', 'OrderController@sync');
Route::get('orders/{id}/thanks', 'OrderController@thanks');
Route::get('orders/{id}/calendar.ics', 'OrderController@calendar');

Route::get('documents/nl/privacy', 'DocumentController@privacy');
Route::get('documents/nl/tos', 'DocumentController@tos');

Route::get('docs', 'SwaggerController@swagger');

Route::get('sitemap.xml', 'SitemapController@sitemap');

// routes
\App\Http\Controllers\ReferenceController::routes();

// ---------------------------------------------------------------------------
// CMS (pages, blog). MUST stay last: the page route is a catch-all and any
// route registered after it is shadowed. New top-level paths also belong in
// config/cms.php 'reserved_slugs' (ReservedSlugsTest enforces both).
// ---------------------------------------------------------------------------
\App\Http\Controllers\PageController::routes();
// Every method, not just GET (Route::fallback): a POST to an unknown path
// stays a 404 instead of a 405 caused by the GET-only fallback matching it.
Route::any('{fallbackPlaceholder}', 'RedirectController@fallback')
    ->where('fallbackPlaceholder', '.*')
    ->fallback();
