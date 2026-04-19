<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="flex min-h-screen flex-col items-center justify-center bg-bg px-6 py-12 font-sans text-ink antialiased">

        <a href="{{ route('home') }}" class="mb-12">
            <x-app-logo />
        </a>

        <div class="w-full max-w-sm text-center">
            <h1 class="font-serif text-[32px] font-medium text-ink">Your personal library.</h1>
            <p class="mt-3 font-sans text-[14px] text-muted">Track what you read, own, and want to read — all in one place.</p>

            <div class="mt-8 flex flex-col gap-3">
                @auth
                    <a
                        href="{{ route('books.shelf') }}"
                        wire:navigate
                        class="flex w-full items-center justify-center rounded-[10px] bg-accent px-4 py-2.5 font-sans text-[14px] font-semibold text-card shadow-[0_1px_0_rgba(0,0,0,0.08)] transition hover:brightness-110"
                    >
                        Go to my shelf
                    </a>
                @else
                    @if (config('demo.enabled'))
                        <a
                            href="{{ route('demo.login') }}"
                            class="flex w-full items-center justify-center rounded-[10px] bg-accent px-4 py-2.5 font-sans text-[14px] font-semibold text-card shadow-[0_1px_0_rgba(0,0,0,0.08)] transition hover:brightness-110"
                        >
                            Get started
                        </a>
                    @else
                        @if (Route::has('register'))
                            <a
                                href="{{ route('register') }}"
                                wire:navigate
                                class="flex w-full items-center justify-center rounded-[10px] bg-accent px-4 py-2.5 font-sans text-[14px] font-semibold text-card shadow-[0_1px_0_rgba(0,0,0,0.08)] transition hover:brightness-110"
                            >
                                Get started
                            </a>
                        @endif
                        @if (Route::has('login'))
                            <a
                                href="{{ route('login') }}"
                                wire:navigate
                                class="flex w-full items-center justify-center rounded-[10px] border border-line bg-card px-4 py-2.5 font-sans text-[14px] font-medium text-ink-2 transition hover:border-line-2 hover:bg-bg-2"
                            >
                                Log in
                            </a>
                        @endif
                    @endif
                @endauth
            </div>
        </div>

        @fluxScripts
    </body>
</html>
