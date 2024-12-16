<div class="navbar-header">
    @if(organisation() && organisation()->logo)
    <div class="logo">
        <a href="/">
            <img src="{{ organisation()->logo->getUrl(['width' => 80 ]) }}" alt="{{ organisation()->name }}" title="{{ organisation()->website_url }}" />
        </a>
    </div><!-- logo end -->
    @else
        <ul class="nav navbar-nav">
            <li>
                <a href="/">Home</a>
            </li>
        </ul>
    @endif
</div><!-- Navbar header end -->

<div class="site-nav-inner">
    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
        <span class="sr-only">Toggle navigatie</span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
    </button>

    <nav class="collapse navbar-collapse navbar-responsive-collapse pull-right">

        @if(organisation())
        <ul class="nav navbar-nav">


            @foreach(organisation()->series()->active()->get() as $navSeries)
                <li
                    class="@if(\Str::endsWith(url()->current(), $navSeries->getUrl())) active @endif @if(!$navSeries->hasNextEvent()) no-next-event @endif"
                >
                    <a
                        href="{{ $navSeries->getUrl() }}"
                    >
                        {{ $navSeries->name }}

                        @if($navSeries->hasNextEvent())
                            <span class="badge badge-light">{{$navSeries->countUpcomingEvents()}}</span>
                            <span class="sr-only">komende evenementen</span>
                        @endif
                    </a>


                </li>
            @endforeach


            <!--
            <li class="dropdown active">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown">Home <i class="fa fa-angle-down"></i></a>
                <ul class="dropdown-menu menu-center" role="menu">
                    <li><a href="index.html">Home One</a></li>
                    <li><a href="index-2.html">Home Two</a></li>
                    <li><a href="index-3.html">Home Three</a></li>
                    <li class="active"><a href="index-4.html">Home Four</a></li>
                    <li><a href="index-5.html">Home Five</a></li>
                    <li><a href="index-6.html">Home Six</a></li>
                </ul>
            </li>
            -->

            <li class="dropdown">
                <a class="dropdown-toggle" data-toggle="dropdown">Quizzes <i class="fa fa-angle-down"></i></a>
                <ul class="dropdown-menu menu-center" role="menu">
                    <li><a href="{{ action('EventController@calendar') }}">Kalender</a></li>
                    @if(organisation()->competitions()->count() > 0)
                        <li><a href="{{ action('CompetitionController@index') }}">Competities</a></li>
                    @endif
                    @if(organisation()->blog_url)
                        <li><a href="{{ organisation()->blog_url }}"><i class="fa fa-external-link"></i> Blog</a></li>
                    @endif
                    <!--<li><a href="https://www.quizploeg.com/"><i class="fa fa-external-link"></i> Quizzer zkt ploeg</a></li>-->
                    @if(organisation()->support_email)
                        <li><a href="mailto:{{organisation()->support_email}}"><i class="fa fa-envelope"></i> Contact</a></li>
                    @endif
                </ul>
            </li>

            @if (!Auth::guest())&nbsp;
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                        {{ Auth::user()->username }} <i class="fa fa-angle-down"></i>
                    </a>
                    <ul class="dropdown-menu menu-center" role="menu">

                        @if(Auth::user()->isAdmin())
                            <li><a href="{{ action('HomeController@admin') }}">Admin panel</a></li>
                        @endif

                        <li><a href="{{ action('GroupController@index') }}">Mijn teams</a></li>
                        <li><a href="{{ action('OrderController@index') }}">Mijn tickets</a></li>
                        <li><a href="{{ action('CatLabAccountController@redirect', [ 'myaccount' ]) }}" target="_blank" rel="noopener">Mijn account</a></li>

                        <li>
                            <a href="{{ route('logout') }}" onclick="event.preventDefault();document.getElementById('logout-form').submit();">
                                Logout
                            </a>

                            <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                                {{ csrf_field() }}
                            </form>
                        </li>
                    </ul>
                </li>
            @else
                <li><a href="{{ route('login') }}">Login</a></li>
            @endif

        </ul><!--/ Nav ul end -->
        @endif

    </nav><!--/ Collapse end -->

</div><!--/ Site nav inner end -->
