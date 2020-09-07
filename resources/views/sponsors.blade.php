<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="De quizfabriek organiseert elk jaar een aantal quizzes waarbij zowel nadruk wordt gelegd op kennis als op entertainment. Onze quizzes zijn dan ook wat minder traditioneel. Natuurlijk staat kennis centraal, maar laat je niet vangen door een ronde armworstelen, retoriek or zelfs bier-proeven.">
    <meta name="author" content="">
    <title>Quizfabriek</title>

    @include('layouts/blocks/head')

    <style>
        * {
            margin: 0;
            padding: 0;
            background: #525659;
        }

        p {
            margin: 10px;
            font-size: .8em;
            color: lightgray;
            font-family: sans-serif;
        }
    </style>
</head>

<body>

<p>Download voorbereiden...</p>
<script>
    setTimeout(function() {
        window.location = '/sponsordossier/Sponsordossier.pdf';
    }, 1000);
</script>

</body>