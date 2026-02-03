<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Log Detail') }}
            </h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('rest-tool.logs') }}" class="text-sm text-indigo-600 hover:text-indigo-800">
                    Back to Logs
                </a>
                <a href="{{ route('rest-tool.index', ['log' => $log->id]) }}"
                    class="text-sm text-indigo-600 hover:text-indigo-800">
                    Retry in Tool
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <div class="text-xs uppercase text-gray-500">Method</div>
                            <div class="text-sm text-gray-800">{{ $log->method }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase text-gray-500">Status Code</div>
                            <div class="text-sm text-gray-800">{{ $log->status_code ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase text-gray-500">URL</div>
                            <div class="text-sm text-gray-800 break-all">{{ $log->url }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase text-gray-500">User</div>
                            <div class="text-sm text-gray-800">{{ $log->user?->name ?? 'Unknown' }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase text-gray-500">Date</div>
                            <div class="text-sm text-gray-800">{{ $log->created_at->format('Y-m-d H:i') }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase text-gray-500">Result</div>
                            <div class="text-sm text-gray-800">
                                <span
                                    class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $log->success ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                    {{ $log->success ? 'Success' : 'Failed' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    @if ($log->error_message)
                        <div class="rounded-md bg-red-50 p-3 text-sm text-red-700">
                            {{ $log->error_message }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-800">Request XML</h3>
                    <textarea readonly
                        class="mt-2 bg-gray-50 border border-gray-200 rounded-md p-3 text-xs font-mono overflow-hidden w-full resize-none whitespace-pre"
                        data-autoresize>{!! $requestXmlFormatted ?? $requestXmlDisplay ?? $log->request_xml ?? '—' !!}</textarea>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-800">Response XML</h3>
                    <textarea readonly
                        class="mt-2 bg-gray-50 border border-gray-200 rounded-md p-3 text-xs font-mono overflow-hidden w-full resize-none whitespace-pre"
                        data-autoresize>{!! $responseXmlFormatted ?? $responseXmlDisplay ?? $log->response_xml ?? '—' !!}</textarea>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll("textarea[data-autoresize]").forEach((area) => {
            area.style.height = "auto";
            area.style.height = `${area.scrollHeight}px`;
        });
    </script>
</x-app-layout>