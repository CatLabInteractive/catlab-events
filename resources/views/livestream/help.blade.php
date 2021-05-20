@if(
    (!isset($livestream) || $livestream->show_instructions) &&
    (!isset($hideSupport) || !$hideSupport)
)
    <div class="help">

        <h2>{{ __('livestreams.welcome') }}</h2>
        <p>{{ __('livestreams.introduction') }}</p>

        @if($code)
            <p>{{ __('livestreams.aboutPhone') }}</p>

            <ol class="list">
                <li>
                    {!! __('livestreams.surfToQuizWitz', [ 'website' => '<code>www.quizwitz.tv</code>' ]) !!}
                </li>

                <li>
                    {!! __('livestreams.enterCode', [ 'code' => '<code>' . $code . '</code>' ]) !!}
                </li>

                <li>
                    {{ __('livestreams.readyToPlay') }}
                </li>
            </ol>
        @endif

        <h3>{{ __('livestreams.noAudioVideo') }}</h3>
        <h4>{{ __('livestreams.noVideo') }}</h4>
        <ul class="list">
            <li>
                {{ __('livestreams.noVideo1') }}
                <a href="{{ url()->full() }}">{{ __('livestreams.firstTryRefreshPage') }}</a>
            </li>

            <li>
                {{ __('livestreams.noOldBrowsers') }}
            </li>

            <li>
                {{ __('livestreams.noVpn') }}
            </li>

            <li>
                {{ __('livestreams.stuttering') }}

            </li>
        </ul>

        <h4>{{ __('livestreams.noAudio') }}</h4>
        <ul class="list">
            <li>{{ __('livestreams.muted') }}</li>
            <li>{{ __('livestreams.noVolume') }}</li>
        </ul>

        <h4>{{ __('livestreams.answerProblems') }}</h4>
        <ul class="list">
            <li>
                {{ __('livestreams.answersSecondDevice') }}
            </li>

            <li>
                {!! __('livestreams.answersTooSoon', [
                    'refreshThePage' => '<a href="' . url()->full() . '">'.__('livestreams.refreshThisPage').'</a>'
                ]) !!}
            </li>

            <li>
                {{ __('livestreams.connectionLostStandby') }}
            </li>

            <li>
                {{ __('livestreams.connectionLostRefresh') }}
            </li>
        </ul>

        <p>
            {{ __('livestreams.solutionsToEverything') }}
            <a href="{{ url()->full() }}">{{ __('livestreams.refreshThePage') }}</a>
        </p>
    </div>
@endif
