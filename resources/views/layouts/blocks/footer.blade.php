@if(organisation())
    <footer id="footer" class="footer kv-footer">
        <div class="kv-wrap">
            <div class="kv-footer-grid">
                <div>
                    <span class="kv-footer-dots" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
                    <p class="kv-footer-claim">Quizzen met plezier.</p>
                </div>

                <div>
                    <h4>Tickets</h4>
                    <ul>
                        <li><a href="{{ action('EventController@calendar') }}">Kalender</a></li>
                        <li><a href="{{ action('OrderController@index') }}">Mijn tickets</a></li>
                        <li><a href="{{ action('CatLabAccountController@redirect', [ 'myaccount' ]) }}" target="_blank" rel="noopener">Mijn account</a></li>
                    </ul>
                </div>

                <?php $footerSeries = organisation()->series()->active()->get(); ?>
                @if($footerSeries->count() > 0)
                    <div>
                        <h4>Quizreeksen</h4>
                        <ul>
                            @foreach($footerSeries as $navSeries)
                                <li><a href="{{ $navSeries->getUrl() }}">{{ $navSeries->name }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <?php
                    $hasSocial = organisation()->facebook_url || organisation()->twitter_url || organisation()->youtube_url
                        || organisation()->instagram_url || organisation()->linkedin_url;
                ?>
                @if($hasSocial || organisation()->support_email)
                <div>
                    <h4>{{ $hasSocial ? 'Volg ons' : 'Contact' }}</h4>
                    <div class="kv-footer-social">
                        @if(organisation()->facebook_url)
                            <a href="{{organisation()->facebook_url}}" title="{{organisation()->name}} Facebook"><i class="fa fa-facebook"></i><span class="sr-only">Facebook</span></a>
                        @endif
                        @if(organisation()->twitter_url)
                            <a href="{{organisation()->twitter_url}}" title="{{organisation()->name}} Twitter"><i class="fa fa-twitter"></i><span class="sr-only">Twitter</span></a>
                        @endif
                        @if(organisation()->youtube_url)
                            <a href="{{organisation()->youtube_url}}" title="{{organisation()->name}} YouTube"><i class="fa fa-youtube"></i><span class="sr-only">YouTube</span></a>
                        @endif
                        @if(organisation()->instagram_url)
                            <a href="{{organisation()->instagram_url}}" title="{{organisation()->name}} Instagram"><i class="fa fa-instagram"></i><span class="sr-only">Instagram</span></a>
                        @endif
                        @if(organisation()->linkedin_url)
                            <a href="{{organisation()->linkedin_url}}" title="{{organisation()->name}} LinkedIn"><i class="fa fa-linkedin"></i><span class="sr-only">LinkedIn</span></a>
                        @endif
                    </div>
                    @if(organisation()->support_email)
                        <ul>
                            <li><a href="mailto:{{ organisation()->support_email }}">{{ organisation()->support_email }}</a></li>
                        </ul>
                    @endif
                </div>
                @endif
            </div>

            <div class="kv-footer-legal">
                <p>
                    Copyright © 2016-{{ date('y') }} @if(organisation()->website_url)<a target="_blank" href="{{organisation()->website_url}}">{{organisation()->getLegalName()}}</a>@else{{organisation()->getLegalName()}}@endif.
                    Website en ticketsysteem aangeboden door <a href="{{ config('app.owner.url') }}">{{ config('app.owner.name') }}</a>.
                    <a href="http://www.quizwitz.com/">QuizWitz</a> is een geregistreerd merk van <a href="https://www.catlab.eu/">CatLab Interactive</a>.
                    Lees ons <a href="{{ action('DocumentController@privacy') }}">Privacy beleid</a> en
                    <a href="{{ action('DocumentController@tos') }}">Gebruiksvoorwaarden</a>.
                    Deze website is een <a href="https://github.com/CatLabInteractive/catlab-events">open source</a> project.
                </p>

                @if(organisation()->footer_html)
                    {!! organisation()->footer_html !!}
                @endif
            </div>
        </div>

        <div id="back-to-top" data-spy="affix" data-offset-top="10" class="back-to-top affix" style="display: block;">
            <button class="btn btn-primary" title="Terug naar boven">
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

@endif
