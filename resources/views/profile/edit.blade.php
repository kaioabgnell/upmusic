<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-brand-ink">Meu perfil</h2>
    </x-slot>

    <x-page-header title="Meu perfil" subtitle="Gerencie sua foto, dados pessoais e senha." icon="fa-user" />

    <div class="max-w-xl space-y-6">
        <div class="bg-white border border-hairline rounded-xl p-6">
            @include('profile.partials.update-avatar-form')
        </div>

        <div class="bg-white border border-hairline rounded-xl p-6">
            @include('profile.partials.update-profile-information-form')
        </div>

        <div class="bg-white border border-hairline rounded-xl p-6">
            @include('profile.partials.update-password-form')
        </div>

        <div class="bg-white border border-hairline rounded-xl p-6">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</x-app-layout>
