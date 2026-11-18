{{--
    The site header: logo, the quiz series and a few menus. Mobile first: on
    small screens the links fold into a panel behind the menu button. The
    account menu opens on hover (with a mouse) and on click or tap; the script
    below handles the toggling. No Bootstrap navbar classes, so the old theme styles don't apply.
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
                    @foreach($navOrganisation->series()->active()->get() as $navSeries)
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

                    @if($navOrganisation->competitions()->count() > 0)
                        <li class="kv-nav-item"><a href="{{ action('CompetitionController@index') }}">Competities</a></li>
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

<script>
    (function () {
        var header = document.getElementById('header');
        if (!header) {
            return;
        }
        var button = header.querySelector('.kv-nav-button');
        var groups = header.querySelectorAll('.kv-nav-group');

        function setGroup(group, open) {
            group.classList.toggle('is-open', open);
            group.querySelector('.kv-nav-toggle').setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        function closeGroups(except) {
            Array.prototype.forEach.call(groups, function (group) {
                if (group !== except) {
                    setGroup(group, false);
                }
            });
        }

        if (button) {
            button.addEventListener('click', function () {
                var open = !header.classList.contains('kv-nav-open');
                header.classList.toggle('kv-nav-open', open);
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }

        Array.prototype.forEach.call(groups, function (group) {
            group.querySelector('.kv-nav-toggle').addEventListener('click', function (e) {
                e.stopPropagation();
                closeGroups(group);
                setGroup(group, !group.classList.contains('is-open'));
            });
        });

        document.addEventListener('click', function (e) {
            if (!header.contains(e.target)) {
                closeGroups(null);
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeGroups(null);
            }
        });
    })();
</script>
