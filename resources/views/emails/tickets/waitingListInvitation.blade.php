@extends('emails/layouts/layout')

@section('content')

    <h2>Er is een ticket vrijgekomen!</h2>

    <p>
        Beste {{ $user->username }},
    </p>

    @php
        $availableDates = $event->eventDates
            ->filter(function($date) { return !$date->isSoldOut(); })
            ->map(function($date) { return $date->startDate->format('d/m/Y'); })
            ->join(' & ');
    @endphp

    <p>
        Er is een ticket vrijgekomen voor {{ $event->name }}@if($availableDates) op {{ $availableDates }}@endif
        en jij staat op de wachtlijst.
    </p>

    <p>
        Ben je nog geïnteresseerd in het ticket? Bestel het dan via de onderstaande knop.
    </p>

    <table cellpadding="0" border="0" cellspacing="0"
           style="border-collapse:collapse; color:#444; font-family:Arial, &quot;Helvetica Neue&quot;, Helvetica, sans-serif; font-size:14px; line-height:1.5; border-color:#ddd; border-style:solid; border-width:1px; border:none; margin-left:auto; margin-right:auto; background:#4cb050; border-radius:4px"
           width="184" height="50" class="btnCls">
        <tbody>
        <tr style="border-color:transparent">
            <td style="border-collapse:collapse; border-color:#ddd; border-style:solid; border-width:1px; padding:0; border:none; width:21px !important"
                width="21px !important">&nbsp;
            </td>
            <td style="border-collapse:collapse; border-color:#ddd; border-style:solid; border-width:1px; padding:0; border:none; align:center; background:#4cb050; border-radius:4px; height:50px; text-align:center; vertical-align:middle; width:184px"
                height="50" align="center" valign="middle" width="184">
                <table cellpadding="0" border="0" cellspacing="0" width="100%"
                       style="border-collapse:collapse; color:#444; font-family:Arial, &quot;Helvetica Neue&quot;, Helvetica, sans-serif; font-size:14px; line-height:1.5; border-color:#ddd; border-style:solid; border-width:1px; border:none">
                    <tbody>
                    <tr style="border-color:transparent">
                        <td align="center"
                            style="border-collapse:collapse; border-color:#ddd; border-style:solid; border-width:1px; padding:0; border:none; line-height:1">
                            <a style="text-decoration:none; color:#FFF; display:block; font-family:Arial, &quot;Helvetica Neue&quot;, Helvetica, sans-serif; font-family-short:arial; font-size:18px; font-weight:normal"
                               href="{{ $url }}">Bestel je ticket</a></td>
                    </tr>
                    </tbody>
                </table>
            </td>
            <td style="border-collapse:collapse; border-color:#ddd; border-style:solid; border-width:1px; padding:0; border:none; width:21px !important"
                width="21px !important">&nbsp;
            </td>
        </tr>
        </tbody>
    </table>

    <p>
        Werkt de knop niet? Gebruik dan deze link:<br />
        <a href="{{ $url }}">{{ $url }}</a>
    </p>

    <p>
        Wees er snel bij, want we hebben dit mailtje naar enkele mensen gestuurd.
    </p>

    <p>
        Toch geen interesse? Stuur ons dan een mailtje terug, zodat wij de volgende kunnen uitnodigen.
    </p>

    <p>
        Veel succes!<br />
        De Quizfabriek
    </p>

@endsection
