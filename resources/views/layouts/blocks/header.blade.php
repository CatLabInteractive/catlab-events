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
                    {{--
                        The CMS pages marked "toon in menu", in their order; a page can be a
                        link to the quiz series, the calendar, the blog (once it has posts)
                        or any URL. Fixed parts without such a page keep their default spot
                        (App\Cms\Menu::entries()). Subpages open as a dropdown under their page.
                    --}}
                    @php($navBlogUrl = app(\App\Cms\Blog::class)->navigationUrl($navOrganisation, $cmsLocale ?? null))
                    @foreach(app(\App\Cms\Menu::class)->entries($navOrganisation, $cmsLocale ?? null, $navBlogUrl) as $navEntry)
                        @if($navEntry['type'] === 'series')
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
                        @else
                            <?php $navPage = $navEntry['page']; $navActive = rtrim(url()->current(), '/') === $navPage['url']; ?>
                            @if(empty($navPage['children']))
                                <li class="kv-nav-item @if($navActive) is-active @endif"><a href="{{ $navPage['url'] }}" @if($navActive) aria-current="page" @endif>{{ $navPage['title'] }}</a></li>
                            @else
                                <?php $navChildActive = in_array(rtrim(url()->current(), '/'), array_column($navPage['children'], 'url'), true); ?>
                                <li class="kv-nav-item kv-nav-group kv-nav-pages @if($navActive || $navChildActive) is-active @endif">
                                    <a href="{{ $navPage['url'] }}" @if($navActive) aria-current="page" @endif>{{ $navPage['title'] }}</a>
                                    <button type="button" class="kv-nav-toggle" aria-expanded="false">
                                        <i class="kv-nav-caret" aria-hidden="true"></i><span class="sr-only">{{ $navPage['title'] }}: meer</span>
                                    </button>
                                    <ul class="kv-nav-sub">
                                        @foreach($navPage['children'] as $navChild)
                                            <?php $navActive = rtrim(url()->current(), '/') === $navChild['url']; ?>
                                            <li><a href="{{ $navChild['url'] }}" @if($navActive) aria-current="page" @endif>{{ $navChild['title'] }}</a></li>
                                        @endforeach
                                    </ul>
                                </li>
                            @endif
                        @endif
                    @endforeach

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

