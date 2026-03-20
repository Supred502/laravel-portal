<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('REST XML Tool') }}
            </h2>
            <a href="{{ route('rest-tool.logs') }}" class="text-sm text-indigo-600 hover:text-indigo-800">
                View Logs
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg" data-panel-id="url-auth">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">URL + Auth</h3>
                        <button type="button" class="panel-toggle text-sm text-gray-500 hover:text-gray-700"
                            data-panel-toggle="url-auth">
                            Toggle
                        </button>
                    </div>

                    <div class="panel-body mt-4" data-panel-body="url-auth">
                        <form id="fetch-xml-form" method="POST" action="{{ route('rest-tool.fetch') }}" class="space-y-4">
                            @csrf

                            <div class="space-y-4">
                                <div>
                                    <x-input-label for="url" value="REST URL" />
                                    <x-text-input id="url" name="url" type="url" class="mt-1 block w-full"
                                        value="{{ old('url', $rememberedUrl) }}" required />
                                    <x-input-error :messages="$errors->get('url')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="auth_username" value="Username" />
                                    <x-text-input id="auth_username" name="auth_username" type="text"
                                        class="mt-1 block w-full"
                                        value="{{ old('auth_username', $rememberedAuthUsername) }}" />
                                </div>
                                <div>
                                    <x-input-label for="auth_password" value="Password" />
                                    <x-text-input id="auth_password" name="auth_password" type="password"
                                        class="mt-1 block w-full" autocomplete="new-password"
                                        value="{{ old('auth_password', $rememberedAuthPassword) }}" />
                                </div>
                                <div class="flex items-center gap-2 mt-2 mb-2 md:col-span-2">
                                    <input id="remember_auth" name="remember_auth" type="checkbox" value="1"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm" {{ old('remember_auth', $rememberAuth) ? 'checked' : '' }}>
                                    <label for="remember_auth" class="text-sm text-gray-600">Remember username and
                                        password</label>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <x-primary-button id="fetch-xml-submit">Fetch XML</x-primary-button>
                                @if ($fetchStatus)
                                    <span class="text-sm text-gray-600">{{ $fetchStatus }}</span>
                                @endif
                            </div>

                            @if ($fetchError)
                                <div class="mt-2 rounded-md bg-red-50 p-3 text-sm text-red-700">
                                    {{ $fetchError }}
                                </div>
                            @endif
                        </form>

                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg" data-panel-id="buve-tools">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">Būve</h3>
                        <button type="button" class="panel-toggle text-sm text-gray-500 hover:text-gray-700"
                            data-panel-toggle="buve-tools">
                            Toggle
                        </button>
                    </div>

                    <div class="panel-body mt-4" data-panel-body="buve-tools">
                        @include('rest-tool.partials.buve-manager')
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg" data-panel-id="xml-viewer">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">XML Viewer</h3>
                        <button type="button" class="panel-toggle text-sm text-gray-500 hover:text-gray-700"
                            data-panel-toggle="xml-viewer">
                            Toggle
                        </button>
                    </div>

                    <div class="panel-body mt-4" data-panel-body="xml-viewer">
                        @if ($xmlFormatted)
                            <div>
                                <h4 class="text-sm font-semibold text-gray-600 mb-2">Formatted XML</h4>
                                <textarea readonly id="formatted-xml"
                                    class="bg-gray-50 border border-gray-200 rounded-md p-3 text-xs font-mono overflow-hidden w-full whitespace-pre resize-none">{!! $xmlFormatted !!}</textarea>
                            </div>
                        @else
                            <div class="text-sm text-gray-500">Fetch XML to view the tree.</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg" data-panel-id="xml-tree">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">Collapsible Tree</h3>
                        <button type="button" class="panel-toggle text-sm text-gray-500 hover:text-gray-700"
                            data-panel-toggle="xml-tree">
                            Toggle
                        </button>
                    </div>

                    <div class="panel-body mt-4" data-panel-body="xml-tree">
                        @if ($xmlTree)
                            <div id="xml-tree"
                                class="bg-gray-50 border border-gray-200 rounded-md p-3 text-sm font-mono leading-relaxed overflow-auto h-96 xml-tree">
                                @include('partials.xml-tree', ['node' => $xmlTree, 'isRoot' => true])
                            </div>
                        @elseif ($xmlFormatted)
                            <div class="text-sm text-gray-500">XML is large; tree view is disabled for performance.</div>
                        @else
                            <div class="text-sm text-gray-500">Fetch XML to view the tree.</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg" data-panel-id="extracted-data">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">Extracted Data</h3>
                        <button type="button" class="panel-toggle text-sm text-gray-500 hover:text-gray-700"
                            data-panel-toggle="extracted-data">
                            Toggle
                        </button>
                    </div>

                    <div class="panel-body mt-4" data-panel-body="extracted-data">
                        @if (!empty($keyFields))
                            <div class="mb-6">
                                <h4 class="text-sm font-semibold text-gray-600 mb-2">Key Fields</h4>
                                <div class="overflow-auto" style="overflow-x: auto;">
                                    <table class="text-sm text-left" style="min-width: max-content;">
                                        <thead class="text-xs uppercase text-gray-500 border-b">
                                            <tr>
                                                <th class="py-2 px-3">Field</th>
                                                <th class="py-2 px-3">Value</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y">
                                            @foreach ($keyFields as $field)
                                                <tr>
                                                    <td class="py-2 px-3 text-gray-600 font-medium">
                                                        {{ $field['name'] }}
                                                    </td>
                                                    <td class="py-2 px-3">
                                                        <span class="xml-table-value xml-node-value xml-edit-input"
                                                            contenteditable="true" title="{{ $field['value'] ?? '' }}"
                                                            data-placeholder="(empty)"
                                                            style="display:inline-block;min-width:8rem;min-height:2rem;padding:0.25rem 0.5rem;border:1px solid #d1d5db;border-radius:0.375rem;background:#fff;box-shadow:inset 0 1px 2px rgba(0,0,0,.06),0 1px 2px rgba(15,23,42,.06);"
                                                            @if (!empty($field['path'])) data-path="{{ $field['path'] }}" data-original="{{ e($field['value'] ?? '') }}"
                                                            @else data-disabled="true" @endif>{{ $field['value'] ?? '' }}</span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        @if (!empty($xmlRows))
                            @foreach ($xmlRows as $groupIndex => $group)
                                <div class="mb-6">
                                    <div class="flex items-center justify-between gap-3 mb-2">
                                        <h4 class="text-sm font-semibold text-gray-600">Repeating: {{ $group['label'] }}</h4>
                                        @if (count($group['columns']) > 12)
                                            <button type="button" class="text-xs text-indigo-600 hover:text-indigo-800"
                                                data-columns-toggle="xml-table-{{ $groupIndex }}">
                                                Show all columns
                                            </button>
                                        @endif
                                    </div>
                                    <div class="overflow-auto" style="overflow-x: auto;">
                                        <table id="xml-table-{{ $groupIndex }}" data-columns-limit="12"
                                            class="text-sm text-left xml-rows-table" style="min-width: max-content;">
                                            <thead class="text-xs uppercase text-gray-500 border-b">
                                                <tr>
                                                    <th class="py-2 px-3">Row</th>
                                                    @foreach ($group['columns'] as $columnIndex => $column)
                                                        <th class="py-2 px-3" data-col-index="{{ $columnIndex }}">
                                                            {{ $column }}
                                                        </th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y">
                                                @foreach ($group['rows'] as $index => $row)
                                                    <tr>
                                                        <td class="py-2 px-3 text-gray-500">
                                                            <div class="font-medium text-gray-700">{{ $index + 1 }}</div>
                                                            @if (!empty($row['summary']))
                                                                <div class="text-xs text-gray-500">{{ $row['summary'] }}</div>
                                                            @endif
                                                        </td>
                                                        @foreach ($group['columns'] as $columnIndex => $column)
                                                            @php
                                                                $field = $row['fieldMap'][$column] ?? null;
                                                                $value = $field['value'] ?? '';
                                                                $path = $field['path'] ?? null;
                                                            @endphp
                                                            <td class="py-2 px-3" data-col-index="{{ $columnIndex }}">
                                                                <span class="xml-table-value xml-node-value xml-edit-input"
                                                                    contenteditable="true" title="{{ $value }}"
                                                                    data-placeholder="(empty)"
                                                                    style="display:inline-block;min-width:2rem;min-height:2rem;padding:0.25rem 0.5rem;border:1px solid #d1d5db;border-radius:0.375rem;background:#fff;box-shadow:inset 0 1px 2px rgba(0,0,0,.06),0 1px 2px rgba(15,23,42,.06);"
                                                                    @if ($path) data-path="{{ $path }}" data-original="{{ e($value) }}"
                                                                    @else data-disabled="true" @endif>{{ $value }}</span>
                                                            </td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="text-sm text-gray-500">No repeating rows detected in XML.</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg" data-panel-id="change-tracker">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">Change Tracker</h3>
                        <button type="button" class="panel-toggle text-sm text-gray-500 hover:text-gray-700"
                            data-panel-toggle="change-tracker">
                            Toggle
                        </button>
                    </div>

                    <div class="panel-body mt-4" data-panel-body="change-tracker">
                        <div id="change-tracker" class="text-sm text-gray-600">
                            <div class="text-gray-500">No changes yet.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg" data-panel-id="minimal-xml">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">Minimal XML Generator</h3>
                        <button type="button" class="panel-toggle text-sm text-gray-500 hover:text-gray-700"
                            data-panel-toggle="minimal-xml">
                            Toggle
                        </button>
                    </div>

                    <div class="panel-body mt-4" data-panel-body="minimal-xml">
                        <div class="flex items-center gap-3 mb-3">
                            <x-secondary-button type="button" id="generate-minimal-xml">Generate Minimal
                                XML</x-secondary-button>
                            <span class="text-xs text-gray-500">Only changed fields + required ancestors.</span>
                        </div>
                        <textarea id="generated-xml" name="generated_xml" form="post-xml-form"
                            class="w-full h-48 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Generated XML will appear here...">{{ old('generated_xml') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg" data-panel-id="post-panel">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">POST XML</h3>
                        <button type="button" class="panel-toggle text-sm text-gray-500 hover:text-gray-700"
                            data-panel-toggle="post-panel">
                            Toggle
                        </button>
                    </div>

                    <div class="panel-body mt-4" data-panel-body="post-panel">
                        <form id="post-xml-form" method="POST" action="{{ route('rest-tool.post') }}"
                            class="space-y-4">
                            @csrf

                            <div class="space-y-4">
                                <div>
                                    <x-input-label for="post_url" value="POST URL" />
                                    <x-text-input id="post_url" name="post_url" type="url" class="mt-1 block w-full"
                                        value="{{ old('post_url', $rememberedUrl) }}" required />
                                    <x-input-error :messages="$errors->get('post_url')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="post_auth_username" value="Username" />
                                    <x-text-input id="post_auth_username" name="auth_username" type="text"
                                        class="mt-1 block w-full"
                                        value="{{ old('auth_username', $rememberedAuthUsername) }}" />
                                </div>
                                <div>
                                    <x-input-label for="post_auth_password" value="Password" />
                                    <x-text-input id="post_auth_password" name="auth_password" type="password"
                                        class="mt-1 block w-full" autocomplete="new-password"
                                        value="{{ old('auth_password', $rememberedAuthPassword) }}" />
                                </div>
                                <div class="flex items-center gap-2 mt-2 mb-2 md:col-span-2">
                                    <input id="post_remember_auth" name="remember_auth" type="checkbox" value="1"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm" {{ old('remember_auth', $rememberAuth) ? 'checked' : '' }}>
                                    <label for="post_remember_auth" class="text-sm text-gray-600">Remember username and
                                        password</label>
                                </div>
                            </div>

                            <textarea id="request-xml" name="request_xml"
                                class="hidden">{{ e($xmlRaw ?? '') }}</textarea>

                            <div class="flex items-center gap-3">
                                <x-primary-button id="post-xml-submit">Send POST</x-primary-button>
                                @if ($postStatus)
                                    <span class="text-sm text-gray-600">{{ $postStatus }}</span>
                                @endif
                            </div>

                            @if ($postError)
                                <div class="mt-2 rounded-md bg-red-50 p-3 text-sm text-red-700">
                                    {{ $postError }}
                                </div>
                            @endif

                            @if ($postResponseFormatted)
                                <div class="mt-4">
                                    <h4 class="text-sm font-semibold text-gray-600 mb-2">Response XML</h4>
                                    <textarea readonly id="post-response-xml" data-autoresize
                                        class="bg-gray-50 border border-gray-200 rounded-md p-3 text-xs font-mono overflow-hidden w-full resize-none whitespace-pre">{!! $postResponseFormatted !!}</textarea>
                                </div>
                            @elseif ($postResponseDisplay || $postResponseXml)
                                <div class="mt-4">
                                    <h4 class="text-sm font-semibold text-gray-600 mb-2">Response XML</h4>
                                    <textarea readonly id="post-response-xml" data-autoresize
                                        class="bg-gray-50 border border-gray-200 rounded-md p-3 text-xs font-mono overflow-hidden w-full resize-none whitespace-pre">{!! $postResponseDisplay ?? e($postResponseXml) !!}</textarea>
                                </div>
                            @endif
                        </form>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg" data-panel-id="attachments-panel">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">Attachment Upload</h3>
                        <button type="button" class="panel-toggle text-sm text-gray-500 hover:text-gray-700"
                            data-panel-toggle="attachments-panel">
                            Toggle
                        </button>
                    </div>

                    <div class="panel-body mt-4" data-panel-body="attachments-panel">
                        <form id="attachment-upload-form" method="POST" action="{{ route('rest-tool.post') }}"
                            enctype="multipart/form-data" class="space-y-4">
                            @csrf

                            <div class="space-y-4">
                                <div>
                                    <x-input-label for="attachment_post_url" value="Attachment POST URL" />
                                    <x-text-input id="attachment_post_url" name="post_url" type="url"
                                        class="mt-1 block w-full"
                                        value="{{ old('post_url', $rememberedUrl) }}" required />
                                    <p class="text-xs text-gray-500 mt-1">/attachments is appended automatically.</p>
                                </div>
                                <div>
                                    <x-input-label for="attachment_auth_username" value="Username" />
                                    <x-text-input id="attachment_auth_username" name="auth_username" type="text"
                                        class="mt-1 block w-full"
                                        value="{{ old('auth_username', $rememberedAuthUsername) }}" />
                                </div>
                                <div>
                                    <x-input-label for="attachment_auth_password" value="Password" />
                                    <x-text-input id="attachment_auth_password" name="auth_password" type="password"
                                        class="mt-1 block w-full" autocomplete="new-password"
                                        value="{{ old('auth_password', $rememberedAuthPassword) }}" />
                                </div>
                                <div class="flex items-center gap-2 mt-2 mb-2 md:col-span-2">
                                    <input id="attachment_remember_auth" name="remember_auth" type="checkbox" value="1"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm" {{ old('remember_auth', $rememberAuth) ? 'checked' : '' }}>
                                    <label for="attachment_remember_auth" class="text-sm text-gray-600">Remember username and
                                        password</label>
                                </div>
                            </div>

                            <div>
                                <x-input-label for="attachments" value="File Upload (PDF)" />
                                <div class="mt-1 flex flex-wrap items-center gap-3">
                                    <input id="attachments" name="attachments[]" type="file" class="sr-only" multiple
                                        accept="application/pdf" />
                                    <label for="attachments"
                                        class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 cursor-pointer">
                                        Choose Files
                                    </label>
                                    <span id="attachments-filename" class="text-xs text-gray-500">No file chosen</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">PDF files only. Maximum 10MB each.</p>
                            </div>

                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <x-input-label for="attachment_storage_id" value="Storage ID" />
                                    <x-text-input id="attachment_storage_id" name="attachment_storage_id" type="number"
                                        class="mt-1 block w-full" min="1" value="{{ old('attachment_storage_id', 3) }}" />
                                </div>
                                <div>
                                    <x-input-label for="attachment_author" value="Author" />
                                    <x-text-input id="attachment_author" name="attachment_author" type="text"
                                        class="mt-1 block w-full"
                                        value="{{ old('attachment_author', $rememberedAuthUsername) }}" />
                                </div>
                                <div>
                                    <x-input-label for="attachment_description" value="Description" />
                                    <x-text-input id="attachment_description" name="attachment_description" type="text"
                                        class="mt-1 block w-full" value="{{ old('attachment_description') }}" />
                                </div>
                                <div>
                                    <x-input-label for="attachment_comment" value="Comment" />
                                    <x-text-input id="attachment_comment" name="attachment_comment" type="text"
                                        class="mt-1 block w-full" value="{{ old('attachment_comment') }}" />
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <x-primary-button>Upload PDF</x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <textarea id="original-xml" class="hidden">{{ e($xmlRaw ?? '') }}</textarea>
    @if ($selectedLog)
        <input type="hidden" id="rest-tool-log-id" value="{{ $selectedLog->id }}" />
        <input type="hidden" id="rest-tool-log-method" value="{{ $selectedLogMethod }}" />
    @endif

    <script>
        const attachmentsInput = document.getElementById("attachments");
        const attachmentsName = document.getElementById("attachments-filename");
        if (attachmentsInput && attachmentsName) {
            attachmentsInput.addEventListener("click", () => {
                attachmentsInput.value = "";
            });
            attachmentsInput.addEventListener("change", () => {
                const files = attachmentsInput.files || [];
                if (!files.length) {
                    attachmentsName.textContent = "No file chosen";
                    return;
                }
                if (files.length === 1) {
                    attachmentsName.textContent = files[0].name;
                    return;
                }
                attachmentsName.textContent = `${files.length} files selected`;
            });
        }
    </script>

    <script src="{{ asset('js/buve-manager.js') }}?v={{ filemtime(public_path('js/buve-manager.js')) }}"></script>
    <script src="{{ asset('js/xml-tree.js') }}?v={{ filemtime(public_path('js/xml-tree.js')) }}"></script>
</x-app-layout>