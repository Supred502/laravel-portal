<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('REST XML Tool Logs') }}
            </h2>
            <a href="{{ route('rest-tool.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">
                Back to Tool
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="overflow-auto">
                        <table class="min-w-full text-sm text-left">
                            <thead class="text-xs uppercase text-gray-500 border-b">
                                <tr>
                                    <th class="py-2 px-3">Method</th>
                                    <th class="py-2 px-3">URL</th>
                                    <th class="py-2 px-3">Status</th>
                                    <th class="py-2 px-3">User</th>
                                    <th class="py-2 px-3">Date</th>
                                    <th class="py-2 px-3">Result</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach ($logs as $log)
                                    <tr>
                                        <td class="py-2 px-3">
                                            @php
                                                $methodTarget = $log->success && $log->method === 'GET'
                                                    ? route('rest-tool.index', ['log' => $log->id])
                                                    : route('rest-tool.logs.show', $log->id);
                                            @endphp
                                            <a href="{{ $methodTarget }}" class="text-indigo-600 hover:text-indigo-800">
                                                {{ $log->method }}
                                            </a>
                                        </td>
                                        <td class="py-2 px-3 break-all">{{ $log->url }}</td>
                                        <td class="py-2 px-3">{{ $log->status_code ?? '—' }}</td>
                                        <td class="py-2 px-3">{{ $log->user?->name ?? 'Unknown' }}</td>
                                        <td class="py-2 px-3">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                                        <td class="py-2 px-3">
                                            <span
                                                class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $log->success ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                                {{ $log->success ? 'Success' : 'Failed' }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $logs->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>