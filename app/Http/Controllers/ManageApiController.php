<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProviderRequest;
use App\Http\Requests\ApiKeysRequest;
use App\Models\AiProvider;
use App\Models\ApiKey;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ManageApiController extends Controller
{
    public function index()
    {
        return view('pages.manageApi');
    }

    public function createProvider(ProviderRequest $request)
    {
        try {
            DB::beginTransaction();

            AiProvider::create([
                'slug' => strtolower($request->slug),
                'default_model' => $request->default_model,
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Provider AI berhasil ditambahkan!');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return redirect()->back()->with('error', 'Gagal menambahkan provider: ' . $e->getMessage());
        }
    }

    public function updateProvider(ProviderRequest $request, $id_provider)
    {
        try {
            DB::beginTransaction();

            $provider = AiProvider::findOrFail($id_provider);
            $provider->update([
                'slug' => strtolower($request->slug),
                'default_model' => $request->default_model,
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Provider AI berhasil diperbarui!');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return redirect()->back()->with('error', 'Gagal memperbarui provider: ' . $e->getMessage());
        }
    }

    public function deleteProvider($id_provider)
    {
        try {
            DB::beginTransaction();

            $provider = AiProvider::findOrFail($id_provider);
            $provider->apiKeys()->delete();
            $provider->delete();

            DB::commit();
            return redirect()->back()->with('success', 'Provider AI berhasil dihapus!');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return redirect()->back()->with('error', 'Gagal menghapus provider: ' . $e->getMessage());
        }
    }

    public function insertApiKey(ApiKeysRequest $request, $id_provider)
    {
        try {
            DB::beginTransaction();

            $targetPriority = (int) $request->priority;

            ApiKey::where('id_provider', $id_provider)
                ->where('priority', '>=', $targetPriority)
                ->increment('priority');

            // Gunakan trim() untuk memastikan string API Key bersih dari spasi/karakter tersembunyi
            ApiKey::create([
                'id_provider' => $id_provider,
                'name' => trim($request->name),
                'encrypted_key' => trim($request->encrypted_key),
                'priority' => $targetPriority,
                'rate_limit' => $request->rate_limit,
                'status' => 'ready',
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'API Key berhasil ditambahkan!');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return redirect()->back()->with('error', 'Gagal menambahkan API Key: ' . $e->getMessage());
        }
    }

    public function updateApiKey(ApiKeysRequest $request, $id_api)
    {
        try {
            DB::beginTransaction();

            $apiKey = ApiKey::findOrFail($id_api);
            $oldPriority = $apiKey->priority;
            $newPriority = (int) $request->priority;
            $idProvider = $apiKey->id_provider;

            if ($oldPriority !== $newPriority) {
                ApiKey::where('id_provider', $idProvider)
                    ->where('id_api', '!=', $id_api)
                    ->where('priority', '>=', $newPriority)
                    ->increment('priority');
            }

            $updateData = [
                'name' => $request->name,
                'priority' => $newPriority,
                'rate_limit' => $request->rate_limit,
            ];

            if ($request->filled('encrypted_key')) {
                $updateData['encrypted_key'] = $request->encrypted_key;
            }

            $apiKey->update($updateData);

            DB::commit();
            return redirect()->back()->with('success', 'API Key berhasil diperbarui!');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return redirect()->back()->with('error', 'Gagal memperbarui API Key: ' . $e->getMessage());
        }
    }
}
