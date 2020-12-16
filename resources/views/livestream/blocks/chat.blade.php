@if(!$user && $rocketChatUrl)
    <p class="login-prompt">
        {!! __('livestreams.loginToChat', [ 'action' => '<a href="'.$loginUrl.'">' . __('livestreams.loginAction') . '</a>' ]) !!}
    </p>
@endif

@if($rocketChatUrl)
    <iframe
            frameborder="0"
            scrolling="no"
            src="{{$rocketChatUrl}}"
            height="100%"
            width="100%"
            id="rocketChatIframe"
    >
    </iframe>

    @if($rocketChatAuthUrl)
        <script type="text/javascript">
            fetchRocketChatAuthToken('{{$rocketChatAuthUrl}}');
        </script>
    @endif
@endif

@if($deadSimpleChat)
    <iframe src="{{ $deadSimpleChat }}" style="width: 100%; height: 100%;" frameborder="0" scrolling="no"></iframe>
@endif
