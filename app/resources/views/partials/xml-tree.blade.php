@php
    $hasChildren = !empty($node['children']);
    $textValue = $node['text'] ?? '';
    $isRoot = $isRoot ?? false;
@endphp

<ul class="{{ $isRoot ? 'ml-0 pl-0' : 'ml-6 pl-4 border-l border-gray-200' }} space-y-1">
    <li class="py-1">
        <div class="flex items-start gap-2">
            <button type="button" class="tree-toggle text-xs text-gray-500 hover:text-gray-700" data-node-toggle
                data-node-path="{{ $node['path'] }}" {{ $hasChildren ? '' : 'disabled' }}>
                {{ $hasChildren ? '▾' : '•' }}
            </button>
            <div class="flex-1 min-w-0 flex items-start gap-3">
                <div class="flex flex-wrap items-center gap-2 min-w-0">
                    <span class="font-medium text-gray-700 node-label cursor-pointer"
                        data-node-path="{{ $node['path'] }}" data-node-value="{{ e($textValue) }}"
                        data-node-original="{{ e($textValue) }}">
                        {{ $node['name'] }}
                    </span>
                    @if (!empty($node['attributes']))
                        <span class="text-xs text-gray-500">
                            @foreach ($node['attributes'] as $attribute)
                                <span class="me-1">{{ $attribute['name'] }}=&quot;{{ $attribute['value'] }}&quot;</span>
                            @endforeach
                        </span>
                    @endif
                </div>
                @if (!$hasChildren)
                    <span
                        class="text-sm text-gray-800 break-all node-value cursor-pointer inline-flex min-w-[12rem] max-w-[60%] ms-auto px-2 py-1 border border-gray-300 rounded bg-white shadow-inner xml-node-value"
                        data-node-path="{{ $node['path'] }}" data-node-value="{{ e($textValue) }}"
                        data-node-original="{{ e($textValue) }}" contenteditable="true" spellcheck="false"
                        data-placeholder="(empty)">{{ $textValue }}</span>
                @endif
            </div>
        </div>

        @if ($hasChildren)
            <div class="tree-children" data-node-children="{{ $node['path'] }}">
                @foreach ($node['children'] as $child)
                    @include('partials.xml-tree', ['node' => $child, 'isRoot' => false])
                @endforeach
            </div>
        @endif
    </li>
</ul>