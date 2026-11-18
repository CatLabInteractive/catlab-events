@extends('layouts/front')

@section('title')
    Registreren
@endsection

@section('content')

    <p class="kv-lead">Voor welke editie wil je inschrijven?</p>
    @if (count($events) === 0)
        <p>Er zijn nog geen quizzen gepland.</p>
    @else
        <div class="kv-table-scroll"><table class="table">
            @foreach($events as $v)

                <tr>
                    <td>
                        @if($v->startDate)
                            <a href="{{ $v->getUrl() }}">
                                {{ $v->startDate->format('d/m/Y H:i') }}
                            </a>
                        @endif
                    </td>

                    <td>
                        <a href="{{ $v->getUrl() }}">
                            {{ $v->name }}
                        </a>

                        @if($v->isSoldOut())
                            <span class="kv-flag">Uitverkocht</span>
                        @elseif($v->isLastTicketsWarning())
                            <?php $availableTickets = $v->countAvailableTickets(); ?>
                            <span class="kv-flag">Laatste {{ $availableTickets }} tickets</span>
                        @endif
                    </td>

                    <td>
                        @if($v->venue)
                            {{ $v->venue->name }}
                        @endif
                    </td>

                    <td>
                        @if($v->isSelling())
                            <a href="{{ action('EventController@selectTicketCategory', [ $v->id ] ) }}" class="btn btn-primary btn-sm">{{ $v->getOrderLabel() }}</a>
                        @elseif($v->isSoldOut())
                            Uitverkocht
                        @else
                            {{ $v->getNotSellingReason() }}
                        @endif
                    </td>
                </tr>

            @endforeach
        </table></div>
    @endif

@endsection
