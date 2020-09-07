@extends('emails/layouts/layout')

@section('content')

    <h2>Wij willen jou!</h2>
    <p>
        Dag {{ $invitation->name }},
    </p>

    <p>
        {{ $from->username }} heeft je toegevoegd aan het team "{{ $group->name }}"
    </p>

    <p>
        Klik op de onderstaande link om je lidmaatschap te accepteren.
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
                height="50" align="center" valign="middle" width="184"><a
                        style="text-decoration:none; color:#0089bf; display:block" href="http://"></a>
                <table cellpadding="0" border="0" cellspacing="0" width="100%"
                       style="border-collapse:collapse; color:#444; font-family:Arial, &quot;Helvetica Neue&quot;, Helvetica, sans-serif; font-size:14px; line-height:1.5; border-color:#ddd; border-style:solid; border-width:1px; border:none">                    <tbody>
                    <tr style="border-color:transparent">
                        <td align="center"
                            style="border-collapse:collapse; border-color:#ddd; border-style:solid; border-width:1px; padding:0; border:none; line-height:1">
                            <a style="text-decoration:none; color:#FFF; display:block; font-family:Arial, &quot;Helvetica Neue&quot;, Helvetica, sans-serif; font-family-short:arial; font-size:18px; font-weight:normal"
                               href="{{ $inviteUrl }}">Accepteren</a></td>
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
        Veel quizplezier!<br />
        De Quizfabriek
    </p>

@endsection