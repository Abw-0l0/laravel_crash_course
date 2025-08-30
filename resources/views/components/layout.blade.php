<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ABW-0.1</title>
    @vite(['resources/css/app.css'])
</head>
<body>
    <x-partials.header/>
    {{ $slot }}
</body>
</html>