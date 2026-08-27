<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\AiProvider;
use App\Models\ApiKey;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    public function toggleStatus(int $idApi, string $newStatus): void
    {
        $apiKey = ApiKey::find($idApi);

        // Mencegah perubahan status jika sedang dalam kondisi cooldown
        if ($apiKey && $apiKey->status !== 'cooldown') {
            $apiKey->update([
                'status' => $newStatus,
                'cooldown_until' => $newStatus === 'ready' ? null : $apiKey->cooldown_until,
            ]);
        }
    }

    public function deleteKey(int $idApi): void
    {
        ApiKey::where('id_api', $idApi)->delete();
    }

    public function with(): array
    {
        // Auto-reset API Key yang sudah melewati masa cooldown
        ApiKey::where('status', 'cooldown')
            ->where('cooldown_until', '<=', Carbon::now())
            ->update([
                'status' => 'ready',
                'cooldown_until' => null,
                'error_count' => 0,
            ]);

        return [
            'providers' => AiProvider::all(),
        ];
    }
};
?>

<div class="space-y-6">
    {{-- Alert Messages --}}
    @if (session()->has('success'))
        <div class="p-4 mb-4 text-sm text-green-300 rounded-lg bg-green-950/80 border border-green-700" role="alert">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="p-4 mb-4 text-sm text-red-300 rounded-lg bg-red-950/80 border border-red-700" role="alert">
            {{ session('error') }}
        </div>
    @endif

    {{-- 1. List Provider & API Key --}}
    <div class="space-y-4">
        @forelse($providers as $provider)
            @php
                $apiKeys = ApiKey::where('id_provider', $provider->id_provider)
                    ->orderBy('priority', 'asc')
                    ->paginate(5, ['*'], 'page_' . $provider->id_provider);
            @endphp

            {{-- Card Accordion per Slug --}}
            <div x-data="{ open: false, editing: false }" class="border border-gray-700 bg-gray-800 rounded-lg overflow-hidden shadow-sm">

                {{-- Header Accordion --}}
                <div
                    class="w-full px-5 py-4 flex items-center justify-between text-left hover:bg-gray-700/50 transition-colors">
                    <div class="flex items-center space-x-3 cursor-pointer flex-1" @click="open = !open">
                        <span class="text-lg font-semibold text-white tracking-wide uppercase">
                            {{ $provider->slug }}
                        </span>
                        <span
                            class="text-xs px-2.5 py-0.5 rounded-full bg-blue-900/60 text-blue-300 border border-blue-700">
                            Default: {{ $provider->default_model }}
                        </span>
                        <span class="text-xs text-gray-400">
                            ({{ $apiKeys->total() }} Key)
                        </span>
                    </div>

                    {{-- Action Header --}}
                    <div class="flex items-center space-x-2">
                        <button @click.stop="editing = !editing; if(editing) open = true;" type="button"
                            class="text-xs px-2.5 py-1.5 bg-blue-700/80 hover:bg-blue-600 text-white rounded-md transition flex items-center space-x-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            <span x-text="editing ? 'Batal' : 'Edit'">Edit</span>
                        </button>

                        <form action="{{ route('providers.destroy', $provider->id_provider) }}" method="POST"
                            onsubmit="return confirm('Apakah Anda yakin ingin menghapus provider {{ strtoupper($provider->slug) }} ini beserta seluruh API Key di dalamnya?');"
                            class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" @click.stop
                                class="text-xs px-2.5 py-1.5 bg-red-800/80 hover:bg-red-700 text-white rounded-md transition flex items-center space-x-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                <span>Hapus</span>
                            </button>
                        </form>

                        <button @click="open = !open" type="button" class="focus:outline-none pl-1">
                            <svg class="w-5 h-5 text-gray-400 transform transition-transform duration-200"
                                :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Inline Form: Edit Provider --}}
                <div x-show="editing" x-collapse x-cloak class="p-4 border-t border-b border-gray-700 bg-gray-900/70">
                    <form action="{{ route('providers.update', $provider->id_provider) }}" method="POST"
                        class="space-y-3">
                        @csrf
                        @method('PUT')
                        <div class="text-xs font-semibold uppercase text-blue-400 tracking-wider mb-2">Edit Provider:
                            {{ $provider->slug }}</div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block mb-1 text-xs font-medium text-gray-300">Slug Provider</label>
                                <input type="text" name="slug" value="{{ old('slug', $provider->slug) }}" required
                                    class="w-full px-3 py-1.5 bg-gray-800 border border-gray-600 text-white text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block mb-1 text-xs font-medium text-gray-300">Default Model</label>
                                <input type="text" name="default_model"
                                    value="{{ old('default_model', $provider->default_model) }}" required
                                    class="w-full px-3 py-1.5 bg-gray-800 border border-gray-600 text-white text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        <div class="flex justify-end space-x-2 pt-1">
                            <button @click="editing = false" type="button"
                                class="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs rounded-lg transition">
                                Batal
                            </button>
                            <button type="submit"
                                class="px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg transition">
                                Perbarui Provider
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Body Accordion --}}
                <div x-show="open" x-collapse x-cloak class="border-t border-gray-700 bg-gray-900/50">
                    <div class="overflow-x-auto p-4">
                        <table class="w-full text-sm text-left text-gray-300">
                            <thead class="text-xs uppercase bg-gray-800 text-gray-400 border-b border-gray-700">
                                <tr>
                                    <th scope="col" class="px-4 py-3">No</th>
                                    <th scope="col" class="px-4 py-3">Name</th>
                                    <th scope="col" class="px-4 py-3">Key (Encrypted)</th>
                                    <th scope="col" class="px-4 py-3">Status</th>
                                    <th scope="col" class="px-4 py-3">Priority</th>
                                    <th scope="col" class="px-4 py-3">Req / Err</th>
                                    <th scope="col" class="px-4 py-3">Last Used</th>
                                    <th scope="col" class="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                @forelse($apiKeys as $index => $key)
                                    <tr class="hover:bg-gray-800/50 transition-colors">
                                        <td class="px-4 py-3 font-medium text-gray-400">
                                            {{ $apiKeys->firstItem() + $index }}
                                        </td>
                                        <td class="px-4 py-3 font-semibold text-white">{{ $key->name }}</td>
                                        <td class="px-4 py-3 font-mono text-xs text-gray-400">
                                            {{ Str::limit($key->encrypted_key, 20, '...') }}
                                        </td>

                                        {{-- KOLOM STATUS --}}
                                        <td class="px-4 py-3">
                                            @if ($key->status === 'ready')
                                                <button wire:click="toggleStatus({{ $key->id_api }}, 'disabled')"
                                                    type="button"
                                                    class="px-2.5 py-1 text-xs font-semibold rounded-md bg-green-900/50 hover:bg-green-800/80 text-green-400 border border-green-700 transition flex items-center space-x-1 group"
                                                    title="Klik untuk menonaktifkan API Key">
                                                    <span class="w-2 h-2 rounded-full bg-green-400"></span>
                                                    <span>Ready</span>
                                                </button>
                                            @elseif($key->status === 'cooldown')
                                                <div class="flex flex-col space-y-0.5">
                                                    <button type="button" disabled
                                                        class="px-2.5 py-1 text-xs font-semibold rounded-md bg-yellow-900/40 text-yellow-400 border border-yellow-700/80 cursor-not-allowed opacity-80 flex items-center space-x-1"
                                                        title="Cooldown s/d {{ $key->cooldown_until ? $key->cooldown_until->format('H:i:s d/m/Y') : '-' }}">
                                                        <span
                                                            class="w-2 h-2 rounded-full bg-yellow-400 animate-pulse"></span>
                                                        <span>Cooldown</span>
                                                    </button>
                                                    @if ($key->cooldown_until)
                                                        <span class="text-[10px] text-yellow-400/80 font-mono">
                                                            s/d {{ $key->cooldown_until->format('H:i:s') }}
                                                            ({{ $key->cooldown_until->diffForHumans() }})
                                                        </span>
                                                    @endif
                                                </div>
                                            @else
                                                <button wire:click="toggleStatus({{ $key->id_api }}, 'ready')"
                                                    type="button"
                                                    class="px-2.5 py-1 text-xs font-semibold rounded-md bg-red-900/50 hover:bg-red-800/80 text-red-400 border border-red-700 transition flex items-center space-x-1 group"
                                                    title="Klik untuk mengaktifkan API Key">
                                                    <span class="w-2 h-2 rounded-full bg-red-400"></span>
                                                    <span>Disabled</span>
                                                </button>
                                            @endif
                                        </td>

                                        <td class="px-4 py-3 font-bold text-blue-400">{{ $key->priority }}</td>
                                        <td class="px-4 py-3 text-xs">
                                            <span class="text-green-400">{{ $key->request_count }}</span> /
                                            <span class="text-red-400">{{ $key->error_count }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-400">
                                            {{ $key->last_used_at ? $key->last_used_at->diffForHumans() : '-' }}
                                        </td>

                                        {{-- KOLOM AKSI --}}
                                        <td class="px-4 py-3 text-right">
                                            <button wire:click="deleteKey({{ $key->id_api }})" type="button"
                                                wire:confirm="Yakin ingin menghapus API key ini?"
                                                class="text-xs px-2.5 py-1 bg-red-800/80 hover:bg-red-700 text-white rounded transition">
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">
                                            Belum ada API Key untuk provider ini. Buka form di bawah untuk menambahkan.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($apiKeys->hasPages())
                        <div class="px-4 pb-4">
                            {{ $apiKeys->links() }}
                        </div>
                    @endif

                    {{-- Form Tambah API Key --}}
                    <div class="p-4 pt-0">
                        <details
                            class="group bg-gray-800/80 border border-gray-700/80 rounded-lg overflow-hidden shadow-inner">
                            <summary
                                class="flex items-center justify-between px-4 py-3 font-semibold text-emerald-400 cursor-pointer hover:bg-gray-700/40 transition-colors select-none text-sm">
                                <span class="flex items-center space-x-2">
                                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v16m8-8H4" />
                                    </svg>
                                    <span>Tambah API Key untuk {{ strtoupper($provider->slug) }}</span>
                                </span>
                                <svg class="w-4 h-4 text-gray-400 transform group-open:rotate-180 transition-transform duration-200"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7" />
                                </svg>
                            </summary>

                            <div class="p-4 border-t border-gray-700 bg-gray-900/60">
                                <form action="{{ route('api-keys.store', $provider->id_provider) }}" method="POST"
                                    class="space-y-3">
                                    @csrf
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                        <div>
                                            <label class="block mb-1 text-xs font-medium text-gray-300">Nama
                                                Key</label>
                                            <input type="text" name="name"
                                                placeholder="mis. Key Utama / Production" required
                                                class="w-full px-3 py-1.5 bg-gray-800 border border-gray-600 text-white text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500">
                                        </div>

                                        <div>
                                            <label class="block mb-1 text-xs font-medium text-gray-300">API Key
                                                String</label>
                                            <input type="text" name="encrypted_key" placeholder="AIzaSy..."
                                                autocomplete="off" autocorrect="off" autocapitalize="off"
                                                spellcheck="false" required
                                                class="w-full px-3 py-1.5 bg-gray-800 border border-gray-600 text-white text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 font-mono">
                                        </div>

                                        <div>
                                            <label
                                                class="block mb-1 text-xs font-medium text-gray-300">Priority</label>
                                            <input type="number" name="priority" min="0" value="1"
                                                required
                                                class="w-full px-3 py-1.5 bg-gray-800 border border-gray-600 text-white text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500">
                                        </div>

                                        <div>
                                            <label class="block mb-1 text-xs font-medium text-gray-300">Rate Limit
                                                (Req/Min)</label>
                                            <input type="number" name="rate_limit" min="1" value="60"
                                                required
                                                class="w-full px-3 py-1.5 bg-gray-800 border border-gray-600 text-white text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500">
                                        </div>
                                    </div>

                                    <div class="flex justify-end pt-1">
                                        <button type="submit"
                                            class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold rounded-lg transition-colors shadow">
                                            Simpan API Key
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </details>
                    </div>
                </div>
            </div>
        @empty
            <div class="p-8 text-center bg-gray-800 rounded-lg border border-gray-700 text-gray-400">
                Tidak ada data provider ditemukan.
            </div>
        @endforelse
    </div>

    {{-- 2. Form Tambah Provider --}}
    <details class="group bg-gray-800 border border-gray-700 rounded-lg shadow-sm overflow-hidden">
        <summary
            class="flex items-center justify-between px-5 py-4 font-semibold text-white cursor-pointer bg-gray-800 hover:bg-gray-700/50 transition-colors select-none">
            <span class="flex items-center space-x-2">
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Tambah Provider AI Baru</span>
            </span>
            <svg class="w-5 h-5 text-gray-400 transform group-open:rotate-180 transition-transform duration-200"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </summary>

        <div class="p-5 border-t border-gray-700 bg-gray-900/40">
            <form action="{{ route('providers.store') }}" method="POST" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="slug" class="block mb-1 text-sm font-medium text-gray-300">Slug
                            Provider</label>
                        <input type="text" id="slug" name="slug" value="{{ old('slug') }}"
                            placeholder="Contoh: gemini, openai" required
                            class="w-full px-3.5 py-2 bg-gray-800 border border-gray-600 text-white text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 @error('slug') border-red-500 @enderror">
                        @error('slug')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="default_model" class="block mb-1 text-sm font-medium text-gray-300">Default
                            Model</label>
                        <input type="text" id="default_model" name="default_model"
                            value="{{ old('default_model') }}" placeholder="Contoh: gemini-1.5-flash, gemini-1.5-pro"
                            required
                            class="w-full px-3.5 py-2 bg-gray-800 border border-gray-600 text-white text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 @error('default_model') border-red-500 @enderror">
                        @error('default_model')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="submit"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded-lg transition-colors shadow">
                        Simpan Provider
                    </button>
                </div>
            </form>
        </div>
    </details>
</div>
