@extends('layouts/front')

@section('title')
    Registreren
@endsection

@section('content')

    <h2>Inschrijven</h2>
    <p>Voor welke editie wilt u registreren?</p>
    @if (count($events) === 0)
        <p>Er zijn nog geen evenementen gepland.
    @else
        <table class="table">
            @foreach($events as $v)

                <tr>
                    <td>
                        <a href="{{ $v->getUrl() }}">
                            {{ $v->startDate->format('d/m/Y H:i') }}
                        </a>
                    </td>

                    <td>
                        <a href="{{ $v->getUrl() }}">
                            {{ $v->name }}
                        </a>

                        @if($v->isSoldOut())
                            <span class="lastTickets">Uitverkocht!</span>
                        @elseif($v->isLastTicketsWarning())
                            <?php $availableTickets = $v->countAvailableTickets(); ?>
                            <span class="lastTickets">Laatste {{ $availableTickets }} tickets!</span>
                        @endif
                    </td>

                    <td>
                        @if($v->venue)
                            {{ $v->venue->name }}
                        @endif
                    </td>

                    <td>
                        @if($v->isSelling())
                            <a href="{{ action('EventController@selectTicketCategory', [ $v->id ] ) }}" class="btn btn-default">{{ $v->getOrderLabel() }}</a>
                        @elseif($v->isSoldOut())
                            Uitverkocht
                        @else
                            {{ $v->getNotSellingReason() }}
                        @endif
                    </td>
                </tr>

            @endforeach
        </table>
    @endif

@endsection

@section('critical-css')
    <style>{!! file_get_contents(resource_path('criticalcss/register_critical.min.css')) !!}</style>
@endsection
