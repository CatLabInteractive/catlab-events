<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','GTM-5PVSCV7');</script>
    <!-- End Google Tag Manager -->

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Styles -->
    <link href="{{ asset('css/admin.css') }}" rel="stylesheet">
</head>
<body>
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5PVSCV7"
                      height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->

    <header>
    <nav class="navbar navbar-expand-md navbar-dark bg-dark">
        <a class="navbar-brand" href="{{ Auth::guest() ? url('/') : action('Admin\EventController@index') }}">Admin panel</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav mr-auto">

                <li class="nav-item"><a class="nav-link" href="{{ action('Admin\OrganisationController@edit', [ Auth::user()->getActiveOrganisation()->id ]) }}">Organisation</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ action('Admin\EventController@index') }}">Events</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ action('Admin\VenueController@index') }}">Venues</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ action('Admin\CompetitionController@index') }}">Competitions</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ action('Admin\SeriesController@index') }}">Series</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ action('Admin\PeopleController@index') }}">People</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ action('Admin\LiveStreamController@index') }}">Livestreams</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ action('Admin\UitDbController@index') }}">UitDB</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ action('Admin\AssetController@index') }}">Assets</a></li>
            </ul>

            <!-- Right Side Of Navbar -->
            <ul class="nav navbar-nav navbar-right">
                <!-- Authentication Links -->
                @if (Auth::guest())
                    <li class="nav-item"><a href="{{ route('login') }}">Login</a></li>
                    <li class="nav-item"><a href="{{ route('register') }}">Register</a></li>
                @else
                    <li class="nav-item dropdown">

                        <a href="#" class="dropdown-toggle nav-link" data-toggle="dropdown" role="button" aria-expanded="false">
                            {{ Auth::user()->getActiveOrganisation()->name }} <span class="caret"></span>
                        </a>

                        <ul class="dropdown-menu" role="menu">

                            @foreach(Auth::user()->organisations()->get() as $organisation)
                                <li>
                                    @if($organisation->id !== Auth::user()->getActiveOrganisation()->id)
                                        <a class="dropdown-item" href="{{ action('Admin\EventController@index', [ 'switchOrganisations' => $organisation->id ]) }}">
                                            {{ $organisation->name }}
                                        </a>
                                    @endif
                                </li>
                            @endforeach

                            <li>
                                <a class="dropdown-item" href="{{ route('logout') }}"
                                   onclick="event.preventDefault();
                                                     document.getElementById('logout-form').submit();">
                                    Logout
                                </a>

                                <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                                    {{ csrf_field() }}
                                </form>
                            </li>
                        </ul>
                    </li>
                @endif
            </ul>
        </div>
    </nav>
    </header>

    <main role="main">

        <section class="container-fluid">

            @if (Session::has('message'))
                <div class="alert alert-info">{{ Session::get('message') }}</div>
            @endif

            @yield('content')
        </section>

    </main>

    <!-- Scripts -->
    <script src="{{ asset('js/admin.js') }}"></script>
</body>
</html>
