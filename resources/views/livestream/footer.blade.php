@if(\Auth::user())
    <p>
        {{ __('livestreams.loggedIn', [ 'name' => \Auth::user()->name ]) }}
        <a class="dropdown-item" href="{{ route('logout') }}"
           onclick="event.preventDefault();
            document.getElementById('logout-form').submit();">
            {{ __('livestreams.logout') }}
        </a>
    </p>
@endif

<form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
    {{ csrf_field() }}
</form>

@if(!isset($hideSupport) || !$hideSupport)
    <p>
    @if($organisation->helpdesk_url)
        {!! __('livestreams.helpdesk', [ 'url' => '<a href="' . $organisation->helpdesk_url . '" target="_blank">' . parse_url($organisation->helpdesk_url)['host'] . '</a>']) !!}<br />
    @endif

    @if(count($organisation->getSocialLinks()) > 0)
        {!! $organisation->getSocialLinksText() !!}
    @endif
    </p>
@endif
