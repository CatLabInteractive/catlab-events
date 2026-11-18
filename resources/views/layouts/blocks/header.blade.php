{{--
    The site header: logo, the quiz series and a few menus. Mobile first: on
    small screens the links fold into a panel behind the menu button. The
    account menu opens on hover (with a mouse) and on click or tap; kv.js
    handles the toggling. No Bootstrap navbar classes, so the old theme styles don't apply.
--}}
<?php $navOrganisation = organisation(); ?>
<header id="header" class="kv-header">
    <div class="kv-header-inner">

        <a class="kv-header-logo" href="/">
            @if($navOrganisation && $navOrganisation->logo)
                <img src="{{ $navOrganisation->logo->getUrl(['width' => 104 ]) }}" alt="{{ $navOrganisation->name }}" width="52" />
            @else
                <span>{{ $navOrganisation ? $navOrganisation->name : 'Home' }}</span>
            @endif
        </a>

        @if($navOrganisation)
            <button type="button" class="kv-nav-button" aria-expanded="false" aria-controls="kv-nav">
                <span class="kv-nav-button-bars" aria-hidden="true"><i></i><i></i><i></i></span>
                <span class="sr-only">Menu</span>
            </button>

            <nav class="kv-nav" id="kv-nav" aria-label="Hoofdmenu">
                <ul class="kv-nav-list">
                    @foreach($navOrganisation->series()->active()->with('organisation')->get() as $navSeries)
                        <?php $navActive = \Str::endsWith(url()->current(), $navSeries->getUrl()); ?>
                        <li class="kv-nav-item {{ $navSeries->getColourClass() }} @if($navActive) is-active @endif @if(!$navSeries->hasNextEvent()) is-quiet @endif">
                            <a href="{{ $navSeries->getUrl() }}" @if($navActive) aria-current="page" @endif>
                                <i class="kv-nav-dot" aria-hidden="true"></i>
                                {{ $navSeries->name }}
                                @if($navSeries->hasNextEvent())
                                    <span class="kv-nav-count">{{ $navSeries->countUpcomingEvents() }}<span class="sr-only"> komende quizzen</span></span>
                                @endif
                            </a>
                        </li>
                    @endforeach

                    <li class="kv-nav-item"><a href="{{ action('EventController@calendar') }}">Kalender</a></li>

                    {{-- CMS pages marked "toon in menu", in their own order. --}}
                    @foreach(app(\App\Cms\Menu::class)->items($navOrganisation, $cmsLocale ?? null) as $navPage)
                        <?php $navActive = rtrim(url()->current(), '/') === $navPage['url']; ?>
                        <li class="kv-nav-item @if($navActive) is-active @endif"><a href="{{ $navPage['url'] }}" @if($navActive) aria-current="page" @endif>{{ $navPage['title'] }}</a></li>
                    @endforeach

                    {{-- The organisation's own blog, only once it has posts (no external links in the menu). --}}
                    @php($navBlogUrl = app(\App\Cms\Blog::class)->navigationUrl($navOrganisation, $cmsLocale ?? null))
                    @if($navBlogUrl)
                        <li class="kv-nav-item"><a href="{{ $navBlogUrl }}">{{ __('cms.blog') }}</a></li>
                    @endif

                    @if(!Auth::guest())
                        <li class="kv-nav-item kv-nav-group">
                            <button type="button" class="kv-nav-toggle" aria-expanded="false">
                                {{ Auth::user()->username }} <i class="kv-nav-caret" aria-hidden="true"></i>
                            </button>
                            <ul class="kv-nav-sub">
                                @if(Auth::user()->isAdmin())
                                    <li><a href="{{ action('HomeController@admin') }}">Admin panel</a></li>
                                @endif
                                <li><a href="{{ action('GroupController@index') }}">Mijn teams</a></li>
                                <li><a href="{{ action('OrderController@index') }}">Mijn tickets</a></li>
                                <li><a href="{{ action('CatLabAccountController@redirect', [ 'myaccount' ]) }}" target="_blank" rel="noopener">Mijn account</a></li>
                                <li>
                                    <form action="{{ route('logout') }}" method="POST">
                                        {{ csrf_field() }}
                                        <button type="submit" class="kv-nav-sub-button">Logout</button>
                                    </form>
                                </li>
                            </ul>
                        </li>
                    @else
                        <li class="kv-nav-item"><a href="{{ route('login') }}">Login</a></li>
                    @endif
                </ul>
            </nav>
        @endif

    </div>
</header>

