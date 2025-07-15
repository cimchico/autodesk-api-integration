<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="{{ asset('build/assets/app-BM_z9CEK.css') }}">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Scripts -->
    <script src="{{ asset('build/assets/app-CFarXu-w.js') }}" defer></script>

    <!-- Bootstrap Bundle JS (with Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


    <!-- jQuery CDN (required for refresh button to work) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body class="font-sans text-gray-900 antialiased bg-gray-100 dark:bg-gray-900">

    <!-- Logo -->
    <div class="p-4 text-center">
        <!-- <a href="/">
            <x-application-logo class="w-20 h-20 fill-current text-gray-500" />
        </a> -->
    </div>

    <!-- Container -->
    <div class="w-full max-w-7xl px-4 pb-4 bg-white dark:bg-gray-800 shadow-md sm:rounded-lg mx-auto">

         <h2 class="text-3xl font-bold text-center text-gray-800 dark:text-gray-100 mb-6">
            📊 Project Material Request Dashboard
        </h2>

        <!-- Refresh Button -->
        <div class="text-center mb-4">
            <button onclick="refreshIframe()" class="btn btn-success btn-lg px-5 shadow-sm">
                🔄 Refresh Power BI Report
            </button>
        </div>

        <hr>

        <!-- Power BI iframe (nearly full height) -->
        <div class="w-full" style="height: calc(100vh - 200px);">
            <iframe id="powerbi-frame"
                    src="https://app.powerbi.com/reportEmbed?reportId=1134589b-28dd-4e51-852c-dfa7f9b270e1&autoAuth=true&ctid=0615ec66-11f8-4f9f-b5ce-c9e4e0d80c37"
                     style="width: 100%; height: 100%; border: none; transform: scale(1); transform-origin: 0 0;"
                    allowfullscreen="true">
            </iframe>
        </div>
    </div>

    <!-- Script to refresh iframe -->
    <script>
        function refreshIframe() {
            $('#powerbi-frame').attr('src', function(i, val) {
                return val;
            });
        }
    </script>

</body>
</html>
