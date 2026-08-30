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

    <button class="sidebar-toggle" onclick="document.querySelector('.admin-sidebar').classList.toggle('open'); document.querySelector('.sidebar-overlay').classList.toggle('open');">
        &#9776;
    </button>

    <div class="sidebar-overlay" onclick="document.querySelector('.admin-sidebar').classList.remove('open'); this.classList.remove('open');"></div>

    <div class="admin-wrapper">
        <nav class="admin-sidebar">
            <div class="sidebar-brand">
                <a href="{{ Auth::guest() ? url('/') : action('Admin\EventController@index') }}">Admin panel</a>
            </div>

            @if (Auth::guest())
                <ul class="sidebar-nav">
                    <li><a href="{{ route('login') }}">Login</a></li>
                    <li><a href="{{ route('register') }}">Register</a></li>
                </ul>
            @else
                @php($navItem = fn (string $path, string $url, string $label) =>
                    '<li' . (request()->is('admin/' . $path, 'admin/' . $path . '/*') ? ' class="active"' : '') .
                    '><a href="' . e($url) . '">' . e($label) . '</a></li>')

                <ul class="sidebar-nav sidebar-section sidebar-section-programme">
                    <li class="sidebar-heading">Programme</li>
                    {!! $navItem('events', action('Admin\EventController@index'), 'Events') !!}
                    {!! $navItem('series', action('Admin\SeriesController@index'), 'Series') !!}
                    {!! $navItem('competitions', action('Admin\CompetitionController@index'), 'Competitions') !!}
                </ul>

                <ul class="sidebar-nav sidebar-section">
                    <li class="sidebar-heading">Venues &amp; people</li>
                    {!! $navItem('venues', action('Admin\VenueController@index'), 'Venues') !!}
                    {!! $navItem('people', action('Admin\PeopleController@index'), 'People') !!}
                </ul>

                <ul class="sidebar-nav sidebar-section">
                    <li class="sidebar-heading">Sales</li>
                    {!! $navItem('orders', action('Admin\OrderController@index'), 'Orders') !!}
                </ul>

                <ul class="sidebar-nav sidebar-section">
                    <li class="sidebar-heading">Media</li>
                    {!! $navItem('livestreams', action('Admin\LiveStreamController@index'), 'Livestreams') !!}
                    {!! $navItem('assets', action('Admin\AssetController@index'), 'Assets') !!}
                </ul>

                <ul class="sidebar-nav sidebar-section">
                    <li class="sidebar-heading">Settings</li>
                    @if(Auth::user()->getActiveOrganisation())
                        {!! $navItem('organisations', action('Admin\OrganisationController@edit', [ Auth::user()->getActiveOrganisation()->id ]), 'Organisation') !!}
                    @endif
                    {!! $navItem('uitdb', action('Admin\UitDbController@index'), 'UitDB') !!}
                </ul>

                <div class="sidebar-footer">
                    @php($activeOrganisation = Auth::user()->getActiveOrganisation())
                    @if($activeOrganisation)
                        <div class="sidebar-organisation-select">
                            <label for="organisation-selector">Organisation</label>
                            <select id="organisation-selector" class="form-control" onchange="if(this.value) window.location.href=this.value;">
                                @foreach(Auth::user()->organisations()->get() as $organisation)
                                    <option value="{{ action('Admin\EventController@index', [ 'switchOrganisations' => $organisation->id ]) }}"
                                        @if($organisation->id === $activeOrganisation->id) selected @endif>
                                        {{ $organisation->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <ul class="sidebar-nav">
                        <li>
                            <a href="{{ route('logout') }}"
                               onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                Logout
                            </a>

                            <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                                {{ csrf_field() }}
                            </form>
                        </li>
                    </ul>
                </div>
            @endif
        </nav>

        <main class="admin-content" role="main">
            @if (Session::has('message'))
                <div class="alert alert-info">{{ Session::get('message') }}</div>
            @endif

            @yield('content')
        </main>
    </div>

    <!-- Scripts -->
    <script src="{{ asset('js/admin.js') }}"></script>
</body>
</html>
