<!DOCTYPE html>
<html lang="{{ mb_substr(app()->getLocale(), 0, 2) }}">
<head>
</head>

<body>

    <h1>Reservation</h1>
    <form action="{{ $action }}" method="post">
        <textarea name="reservation" style="width: 100%; height: 600px;"></textarea>

        <button type="submit">Generate personal urls</button>
    </form>


</body>
