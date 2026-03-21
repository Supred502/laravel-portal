@php
    $selectedBuveMode = old('buve_mode', $buveRememberedMode ?? 'predefined');
    if (is_array($selectedBuveMode)) {
        $selectedBuveMode = count($selectedBuveMode) === 1 ? $selectedBuveMode[0] : null;
    }
@endphp

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h4 class="text-base font-semibold text-gray-800">Būve Bulk Tools</h4>
    </div>

    <form id="buve-load-form" method="POST" action="{{ route('rest-tool.buves.load') }}" class="space-y-4">
        @csrf

        <div class="space-y-4">
            <div>
                <x-input-label for="buve_base_url" value="Būve REST URL" />
                <x-text-input id="buve_base_url" name="buve_base_url" type="url" class="mt-1 block w-full"
                    value="{{ old('buve_base_url', $buveRememberedBaseUrl ?? $rememberedUrl) }}" required />
                <x-input-error :messages="$errors->get('buve_base_url')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="buve_auth_username" value="Username" />
                <x-text-input id="buve_auth_username" name="buve_auth_username" type="text"
                    class="mt-1 block w-full"
                    value="{{ old('buve_auth_username', $buveRememberedAuthUsername ?? $rememberedAuthUsername) }}" />
                <x-input-error :messages="$errors->get('buve_auth_username')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="buve_auth_password" value="Password" />
                <x-text-input id="buve_auth_password" name="buve_auth_password" type="password"
                    class="mt-1 block w-full" autocomplete="new-password"
                    value="{{ old('buve_auth_password', $buveRememberedAuthPassword ?? $rememberedAuthPassword) }}" />
                <x-input-error :messages="$errors->get('buve_auth_password')" class="mt-2" />
            </div>
            <div class="flex items-center gap-2 mt-2">
                <input id="buve_remember_auth" name="buve_remember_auth" type="checkbox" value="1"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm" {{ old('buve_remember_auth', $buveRememberAuth ?? $rememberAuth) ? 'checked' : '' }}>
                <label for="buve_remember_auth" class="text-sm text-gray-600">Remember username and password for Būve</label>
            </div>
        </div>

        <div class="rounded-md border border-gray-200 p-4 space-y-3">
            <p class="text-sm font-semibold text-gray-700">Data Retrieval Mode</p>
            <div class="space-y-2">
                <label class="flex items-start gap-2 text-sm text-gray-700">
                    <input type="radio" name="buve_mode" value="all" class="mt-1 buve-mode-radio"
                        {{ $selectedBuveMode === 'all' ? 'checked' : '' }}>
                    <span>Load all records</span>
                </label>
                <label class="flex items-start gap-2 text-sm text-gray-700">
                    <input type="radio" name="buve_mode" value="dynamic" class="mt-1 buve-mode-radio"
                        {{ $selectedBuveMode === 'dynamic' ? 'checked' : '' }}>
                    <span>Load using dynamic REST query</span>
                </label>
                <label class="flex items-start gap-2 text-sm text-gray-700">
                    <input type="radio" name="buve_mode" value="predefined" class="mt-1 buve-mode-radio"
                        {{ $selectedBuveMode === 'predefined' ? 'checked' : '' }}>
                    <span>Load using predefined filters</span>
                </label>
            </div>
            <x-input-error :messages="$errors->get('buve_mode')" class="mt-2" />

            <div id="buve-dynamic-query-fields" class="space-y-2 {{ $selectedBuveMode === 'dynamic' ? '' : 'hidden' }}">
                <x-input-label for="buve_dynamic_query" value="Dynamic Query Path" />
                <div class="flex flex-col sm:flex-row gap-2">
                    <x-text-input id="buve_dynamic_query" name="buve_dynamic_query" type="text" class="block w-full"
                        value="{{ old('buve_dynamic_query', $buveRememberedDynamicQuery ?: '/query') }}"
                        placeholder="/query?limit=20" />
                </div>
                <p class="text-xs text-gray-500">Uses Būve URL + Auth values above for request and authentication.</p>
                <x-input-error :messages="$errors->get('buve_dynamic_query')" class="mt-2" />
            </div>

            <div id="buve-predefined-filter-fields" class="space-y-2 {{ $selectedBuveMode === 'predefined' ? '' : 'hidden' }}">
                <x-input-label for="buve_filter_pilnadrese" value="PILNADRESE contains" />
                <x-text-input id="buve_filter_pilnadrese" name="buve_filter_pilnadrese" type="text" class="block w-full"
                    value="{{ old('buve_filter_pilnadrese', $buveRememberedAddressFilter) }}" placeholder="Rīga" />
                <p class="text-xs text-gray-500">Example: input Rīga returns all rows where PILNADRESE contains Rīga.</p>
                <x-input-error :messages="$errors->get('buve_filter_pilnadrese')" class="mt-2" />
            </div>
        </div>

        <div class="flex items-center gap-3">
            <x-primary-button id="buve-load-submit">Load Būves</x-primary-button>
            @if ($buveLoadStatus)
                <span class="text-sm text-gray-600">{{ $buveLoadStatus }}</span>
            @endif
        </div>

        @if ($buveLoadError)
            <div class="rounded-md bg-red-50 p-3 text-sm text-red-700">
                {{ $buveLoadError }}
            </div>
        @endif
    </form>

    @if (!empty($buveRows))
        <form id="buve-update-form" method="POST" action="{{ route('rest-tool.buves.update') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="buve_base_url" id="buve_update_base_url"
                value="{{ old('buve_base_url', $buveRememberedBaseUrl ?? $rememberedUrl) }}" />
            <input type="hidden" name="buve_auth_username" id="buve_update_auth_username"
                value="{{ old('buve_auth_username', $buveRememberedAuthUsername ?? $rememberedAuthUsername) }}" />
            <input type="hidden" name="buve_auth_password" id="buve_update_auth_password"
                value="{{ old('buve_auth_password', $buveRememberedAuthPassword ?? $rememberedAuthPassword) }}" />
            <input type="hidden" name="buve_remember_auth" id="buve_update_remember_auth"
                value="{{ old('buve_remember_auth', $buveRememberAuth ?? $rememberAuth) ? 1 : 0 }}" />

            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                <h4 class="text-sm font-semibold text-gray-700">Loaded Būves: {{ count($buveRows) }}</h4>
                <div class="text-xs text-gray-500">Modified rows: <span id="buve-modified-count">0</span></div>
            </div>

            <p class="text-xs text-gray-500">Fields grouped by XML node: DmPNSObjMBL and dmPNSBuveP2BL. Linked cadastral fields auto-refresh APZIMKOP.</p>

            <style>
                .buve-grid-table th,
                .buve-grid-table td {
                    border-right: 1px solid #e5e7eb;
                }

                .buve-grid-table th:last-child,
                .buve-grid-table td:last-child {
                    border-right: none;
                }

                .buve-grid-table tbody td {
                    border-bottom: 1px solid #e5e7eb;
                }

                .buve-grid-table tbody tr:last-child td {
                    border-bottom: none;
                }
            </style>

            <div class="overflow-auto border border-gray-200 rounded-md bg-white relative" style="overflow-x: auto; overflow-y: auto; height: 22rem; min-height: 14rem; resize: vertical;">
                <table class="buve-grid-table text-sm text-left" style="min-width: max-content; border-collapse: separate; border-spacing: 0;">
                    <thead class="text-xs text-gray-600 border-b">
                        <tr class="uppercase border-b">
                            <th class="py-2 px-3" style="position: sticky; top: 0; left: 0; z-index: 50; background-color: #ffffff; border-bottom: 1px solid #e5e7eb; height: 2.5rem;" rowspan="2">ID</th>
                            <th class="py-2 px-3 text-center" style="position: sticky; top: 0; z-index: 30; background-color: #ffffff; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb; height: 2.5rem;" colspan="7">DmPNSObjMBL</th>
                            <th class="py-2 px-3 text-center" style="position: sticky; top: 0; z-index: 30; background-color: #ffffff; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb; height: 2.5rem;" colspan="6">dmPNSBuveP2BL</th>
                            <th class="py-2 px-3 text-center" style="position: sticky; top: 0; z-index: 30; background-color: #ffffff; border-bottom: 1px solid #e5e7eb; height: 2.5rem;" colspan="1">Entity</th>
                        </tr>
                        <tr class="uppercase">
                            <th class="py-2 px-3" style="position: sticky; top: 2.5rem; z-index: 30; background-color: #ffffff; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">PILNADRESE</th>
                            <th class="py-2 px-3" style="position: sticky; top: 2.5rem; z-index: 30; background-color: #ffffff; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">ADRESE</th>
                            <th class="py-2 px-3" style="position: sticky; top: 2.5rem; z-index: 30; background-color: #ffffff; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">KADTER</th>
                            <th class="py-2 px-3" style="position: sticky; top: 2.5rem; z-index: 30; background-color: #ffffff; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">KADGRUPA</th>
                            <th class="py-2 px-3" style="position: sticky; top: 2.5rem; z-index: 30; background-color: #ffffff; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">ZEMENR</th>
                            <th class="py-2 px-3" style="position: sticky; top: 2.5rem; z-index: 30; background-color: #ffffff; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">ZDBUVENR</th>
                            <th class="py-2 px-3" style="position: sticky; top: 2.5rem; z-index: 30; background-color: #ffffff; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">APZIMKOP</th>
                            <th class="py-2 px-3" style="position: sticky; top: 2.5rem; z-index: 30; background-color: #ffffff; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">PK_BUVEGRP</th>
                            <th class="py-2 px-3" style="position: sticky; top: 2.5rem; z-index: 30; background-color: #ffffff; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">GADS</th>
                            <th class="py-2 px-3" style="position: sticky; top: 2.5rem; z-index: 30; background-color: #ffffff; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">INBUVE</th>
                            <th class="py-2 px-3" style="position: sticky; top: 2.5rem; z-index: 30; background-color: #ffffff; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">EFEKTIV</th>
                            <th class="py-2 px-3" style="position: sticky; top: 2.5rem; z-index: 30; background-color: #ffffff; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">ATSAVIN</th>
                            <th class="py-2 px-3" style="position: sticky; top: 2.5rem; z-index: 30; background-color: #ffffff; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">PK_BSERIJA</th>
                            <th class="py-2 px-3" style="position: sticky; top: 2.5rem; z-index: 30; background-color: #ffffff; border-bottom: 1px solid #e5e7eb;">PIEZIMES</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($buveRows as $row)
                            @php
                                $rowId = (string) ($row['id'] ?? '');
                                $currentPilnadrese = old('rows.' . $rowId . '.pilnadrese', $row['pilnadrese'] ?? '');
                                $currentAdrese = old('rows.' . $rowId . '.adrese', $row['adrese'] ?? '');
                                $currentKadter = old('rows.' . $rowId . '.kadter', $row['kadter'] ?? '');
                                $currentKadgrupa = old('rows.' . $rowId . '.kadgrupa', $row['kadgrupa'] ?? '');
                                $currentZemenr = old('rows.' . $rowId . '.zemenr', $row['zemenr'] ?? '');
                                $currentZdbuvenr = old('rows.' . $rowId . '.zdbuvenr', $row['zdbuvenr'] ?? '');
                                $currentApzimkop = old('rows.' . $rowId . '.apzimkop', $row['apzimkop'] ?? '');
                                $currentPkBuvegrp = old('rows.' . $rowId . '.pk_buvegrp', $row['pk_buvegrp'] ?? '');
                                $currentGads = old('rows.' . $rowId . '.gads', $row['gads'] ?? '');
                                $currentInbuve = old('rows.' . $rowId . '.inbuve', $row['inbuve'] ?? '');
                                $currentEfektiv = old('rows.' . $rowId . '.efektiv', $row['efektiv'] ?? '');
                                $currentAtsavin = old('rows.' . $rowId . '.atsavin', $row['atsavin'] ?? '');
                                $currentPkBserija = old('rows.' . $rowId . '.pk_bserija', $row['pk_bserija'] ?? '');
                                $currentPiezimes = old('rows.' . $rowId . '.piezimes', $row['piezimes'] ?? '');
                            @endphp
                            <tr data-buve-row>
                                <td class="py-2 px-3 text-gray-700 font-medium" style="position: sticky; left: 0; z-index: 20; background-color: #ffffff;">
                                    {{ $rowId }}
                                    <input type="hidden" name="rows[{{ $rowId }}][id]" value="{{ $rowId }}" />
                                </td>
                                <td class="py-2 px-3 min-w-44">
                                    <input type="text" name="rows[{{ $rowId }}][pilnadrese]" value="{{ $currentPilnadrese }}"
                                        data-field="pilnadrese" data-original="{{ $row['pilnadrese'] ?? '' }}"
                                        class="buve-editable-input w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </td>
                                <td class="py-2 px-3 min-w-44">
                                    <input type="text" name="rows[{{ $rowId }}][adrese]" value="{{ $currentAdrese }}"
                                        data-field="adrese" data-original="{{ $row['adrese'] ?? '' }}"
                                        class="buve-editable-input w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </td>
                                <td class="py-2 px-3 min-w-24">
                                    <input type="text" name="rows[{{ $rowId }}][kadter]" value="{{ $currentKadter }}"
                                        data-field="kadter" data-original="{{ $row['kadter'] ?? '' }}"
                                        class="buve-editable-input w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </td>
                                <td class="py-2 px-3 min-w-24">
                                    <input type="text" name="rows[{{ $rowId }}][kadgrupa]" value="{{ $currentKadgrupa }}"
                                        data-field="kadgrupa" data-original="{{ $row['kadgrupa'] ?? '' }}"
                                        class="buve-editable-input w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </td>
                                <td class="py-2 px-3 min-w-24">
                                    <input type="text" name="rows[{{ $rowId }}][zemenr]" value="{{ $currentZemenr }}"
                                        data-field="zemenr" data-original="{{ $row['zemenr'] ?? '' }}"
                                        class="buve-editable-input w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </td>
                                <td class="py-2 px-3 min-w-24">
                                    <input type="text" name="rows[{{ $rowId }}][zdbuvenr]" value="{{ $currentZdbuvenr }}"
                                        data-field="zdbuvenr" data-original="{{ $row['zdbuvenr'] ?? '' }}"
                                        class="buve-editable-input w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </td>
                                <td class="py-2 px-3 min-w-44">
                                    <input type="text" name="rows[{{ $rowId }}][apzimkop]" value="{{ $currentApzimkop }}"
                                        data-field="apzimkop" data-original="{{ $row['apzimkop'] ?? '' }}"
                                        class="buve-editable-input w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </td>
                                <td class="py-2 px-3 min-w-24">
                                    <input type="text" name="rows[{{ $rowId }}][pk_buvegrp]" value="{{ $currentPkBuvegrp }}"
                                        data-field="pk_buvegrp" data-original="{{ $row['pk_buvegrp'] ?? '' }}"
                                        class="buve-editable-input w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </td>
                                <td class="py-2 px-3 min-w-24">
                                    <input type="text" name="rows[{{ $rowId }}][gads]" value="{{ $currentGads }}"
                                        data-field="gads" data-original="{{ $row['gads'] ?? '' }}"
                                        class="buve-editable-input w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </td>
                                <td class="py-2 px-3 min-w-24">
                                    <input type="text" name="rows[{{ $rowId }}][inbuve]" value="{{ $currentInbuve }}"
                                        data-field="inbuve" data-original="{{ $row['inbuve'] ?? '' }}"
                                        class="buve-editable-input w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </td>
                                <td class="py-2 px-3 min-w-24">
                                    <input type="text" name="rows[{{ $rowId }}][efektiv]" value="{{ $currentEfektiv }}"
                                        data-field="efektiv" data-original="{{ $row['efektiv'] ?? '' }}"
                                        class="buve-editable-input w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </td>
                                <td class="py-2 px-3 min-w-24">
                                    <input type="text" name="rows[{{ $rowId }}][atsavin]" value="{{ $currentAtsavin }}"
                                        data-field="atsavin" data-original="{{ $row['atsavin'] ?? '' }}"
                                        class="buve-editable-input w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </td>
                                <td class="py-2 px-3 min-w-24">
                                    <input type="text" name="rows[{{ $rowId }}][pk_bserija]" value="{{ $currentPkBserija }}"
                                        data-field="pk_bserija" data-original="{{ $row['pk_bserija'] ?? '' }}"
                                        class="buve-editable-input w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </td>
                                <td class="py-2 px-3 min-w-44">
                                    <input type="text" name="rows[{{ $rowId }}][piezimes]" value="{{ $currentPiezimes }}"
                                        data-field="piezimes" data-original="{{ $row['piezimes'] ?? '' }}"
                                        class="buve-editable-input w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-input-error :messages="$errors->get('rows')" class="mt-2" />

            <div class="flex items-center gap-3">
                <x-primary-button id="buve-update-submit">Update Modified Rows</x-primary-button>
                @if ($buveUpdateStatus)
                    <span class="text-sm text-gray-600">{{ $buveUpdateStatus }}</span>
                @endif
            </div>

            @if ($buveUpdateError)
                <div class="rounded-md bg-red-50 p-3 text-sm text-red-700">
                    {{ $buveUpdateError }}
                </div>
            @endif
        </form>
    @else
        <div class="text-sm text-gray-500">No Būve rows loaded yet.</div>
    @endif

    @if (!empty($buveUpdateResults))
        <div class="rounded-md border border-gray-200">
            <div class="px-4 py-3 border-b bg-gray-50">
                <h4 class="text-sm font-semibold text-gray-700">Bulk Update Results</h4>
            </div>
            <div class="overflow-auto" style="overflow-x: auto;">
                <table class="text-sm text-left w-full" style="min-width: max-content;">
                    <thead class="text-xs uppercase text-gray-500 border-b">
                        <tr>
                            <th class="py-2 px-3">ID</th>
                            <th class="py-2 px-3">Status</th>
                            <th class="py-2 px-3">HTTP</th>
                            <th class="py-2 px-3">Message</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($buveUpdateResults as $result)
                            <tr>
                                <td class="py-2 px-3">{{ $result['id'] ?? '' }}</td>
                                <td class="py-2 px-3">
                                    @if (!empty($result['success']))
                                        <span class="text-green-700">Success</span>
                                    @else
                                        <span class="text-red-700">Failed</span>
                                    @endif
                                </td>
                                <td class="py-2 px-3">{{ $result['status_code'] ?? '-' }}</td>
                                <td class="py-2 px-3">{{ $result['message'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

