<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Laravel</title>
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        <!-- Styles / Scripts -->
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
        <style>
            body { font-family: 'Instrument Sans', sans-serif; background-color: #f3f4f6; color: #1f2937; margin: 0; padding: 0; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
            .container { text-align: center; }
            h1 { font-size: 3rem; font-weight: 600; margin-bottom: 1rem; color: #ef4444; }
            p { font-size: 1.25rem; color: #4b5563; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Laravel 12 Clean Slate</h1>
            <p>Your project is ready to go!</p>
        </div>
    </body>
</html>
