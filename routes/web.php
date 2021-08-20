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
use CatLab\CharonFrontend\Controllers\FrontCrudController;


// Do we have catlab client id? (my own personal single sign on service)
if (config('services.catlab.client_id')) {
    \CatLab\Accounts\Client\Controllers\LoginController::setRoutes();
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
            FrontCrudController::routes('organisations', 'Admin\OrganisationController', 'organisation');
            FrontCrudController::routes('competitions', 'Admin\CompetitionController', 'competition');
            FrontCrudController::routes('series', 'Admin\SeriesController', 'series');
            \App\Http\Controllers\Admin\EventController::routes('events', 'Admin\EventController', 'event');

            FrontCrudController::routes('venues', 'Admin\VenueController', 'venue');
            FrontCrudController::routes('people', 'Admin\PeopleController', 'person');
            FrontCrudController::routes('livestreams', 'Admin\LiveStreamController', 'livestream');
            FrontCrudController::routes('events/{event}/ticketCategories', 'Admin\TicketCategoryController', 'ticketCategory');
            FrontCrudController::routes('events/{event}/dates', 'Admin\EventDateController', 'eventDate');

            Route::get('/livestreams/{id}/generateUrls', 'Admin\LiveStreamController@generateUrlsForm');
            Route::post('/livestreams/{id}/generateUrls', 'Admin\LiveStreamController@processGenerateUrls');

            Route::get('assets', 'Admin\AssetController@index');
            Route::post('assets', 'Admin\AssetController@upload');

            Route::get('uitdb', 'Admin\UitDbController@index');
            Route::get('uitdb/connect', 'Admin\UitDbController@link');
            Route::get('uitdb/disconnect', 'Admin\UitDbController@unlink');
            Route::get('uitdb/connect/next', 'Admin\UitDbController@afterLink');

            Route::get('events/{event}/export/members', 'Admin\EventController@exportMembers');
            Route::get('events/{event}/export/sales', 'Admin\EventController@exportSales');
            Route::get('events/{event}/export/clearing', 'Admin\EventController@exportClearing');
            Route::get('events/{event}/export/salesovertime', 'Admin\EventController@exportSalesTimeline');
        }
    );

    FrontCrudController::routes('groups', 'GroupController', 'group');
    Route::get('groups/{id}/merge', 'GroupController@mergeGroup');
    Route::post('groups/{id}/merge', 'GroupController@confirmMergeGroup');
    Route::post('groups/{id}/merge/confirm', 'GroupController@processMergeGroup');

    FrontCrudController::routes('groups/{group}/members', 'GroupMemberController', 'member');

    Route::get('invitations/{id}/{token}/accept', 'GroupController@acceptInvitation');

});

Route::get('invitations/{id}/{token}', 'GroupController@viewInvitation');

// Public routes
Route::get('organisations/{organisationId}', 'EventController@fromPublisher');
Route::get('events/register', 'EventController@registerIndex');
Route::get('archive', 'EventController@archive');
Route::get('calendar', 'EventController@calendar');
Route::get('events/{id}', 'EventController@view');
Route::get('s/{series}/{slug?}', 'SeriesController@view');
Route::get('e/{event}/{slug?}', 'EventController@view');
Route::get('events/{event}/scores', 'EventController@scores');
Route::get('venues/{id}', 'EventController@fromVenue');
Route::get('competitions', 'CompetitionController@index');
Route::get('competitions/{id}', 'CompetitionController@show');
Route::get('author/{id}/{slug?}', 'AuthorController@view');

Route::get('events/{event}/waitinglist', 'WaitingListController@waitingList');
Route::get('events/{event}/waitinglist/subscribe', 'WaitingListController@addToWaitinglist')->middleware([ 'auth' ]);

Route::get('events/{event}/register', 'EventController@selectTicketCategory');

Route::get('orders/{id}/thanks', 'OrderController@thanks');

Route::group([
    'middleware' => [ 'auth' ],
], function() {

    Route::get('events/{event}/register-auth', 'EventController@authSelectTicketCategory');
    Route::get('events/{event}/register/{ticketCategoryId}', 'EventController@register');

    Route::post('events/{event}/register/{ticketCategoryId}', 'EventController@confirmRegister');
    Route::post('events/{event}/register/{ticketCategoryId}/process', 'EventController@processRegister');

    Route::get('events/{event}/waitinglist/view', 'WaitingListController@viewList')->middleware([ 'admin' ]);
    Route::get('events/{event}/waitinglist/generate/{user}', 'WaitingListController@generateAccessToken')->middleware([ 'admin' ]);

    Route::get('orders', 'OrderController@index');
    Route::get('orders/{id}', 'OrderController@view');

    Route::get('catlabaccount/{remotePath?}', 'CatLabAccountController@redirect')
        ->where('remotePath', '(.*)');

});

Route::get('orders/{id}/sync', 'OrderController@sync');

Route::get('documents/nl/privacy', 'DocumentController@privacy');
Route::get('documents/nl/tos', 'DocumentController@tos');

Route::get('docs', 'SwaggerController@swagger');

Route::get('sitemap.xml', 'SitemapController@sitemap');

// routes
\App\Http\Controllers\ReferenceController::routes();
