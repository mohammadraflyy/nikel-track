<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>NikelTrack - Vehicle Management System</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        
        <style>
            .mining-bg {
                background-image: url('https://images.unsplash.com/photo-1604998103924-89e012e5265a?q=80&w=1470&auto=format&fit=crop');
                background-size: cover;
                background-position: center;
                background-blend-mode: overlay;
                background-color: rgba(10, 10, 10, 0.85);
            }
            body {
                background-color: #0a0a0a;
                color: #EDEDEC;
            }
        </style>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex p-6 lg:p-8 items-center lg:justify-center min-h-screen flex-col">
        <header class="w-full lg:max-w-4xl max-w-[335px] text-sm mb-6 not-has-[nav]:hidden">
            @if (Route::has('login'))
                <nav class="flex items-center justify-end gap-4">
                    @auth
                        <a
                            href="{{ url('/dashboard') }}"
                            class="inline-block px-5 py-1.5 text-[#EDEDEC] border-[#3E3E3A] hover:border-[#62605b] border rounded-sm text-sm leading-normal"
                        >
                            Dashboard
                        </a>
                    @else
                        <a
                            href="{{ route('login') }}"
                            class="inline-block px-5 py-1.5 bg-[#2A5C8D] text-white hover:bg-[#1E456E] rounded-sm text-sm leading-normal"
                        >
                            Log in
                        </a>
                    @endauth
                </nav>
            @endif
        </header>
        <div class="flex items-center justify-center w-full transition-opacity opacity-100 duration-750 lg:grow starting:opacity-0">
            <main class="flex max-w-[335px] w-full flex-col-reverse lg:max-w-5xl lg:flex-row shadow-lg rounded-lg overflow-hidden">
                <div class="mining-bg text-white flex-1 p-8 lg:p-12">
                    <div class="max-w-md">
                        <h1 class="text-3xl font-bold mb-4">NikelTrack</h1>
                        <h2 class="text-xl font-semibold mb-6">Mining Vehicle Management System</h2>
                        <ul class="space-y-4 mb-8">
                            <li class="flex items-start gap-3">
                                <svg class="w-5 h-5 mt-0.5 flex-shrink-0 text-[#3E8A5E]" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span>Track vehicle fuel consumption</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <svg class="w-5 h-5 mt-0.5 flex-shrink-0 text-[#3E8A5E]" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span>Multi-level approval workflow</span>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="bg-[#161615] text-[#EDEDEC] p-8 lg:p-12 lg:w-[450px] border-l border-[#3E3E3A]">
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold mb-2">Get Started</h3>
                        <p class="text-[#A1A09A]">Manage your mining fleet efficiently with our comprehensive vehicle tracking system.</p>
                    </div>
                    
                    <div class="space-y-6">
                        <div>
                            <h4 class="font-medium mb-3">For Approvers</h4>
                            <a href="{{ route('login') }}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-[#3E8A5E] hover:bg-[#2D6A4A] focus:ring-2 focus:ring-offset-2 focus:ring-[#3E8A5E] focus:ring-offset-[#161615]">
                                Review Requests
                            </a>
                        </div>
                        
                        <div>
                            <h4 class="font-medium mb-3">For Administrators</h4>
                            <a href="{{ route('login') }}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-[#5E3E8A] hover:bg-[#4A2D6A] focus:ring-2 focus:ring-offset-2 focus:ring-[#5E3E8A] focus:ring-offset-[#161615]">
                                Manage Fleet
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>

        <footer class="mt-8 text-sm text-[#A1A09A]">
            © {{ date('Y') }} NikelTrack - PT. Nickel Mining Corporation
        </footer>
    </body>
</html>