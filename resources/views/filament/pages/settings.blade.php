<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div style="margin-top: 1.25rem">
            <x-filament::button type="submit">حفظ الإعدادات</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
