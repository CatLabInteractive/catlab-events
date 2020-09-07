@if(organisation()->showSponsors())
    <section id="ts-sponsors" class="ts-sponsors">
        <div class="container">
            <div class="row text-center">
                <h2 class="section-title">Liefde!</h2>
                <p class="section-sub-title">Sponsors</p>
            </div><!--/ Title row end -->

            <!--
            <div class="row text-center">
                <h3 class="sponsor-title">Hoofdsponsor</h3>
                <div class="col-xs-12 col-sm-12 col-md-12">
                    <a href="https://www.app-etizer.eu/" class="sponsor-logo">
                        <img class="img-responsive lazy" data-src="/images/sponsor/appetizerv3.png" alt="Appetizer logo" title="Appetizer Logo" />
                    </a>
                </div>
            </div>

            <div class="row text-center">
                <h3 class="sponsor-title">Goud sponsors</h3>
                <div class="col-xs-4 col-sm-4 col-md-4">
                    <a href="https://www.quizwitz.com" class="sponsor-logo">
                        <img class="img-responsive lazy" data-src="/images/sponsor/logo_met_ted_small.png" alt="QuizWitz logo" title="QuizWitz Logo" />
                    </a>
                </div>

                <div class="col-xs-4 col-sm-4 col-md-4">
                    <a href="https://www.nationale-loterij.be/nl" class="sponsor-logo">
                        <img class="img-responsive lazy" data-src="/images/sponsor/nationaleloterij_small.png" alt="Nationale Loterij logo" title="Nationale Loterij Logo" />
                    </a>
                </div>

                <div class="col-xs-4 col-sm-4 col-md-4">
                    <a href="http://www.t-forceevents.be/" class="sponsor-logo">
                        <img class="img-responsive lazy" data-src="/images/sponsor/tforce_small.png" alt="T-Force logo" title="T-Force Events logo" />
                    </a>
                </div>
            </div>
            -->

            <div class="row text-center">
                <!--<h3 class="sponsor-title">Brons sponsors</h3>-->
                <div class="col-sm-3 col-md-3">
                    <a href="https://www.worldsendcomics.com/nl" class="sponsor-logo">
                        <img class="img-responsive lazy" data-src="/images/sponsor/worlds-end_small.png" alt="Worlds-End Logo" title="Worlds end logo" />
                    </a>
                </div>

                <div class="col-sm-3 col-md-3">
                    <a href="http://www.gruut.be/" class="sponsor-logo">
                        <img class="img-responsive lazy" data-src="/images/sponsor/gruut.jpg" alt="Gruut Logo" title="Gruut logo" />
                    </a>
                </div>

                <div class="col-sm-3 col-md-3">
                    <a href="http://www.gruut.be/" class="sponsor-logo">
                        <img class="img-responsive lazy" data-src="/images/sponsor/tforce_small.png" alt="T-Force logo" title="T-Force Events logo" />
                    </a>
                </div>

                <div class="col-sm-3 col-md-3">
                    <a href="http://www.gruut.be/" class="sponsor-logo">
                        <img class="img-responsive lazy" data-src="/images/sponsor/logo_met_ted_small.png" alt="QuizWitz logo" title="QuizWitz Logo" />
                    </a>
                </div>

            </div>

            <div class="row">
                <div class="general-btn text-center">
                    <a class="btn btn-primary" href="{{ url('sponsors') }}">Sponsor worden?</a>
                </div>
            </div><!--/ Content row 3 end -->

        </div><!--/ Container end -->
    </section>
@endif
