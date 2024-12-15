@extends('layouts/home')

@section('content')


    <section id="home">
        <div id="main-slider" class="carousel slide" data-ride="carousel">
            <!--
            <ol class="carousel-indicators">
                <li data-target="#main-slider" data-slide-to="0" class="active"></li>
                <li data-target="#main-slider" data-slide-to="1"></li>
                <li data-target="#main-slider" data-slide-to="2"></li>
                <li data-target="#main-slider" data-slide-to="3"></li>
                <li data-target="#main-slider" data-slide-to="4"></li>
            </ol>
            -->

            <?php

            if (isset($nextEvent)) {
                $url = $nextEvent->getUrl();

                if (!$nextEvent->isSoldOut()) {
                $caption = <<<EOT
                    <h2>{$nextEvent->name}</h2>
                    <h4>
                        {$nextEvent->startDate->formatLocalized('%-d %B, H:i')}, {$nextEvent->venue->city}.<br>

                        @if($nextEvent->team_size)
                            Tickets: vanaf {!! $nextEvent->getFormattedPublishedPrice(true) !!} per team (max {{$nextEvent->team_size}} spelers)
                        @else
                            Tickets: vanaf {!! $nextEvent->getFormattedPublishedPrice(true) !!}
                        @endif
                    </h4>
                    <a href="{$url}">schrijf je in <i class="fa fa-angle-right"></i></a>
EOT;
                } else {
                    $caption = <<<EOT
                        <h2>{$nextEvent->name}</h2>
                        <h4>
                            {$nextEvent->startDate->formatLocalized('%-d %B, H:i')}, {$nextEvent->venue->city}.<br>
                            Tickets: UITVERKOCHT!
                        </h4>
EOT;
                }
            } else {
                $caption = '<h2>nog geen volgende editie gepland</h2><h4>... maar hou deze site in de gaten!</h4>';
            }
            ?>


            <div class="carousel-inner">
                <div class="item active">
                    <div class="image-container">
                        <img class="img-responsive lazy" data-src="{{ asset('images/fotos/QuizWitz-Live-promo.jpg') }}" alt="slider">
                    </div>
                    <div class="carousel-caption">
                        <?php echo $caption; ?>
                    </div>
                </div>
                <!--
                <div class="item">
                    <div class="image-container">
                        <img class="img-responsive" src="{{ asset('images/fotos/QuizWitz-Live-euphoria.jpg') }}" alt="slider">
                    </div>
                    <div class="carousel-caption">
                        <?php echo $caption; ?>
                    </div>
                </div>
                -->
                <div class="item">
                    <div class="image-container">
                        <img class="img-responsive lazy" data-src="{{ asset('images/fotos/QuizWitz-Live-Broken.jpg') }}" alt="slider">
                    </div>
                    <div class="carousel-caption">
                        <?php echo $caption; ?>
                    </div>
                </div>
                <div class="item">
                    <div class="image-container">
                        <img class="img-responsive lazy" data-src="{{ asset('images/fotos/QuizWitz-Live.jpg')  }}" alt="slider">
                    </div>
                    <div class="carousel-caption">
                        <?php echo $caption; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if(isset($nextEvent))
        <section id="next-event">
            <div class="container">

                <div class="row">
                    <div class="col-sm-12">
                        <h2>{{ $nextEvent->name }}</h2>
                        {!! $nextEvent->description !!}
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-12">
                        <a href="{{ $nextEvent->getUrl() }}" class="btn btn-primary">
                            @if($nextEvent->isSoldOut())
                                Uitverkocht <i class="fa fa-angle-right"></i>
                            @else
                                Schrijf je nu in <i class="fa fa-angle-right"></i>
                            @endif
                        </a>
                    </div>
                </div>
            </div>
        </section><!--/#about-->
    @endif

    @if(isset($countdownEvent))
        <section id="explore">
            <div class="container">
                <div class="row">
                    <div class="col-sm-6">
                        <h2>
                            Volgende quiz:<br>

                            <?php if ($countdownEvent) { ?>
                            <a href="{{ $countdownEvent->getUrl() }}">{{ $countdownEvent->name }}</a>,<br>
                            {{ $countdownEvent->startDate->formatLocalized('%d %B') }},
                            <a href="{{ action('EventController@fromVenue', $countdownEvent->venue->id) }}">{{ $countdownEvent->venue->name }}</a>,
                            {{ $countdownEvent->venue->city }}
                            <?php } else { ?>
                            Binnenkort meer info!
                            <?php } ?>
                        </h2>
                    </div>

                    <script>NEXT_EVENT_DATE = '<?php echo $countdownEvent ? $countdownEvent->startDate->format('D, d M Y H:i:s O') : ''; ?>';</script>

                    <div class="col-md-6">
                        <ul id="countdown">
                            <li>
                                <span class="days time-font">00</span>
                                <p>dagen </p>
                            </li>
                            <li>
                                <span class="hours time-font">00</span>
                                <p class="">uur </p>
                            </li>
                            <li>
                                <span class="minutes time-font">00</span>
                                <p class="">minuten</p>
                            </li>
                            <li>
                                <span class="seconds time-font">00</span>
                                <p class="">seconden</p>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="cart">
                    <a href="{{ $nextEvent->getUrl() }}"><i class="fa fa-shopping-cart"></i> <span>Koop Tickets</span></a>
                </div>
            </div>
        </section><!--/#explore-->
    @endif

    @include('blocks.video')

    <section>
        <div class="container" id="agenda">

            @if (Session::has('message'))
                <div class="alert alert-info">{{ Session::get('message') }}</div>
            @endif

            @include('blocks.agenda')
            <p><a href="{{ action('EventController@archive') }}">Bekijk alle voorbije events</a></p>

        </div>
    </section>

    <section>
        <!-- SendPulse Form -->
        <style >.sp-force-hide { display: none;}.sp-form[sp-id="84518"] { display: block; background: #1b7b9c; padding: 15px; width: 100%; max-width: 100%; border-radius: 8px; -moz-border-radius: 8px; -webkit-border-radius: 8px; font-family: Georgia, serif; background-repeat: no-repeat; background-position: center; background-size: auto;}.sp-form[sp-id="84518"] .sp-form-fields-wrapper { margin: 0 auto; width: 930px;}.sp-form[sp-id="84518"] .sp-form-control { background: #ffffff; border-color: #cccccc; border-style: solid; border-width: 1px; font-size: 15px; padding-left: 8.75px; padding-right: 8.75px; border-radius: 4px; -moz-border-radius: 4px; -webkit-border-radius: 4px; height: 35px; width: 100%;}.sp-form[sp-id="84518"] .sp-field label { color: rgba(255, 255, 255, 1); font-size: 13px; font-style: normal; font-weight: bold;}.sp-form[sp-id="84518"] .sp-button { border-radius: 4px; -moz-border-radius: 4px; -webkit-border-radius: 4px; background-color: #0089bf; color: #ffffff; width: auto; font-weight: bold; font-style: normal; font-family: Arial, sans-serif;}.sp-form[sp-id="84518"] .sp-button-container { text-align: left;}</style><div class="sp-form-outer sp-force-hide"><div id="sp-form-84518" sp-id="84518" sp-hash="6c45850d8175f2ee826a842e320ed66c41578c8e75d77d35966c952a652d941b" sp-lang="en" class="sp-form sp-form-regular sp-form-embed sp-form-full-width" sp-show-options="%7B%22amd%22%3Afalse%2C%22condition%22%3A%22onEnter%22%2C%22scrollTo%22%3A25%2C%22delay%22%3A10%2C%22repeat%22%3A3%2C%22background%22%3A%22rgba(0%2C%200%2C%200%2C%200.5)%22%2C%22position%22%3A%22bottom-right%22%2C%22animation%22%3A%22%22%2C%22hideOnMobile%22%3Afalse%2C%22urlFilter%22%3Afalse%2C%22urlFilterConditions%22%3A%5B%7B%22force%22%3A%22hide%22%2C%22clause%22%3A%22contains%22%2C%22token%22%3A%22%22%7D%5D%7D"><div class="sp-form-fields-wrapper show-grid"><div class="sp-message"><div></div></div><div class="sp-element-container"><div class="sp-field " sp-id="sp-f81bddc4-a950-4317-aa7d-ca7f74fd61d2"><div style="font-family: inherit; line-height: 1.2;"><p><span style="color: #ffffff;">Schrijf je in op onze nieuwsbrief om op de hoogte te blijven.</span></p></div></div><div class="sp-field " sp-id="sp-eea3d1ae-7fc9-4dee-83ae-595171ac3d48"><label class="sp-control-label"><span >Email</span><strong >*</strong></label><input type="email" sp-type="email" name="sform[email]" class="sp-form-control " placeholder="username@gmail.com" sp-tips="%7B%22required%22%3A%22Required%20file%22%2C%22wrong%22%3A%22Wrong%20email%22%7D" required="required"></div><div class="sp-field sp-button-container " sp-id="sp-8eecf069-bb45-421f-8526-93d87347c591"><button id="sp-8eecf069-bb45-421f-8526-93d87347c591" class="sp-button">Inschrijven </button></div></div><div class="sp-link-wrapper sp-brandname__left"><a class="sp-link " target="_blank" href="https://sendpulse.com/en/?ref=6755964"><span class="sp-link-img">&nbsp;</span><span translate="FORM.PROVIDED_BY">Provided by SendPulse</span></a></div></div></div></div><script type="text/javascript" src="//static-login.sendpulse.com/apps/fc3/build/default-handler.js?1507899681304"></script>
        <!-- /SendPulse Form -->
    </section>

    @include('blocks.about')

    {{--@include('layouts.blocks.sponsor')--}}

@endsection
