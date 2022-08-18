@if(organisation()->events()->count() > 0)
    <section>
        <div class="container" id="facts">

            <h2 class="intro-title">Dit is {{organisation()->name}}</h2><br>
            <div class="row facts-wrapper">

                <?php $firstQuizDate = organisation()->getFirstEventDate(); ?>
                @if($firstQuizDate && (new DateTime())->diff($firstQuizDate)->y > 0)
                    <div class="col-sm-3 col-xs-6">

                        <div class="ts-facts">
                        <span class="ts-facts-img">
                        <img class="lazy" data-src="/images/icons/fact-country.png" alt="Jaren actief icoon"
                             title="{{ organisation()->name }} is reeds {{ (new DateTime())->diff($firstQuizDate)->y }} actief."/>
                        </span>
                            <div class="ts-facts-content">
                                <h2 class="ts-facts-num"><span
                                            class="counterUp">{{ (new DateTime())->diff($firstQuizDate)->y }}</span>
                                </h2>
                                <h3 class="ts-facts-title">Jaar actief</h3>
                            </div>
                        </div>
                    </div>
                @endif

            <!-- Col 1 end -->
                <div class="col-sm-3 col-xs-6">
                    <div class="ts-facts">
                    <span class="ts-facts-img">
                    <img class="lazy" data-src="/images/icons/fact-speaker.png" alt="Aantal quizzen icoon"
                         title="We hebben reeds {{ organisation()->countEvents() }} quizzen georganiseerd."/>
                    </span>
                        <div class="ts-facts-content">
                            <h2 class="ts-facts-num"><span
                                        class="counterUp">{{ organisation()->events()->count() }}</span></h2>
                            <h3 class="ts-facts-title">Quizzes</h3>
                        </div>
                    </div>
                    <!--Facts end -->
                </div>
                <!-- Col 2 end -->
                <?php $groups = organisation()->countGroups(); ?>
                <div class="col-sm-3 col-xs-6">
                    <div class="ts-facts">
                                <span class="ts-facts-img">
                                <img class="lazy" data-src="/images/icons/fact-sponsor.png"
                                     alt="Geregistreerde groepen icoon"
                                     title="Reeds {{ $groups }} teams hebben zich geregistreerd."/>
                                </span>
                        <div class="ts-facts-content">
                            <h2 class="ts-facts-num"><span class="counterUp">{{ $groups }}</span></h2>
                            <h3 class="ts-facts-title">Teams</h3>
                        </div>
                    </div>
                    <!--Facts end -->
                </div>

                <?php $acceptedOrderPlayers = organisation()->countPlayers(); ?>
                <div class="col-sm-3 col-xs-6">
                    <div class="ts-facts last">
                                <span class="ts-facts-img">
                                <img class="lazy" data-src="/images/icons/fact-sponsor.png" alt="Spelers icoon"
                                     title="Er hebben al minstens {{ $acceptedOrderPlayers }} spelers meegedaan aan onze quizzen."/>
                                </span>
                        <div class="ts-facts-content">
                            <h2 class="ts-facts-num"><span class="counterUp">{{ $acceptedOrderPlayers }}</span></h2>
                            <h3 class="ts-facts-title">Spelers</h3>
                        </div>
                    </div>
                </div>

                <!-- Col 4 end -->
            </div>
            <!-- Content Row 2 end -->

        </div>
    </section>
@endif
