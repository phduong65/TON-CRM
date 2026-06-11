<!DOCTYPE html>
<html lang="vi">
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
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900 dark:to-slate-800 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-xl bg-pcrm-600 text-white mb-4">
                <i class="ph-shield-check text-3xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">P-CRM</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Hệ thống Quản lý Kỷ luật Nhân sự</p>
        </div>

        <!-- Card -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-lg border border-slate-200 dark:border-slate-700 p-6 md:p-8">
            @if(session('status'))
                <div class="alert-success mb-4">{{ session('status') }}</div>
            @endif

            @if($errors->any())
                <div class="alert-danger mb-4">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </div>

        <p class="text-center text-xs text-slate-400 dark:text-slate-500 mt-6">
            &copy; {{ date('Y') }} P-CRM. Phiên bản 1.0
        </p>
    </div>
</body>
</html>
