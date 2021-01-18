<!DOCTYPE html>
<html lang="{{ mb_substr(app()->getLocale(), 0, 2) }}">
<head>
</head>

<body>

    <h1>Reservation</h1>
    <table class="table">

        <tr>
            <th>Name</th>
            <th>Connect code</th>
            <th>Livestream URL</th>
        </tr>

        @foreach($players as $player)
            <tr>
                <td>{{ $player['name'] }}</td>
                <td>{{ $player['token'] }}</td>
                <td>{{ $player['url'] }}</td>
            </tr>
        @endforeach

    </table>

</body>
