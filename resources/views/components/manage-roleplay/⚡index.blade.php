<?php

use Livewire\Component;
use App\Services\RoleplayService;

new class extends Component {
    public string $roleplayContent = '';

    public function mount(RoleplayService $service): void
    {
        $this->roleplayContent = $service->getRoleplay();
    }

    public function save(RoleplayService $service): void
    {
        $this->validate([
            'roleplayContent' => 'required|string',
        ]);

        $service->saveRoleplay($this->roleplayContent);

        session()->flash('success', 'Data roleplay berhasil disimpan dan di-cache!');
    }
};
?>

<div class="w-full min-h-screen bg-gray-900 p-6 flex flex-col justify-between space-y-4 text-gray-100">

    {{-- Alert Notification --}}
    @if (session()->has('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
            class="w-full p-4 bg-emerald-950/80 border border-emerald-700 text-emerald-300 font-mono text-sm rounded-lg shadow-md flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    <form wire:submit="save" class="w-full flex-1 flex flex-col justify-between space-y-4">

        {{-- Area Textarea Roleplay (Fit Full Height) --}}
        <div
            class="flex-1 w-full border border-gray-700 bg-gray-800 rounded-lg p-4 relative shadow-sm focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500 transition-all">
            <textarea wire:model.blur="roleplayContent" placeholder="TEXT AREA UNTUK DATA ROLEPLAY"
                class="w-full h-full min-h-[500px] border-none outline-none resize-none font-mono text-base text-gray-200 placeholder-gray-500 bg-transparent p-2 focus:ring-0 focus:outline-none scrollbar-thin scrollbar-thumb-gray-600 scrollbar-track-transparent">
            </textarea>
        </div>

        @error('roleplayContent')
            <span class="text-red-400 font-mono text-xs">{{ $message }}</span>
        @enderror

        {{-- Save Button --}}
        <button type="submit" wire:loading.attr="disabled"
            class="w-full py-3.5 border border-emerald-600 bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white font-bold text-base tracking-widest rounded-lg transition-all duration-150 uppercase shadow-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center space-x-2">
            <span wire:loading.remove class="flex items-center space-x-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                </svg>
                <span>Simpan Data Roleplay</span>
            </span>
            <span wire:loading class="flex items-center space-x-2">
                <svg class="animate-spin w-5 h-5 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                        stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                    </path>
                </svg>
                <span>Menyimpan...</span>
            </span>
        </button>

    </form>
</div>
