<footer id="footer" class="footer text-center">
    <div class="container">
        <div class="row">
            <div class="col-md-12">

                <div class="footer-menu">
                    <!--<p>Quizreeksen</p>-->

                    <ul class="nav unstyled">
                        <li>
                            <a href="{{ action('EventController@calendar') }}"><i class="fa fa-calendar"></i> Kalender</a>
                        </li>

                        <!--
                        <li>
                            <a href="{{ action('EventController@registerIndex') }}"><i class="fa fa-ticket"></i> Koop tickets</a>
                        </li>
                        -->

                        <li>
                            <a href="{{ action('CatLabAccountController@redirect', [ 'myaccount' ]) }}" target="_blank" rel="noopener"><i class="fa fa-user"></i> Mijn account</a>
                        </li>

                        <li>
                            <a href="{{ action('OrderController@index') }}"><i class="fa fa-ticket"></i> Mijn tickets</a>
                        </li>
                    </ul>

                    <br />

                    <ul class="nav unstyled">
                        @foreach(organisation()->series()->active()->get() as $navSeries)
                            <li>
                                <a href="{{ $navSeries->getUrl() }}">{{ $navSeries->name }}</a>
                            </li>
                        @endforeach
                    </ul>
                    <br />

                    <!--
                    <p>Meer {{ organisation()->name }}</p>
                    <ul class="nav unstyled">
                        <li>
                            <a href="https://www.quizploeg.com/">Quizzer zkt. ploeg</a>
                        </li>
                    </ul>
                    -->
                </div>

                {{--
                @if(organisation()->logo)
                    <div class="footer-logo">
                        <img class="lazy" data-src="{{ organisation()->logo->getUrl([ 'width' => 150 ]) }}" alt="{{organisation()->name}} Logo" title="{{organisation()->name }}" />
                    </div>
                @endif
                --}}

                <div class="footer-social">
                    <ul>
                        @if(organisation()->facebook_url)
                            <li><a href="{{organisation()->facebook_url}}" title="{{organisation()->name}} Facebook"><i class="fa fa-facebook"></i></a></li>
                        @endif

                        @if(organisation()->twitter_url)
                            <li><a href="{{organisation()->twitter_url}}" title="{{organisation()->name}} Twitter"><i class="fa fa-twitter"></i></a></li>
                        @endif

                        @if(organisation()->youtube_url)
                            <li><a href="{{organisation()->youtube_url}}" title="{{organisation()->name}} YouTube channel"><i class="fa fa-youtube"></i></a></li>
                        @endif

                        @if(organisation()->instagram_url)
                            <li><a href="{{organisation()->instagram_url}}" title="{{organisation()->name}} Instagram"><i class="fa fa-instagram"></i></a></li>
                        @endif

                        @if(organisation()->googleplus_url)
                            <li><a href="{{organisation()->googleplus_url}}" title="{{organisation()->name}} Google+"><i class="fa fa-google-plus"></i></a></li>
                        @endif

                        @if(organisation()->linkedin_url)
                            <li><a href="{{organisation()->linkedin_url}}" title="{{organisation()->name}} LinkedIn"><i class="fa fa-linkedin"></i></a></li>
                        @endif
                    </ul>
                </div>

                <div class="copyright-info">


                    <p>
                        Copyright © 2016-{{ date('y') }} @if(organisation()->website_url)
                                <a target="_blank" href="{{organisation()->website_url}}">{{organisation()->getLegalName()}}</a>@else{{organisation()->getLegalName()}}@endif.
                        <br />
                        Website en ticketsysteem aangeboden door <a href="{{ config('app.owner.url') }}">{{ config('app.owner.name') }}</a>.<br />
                        <a href="http://www.quizwitz.com/">QuizWitz</a> is een geregistreerd merk van <a href="https://www.catlab.eu/">CatLab Interactive</a>.<br />
                        Lees ons <a href="{{ action('DocumentController@privacy') }}">Privacy beleid</a> en
                        <a href="{{ action('DocumentController@tos') }}">Gebruiksvoorwaarden</a>.
                    </p>

                    @if(organisation()->footer_html)
                        {!! organisation()->footer_html !!}
                    @endif

                </div><!-- Copyright info end -->

            </div><!-- Content col end -->
        </div><!-- Content row end -->
    </div><!-- Container end -->
    <div id="back-to-top" data-spy="affix" data-offset-top="10" class="back-to-top affix" style="display: block;">
        <button class="btn btn-primaFry" title="Back to Top">
            <i class="fa fa-angle-up"></i>
        </button>
    </div>
</footer>

<script type="text/javascript" src="{{ mix('js/app.js') }}"></script>
<script type="text/javascript">
    var myLazyLoad = new LazyLoad({
        elements_selector: ".lazy"
    });
</script>

@if(!\Auth::user())

    <style scoped>@import url("//cdnjs.cloudflare.com/ajax/libs/cookieconsent2/3.0.3/cookieconsent.min.css");</style>
    <script async src="//cdnjs.cloudflare.com/ajax/libs/cookieconsent2/3.0.3/cookieconsent.min.js"></script>

    <script>
        window.addEventListener("load", function() {
            setTimeout(
                function() {
                    window.cookieconsent.initialise({
                        "palette": {
                            "popup": {
                                "background": "#3937a3"
                            },
                            "button": {
                                "background": "#e62576"
                            }
                        },
                        "content": {
                            "message": "Deze website gebruikt cookies om je een optimale ervaring te kunnen bieden.",
                            "dismiss": "Snap ik!",
                            "link": "Lees privacybeleid",
                            "href": "http://events.catlab.eu/documents/nl/privacy"
                        }
                    });
                },
                5000
            )
        });
    </script>

@endif

<script type="text/javascript" src="//maps.googleapis.com/maps/api/js?key=AIzaSyDril7UCS0iQFnCACg-fU4tgvrrTJG2C2Y"></script>
