<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-bg font-sans text-ink antialiased">
        <div class="flex min-h-screen flex-col items-center justify-center gap-6 px-6 py-12">
            <a href="{{ route('home') }}" wire:navigate class="flex flex-col items-center gap-3">
                <x-app-logo />
            </a>

            <div class="w-full max-w-sm">
                {{ $slot }}
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
