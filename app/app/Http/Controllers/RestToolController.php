<?php

namespace App\Http\Controllers;

use App\Models\RestActionLog;
use DOMDocument;
use DOMElement;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;

class RestToolController extends Controller
{
    public function index(Request $request)
    {
        $log = null;
        $xmlRaw = null;
        $xmlFormatted = null;
        $xmlTree = null;
        $xmlRows = [];

        $fetchStatus = session('rest_tool.fetch_status');
        $fetchError = session('rest_tool.fetch_error');
        $rememberedAuthUsername = session('rest_tool.auth_username');
        $rememberedAuthPassword = session('rest_tool.auth_password');
        $rememberedUrl = session('rest_tool.url');
        $rememberAuth = session('rest_tool.remember_auth', false);

        if ($request->filled('log')) {
            $log = RestActionLog::where('user_id', $request->user()->id)
                ->findOrFail($request->input('log'));

            if ($log->method === 'POST') {
                $xmlRaw = $log->base_request_xml ?: $log->request_xml ?: $log->response_xml;
            } else {
                $xmlRaw = $log->request_xml ?: $log->response_xml;
            }

            if ($log->method === 'GET') {
                $fetchStatus = $log->status_code ? 'GET ' . $log->status_code : 'GET failed';
                $fetchError = $log->error_message;
                $rememberedUrl = $log->url ?? $rememberedUrl;
                if ($log->auth_username) {
                    $rememberedAuthUsername = $log->auth_username;
                }
            }
        }

        if ($xmlRaw) {
            try {
                $parsed = $this->parseXml($xmlRaw);
                $xmlFormatted = $parsed['formatted'];
                $xmlTree = $parsed['tree'];
                $xmlRows = $parsed['rows'];
            } catch (Exception $exception) {
                $xmlFormatted = null;
                $xmlTree = null;
                $xmlRows = [];
            }
        }

        return view('rest-tool.index', [
            'xmlRaw' => $xmlRaw,
            'xmlFormatted' => $xmlFormatted,
            'xmlTree' => $xmlTree,
            'xmlRows' => $xmlRows,
            'fetchStatus' => $fetchStatus,
            'fetchError' => $fetchError,
            'postStatus' => session('rest_tool.post_status'),
            'postError' => session('rest_tool.post_error'),
            'postResponseXml' => session('rest_tool.post_response_xml'),
            'postResponseFormatted' => session('rest_tool.post_response_formatted'),
            'postResponseDisplay' => session('rest_tool.post_response_display'),
            'rememberedAuthUsername' => $rememberedAuthUsername,
            'rememberedAuthPassword' => $rememberedAuthPassword,
            'rememberedUrl' => $rememberedUrl,
            'rememberAuth' => $rememberAuth,
            'selectedLog' => $log,
            'selectedLogMethod' => $log?->method,
        ]);
    }

    public function fetch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'url' => ['required', 'url', 'max:2048'],
            'auth_username' => ['nullable', 'string', 'max:255'],
            'auth_password' => ['nullable', 'string', 'max:255'],
            'remember_auth' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('rest-tool.index')
                ->withErrors($validator)
                ->withInput();
        }

        $url = $validator->validated()['url'];
        if (!$this->isHttpUrl($url)) {
            return redirect()->route('rest-tool.index')
                ->with('rest_tool.fetch_error', 'Only http/https URLs are allowed.')
                ->withInput();
        }

        $username = $validator->validated()['auth_username'] ?? null;
        $password = $validator->validated()['auth_password'] ?? null;
        $rememberAuth = (bool) ($validator->validated()['remember_auth'] ?? false);

        if ($rememberAuth && $username) {
            session()->put('rest_tool.auth_username', $username);
            if ($password) {
                session()->put('rest_tool.auth_password', $password);
            }
            session()->put('rest_tool.remember_auth', true);
        } else {
            session()->forget('rest_tool.auth_username');
            session()->forget('rest_tool.auth_password');
            session()->put('rest_tool.remember_auth', false);
        }

        session()->put('rest_tool.url', $url);

        $xmlRaw = null;
        $xmlFormatted = null;
        $xmlTree = null;
        $xmlRows = [];
        $fetchError = null;
        $statusCode = null;
        $success = false;

        try {
            $client = Http::timeout(15)->accept('application/xml');
            if ($username || $password) {
                $client = $client->withBasicAuth($username ?? '', $password ?? '');
            }

            $response = $client->get($url);
            $statusCode = $response->status();
            $xmlRaw = $response->body();

            if (strlen($xmlRaw) > 2 * 1024 * 1024) {
                throw new Exception('XML response exceeds 2MB limit.');
            }

            if (!$response->successful()) {
                $fetchError = 'Request failed with status ' . $statusCode . '. Response: ' . $this->truncate($xmlRaw ?? '');
            }

            $parsed = $this->parseXml($xmlRaw);
            $xmlFormatted = $parsed['formatted'];
            $xmlTree = $parsed['tree'];
            $xmlRows = $parsed['rows'];
            $success = $response->successful();
        } catch (Exception $exception) {
            $fetchError = $exception->getMessage();
        }

        $log = RestActionLog::create([
            'user_id' => $request->user()->id,
            'method' => 'GET',
            'url' => $url,
            'status_code' => $statusCode,
            'success' => $success,
            'request_xml' => null,
            'response_xml' => $xmlRaw,
            'error_message' => $fetchError,
            'auth_username' => $username,
        ]);

        session()->flash('rest_tool.fetch_status', $statusCode ? 'GET ' . $statusCode : 'GET failed');
        session()->flash('rest_tool.fetch_error', $fetchError);

        if ($success) {
            return redirect()
                ->route('rest-tool.index', ['log' => $log->id]);
        }

        return redirect()
            ->route('rest-tool.logs.show', ['id' => $log->id]);
    }

    public function postXml(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'post_url' => ['required', 'url', 'max:2048'],
            'auth_username' => ['nullable', 'string', 'max:255'],
            'auth_password' => ['nullable', 'string', 'max:255'],
            'remember_auth' => ['nullable', 'boolean'],
            'generated_xml' => ['nullable', 'string'],
            'request_xml' => ['nullable', 'string'],
            'attachment_storage_id' => ['nullable', 'integer', 'min:1'],
            'attachment_author' => ['nullable', 'string', 'max:255'],
            'attachment_description' => ['nullable', 'string', 'max:255'],
            'attachment_comment' => ['nullable', 'string', 'max:255'],
            'attachments' => ['nullable', 'array', 'max:1'],
            'attachments.*' => ['nullable', 'file', 'mimetypes:application/pdf', 'max:10240'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('rest-tool.index')
                ->withErrors($validator)
                ->withInput();
        }

        $postUrl = $validator->validated()['post_url'];
        if (!$this->isHttpUrl($postUrl)) {
            return redirect()->route('rest-tool.index')
                ->with('rest_tool.post_error', 'Only http/https URLs are allowed.')
                ->withInput();
        }

        $username = $validator->validated()['auth_username'] ?? null;
        $password = $validator->validated()['auth_password'] ?? null;
        $rememberAuth = (bool) ($validator->validated()['remember_auth'] ?? false);

        if ($rememberAuth && $username) {
            session()->put('rest_tool.auth_username', $username);
            if ($password) {
                session()->put('rest_tool.auth_password', $password);
            }
            session()->put('rest_tool.remember_auth', true);
        } else {
            session()->forget('rest_tool.auth_username');
            session()->forget('rest_tool.auth_password');
            session()->put('rest_tool.remember_auth', false);
        }

        $validated = $validator->validated();
        $xmlPayload = $validated['generated_xml'] ?? $validated['request_xml'] ?? null;

        $statusCode = null;
        $responseXml = null;
        $postError = null;
        $success = false;

        try {
            $client = Http::timeout(15)->accept('application/xml');
            if ($username || $password) {
                $client = $client->withBasicAuth($username ?? '', $password ?? '');
            }

            $attachments = $request->file('attachments', []);
            $path = (string) (parse_url($postUrl, PHP_URL_PATH) ?? '');
            $isAttachmentsEndpoint = str_contains($path, '/attachments');

            if (!$isAttachmentsEndpoint && !$xmlPayload) {
                return redirect()->route('rest-tool.index')
                    ->with('rest_tool.post_error', 'No XML to send.')
                    ->withInput();
            }

            if ($xmlPayload && strlen($xmlPayload) > 2 * 1024 * 1024) {
                return redirect()->route('rest-tool.index')
                    ->with('rest_tool.post_error', 'XML payload exceeds 2MB limit.')
                    ->withInput();
            }

            if (!empty($attachments) && !$isAttachmentsEndpoint) {
                return redirect()->route('rest-tool.index')
                    ->with('rest_tool.post_error', 'Use an /attachments URL to upload PDF files.')
                    ->withInput();
            }

            if ($isAttachmentsEndpoint) {
                if (empty($attachments)) {
                    return redirect()->route('rest-tool.index')
                        ->with('rest_tool.post_error', 'Select a PDF file for attachments.')
                        ->withInput();
                }

                $storageId = $validated['attachment_storage_id'] ?? null;
                if (!$storageId) {
                    return redirect()->route('rest-tool.index')
                        ->with('rest_tool.post_error', 'Storage ID is required for attachments.')
                        ->withInput();
                }
                $author = $validated['attachment_author']
                    ?? $validated['auth_username']
                    ?? null;
                $description = $validated['attachment_description'] ?? null;
                $comment = $validated['attachment_comment'] ?? null;
                $xmlPayload = $this->buildAttachmentXml(
                    $attachments[0],
                    $storageId,
                    $author,
                    $description,
                    $comment
                );

                $metaResponse = $client
                    ->withBody($xmlPayload, 'application/xml')
                    ->post($postUrl);

                $statusCode = $metaResponse->status();
                $metaResponseXml = $metaResponse->body();
                $responseXml = $metaResponseXml;
                $success = $metaResponse->successful();
                if (!$success) {
                    $postError = 'Request failed with status ' . $statusCode . '. Response: ' . $this->truncate($responseXml ?? '');
                    throw new Exception($postError);
                }

                $responseXml = $metaResponseXml;
            } else {
                $response = $client->withBody($xmlPayload, 'application/xml')->post($postUrl);
                $statusCode = $response->status();
                $responseXml = $response->body();
                $success = $response->successful();
                if (!$success) {
                    $postError = 'Request failed with status ' . $statusCode . '. Response: ' . $this->truncate($responseXml ?? '');
                }
            }

            if ($responseXml && strlen($responseXml) > 2 * 1024 * 1024) {
                throw new Exception('Response XML exceeds 2MB limit.');
            }
        } catch (Exception $exception) {
            $postError = $exception->getMessage();
        }

        $postResponseFormatted = null;
        $postResponseDisplay = $this->decodeXmlForDisplay($responseXml);
        if ($responseXml) {
            try {
                $postResponseFormatted = $this->parseXml($postResponseDisplay ?? $responseXml)['formatted'];
            } catch (Exception $exception) {
                $postResponseFormatted = null;
            }
        }

        $logData = [
            'user_id' => $request->user()->id,
            'method' => 'POST',
            'url' => $postUrl,
            'status_code' => $statusCode,
            'success' => $success,
            'request_xml' => $xmlPayload,
            'response_xml' => $responseXml,
            'error_message' => $postError,
            'auth_username' => $username,
        ];

        if (Schema::hasColumn('rest_action_logs', 'base_request_xml')) {
            $logData['base_request_xml'] = $validator->validated()['request_xml'] ?? null;
        }

        $log = RestActionLog::create($logData);

        session()->flash('rest_tool.post_status', $statusCode ? 'POST ' . $statusCode : 'POST failed');
        session()->flash('rest_tool.post_error', $postError);
        session()->flash('rest_tool.post_response_xml', $responseXml);
        session()->flash('rest_tool.post_response_formatted', $postResponseFormatted);
        session()->flash('rest_tool.post_response_display', $postResponseDisplay);

        $xmlRaw = $validator->validated()['request_xml'] ?? null;
        $xmlFormatted = null;
        $xmlTree = null;
        $xmlRows = [];
        if ($xmlRaw) {
            try {
                $parsed = $this->parseXml($xmlRaw);
                $xmlFormatted = $parsed['formatted'];
                $xmlTree = $parsed['tree'];
                $xmlRows = $parsed['rows'];
            } catch (Exception $exception) {
                $xmlFormatted = null;
                $xmlTree = null;
                $xmlRows = [];
            }
        }

        return redirect()
            ->route('rest-tool.logs.show', ['id' => $log->id]);
    }

    public function logs(Request $request)
    {
        $logs = RestActionLog::with('user')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('rest-tool.logs.index', [
            'logs' => $logs,
        ]);
    }

    public function logShow(Request $request, $id)
    {
        $log = RestActionLog::with('user')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $requestXmlDisplay = $this->decodeXmlForDisplay($log->request_xml);
        $requestXmlFormatted = null;
        if ($log->request_xml) {
            try {
                $requestXmlFormatted = $this->parseXml($log->request_xml)['formatted'];
            } catch (Exception $exception) {
                $requestXmlFormatted = null;
            }
        }

        $responseXmlDisplay = $this->decodeXmlForDisplay($log->response_xml);
        $responseXmlFormatted = null;
        if ($log->response_xml) {
            try {
                $responseXmlFormatted = $this->parseXml($log->response_xml)['formatted'];
            } catch (Exception $exception) {
                $responseXmlFormatted = null;
            }
        }

        return view('rest-tool.logs.show', [
            'log' => $log,
            'requestXmlDisplay' => $requestXmlDisplay,
            'requestXmlFormatted' => $requestXmlFormatted,
            'responseXmlDisplay' => $responseXmlDisplay,
            'responseXmlFormatted' => $responseXmlFormatted,
        ]);
    }

    private function parseXml(string $xml): array
    {
        $xml = $this->normalizeXmlInput($xml);
        $skipDetails = strlen($xml) > 300000;
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        libxml_use_internal_errors(true);

        if (!$dom->loadXML($xml, LIBXML_NONET)) {
            $error = libxml_get_last_error();
            libxml_clear_errors();
            throw new Exception($error ? trim($error->message) : 'Invalid XML');
        }

        $formatted = $this->normalizeXmlOutput($dom->saveXML());
        if (str_contains($formatted, '&lt;') && str_contains($formatted, '&gt;')) {
            $decoded = html_entity_decode($formatted, ENT_QUOTES | ENT_XML1, 'UTF-8');
            if (str_contains($decoded, '<')) {
                $formatted = $decoded;
            }
        }
        $tree = $skipDetails ? null : $this->buildTree($dom->documentElement);
        $rows = $skipDetails ? [] : $this->extractRepeatingRows($dom->documentElement);

        return [
            'formatted' => $formatted,
            'tree' => $tree,
            'rows' => $rows,
        ];
    }

    private function buildTree(DOMElement $element): array
    {
        $children = [];
        $hasElementChildren = false;
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $hasElementChildren = true;
                $children[] = $this->buildTree($child);
            }
        }

        $attributes = [];
        if ($element->hasAttributes()) {
            foreach ($element->attributes as $attribute) {
                $attributes[] = [
                    'name' => $attribute->nodeName,
                    'value' => $attribute->nodeValue,
                ];
            }
        }

        $textValue = null;
        if (!$hasElementChildren) {
            $textValue = trim($element->textContent ?? '');
        }

        return [
            'name' => $element->nodeName,
            'path' => $this->getElementPath($element),
            'attributes' => $attributes,
            'text' => $textValue,
            'children' => $children,
        ];
    }

    private function extractRepeatingRows(DOMElement $root): array
    {
        $groups = [];
        $queue = [$root];

        while (!empty($queue)) {
            /** @var DOMElement $current */
            $current = array_shift($queue);
            $nameCounts = [];
            foreach ($current->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    $nameCounts[$child->nodeName] = ($nameCounts[$child->nodeName] ?? 0) + 1;
                }
            }

            foreach ($nameCounts as $name => $count) {
                if ($count > 1) {
                    $rows = [];
                    $columnStats = [];
                    foreach ($current->childNodes as $child) {
                        if (!$child instanceof DOMElement || $child->nodeName !== $name) {
                            continue;
                        }
                        $fields = [];
                        $fieldMap = [];
                        foreach ($child->childNodes as $fieldNode) {
                            if ($fieldNode instanceof DOMElement) {
                                $hasElementChildren = false;
                                foreach ($fieldNode->childNodes as $fieldChild) {
                                    if ($fieldChild instanceof DOMElement) {
                                        $hasElementChildren = true;
                                        break;
                                    }
                                }
                                if (!$hasElementChildren) {
                                    $fieldValue = trim($fieldNode->textContent ?? '');
                                    $fields[] = [
                                        'name' => $fieldNode->nodeName,
                                        'value' => $fieldValue,
                                        'path' => $this->getElementPath($fieldNode),
                                    ];
                                    $fieldMap[$fieldNode->nodeName] = [
                                        'name' => $fieldNode->nodeName,
                                        'value' => $fieldValue,
                                        'path' => $this->getElementPath($fieldNode),
                                    ];
                                    $columnStats[$fieldNode->nodeName] = [
                                        'name' => $fieldNode->nodeName,
                                        'nonEmpty' => ($columnStats[$fieldNode->nodeName]['nonEmpty'] ?? 0) + ($fieldValue !== '' ? 1 : 0),
                                    ];
                                }
                            }
                        }
                        $summary = $this->pickRowSummary($fields);
                        $rows[] = [
                            'path' => $this->getElementPath($child),
                            'fields' => $fields,
                            'summary' => $summary,
                            'fieldMap' => $fieldMap,
                        ];
                    }

                    if (!empty($rows)) {
                        $columns = $this->orderColumns($columnStats);
                        $groups[] = [
                            'label' => $name,
                            'rows' => $rows,
                            'columns' => $columns,
                        ];
                    }
                }
            }

            foreach ($current->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    $queue[] = $child;
                }
            }
        }

        return $groups;
    }

    private function pickRowSummary(array $fields): ?string
    {
        if (empty($fields)) {
            return null;
        }

        $priority = ['NOSAUK', 'NAME', 'TITLE', 'DESCRIPTION'];
        foreach ($priority as $needle) {
            foreach ($fields as $field) {
                if (strcasecmp($field['name'] ?? '', $needle) === 0) {
                    $value = trim($field['value'] ?? '');
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        foreach ($fields as $field) {
            $value = trim($field['value'] ?? '');
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function orderColumns(array $columnStats): array
    {
        $columns = array_values($columnStats);
        $priority = [
            'NOSAUK',
            'NAME',
            'TITLE',
            'DESCRIPTION',
            'KODS',
            'CODE',
            'DAUDZ',
            'ID',
            'NUMURS',
            'NR',
            'PK_NOM',
            'PK_DOK',
            'PK_ORDER',
        ];

        usort($columns, function ($a, $b) use ($priority) {
            $aName = strtoupper($a['name'] ?? '');
            $bName = strtoupper($b['name'] ?? '');
            $aPriority = array_search($aName, $priority, true);
            $bPriority = array_search($bName, $priority, true);
            $aPriority = $aPriority === false ? PHP_INT_MAX : $aPriority;
            $bPriority = $bPriority === false ? PHP_INT_MAX : $bPriority;

            if ($aPriority !== $bPriority) {
                return $aPriority <=> $bPriority;
            }

            $aNonEmpty = (int) ($a['nonEmpty'] ?? 0);
            $bNonEmpty = (int) ($b['nonEmpty'] ?? 0);
            if ($aNonEmpty !== $bNonEmpty) {
                return $bNonEmpty <=> $aNonEmpty;
            }

            return $aName <=> $bName;
        });

        return array_map(fn($column) => $column['name'], $columns);
    }

    private function getElementPath(DOMElement $element): string
    {
        $segments = [];
        $current = $element;
        while ($current instanceof DOMElement) {
            $index = 1;
            $sibling = $current->previousSibling;
            while ($sibling) {
                if ($sibling instanceof DOMElement && $sibling->nodeName === $current->nodeName) {
                    $index++;
                }
                $sibling = $sibling->previousSibling;
            }
            $segments[] = $current->nodeName . '[' . $index . ']';
            $current = $current->parentNode instanceof DOMElement ? $current->parentNode : null;
        }

        return '/' . implode('/', array_reverse($segments));
    }

    private function isHttpUrl(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        return in_array($scheme, ['http', 'https'], true);
    }

    private function truncate(string $value, int $limit = 2000): string
    {
        $value = trim($value);
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit) . '…';
    }

        private function buildAttachmentXml($file, int $storageId, ?string $author, ?string $description, ?string $comment): string
        {
                $filename = method_exists($file, 'getClientOriginalName')
                        ? $file->getClientOriginalName()
                        : (string) ($file['name'] ?? 'attachment.pdf');

            $fileContents = method_exists($file, 'getRealPath')
                ? file_get_contents($file->getRealPath())
                : null;
            $fileContents = $fileContents === false ? null : $fileContents;
            $fileSizeBytes = $fileContents !== null ? strlen($fileContents) : 0;
            $fileSizeKb = $fileSizeBytes > 0 ? (int) ceil($fileSizeBytes / 1024) : 0;
            $fileData = $fileContents !== null ? base64_encode($fileContents) : '';

                $now = Carbon::now();
                $created = $now->format('Y-m-d\TH:i:s.vP');
                $fileAddTime = $now->format('Y-m-d');

                $author = $author ?: '';
                $description = $description ?: '';
                $comment = $comment ?: '';

                $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<resource>
    <description>Pievienotais fails</description>
    <entity xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
        <PK_STORAGE>
            <href>/rest/TdmStorageBL/{$storageId}</href>
        </PK_STORAGE>
        <FILENAME>{$this->escapeXml($filename)}</FILENAME>
        <AUTHOR>{$this->escapeXml($author)}</AUTHOR>
        <CREATED>{$created}</CREATED>
        <KOMENTARS>{$this->escapeXml($comment)}</KOMENTARS>
        <FILSIZE>
            <size>{$fileSizeKb}</size>
            <data>{$fileData}</data>
        </FILSIZE>
        <PK_REPNOM/>
        <PK_RPDGRP/>
        <APRAKSTS>{$this->escapeXml($description)}</APRAKSTS>
        <FILE_ADD_TIME>{$fileAddTime}</FILE_ADD_TIME>
        <CSADCREATED/>
        <DIGITSIGN/>
        <SKSTATUSS/>
        <IS_SASK/>
        <IS_CURRENT/>
    </entity>
</resource>
XML;

                return $xml;
        }

        private function escapeXml(string $value): string
        {
                return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        private function extractAttachmentUrl($response, ?string $responseXml, string $baseUrl): ?string
        {
            $location = null;
            try {
                $location = $response?->header('Location');
            } catch (Exception $exception) {
                $location = null;
            }
            if ($location) {
                return $this->buildAbsoluteUrl($baseUrl, $location);
            }

            if (!$responseXml) {
                return null;
            }

            $decoded = $this->decodeXmlForDisplay($responseXml) ?? $responseXml;
            $path = (string) (parse_url($baseUrl, PHP_URL_PATH) ?? '');
            $basePath = rtrim($path, '/');

            if (preg_match('~<href>\s*([^<]+/attachments/\d+)\s*</href>~', $decoded, $matches)) {
                return $this->buildAbsoluteUrl($baseUrl, $matches[1]);
            }

            if (preg_match('~/attachments/([0-9]+)~', $decoded, $matches)) {
                return $this->buildAbsoluteUrl($baseUrl, $matches[0]);
            }

            if (preg_match('~/TdmAttachmentBL/([0-9]+)~', $decoded, $matches)) {
                return $this->buildAbsoluteUrl($baseUrl, $basePath . '/' . $matches[1]);
            }

            if (preg_match('~/TdmPvzAttachmentBL/([0-9]+)~', $decoded, $matches)) {
                return $this->buildAbsoluteUrl($baseUrl, $basePath . '/' . $matches[1]);
            }

            return null;
        }

        private function buildAbsoluteUrl(string $baseUrl, string $path): string
        {
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }
            $parts = parse_url($baseUrl);
            $scheme = $parts['scheme'] ?? 'https';
            $host = $parts['host'] ?? '';
            $port = isset($parts['port']) ? ':' . $parts['port'] : '';
            $path = '/' . ltrim($path, '/');

            $basePath = $parts['path'] ?? '';
            if ($basePath && str_starts_with($path, '/rest/')) {
                $prefixPos = strpos($basePath, '/rest/');
                if ($prefixPos !== false) {
                    $prefix = substr($basePath, 0, $prefixPos);
                    if ($prefix !== '') {
                        $path = rtrim($prefix, '/') . $path;
                    }
                }
            }

            return $scheme . '://' . $host . $port . $path;
        }

        private function normalizeXmlOutput(string $xml): string
    {
        if (!str_contains($xml, '<') && str_contains($xml, '&lt;')) {
            return html_entity_decode($xml, ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        return $xml;
    }

    private function normalizeXmlInput(string $xml): string
    {
        $trimmed = trim($xml);
        for ($i = 0; $i < 2; $i += 1) {
            if (!str_contains($trimmed, '<') && str_contains($trimmed, '&lt;')) {
                $trimmed = html_entity_decode($trimmed, ENT_QUOTES | ENT_XML1, 'UTF-8');
            } else {
                break;
            }
        }

        return $trimmed === '' ? $xml : $trimmed;
    }

    private function decodeXmlForDisplay(?string $xml): ?string
    {
        if (!$xml) {
            return $xml;
        }

        $decoded = $xml;
        for ($i = 0; $i < 2; $i += 1) {
            if (str_contains($decoded, '&lt;') || str_contains($decoded, '&gt;') || str_contains($decoded, '&amp;lt;') || str_contains($decoded, '&amp;gt;')) {
                $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_XML1, 'UTF-8');
            } else {
                break;
            }
        }

        return $decoded;
    }
}
