{{-- resources/views/auth/login.blade.php --}}
<x-guest-layout>
    <form method="POST" action="{{ route('login') }}" class="space-y-4 text-left">
        @csrf

        {{-- Email --}}
        <div>
            <x-input-label for="email" :value="__('Email')" class="text-left" />
            <x-text-input id="email"
                class="block mt-1 w-full text-left"
                type="email"
                name="email"
                :value="old('email')"
                required
                autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        {{-- Password --}}
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" class="text-left" />
            <x-text-input id="password"
                class="block mt-1 w-full text-left"
                type="password"
                name="password"
                required
                autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        {{-- Remember --}}
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox"
                       class="rounded border-gray-300 text-green-700 shadow-sm focus:ring-green-600"
                       name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        {{-- Submit --}}
        <div class="mt-4">
            <x-primary-button
                class="w-full justify-center bg-green-700 hover:bg-green-800 focus:ring-2 focus:ring-offset-2 focus:ring-green-700"
                style="background-color:#0f6e20;">
                {{ __('LOG IN') }}
            </x-primary-button>
        </div>

        {{-- Forgot --}}
        @if (Route::has('password.request'))
            <div class="text-center mt-3">
                <a class="text-sm text-green-700 hover:underline"
                   href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            </div>
        @endif
    </form>
</x-guest-layout>
