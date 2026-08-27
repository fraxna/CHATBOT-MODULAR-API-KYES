<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\AiProvider;
use App\Models\ApiKey;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    // Model State untuk Edit API Key Modal
    public $editingKeyId = null;
    public $edit_name = '';
    public $edit_key = '';
    public $edit_priority = 1;
    public $edit_rate_limit = 60;

    // Model State untuk Create Provider Inline
    public $new_provider_slug = '';
    public $new_provider_default_model = '';

    // Model State untuk Create API Key Inline (Per Provider)
    public $new_key_name = '';
    public $new_key_string = '';
    public $new_key_priority = 1;
    public $new_key_rate_limit = 60;

    protected function rules(): array
    {
        return [
            'edit_name' => 'required|string|max:255',
            'edit_key' => 'required|string',
            'edit_priority' => 'required|integer|min:0',
            'edit_rate_limit' => 'required|integer|min:1',
        ];
    }

    public function toggleStatus(int $idApi, string $newStatus): void
    {
        $apiKey = ApiKey::find($idApi);

        if ($apiKey && $apiKey->status !== 'cooldown') {
            $apiKey->update([
                'status' => $newStatus,
                'cooldown_until' => $newStatus === 'ready' ? null : $apiKey->cooldown_until,
            ]);
            session()->flash('success', 'Status API Key berhasil diperbarui.');
        }
    }

    public function deleteKey(int $idApi): void
    {
        ApiKey::where('id_api', $idApi)->delete();
        session()->flash('success', 'API Key berhasil dihapus.');
    }

    // --- FITUR EDIT API KEY ---
    public function editKey(int $idApi): void
    {
        $key = ApiKey::find($idApi);
        if ($key) {
            $this->editingKeyId = $key->id_api;
            $this->edit_name = $key->name;
            // Mengambil plaintext dari Accessor Model
            $this->edit_key = $key->decrypted_key;
            $this->edit_priority = $key->priority;
            $this->edit_rate_limit = $key->rate_limit ?? 60;
        }
    }

    public function cancelEditKey(): void
    {
        $this->reset(['editingKeyId', 'edit_name', 'edit_key', 'edit_priority', 'edit_rate_limit']);
    }

    // --- UPDATE API KEY ---
    public function updateKey(): void
    {
        $this->validate();

        $apiKey = ApiKey::find($this->editingKeyId);
        if ($apiKey) {
            $oldPriority = $apiKey->priority;
            $newPriority = (int) $this->edit_priority;

            // Geser priority jika terjadi perubahan urutan
            if ($oldPriority !== $newPriority) {
                ApiKey::where('id_provider', $apiKey->id_provider)->where('id_api', '!=', $apiKey->id_api)->where('priority', '>=', $newPriority)->increment('priority');
            }

            $apiKey->update([
                'name' => trim($this->edit_name),
                'encrypted_key' => trim($this->edit_key), // Otomatis di-encrypt oleh Mutator Model
                'priority' => $newPriority,
                'rate_limit' => $this->edit_rate_limit,
            ]);

            session()->flash('success', 'API Key berhasil diperbarui!');
            $this->cancelEditKey();
        }
    }

    // --- TAMBAH API KEY BARU ---
    public function storeKey(int $providerId): void
    {
        $this->validate([
            'new_key_name' => 'required|string|max:255',
            'new_key_string' => 'required|string',
            'new_key_priority' => 'required|integer|min:0',
            'new_key_rate_limit' => 'required|integer|min:1',
        ]);

        $targetPriority = (int) $this->new_key_priority;

        // Geser priority yang ada jika memasukkan priority bentrok/lebih rendah
        ApiKey::where('id_provider', $providerId)->where('priority', '>=', $targetPriority)->increment('priority');

        ApiKey::create([
            'id_provider' => $providerId,
            'name' => trim($this->new_key_name),
            'encrypted_key' => trim($this->new_key_string), // Otomatis di-encrypt oleh Mutator Model
            'priority' => $targetPriority,
            'rate_limit' => $this->new_key_rate_limit,
            'status' => 'ready',
        ]);

        session()->flash('success', 'API Key baru berhasil ditambahkan.');
        $this->reset(['new_key_name', 'new_key_string', 'new_key_priority', 'new_key_rate_limit']);
    }

    public function storeProvider(): void
    {
        $this->validate([
            'new_provider_slug' => 'required|string|unique:ai_providers,slug',
            'new_provider_default_model' => 'required|string',
        ]);

        AiProvider::create([
            'slug' => strtolower(trim($this->new_provider_slug)),
            'default_model' => trim($this->new_provider_default_model),
        ]);

        session()->flash('success', 'Provider AI berhasil ditambahkan.');
        $this->reset(['new_provider_slug', 'new_provider_default_model']);
    }

    public function deleteProvider(int $providerId): void
    {
        AiProvider::destroy($providerId);
        session()->flash('success', 'Provider beserta seluruh API Key di dalamnya berhasil dihapus.');
    }

    public function with(): array
    {
        // Auto-reset status cooldown yang telah lewat waktu
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

{{-- wire:poll.3s.keep memastikan polling tidak mengganggu input saat user fokus mengedit --}}
<div wire:poll.3s.keep class="space-y-6">

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

            <div x-data="{ open: true }" class="border border-gray-700 bg-gray-800 rounded-lg overflow-hidden shadow-sm">

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

                    <div class="flex items-center space-x-2">
                        <button wire:click="deleteProvider({{ $provider->id_provider }})"
                            wire:confirm="Apakah Anda yakin ingin menghapus provider {{ strtoupper($provider->slug) }} beserta seluruh API Key di dalamnya?"
                            type="button"
                            class="text-xs px-2.5 py-1.5 bg-red-800/80 hover:bg-red-700 text-white rounded-md transition flex items-center space-x-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            <span>Hapus Provider</span>
                        </button>

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

                {{-- Body Accordion --}}
                <div x-show="open" x-collapse class="border-t border-gray-700 bg-gray-900/50">
                    <div class="overflow-x-auto p-4">
                        <table class="w-full text-sm text-left text-gray-300">
                            <thead class="text-xs uppercase bg-gray-800 text-gray-400 border-b border-gray-700">
                                <tr>
                                    <th scope="col" class="px-4 py-3">No</th>
                                    <th scope="col" class="px-4 py-3">Name</th>
                                    <th scope="col" class="px-4 py-3">Key (Preview)</th>
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
                                            {{-- Menampilkan 6 karakter pertama dari decrypted key agar aman --}}
                                            {{ Str::limit($key->decrypted_key, 12, '***') }}
                                        </td>

                                        {{-- STATUS --}}
                                        <td class="px-4 py-3">
                                            @if ($key->status === 'ready')
                                                <button wire:click="toggleStatus({{ $key->id_api }}, 'disabled')"
                                                    type="button"
                                                    class="px-2.5 py-1 text-xs font-semibold rounded-md bg-green-900/50 hover:bg-green-800/80 text-green-400 border border-green-700 transition flex items-center space-x-1 group">
                                                    <span class="w-2 h-2 rounded-full bg-green-400"></span>
                                                    <span>Ready</span>
                                                </button>
                                            @elseif($key->status === 'cooldown')
                                                <div class="flex flex-col space-y-0.5">
                                                    <button type="button" disabled
                                                        class="px-2.5 py-1 text-xs font-semibold rounded-md bg-yellow-900/40 text-yellow-400 border border-yellow-700/80 cursor-not-allowed opacity-80 flex items-center space-x-1">
                                                        <span
                                                            class="w-2 h-2 rounded-full bg-yellow-400 animate-pulse"></span>
                                                        <span>Cooldown</span>
                                                    </button>
                                                    @if ($key->cooldown_until)
                                                        <span class="text-[10px] text-yellow-400/80 font-mono">
                                                            s/d {{ $key->cooldown_until->format('H:i:s') }}
                                                        </span>
                                                    @endif
                                                </div>
                                            @else
                                                <button wire:click="toggleStatus({{ $key->id_api }}, 'ready')"
                                                    type="button"
                                                    class="px-2.5 py-1 text-xs font-semibold rounded-md bg-red-900/50 hover:bg-red-800/80 text-red-400 border border-red-700 transition flex items-center space-x-1 group">
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

                                        {{-- AKSI --}}
                                        <td class="px-4 py-3 text-right space-x-1">
                                            <button wire:click="editKey({{ $key->id_api }})" type="button"
                                                class="text-xs px-2.5 py-1 bg-blue-700/80 hover:bg-blue-600 text-white rounded transition">
                                                Edit
                                            </button>
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
                                            Belum ada API Key untuk provider ini.
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
                                <form wire:submit.prevent="storeKey({{ $provider->id_provider }})" class="space-y-3">
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                        <div>
                                            <label class="block mb-1 text-xs font-medium text-gray-300">Nama
                                                Key</label>
                                            <input type="text" wire:model="new_key_name"
                                                placeholder="mis. Key Utama" required
                                                class="w-full px-3 py-1.5 bg-gray-800 border border-gray-600 text-white text-sm rounded-lg focus:ring-emerald-500">
                                        </div>

                                        <div>
                                            <label class="block mb-1 text-xs font-medium text-gray-300">API Key
                                                String</label>
                                            <input type="text" wire:model="new_key_string" placeholder="AIzaSy..."
                                                required
                                                class="w-full px-3 py-1.5 bg-gray-800 border border-gray-600 text-white text-sm rounded-lg focus:ring-emerald-500 font-mono">
                                        </div>

                                        <div>
                                            <label
                                                class="block mb-1 text-xs font-medium text-gray-300">Priority</label>
                                            <input type="number" wire:model="new_key_priority" min="0"
                                                required
                                                class="w-full px-3 py-1.5 bg-gray-800 border border-gray-600 text-white text-sm rounded-lg focus:ring-emerald-500">
                                        </div>

                                        <div>
                                            <label class="block mb-1 text-xs font-medium text-gray-300">Rate Limit
                                                (Req/Min)</label>
                                            <input type="number" wire:model="new_key_rate_limit" min="1"
                                                required
                                                class="w-full px-3 py-1.5 bg-gray-800 border border-gray-600 text-white text-sm rounded-lg focus:ring-emerald-500">
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

    {{-- MODAL UPDATE API KEY (TAMPILKAN DECRYPTED KEY DAN SIMPAN ENCRYPTED) --}}
    @if ($editingKeyId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
            <div class="bg-gray-800 border border-gray-700 rounded-lg p-6 w-full max-w-lg shadow-xl space-y-4">
                <div class="flex justify-between items-center border-b border-gray-700 pb-3">
                    <h3 class="text-lg font-semibold text-white">Edit API Key</h3>
                    <button wire:click="cancelEditKey" class="text-gray-400 hover:text-white">&times;</button>
                </div>

                <form wire:submit.prevent="updateKey" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-300 mb-1">Nama Key</label>
                        <input type="text" wire:model="edit_name" required
                            class="w-full px-3 py-2 bg-gray-900 border border-gray-600 rounded-lg text-white text-sm">
                        @error('edit_name')
                            <span class="text-xs text-red-400">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-300 mb-1">
                            API Key Value <span class="text-emerald-400 text-[10px]">(Sudah di-decrypt)</span>
                        </label>
                        <input type="text" wire:model="edit_key" required
                            class="w-full px-3 py-2 bg-gray-900 border border-gray-600 rounded-lg text-white font-mono text-sm focus:ring-emerald-500">
                        <p class="text-[10px] text-gray-400 mt-1">Mengedit nilai ini akan otomatis meng-encrypt ulang
                            sebelum disimpan ke database.</p>
                        @error('edit_key')
                            <span class="text-xs text-red-400">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-300 mb-1">Priority</label>
                            <input type="number" wire:model="edit_priority" min="0" required
                                class="w-full px-3 py-2 bg-gray-900 border border-gray-600 rounded-lg text-white text-sm">
                            @error('edit_priority')
                                <span class="text-xs text-red-400">{{ $message }}</span>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-300 mb-1">Rate Limit (Req/Min)</label>
                            <input type="number" wire:model="edit_rate_limit" min="1" required
                                class="w-full px-3 py-2 bg-gray-900 border border-gray-600 rounded-lg text-white text-sm">
                            @error('edit_rate_limit')
                                <span class="text-xs text-red-400">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="flex justify-end space-x-2 pt-2">
                        <button type="button" wire:click="cancelEditKey"
                            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-200 text-xs rounded-lg">
                            Batal
                        </button>
                        <button type="submit"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

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
            <form wire:submit.prevent="storeProvider" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-300">Slug Provider</label>
                        <input type="text" wire:model="new_provider_slug" placeholder="Contoh: gemini, openai"
                            required
                            class="w-full px-3.5 py-2 bg-gray-800 border border-gray-600 text-white text-sm rounded-lg focus:ring-blue-500">
                        @error('new_provider_slug')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-300">Default Model</label>
                        <input type="text" wire:model="new_provider_default_model"
                            placeholder="Contoh: gemini-1.5-flash" required
                            class="w-full px-3.5 py-2 bg-gray-800 border border-gray-600 text-white text-sm rounded-lg focus:ring-blue-500">
                        @error('new_provider_default_model')
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
