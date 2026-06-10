<!DOCTYPE html>
<html lang="vi" class="{{ auth()->check() && auth()->user()->theme === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'P-CRM')) — P-CRM</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=be-vietnam-pro:300,400,500,600,700,800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/phosphor-icons/1.4.2/css/phosphor.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body class="bg-slate-100 dark:bg-slate-900 min-h-screen">

    <!-- Top Navigation Bar -->
    @include('components.topbar')

    <!-- Body layout: left panel + main content -->
    <div class="flex" style="height: calc(100vh - 56px);">

        <!-- Left Profile Panel -->
        @include('components.sidebar')

        <!-- Main content scroll area -->
        <div class="flex-1 overflow-y-auto flex flex-col">
            <main class="flex-1 p-5 md:p-6 pcrm-animate-in">

                @if(session('success'))
                    <div class="alert-success mb-4 flex items-center gap-2">
                        <i class="ph-check-circle text-lg"></i>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert-danger mb-4 flex items-center gap-2">
                        <i class="ph-warning-circle text-lg"></i>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <!-- Toast container -->
    <div id="toast-container" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2"></div>

    @stack('scripts')

    <script>
        function openModal(id) {
            const el = document.getElementById(id);
            if (el) { el.classList.remove('hidden'); el.classList.add('flex'); }
        }
        function closeModal(id) {
            const el = document.getElementById(id);
            if (el) { el.classList.add('hidden'); el.classList.remove('flex'); }
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.alert-success, .alert-danger, .alert-warning').forEach(function (alert) {
                setTimeout(function () {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(function () { alert.remove(); }, 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>
