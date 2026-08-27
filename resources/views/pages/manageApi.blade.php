<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Management API Keys</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gray-900 text-gray-100 font-sans min-h-screen p-8">
    <div class="max-w-6xl mx-auto space-y-6">
        <div class="flex justify-between items-center border-b border-gray-800 pb-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-white">API Key Manager</h1>
                <p class="text-sm text-gray-400">Kelola API Keys, Priority, dan Fallback per Provider</p>
            </div>
        </div>

        {{-- Component Management Provider & Key --}}
        <livewire:manage-api.index />
    </div>

    {{-- Call Widget Chatbot di Sini (Akan Melayang di Pojok Kanan Bawah) --}}
    <livewire:chat-widget.index />
</body>

</html>
