@if(\Auth::user())
    <p>
        Ingelogd als {{ \Auth::user()->name }}.
        <a class="dropdown-item" href="{{ route('logout') }}"
           onclick="event.preventDefault();
            document.getElementById('logout-form').submit();">
            Logout
        </a>
    </p>
@endif

<form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
    {{ csrf_field() }}
</form>

@if(!isset($hideSupport) || !$hideSupport)
    <p>
    @if($organisation->helpdesk_url)
        Heb je vragen of problemen? Surf naar <a href="{{$organisation->helpdesk_url}}" target="_blank">{{ parse_url($organisation->helpdesk_url)['host'] }}</a>.<br />
    @endif

    @if(count($organisation->getSocialLinks()) > 0)
        {!! $organisation->getSocialLinksText() !!}
    @endif
    </p>
@endif
