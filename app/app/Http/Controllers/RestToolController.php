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
        $keyFields = [];

        $fetchStatus = session('rest_tool.fetch_status');
        $fetchError = session('rest_tool.fetch_error');
        $postStatus = session('rest_tool.post_status');
        $postError = session('rest_tool.post_error');
        $postResponseXml = session('rest_tool.post_response_xml');
        $postResponseFormatted = session('rest_tool.post_response_formatted');
        $postResponseDisplay = session('rest_tool.post_response_display');
        $rememberedAuthUsername = session('rest_tool.auth_username');
        $rememberedAuthPassword = session('rest_tool.auth_password');
        $rememberedUrl = session('rest_tool.url');
        $rememberAuth = session('rest_tool.remember_auth', false);
        $buveRows = session('rest_tool.buve_rows', []);
        $buveLoadStatus = session('rest_tool.buve_load_status');
        $buveLoadError = session('rest_tool.buve_load_error');
        $buveUpdateStatus = session('rest_tool.buve_update_status');
        $buveUpdateError = session('rest_tool.buve_update_error');
        $buveUpdateResults = session('rest_tool.buve_update_results', []);
        $buveRememberedBaseUrl = session('rest_tool.buve_base_url', $rememberedUrl);
        $buveRememberedAuthUsername = session('rest_tool.buve_active_auth_username')
            ?? session('rest_tool.buve_auth_username', $rememberedAuthUsername);
        $buveRememberedAuthPassword = session('rest_tool.buve_active_auth_password')
            ?? session('rest_tool.buve_auth_password', $rememberedAuthPassword);
        $buveRememberAuth = session('rest_tool.buve_remember_auth', $rememberAuth);
        $buveRememberedMode = session('rest_tool.buve_mode', 'predefined');
        $buveRememberedAddressFilter = session('rest_tool.buve_filter_pilnadrese');
        $buveRememberedDynamicQuery = session('rest_tool.buve_dynamic_query');

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
            } elseif ($log->method === 'POST') {
                $postStatus = $log->status_code ? 'POST ' . $log->status_code : 'POST failed';
                $postError = $log->error_message;
                $postResponseXml = $log->response_xml;
                $postResponseDisplay = $this->decodeXmlForDisplay($log->response_xml);
                $postResponseFormatted = null;
                if ($log->response_xml) {
                    try {
                        $postResponseFormatted = $this->parseXml($postResponseDisplay ?? $log->response_xml)['formatted'];
                    } catch (Exception $exception) {
                        $postResponseFormatted = null;
                    }
                }
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
                $keyFields = $parsed['keyFields'] ?? [];
            } catch (Exception $exception) {
                $xmlFormatted = null;
                $xmlTree = null;
                $xmlRows = [];
                $keyFields = [];
            }
        }

        return view('rest-tool.index', [
            'xmlRaw' => $xmlRaw,
            'xmlFormatted' => $xmlFormatted,
            'xmlTree' => $xmlTree,
            'xmlRows' => $xmlRows,
            'keyFields' => $keyFields,
            'fetchStatus' => $fetchStatus,
            'fetchError' => $fetchError,
            'postStatus' => $postStatus,
            'postError' => $postError,
            'postResponseXml' => $postResponseXml,
            'postResponseFormatted' => $postResponseFormatted,
            'postResponseDisplay' => $postResponseDisplay,
            'rememberedAuthUsername' => $rememberedAuthUsername,
            'rememberedAuthPassword' => $rememberedAuthPassword,
            'rememberedUrl' => $rememberedUrl,
            'rememberAuth' => $rememberAuth,
            'buveRows' => $buveRows,
            'buveLoadStatus' => $buveLoadStatus,
            'buveLoadError' => $buveLoadError,
            'buveUpdateStatus' => $buveUpdateStatus,
            'buveUpdateError' => $buveUpdateError,
            'buveUpdateResults' => $buveUpdateResults,
            'buveRememberedBaseUrl' => $buveRememberedBaseUrl,
            'buveRememberedAuthUsername' => $buveRememberedAuthUsername,
            'buveRememberedAuthPassword' => $buveRememberedAuthPassword,
            'buveRememberAuth' => $buveRememberAuth,
            'buveRememberedMode' => $buveRememberedMode,
            'buveRememberedAddressFilter' => $buveRememberedAddressFilter,
            'buveRememberedDynamicQuery' => $buveRememberedDynamicQuery,
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

        $this->persistGlobalAuthInSession($rememberAuth, $username, $password);

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

        $this->persistGlobalAuthInSession($rememberAuth, $username, $password);

        $validated = $validator->validated();
        $generatedXmlPayload = $validated['generated_xml'] ?? null;
        $baseRequestXml = $validated['request_xml'] ?? null;
        $isGeneratedXmlPayload = trim((string) $generatedXmlPayload) !== '';
        $xmlPayload = $isGeneratedXmlPayload
            ? $generatedXmlPayload
            : $baseRequestXml;

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
                $errorMessage = 'No XML to send.';
                $log = $this->createFailedPostLog(
                    $request,
                    $postUrl,
                    $username,
                    $xmlPayload,
                    $baseRequestXml,
                    $errorMessage
                );

                return redirect()->route('rest-tool.logs.show', ['id' => $log->id]);
            }

            if ($xmlPayload && strlen($xmlPayload) > 2 * 1024 * 1024) {
                $errorMessage = 'XML payload exceeds 2MB limit.';
                $log = $this->createFailedPostLog(
                    $request,
                    $postUrl,
                    $username,
                    $xmlPayload,
                    $baseRequestXml,
                    $errorMessage
                );

                return redirect()->route('rest-tool.logs.show', ['id' => $log->id]);
            }

            if (!empty($attachments) && !$isAttachmentsEndpoint) {
                $errorMessage = 'Use an /attachments URL to upload PDF files.';
                $log = $this->createFailedPostLog(
                    $request,
                    $postUrl,
                    $username,
                    $xmlPayload,
                    $baseRequestXml,
                    $errorMessage
                );

                return redirect()->route('rest-tool.logs.show', ['id' => $log->id]);
            }

            if ($isAttachmentsEndpoint) {
                if (empty($attachments)) {
                    $errorMessage = 'Select a PDF file for attachments.';
                    $log = $this->createFailedPostLog(
                        $request,
                        $postUrl,
                        $username,
                        $xmlPayload,
                        $baseRequestXml,
                        $errorMessage
                    );

                    return redirect()->route('rest-tool.logs.show', ['id' => $log->id]);
                }

                $storageId = $validated['attachment_storage_id'] ?? null;
                if (!$storageId) {
                    $errorMessage = 'Storage ID is required for attachments.';
                    $log = $this->createFailedPostLog(
                        $request,
                        $postUrl,
                        $username,
                        $xmlPayload,
                        $baseRequestXml,
                        $errorMessage
                    );

                    return redirect()->route('rest-tool.logs.show', ['id' => $log->id]);
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
                if ($isGeneratedXmlPayload && $generatedXmlPayload) {
                    $preparedPayload = $this->prepareLatestEntityPostPayload($client, $postUrl, $generatedXmlPayload);
                    $xmlPayload = $preparedPayload['payload'];
                }

                $response = $client->withBody($xmlPayload, 'application/xml')->post($postUrl);
                $statusCode = $response->status();
                $responseXml = $response->body();
                $success = $response->successful();

                if (!$success && $isGeneratedXmlPayload && $generatedXmlPayload && $this->isStaleRecordError($responseXml)) {
                    $retryPreparedPayload = $this->prepareLatestEntityPostPayload($client, $postUrl, $generatedXmlPayload);
                    $xmlPayload = $retryPreparedPayload['payload'];
                    $retryResponse = $client->withBody($xmlPayload, 'application/xml')->post($postUrl);
                    $statusCode = $retryResponse->status();
                    $responseXml = $retryResponse->body();
                    $success = $retryResponse->successful();
                }

                if (!$success) {
                    $postError = 'Request failed with status ' . $statusCode . '. ' . $this->extractApiErrorMessage($responseXml);
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
            $logData['base_request_xml'] = $baseRequestXml;
        }

        $log = RestActionLog::create($logData);

        session()->flash('rest_tool.post_status', $statusCode ? 'POST ' . $statusCode : 'POST failed');
        session()->flash('rest_tool.post_error', $postError);

        $xmlRaw = $baseRequestXml;
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

    public function loadBuves(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'buve_base_url' => ['required', 'url', 'max:2048'],
            'buve_auth_username' => ['nullable', 'string', 'max:255'],
            'buve_auth_password' => ['nullable', 'string', 'max:255'],
            'buve_remember_auth' => ['nullable', 'boolean'],
            'buve_mode' => ['required'],
            'buve_filter_pilnadrese' => ['nullable', 'string', 'max:255'],
            'buve_dynamic_query' => ['nullable', 'string', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('rest-tool.index')
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();
        $baseUrl = trim((string) ($validated['buve_base_url'] ?? session('rest_tool.buve_base_url', session('rest_tool.url', ''))));
        if (!$this->isHttpUrl($baseUrl)) {
            return redirect()->route('rest-tool.index')
                ->with('rest_tool.buve_load_error', 'A valid Būve URL is required.')
                ->withInput();
        }

        $modeInput = $request->input('buve_mode');
        $modes = [];
        if (is_array($modeInput)) {
            foreach ($modeInput as $modeValue) {
                $modeValue = trim((string) $modeValue);
                if ($modeValue !== '') {
                    $modes[] = $modeValue;
                }
            }
        } else {
            $modeValue = trim((string) $modeInput);
            if ($modeValue !== '') {
                $modes[] = $modeValue;
            }
        }

        $modes = array_values(array_unique($modes));
        if (count($modes) > 1) {
            return redirect()->route('rest-tool.index')
                ->with('rest_tool.buve_load_error', 'Only one data retrieval mode can be used at a time.')
                ->withInput();
        }

        if (count($modes) !== 1 || !in_array($modes[0], ['all', 'dynamic', 'predefined'], true)) {
            return redirect()->route('rest-tool.index')
                ->with('rest_tool.buve_load_error', 'Select one data retrieval mode.')
                ->withInput();
        }

        $mode = $modes[0];
        $dynamicQuery = trim((string) ($validated['buve_dynamic_query'] ?? ''));
        $pilnadreseFilter = trim((string) ($validated['buve_filter_pilnadrese'] ?? ''));

        if ($mode === 'dynamic' && $dynamicQuery === '') {
            return redirect()->route('rest-tool.index')
                ->with('rest_tool.buve_load_error', 'Dynamic query path is required for this mode.')
                ->withInput();
        }

        if ($mode === 'predefined' && $pilnadreseFilter === '') {
            return redirect()->route('rest-tool.index')
                ->with('rest_tool.buve_load_error', 'PILNADRESE contains filter is required for predefined mode.')
                ->withInput();
        }

        $username = $validated['buve_auth_username']
            ?? session('rest_tool.buve_active_auth_username')
            ?? session('rest_tool.buve_auth_username')
            ?? session('rest_tool.auth_username');
        $password = $validated['buve_auth_password']
            ?? session('rest_tool.buve_active_auth_password')
            ?? session('rest_tool.buve_auth_password')
            ?? session('rest_tool.auth_password');
        $rememberAuth = (bool) ($validated['buve_remember_auth'] ?? session('rest_tool.buve_remember_auth', session('rest_tool.remember_auth', false)));

        session()->put('rest_tool.buve_active_auth_username', $username);
        session()->put('rest_tool.buve_active_auth_password', $password);
        $this->persistBuveAuthInSession($rememberAuth, $username, $password);
        $this->persistGlobalAuthInSession($rememberAuth, $username, $password);
        session()->put('rest_tool.url', $baseUrl);
        session()->put('rest_tool.buve_base_url', $baseUrl);
        session()->put('rest_tool.buve_mode', $mode);
        session()->put('rest_tool.buve_filter_pilnadrese', $pilnadreseFilter);
        session()->put('rest_tool.buve_dynamic_query', $dynamicQuery);

        try {
            $loadUrl = $this->buildBuveLoadUrl($baseUrl, $mode, $dynamicQuery);
            $client = Http::timeout(20)->accept('application/xml');
            if ($username || $password) {
                $client = $client->withBasicAuth($username ?? '', $password ?? '');
            }

            $response = $client->get($loadUrl);
            $statusCode = $response->status();
            $responseXml = $response->body();

            if (strlen($responseXml) > 8 * 1024 * 1024) {
                throw new Exception('XML response exceeds 8MB limit.');
            }

            if (!$response->successful()) {
                throw new Exception('Request failed with status ' . $statusCode . '. ' . $this->extractApiErrorMessage($responseXml));
            }

            $rows = $this->extractBuveRows($responseXml, $baseUrl);
            if ($mode === 'predefined') {
                $rows = array_values(array_filter($rows, function (array $row) use ($pilnadreseFilter) {
                    return mb_stripos((string) ($row['pilnadrese'] ?? ''), $pilnadreseFilter) !== false;
                }));
            }

            usort($rows, function (array $a, array $b) {
                return strcmp((string) ($a['pilnadrese'] ?? ''), (string) ($b['pilnadrese'] ?? ''));
            });

            $snapshotEntities = [];
            $tableRows = [];
            foreach ($rows as $row) {
                $id = (string) ($row['id'] ?? '');
                $payloadXml = $row['payload_xml'] ?? null;
                $updateUrl = $row['update_url'] ?? null;
                if ($id === '' || !$payloadXml || !$updateUrl) {
                    continue;
                }

                $snapshotEntities[$id] = [
                    'id' => $id,
                    'update_url' => $updateUrl,
                    'payload_xml' => $payloadXml,
                    'original_fields' => [
                        'pilnadrese' => (string) ($row['pilnadrese'] ?? ''),
                        'adrese' => (string) ($row['adrese'] ?? ''),
                        'kadter' => (string) ($row['kadter'] ?? ''),
                        'kadgrupa' => (string) ($row['kadgrupa'] ?? ''),
                        'zemenr' => (string) ($row['zemenr'] ?? ''),
                        'zdbuvenr' => (string) ($row['zdbuvenr'] ?? ''),
                        'apzimkop' => (string) ($row['apzimkop'] ?? ''),
                        'pk_buvegrp' => (string) ($row['pk_buvegrp'] ?? ''),
                        'pk_bserija' => (string) ($row['pk_bserija'] ?? ''),
                        'gads' => (string) ($row['gads'] ?? ''),
                        'inbuve' => (string) ($row['inbuve'] ?? ''),
                        'efektiv' => (string) ($row['efektiv'] ?? ''),
                        'atsavin' => (string) ($row['atsavin'] ?? ''),
                        'piezimes' => (string) ($row['piezimes'] ?? ''),
                    ],
                    'original_piezimes' => (string) ($row['piezimes'] ?? ''),
                ];

                unset($row['payload_xml']);
                $tableRows[] = $row;
            }

            session()->put('rest_tool.buve_snapshot', [
                'base_url' => $baseUrl,
                'loaded_url' => $loadUrl,
                'mode' => $mode,
                'entities' => $snapshotEntities,
            ]);
            session()->put('rest_tool.buve_rows', $tableRows);
            session()->forget('rest_tool.buve_update_status');
            session()->forget('rest_tool.buve_update_error');
            session()->forget('rest_tool.buve_update_results');

            return redirect()->route('rest-tool.index')
                ->with('rest_tool.buve_load_status', 'Loaded ' . count($tableRows) . ' Būve record(s).');
        } catch (Exception $exception) {
            return redirect()->route('rest-tool.index')
                ->withInput()
                ->with('rest_tool.buve_load_error', $exception->getMessage());
        }
    }

    public function updateBuves(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'buve_base_url' => ['nullable', 'url', 'max:2048'],
            'buve_auth_username' => ['nullable', 'string', 'max:255'],
            'buve_auth_password' => ['nullable', 'string', 'max:255'],
            'buve_remember_auth' => ['nullable', 'boolean'],
            'rows' => ['required', 'array'],
            'rows.*.id' => ['required', 'string', 'max:64'],
            'rows.*.pilnadrese' => ['nullable', 'string', 'max:255'],
            'rows.*.adrese' => ['nullable', 'string', 'max:255'],
            'rows.*.kadter' => ['nullable', 'string', 'max:64'],
            'rows.*.kadgrupa' => ['nullable', 'string', 'max:64'],
            'rows.*.zemenr' => ['nullable', 'string', 'max:64'],
            'rows.*.zdbuvenr' => ['nullable', 'string', 'max:64'],
            'rows.*.apzimkop' => ['nullable', 'string', 'max:64'],
            'rows.*.pk_buvegrp' => ['nullable', 'string', 'max:64'],
            'rows.*.pk_bserija' => ['nullable', 'string', 'max:64'],
            'rows.*.gads' => ['nullable', 'string', 'max:32'],
            'rows.*.inbuve' => ['nullable', 'string', 'max:64'],
            'rows.*.efektiv' => ['nullable', 'string', 'max:64'],
            'rows.*.atsavin' => ['nullable', 'string', 'max:64'],
            'rows.*.piezimes' => ['nullable', 'string', 'max:4000'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('rest-tool.index')
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();
        $snapshot = session('rest_tool.buve_snapshot');
        if (!is_array($snapshot) || empty($snapshot['entities']) || !is_array($snapshot['entities'])) {
            return redirect()->route('rest-tool.index')
                ->with('rest_tool.buve_update_error', 'Load Būve records before updating.');
        }

        $baseUrl = trim((string) ($validated['buve_base_url'] ?? ($snapshot['base_url'] ?? session('rest_tool.buve_base_url', session('rest_tool.url', '')))));
        if (!$this->isHttpUrl($baseUrl)) {
            return redirect()->route('rest-tool.index')
                ->with('rest_tool.buve_update_error', 'A valid base URL is required to update records.')
                ->withInput();
        }

        $username = $validated['buve_auth_username']
            ?? session('rest_tool.buve_active_auth_username')
            ?? session('rest_tool.buve_auth_username')
            ?? session('rest_tool.auth_username');
        $password = $validated['buve_auth_password']
            ?? session('rest_tool.buve_active_auth_password')
            ?? session('rest_tool.buve_auth_password')
            ?? session('rest_tool.auth_password');
        $rememberAuth = (bool) ($validated['buve_remember_auth'] ?? session('rest_tool.buve_remember_auth', session('rest_tool.remember_auth', false)));
        session()->put('rest_tool.buve_active_auth_username', $username);
        session()->put('rest_tool.buve_active_auth_password', $password);
        $this->persistBuveAuthInSession($rememberAuth, $username, $password);
        $this->persistGlobalAuthInSession($rememberAuth, $username, $password);
        session()->put('rest_tool.url', $baseUrl);
        session()->put('rest_tool.buve_base_url', $baseUrl);

        $entities = $snapshot['entities'];
        $submittedRows = $validated['rows'] ?? [];
        $editableFields = [
            'pilnadrese',
            'adrese',
            'kadter',
            'kadgrupa',
            'zemenr',
            'zdbuvenr',
            'apzimkop',
            'pk_buvegrp',
            'pk_bserija',
            'gads',
            'inbuve',
            'efektiv',
            'atsavin',
            'piezimes',
        ];
        $modifiedRows = [];

        foreach ($submittedRows as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '' || !isset($entities[$id])) {
                continue;
            }

            $originalFields = $entities[$id]['original_fields'] ?? [
                'pilnadrese' => (string) ($row['pilnadrese'] ?? ''),
                'adrese' => (string) ($row['adrese'] ?? ''),
                'kadter' => (string) ($row['kadter'] ?? ''),
                'kadgrupa' => (string) ($row['kadgrupa'] ?? ''),
                'zemenr' => (string) ($row['zemenr'] ?? ''),
                'zdbuvenr' => (string) ($row['zdbuvenr'] ?? ''),
                'apzimkop' => (string) ($row['apzimkop'] ?? ''),
                'pk_buvegrp' => (string) ($row['pk_buvegrp'] ?? ''),
                'pk_bserija' => (string) ($row['pk_bserija'] ?? ''),
                'gads' => (string) ($row['gads'] ?? ''),
                'inbuve' => (string) ($row['inbuve'] ?? ''),
                'efektiv' => (string) ($row['efektiv'] ?? ''),
                'atsavin' => (string) ($row['atsavin'] ?? ''),
                'piezimes' => (string) ($entities[$id]['original_piezimes'] ?? ''),
            ];

            $changes = [];
            foreach ($editableFields as $fieldName) {
                $newValue = (string) ($row[$fieldName] ?? '');
                $originalValue = (string) ($originalFields[$fieldName] ?? '');
                if ($newValue !== $originalValue) {
                    $changes[$fieldName] = $newValue;
                }
            }

            if (
                isset($changes['kadter']) ||
                isset($changes['kadgrupa']) ||
                isset($changes['zemenr']) ||
                isset($changes['zdbuvenr'])
            ) {
                $changes['apzimkop'] =
                    (string) ($changes['kadter'] ?? $originalFields['kadter'] ?? '') .
                    (string) ($changes['kadgrupa'] ?? $originalFields['kadgrupa'] ?? '') .
                    (string) ($changes['zemenr'] ?? $originalFields['zemenr'] ?? '') .
                    (string) ($changes['zdbuvenr'] ?? $originalFields['zdbuvenr'] ?? '');
            }

            if (empty($changes)) {
                continue;
            }

            $modifiedRows[$id] = [
                'id' => $id,
                'changes' => $changes,
            ];
        }

        if (empty($modifiedRows)) {
            return redirect()->route('rest-tool.index')
                ->with('rest_tool.buve_update_status', 'No modified rows to update.');
        }

        $client = Http::timeout(20)->accept('application/xml');
        if ($username || $password) {
            $client = $client->withBasicAuth($username ?? '', $password ?? '');
        }

        $results = [];
        $updatedValues = [];
        $successCount = 0;
        $failedCount = 0;

        foreach ($modifiedRows as $row) {
            $id = $row['id'];
            $updateUrl = $entities[$id]['update_url'] ?? null;
            if (!$updateUrl || !$this->isHttpUrl($updateUrl)) {
                $failedCount++;
                $results[] = [
                    'id' => $id,
                    'success' => false,
                    'status_code' => null,
                    'message' => 'Missing update URL for this row.',
                ];
                continue;
            }

            $payloadXml = null;
            $responseBody = null;
            $statusCode = null;
            $baseEntityXml = null;

            try {
                $entityResponse = $client->get($updateUrl);
                $entityStatus = $entityResponse->status();
                $entityXml = $entityResponse->body();
                $baseEntityXml = $entityXml;

                if (strlen($entityXml) > 2 * 1024 * 1024) {
                    throw new Exception('Entity XML exceeds 2MB limit.');
                }

                if (!$entityResponse->successful()) {
                    $statusCode = $entityStatus;
                    $responseBody = $entityXml;
                    throw new Exception('Unable to load complete entity. HTTP ' . $entityStatus . '. ' . $this->extractApiErrorMessage($entityXml));
                }

                $payloadXml = $this->ensureResourcePayload($entityXml);
                foreach (($row['changes'] ?? []) as $fieldName => $fieldValue) {
                    $payloadXml = $this->applyBuveFieldChange($payloadXml, (string) $fieldName, (string) $fieldValue);
                }

                $postResponse = $client
                    ->withBody($payloadXml, 'application/xml')
                    ->post($updateUrl);

                $statusCode = $postResponse->status();
                $responseBody = $postResponse->body();

                if (!$postResponse->successful() && $this->isStaleRecordError($responseBody)) {
                    $retryEntityResponse = $client->get($updateUrl);
                    $retryStatus = $retryEntityResponse->status();
                    $retryXml = $retryEntityResponse->body();

                    if ($retryEntityResponse->successful()) {
                        $retryPayloadXml = $this->ensureResourcePayload($retryXml);
                        foreach (($row['changes'] ?? []) as $fieldName => $fieldValue) {
                            $retryPayloadXml = $this->applyBuveFieldChange($retryPayloadXml, (string) $fieldName, (string) $fieldValue);
                        }

                        $retryPostResponse = $client
                            ->withBody($retryPayloadXml, 'application/xml')
                            ->post($updateUrl);

                        $payloadXml = $retryPayloadXml;
                        $postResponse = $retryPostResponse;
                        $statusCode = $retryPostResponse->status();
                        $responseBody = $retryPostResponse->body();
                    } else {
                        $statusCode = $retryStatus;
                        $responseBody = $retryXml;
                    }
                }

                if ($responseBody && strlen($responseBody) > 2 * 1024 * 1024) {
                    throw new Exception('Update response XML exceeds 2MB limit.');
                }

                if (!$postResponse->successful()) {
                    $failedCount++;
                    $results[] = [
                        'id' => $id,
                        'success' => false,
                        'status_code' => $statusCode,
                        'message' => $this->extractApiErrorMessage($responseBody),
                    ];

                    $this->logBuveUpdateAttempt(
                        $request,
                        $username,
                        $updateUrl,
                        $statusCode,
                        false,
                        $payloadXml,
                        $responseBody,
                        $this->extractApiErrorMessage($responseBody),
                        $baseEntityXml
                    );
                    continue;
                }

                $successCount++;
                $updatedValues[$id] = $row['changes'] ?? [];
                $entities[$id]['original_fields'] = array_merge(
                    $entities[$id]['original_fields'] ?? [],
                    $row['changes'] ?? []
                );
                $entities[$id]['original_piezimes'] = (string) ($entities[$id]['original_fields']['piezimes'] ?? '');
                $entities[$id]['payload_xml'] = $payloadXml;

                $results[] = [
                    'id' => $id,
                    'success' => true,
                    'status_code' => $statusCode,
                    'message' => 'Updated successfully.',
                ];

                $this->logBuveUpdateAttempt(
                    $request,
                    $username,
                    $updateUrl,
                    $statusCode,
                    true,
                    $payloadXml,
                    $responseBody,
                    null,
                    $baseEntityXml
                );
            } catch (Exception $exception) {
                $failedCount++;
                $results[] = [
                    'id' => $id,
                    'success' => false,
                    'status_code' => null,
                    'message' => $exception->getMessage(),
                ];

                $this->logBuveUpdateAttempt(
                    $request,
                    $username,
                    $updateUrl,
                    $statusCode,
                    false,
                    $payloadXml,
                    $responseBody,
                    $exception->getMessage(),
                    $baseEntityXml
                );
            }
        }

        $tableRows = session('rest_tool.buve_rows', []);
        if (is_array($tableRows) && !empty($updatedValues)) {
            foreach ($tableRows as &$tableRow) {
                $rowId = (string) ($tableRow['id'] ?? '');
                if (isset($updatedValues[$rowId])) {
                    foreach ($updatedValues[$rowId] as $fieldName => $fieldValue) {
                        $tableRow[$fieldName] = $fieldValue;
                    }
                }
            }
            unset($tableRow);
            session()->put('rest_tool.buve_rows', $tableRows);
        }

        $snapshot['entities'] = $entities;
        session()->put('rest_tool.buve_snapshot', $snapshot);

        $summary = 'Updated ' . $successCount . ' of ' . count($modifiedRows) . ' modified row(s).';
        session()->flash('rest_tool.buve_update_status', $summary);
        session()->flash('rest_tool.buve_update_results', $results);
        if ($failedCount > 0) {
            session()->flash('rest_tool.buve_update_error', 'Some updates failed. See row details below.');
        }

        return redirect()->route('rest-tool.index');
    }

    public function logs(Request $request)
    {
        $logs = RestActionLog::query()
            ->select([
                'id',
                'user_id',
                'method',
                'url',
                'status_code',
                'success',
                'created_at',
            ])
            ->with(['user:id,name'])
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

    private function persistGlobalAuthInSession(bool $rememberAuth, ?string $username, ?string $password): void
    {
        if ($rememberAuth && $username) {
            session()->put('rest_tool.auth_username', $username);
            if ($password) {
                session()->put('rest_tool.auth_password', $password);
            }
            session()->put('rest_tool.remember_auth', true);
            return;
        }

        session()->forget('rest_tool.auth_username');
        session()->forget('rest_tool.auth_password');
        session()->put('rest_tool.remember_auth', false);
    }

    private function persistBuveAuthInSession(bool $rememberAuth, ?string $username, ?string $password): void
    {
        if ($rememberAuth && $username) {
            session()->put('rest_tool.buve_auth_username', $username);
            if ($password) {
                session()->put('rest_tool.buve_auth_password', $password);
            }
            session()->put('rest_tool.buve_remember_auth', true);
            return;
        }

        session()->forget('rest_tool.buve_auth_username');
        session()->forget('rest_tool.buve_auth_password');
        session()->put('rest_tool.buve_remember_auth', false);
    }

    private function buildBuveLoadUrl(string $baseUrl, string $mode, string $dynamicQuery): string
    {
        if ($mode !== 'dynamic') {
            return $baseUrl;
        }

        if ($dynamicQuery === '') {
            throw new Exception('Dynamic query path is required for dynamic mode.');
        }

        if ($this->isHttpUrl($dynamicQuery)) {
            return $dynamicQuery;
        }

        $queryPath = preg_replace('/\s+/', ' ', trim($dynamicQuery)) ?? trim($dynamicQuery);
        $queryPath = str_replace(' ', '%20', $queryPath);
        if (str_starts_with($queryPath, '?')) {
            $queryPath = '/query' . $queryPath;
        }

        $baseUrl = rtrim($baseUrl, '/');
        if (!str_starts_with($queryPath, '/')) {
            $queryPath = '/' . ltrim($queryPath, '/');
        }

        return $baseUrl . $queryPath;
    }

    private function extractBuveRows(string $xml, string $baseUrl): array
    {
        $xml = $this->normalizeXmlInput($xml);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        libxml_use_internal_errors(true);

        if (!$dom->loadXML($xml, LIBXML_NONET)) {
            $error = libxml_get_last_error();
            libxml_clear_errors();
            throw new Exception($error ? trim($error->message) : 'Invalid XML in Būve response.');
        }

        $xpath = new \DOMXPath($dom);
        $entityNodes = $xpath->query('//*[local-name()="entity"]');
        if (!$entityNodes || $entityNodes->length === 0) {
            return [];
        }

        $rows = [];
        foreach ($entityNodes as $entityNode) {
            if (!$entityNode instanceof DOMElement) {
                continue;
            }

            $selfHref = $this->firstNodeText($xpath, $entityNode, './/*[local-name()="PK_OBJ"]/*[local-name()="href" and @rel="self"][1]');
            $pkObjHref = $this->firstNodeText($xpath, $entityNode, './/*[local-name()="PK_OBJ"]/*[local-name()="href"][1]');
            $genericHref = $this->firstNodeText($xpath, $entityNode, './/*[local-name()="href" and contains(text(),"/rest/TdmPNSBuveBL/")][1]');
            $href = $selfHref !== '' ? $selfHref : ($pkObjHref !== '' ? $pkObjHref : $genericHref);

            $id = $this->extractBuveIdFromHref($href);
            if (!$id) {
                continue;
            }

            $rows[] = [
                'id' => $id,
                'update_url' => $this->buildAbsoluteUrl($baseUrl, $href !== '' ? $href : '/rest/TdmPNSBuveBL/' . $id),
                'adrese' => $this->firstNodeText($xpath, $entityNode, './*[local-name()="ADRESE"][1]'),
                'pilnadrese' => $this->firstNodeText($xpath, $entityNode, './/*[local-name()="DmPNSObjMBL"][1]/*[local-name()="PILNADRESE"][1]'),
                'kadter' => $this->firstNodeText($xpath, $entityNode, './/*[local-name()="DmPNSObjMBL"][1]/*[local-name()="KADTER"][1]'),
                'kadgrupa' => $this->firstNodeText($xpath, $entityNode, './/*[local-name()="DmPNSObjMBL"][1]/*[local-name()="KADGRUPA"][1]'),
                'zemenr' => $this->firstNodeText($xpath, $entityNode, './/*[local-name()="DmPNSObjMBL"][1]/*[local-name()="ZEMENR"][1]'),
                'zdbuvenr' => $this->firstNodeText($xpath, $entityNode, './/*[local-name()="DmPNSObjMBL"][1]/*[local-name()="ZDBUVENR"][1]'),
                'apzimkop' => $this->firstNodeText($xpath, $entityNode, './/*[local-name()="DmPNSObjMBL"][1]/*[local-name()="APZIMKOP"][1]'),
                'pk_buvegrp' => $this->firstNodeText($xpath, $entityNode, './/*[local-name()="dmPNSBuveP2BL" or local-name()="DmPNSBuveP2BL"][1]/*[local-name()="PK_BUVEGRP"][1]'),
                'gads' => $this->firstNodeText($xpath, $entityNode, './/*[local-name()="dmPNSBuveP2BL" or local-name()="DmPNSBuveP2BL"][1]/*[local-name()="GADS"][1]'),
                'inbuve' => $this->firstNodeText($xpath, $entityNode, './/*[local-name()="dmPNSBuveP2BL" or local-name()="DmPNSBuveP2BL"][1]/*[local-name()="INBUVE"][1]'),
                'efektiv' => $this->firstNodeText($xpath, $entityNode, './/*[local-name()="dmPNSBuveP2BL" or local-name()="DmPNSBuveP2BL"][1]/*[local-name()="EFEKTIV"][1]'),
                'atsavin' => $this->firstNodeText($xpath, $entityNode, './/*[local-name()="dmPNSBuveP2BL" or local-name()="DmPNSBuveP2BL"][1]/*[local-name()="ATSAVIN"][1]'),
                'pk_bserija' => $this->firstNodeText($xpath, $entityNode, './/*[local-name()="dmPNSBuveP2BL" or local-name()="DmPNSBuveP2BL"][1]/*[local-name()="PK_BSERIJA"][1]'),
                'piezimes' => $this->firstNodeText($xpath, $entityNode, './*[local-name()="PIEZIMES"][1]'),
                'payload_xml' => $this->serializeEntityAsResource($entityNode),
            ];
        }

        return $rows;
    }

    private function firstNodeText(\DOMXPath $xpath, DOMElement $context, string $query): string
    {
        $nodes = $xpath->query($query, $context);
        if (!$nodes || $nodes->length === 0) {
            return '';
        }

        return trim((string) $nodes->item(0)?->textContent);
    }

    private function extractBuveIdFromHref(string $href): ?string
    {
        $href = trim($href);
        if ($href === '') {
            return null;
        }

        if (preg_match('~/TdmPNSBuveBL/([0-9]+)(?:\?.*)?$~i', $href, $matches)) {
            return $matches[1];
        }

        if (preg_match('~/([0-9]+)(?:\?.*)?$~', $href, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function serializeEntityAsResource(DOMElement $entityNode): string
    {
        $payloadDom = new DOMDocument('1.0', 'UTF-8');
        $payloadDom->preserveWhiteSpace = false;
        $payloadDom->formatOutput = true;

        $resource = $payloadDom->createElement('resource');
        $payloadDom->appendChild($resource);
        $resource->appendChild($payloadDom->importNode($entityNode, true));

        return $this->normalizeXmlOutput($payloadDom->saveXML());
    }

    private function ensureResourcePayload(string $xml): string
    {
        $xml = $this->normalizeXmlInput($xml);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        libxml_use_internal_errors(true);

        if (!$dom->loadXML($xml, LIBXML_NONET)) {
            $error = libxml_get_last_error();
            libxml_clear_errors();
            throw new Exception($error ? trim($error->message) : 'Invalid XML payload.');
        }

        $root = $dom->documentElement;
        if (!$root) {
            throw new Exception('XML payload has no root element.');
        }

        $rootName = $root->localName ?: $root->nodeName;
        if (strcasecmp($rootName, 'resource') === 0) {
            return $this->normalizeXmlOutput($dom->saveXML());
        }

        if (strcasecmp($rootName, 'entity') === 0) {
            return $this->serializeEntityAsResource($root);
        }

        $xpath = new \DOMXPath($dom);
        $entityNodes = $xpath->query('//*[local-name()="entity"]');
        if (!$entityNodes || $entityNodes->length === 0 || !($entityNodes->item(0) instanceof DOMElement)) {
            throw new Exception('No entity node found in XML payload.');
        }

        return $this->serializeEntityAsResource($entityNodes->item(0));
    }

    private function setEntityFieldValue(string $resourceXml, string $fieldName, string $value): string
    {
        $resourceXml = $this->ensureResourcePayload($resourceXml);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        libxml_use_internal_errors(true);

        if (!$dom->loadXML($resourceXml, LIBXML_NONET)) {
            $error = libxml_get_last_error();
            libxml_clear_errors();
            throw new Exception($error ? trim($error->message) : 'Invalid XML payload.');
        }

        $xpath = new \DOMXPath($dom);
        $entityNode = $xpath->query('//*[local-name()="entity"][1]')->item(0);
        if (!$entityNode instanceof DOMElement) {
            throw new Exception('No entity node found in XML payload.');
        }

        $fieldNode = $xpath->query('./*[local-name()="' . $fieldName . '"][1]', $entityNode)->item(0);
        if (!$fieldNode instanceof DOMElement) {
            $namespace = $entityNode->namespaceURI;
            $fieldNode = $namespace
                ? $dom->createElementNS($namespace, $fieldName)
                : $dom->createElement($fieldName);
            $entityNode->appendChild($fieldNode);
        }

        while ($fieldNode->firstChild) {
            $fieldNode->removeChild($fieldNode->firstChild);
        }
        $fieldNode->appendChild($dom->createTextNode($value));

        return $this->normalizeXmlOutput($dom->saveXML());
    }

    private function applyBuveFieldChange(string $resourceXml, string $fieldName, string $value): string
    {
        $normalized = strtolower(trim($fieldName));

        return match ($normalized) {
            'adrese' => $this->setEntityFieldValue($resourceXml, 'ADRESE', $value),
            'pilnadrese' => $this->setNestedEntityFieldValue($resourceXml, ['DmPNSObjMBL', 'PILNADRESE'], $value),
            'kadter' => $this->setNestedEntityFieldValue($resourceXml, ['DmPNSObjMBL', 'KADTER'], $value),
            'kadgrupa' => $this->setNestedEntityFieldValue($resourceXml, ['DmPNSObjMBL', 'KADGRUPA'], $value),
            'zemenr' => $this->setNestedEntityFieldValue($resourceXml, ['DmPNSObjMBL', 'ZEMENR'], $value),
            'zdbuvenr' => $this->setNestedEntityFieldValue($resourceXml, ['DmPNSObjMBL', 'ZDBUVENR'], $value),
            'apzimkop' => $this->setNestedEntityFieldValue($resourceXml, ['DmPNSObjMBL', 'APZIMKOP'], $value),
            'pk_buvegrp' => $this->setNestedEntityFieldValue($resourceXml, ['dmPNSBuveP2BL', 'PK_BUVEGRP'], $value),
            'pk_bserija' => $this->setNestedEntityFieldValue($resourceXml, ['dmPNSBuveP2BL', 'PK_BSERIJA'], $value),
            'piezimes' => $this->setEntityFieldValue($resourceXml, 'PIEZIMES', $value),
            'gads' => $this->setNestedEntityFieldValue($resourceXml, ['dmPNSBuveP2BL', 'GADS'], $value),
            'inbuve' => $this->setNestedEntityFieldValue($resourceXml, ['dmPNSBuveP2BL', 'INBUVE'], $value),
            'efektiv' => $this->setNestedEntityFieldValue($resourceXml, ['dmPNSBuveP2BL', 'EFEKTIV'], $value),
            'atsavin' => $this->setNestedEntityFieldValue($resourceXml, ['dmPNSBuveP2BL', 'ATSAVIN'], $value),
            default => $resourceXml,
        };
    }

    private function setNestedEntityFieldValue(string $resourceXml, array $segments, string $value): string
    {
        $resourceXml = $this->ensureResourcePayload($resourceXml);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        libxml_use_internal_errors(true);

        if (!$dom->loadXML($resourceXml, LIBXML_NONET)) {
            $error = libxml_get_last_error();
            libxml_clear_errors();
            throw new Exception($error ? trim($error->message) : 'Invalid XML payload.');
        }

        $xpath = new \DOMXPath($dom);
        $entityNode = $xpath->query('//*[local-name()="entity"][1]')->item(0);
        if (!$entityNode instanceof DOMElement) {
            throw new Exception('No entity node found in XML payload.');
        }

        $current = $entityNode;
        foreach ($segments as $index => $segmentName) {
            $found = null;
            foreach ($current->childNodes as $childNode) {
                if (!$childNode instanceof DOMElement) {
                    continue;
                }

                $localName = $childNode->localName ?: $childNode->nodeName;
                if (strcasecmp($localName, $segmentName) === 0) {
                    $found = $childNode;
                    break;
                }
            }

            if (!$found) {
                $namespace = $current->namespaceURI ?: $entityNode->namespaceURI;
                $elementName = $index === 0 ? (string) ($segments[0] ?? $segmentName) : $segmentName;
                $found = $namespace
                    ? $dom->createElementNS($namespace, $elementName)
                    : $dom->createElement($elementName);

                $insertAfterObj = $index === 0
                    && strcasecmp($segmentName, 'dmPNSBuveP2BL') === 0
                    && $current->isSameNode($entityNode);

                if ($insertAfterObj) {
                    $inserted = false;
                    foreach ($current->childNodes as $siblingNode) {
                        if (!$siblingNode instanceof DOMElement) {
                            continue;
                        }

                        $localName = $siblingNode->localName ?: $siblingNode->nodeName;
                        if (strcasecmp($localName, 'DmPNSObjMBL') !== 0) {
                            continue;
                        }

                        if ($siblingNode->nextSibling) {
                            $current->insertBefore($found, $siblingNode->nextSibling);
                        } else {
                            $current->appendChild($found);
                        }

                        $inserted = true;
                        break;
                    }

                    if (!$inserted) {
                        $current->appendChild($found);
                    }
                } else {
                    $current->appendChild($found);
                }
            }

            $current = $found;
        }

        while ($current->firstChild) {
            $current->removeChild($current->firstChild);
        }
        $current->appendChild($dom->createTextNode($value));

        return $this->normalizeXmlOutput($dom->saveXML());
    }

    private function extractApiErrorMessage(?string $payload): string
    {
        if (!$payload) {
            return 'No details returned by API.';
        }

        $decoded = trim((string) ($this->decodeXmlForDisplay($payload) ?? $payload));
        if ($decoded === '') {
            return 'No details returned by API.';
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);

        if ($dom->loadXML($decoded, LIBXML_NONET)) {
            $xpath = new \DOMXPath($dom);
            $targets = ['message', 'error', 'detail', 'faultstring', 'description'];
            foreach ($targets as $target) {
                $query = '//*[translate(local-name(), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="' . $target . '"]';
                $nodes = $xpath->query($query);
                if (!$nodes) {
                    continue;
                }

                foreach ($nodes as $node) {
                    $value = trim((string) $node->textContent);
                    if ($value !== '') {
                        libxml_clear_errors();
                        return $this->truncate($value, 600);
                    }
                }
            }

            $rootText = trim((string) ($dom->documentElement?->textContent ?? ''));
            if ($rootText !== '') {
                libxml_clear_errors();
                return $this->truncate($rootText, 600);
            }
        }

        libxml_clear_errors();

        $plainText = trim(strip_tags($decoded));
        if ($plainText !== '') {
            return $this->truncate($plainText, 600);
        }

        return $this->truncate($decoded, 600);
    }

    private function isStaleRecordError(?string $payload): bool
    {
        if (!$payload) {
            return false;
        }

        $message = mb_strtolower($this->extractApiErrorMessage($payload));
        $normalized = str_replace(
            ['ā', 'ē', 'ī', 'ū', 'š', 'ģ', 'ķ', 'ļ', 'ņ', 'č', 'ž'],
            ['a', 'e', 'i', 'u', 's', 'g', 'k', 'l', 'n', 'c', 'z'],
            $message
        );

        return str_contains($normalized, 'ieraksts ir mainijies kops nolisisanas')
            || str_contains($normalized, 'ieraksts ir mainijies kops nolasisanas')
            || str_contains($normalized, 'record has changed since reading');
    }

    private function prepareLatestEntityPostPayload($client, string $postUrl, string $patchXml): array
    {
        $entityResponse = $client->get($postUrl);
        $entityStatus = $entityResponse->status();
        $entityXml = $entityResponse->body();

        if (strlen($entityXml) > 2 * 1024 * 1024) {
            throw new Exception('Entity XML exceeds 2MB limit.');
        }

        if (!$entityResponse->successful()) {
            throw new Exception(
                'Unable to load latest entity before POST. HTTP '
                . $entityStatus
                . '. '
                . $this->extractApiErrorMessage($entityXml)
            );
        }

        $payload = $this->mergeResourcePatch($entityXml, $patchXml);
        if (strlen($payload) > 2 * 1024 * 1024) {
            throw new Exception('Prepared XML payload exceeds 2MB limit.');
        }

        return [
            'payload' => $payload,
            'entity_xml' => $entityXml,
        ];
    }

    private function mergeResourcePatch(string $baseXml, string $patchXml): string
    {
        $baseResourceXml = $this->ensureResourcePayload($baseXml);
        $patchResourceXml = $this->ensureResourcePayload($patchXml);

        $baseDom = new DOMDocument('1.0', 'UTF-8');
        $baseDom->preserveWhiteSpace = false;
        $baseDom->formatOutput = true;
        libxml_use_internal_errors(true);

        if (!$baseDom->loadXML($baseResourceXml, LIBXML_NONET)) {
            $error = libxml_get_last_error();
            libxml_clear_errors();
            throw new Exception($error ? trim($error->message) : 'Invalid base XML payload.');
        }

        $patchDom = new DOMDocument('1.0', 'UTF-8');
        $patchDom->preserveWhiteSpace = false;
        $patchDom->formatOutput = true;
        libxml_use_internal_errors(true);

        if (!$patchDom->loadXML($patchResourceXml, LIBXML_NONET)) {
            $error = libxml_get_last_error();
            libxml_clear_errors();
            throw new Exception($error ? trim($error->message) : 'Invalid patch XML payload.');
        }

        $baseXpath = new \DOMXPath($baseDom);
        $patchXpath = new \DOMXPath($patchDom);

        $baseEntity = $baseXpath->query('//*[local-name()="entity"][1]')->item(0);
        $patchEntity = $patchXpath->query('//*[local-name()="entity"][1]')->item(0);

        if (!$baseEntity instanceof DOMElement || !$patchEntity instanceof DOMElement) {
            throw new Exception('Entity node not found for payload merge.');
        }

        $this->mergeElementIntoTarget($baseDom, $baseEntity, $patchEntity);

        return $this->normalizeXmlOutput($baseDom->saveXML());
    }

    private function mergeElementIntoTarget(DOMDocument $targetDom, DOMElement $targetNode, DOMElement $patchNode): void
    {
        if ($patchNode->hasAttributes()) {
            foreach ($patchNode->attributes as $attribute) {
                $targetNode->setAttribute($attribute->nodeName, $attribute->nodeValue ?? '');
            }
        }

        if (!$this->elementHasChildElements($patchNode)) {
            while ($targetNode->firstChild) {
                $targetNode->removeChild($targetNode->firstChild);
            }
            $targetNode->appendChild($targetDom->createTextNode($patchNode->textContent ?? ''));
            return;
        }

        $childPositions = [];
        foreach ($patchNode->childNodes as $patchChildNode) {
            if (!$patchChildNode instanceof DOMElement) {
                continue;
            }

            $childLocalName = strtolower($patchChildNode->localName ?: $patchChildNode->nodeName);
            $childPositions[$childLocalName] = ($childPositions[$childLocalName] ?? 0) + 1;
            $targetChildNode = $this->getNthChildByLocalName($targetNode, $childLocalName, $childPositions[$childLocalName]);

            if (!$targetChildNode) {
                $importedNode = $targetDom->importNode($patchChildNode, true);
                if ($importedNode instanceof DOMElement) {
                    $this->insertMergedChild($targetNode, $importedNode);
                }
                continue;
            }

            $this->mergeElementIntoTarget($targetDom, $targetChildNode, $patchChildNode);
        }
    }

    private function getNthChildByLocalName(DOMElement $parentNode, string $localName, int $index): ?DOMElement
    {
        $count = 0;
        foreach ($parentNode->childNodes as $childNode) {
            if (!$childNode instanceof DOMElement) {
                continue;
            }

            $childLocalName = strtolower($childNode->localName ?: $childNode->nodeName);
            if ($childLocalName !== $localName) {
                continue;
            }

            $count++;
            if ($count === $index) {
                return $childNode;
            }
        }

        return null;
    }

    private function insertMergedChild(DOMElement $parentNode, DOMElement $childNode): void
    {
        $parentLocalName = strtolower($parentNode->localName ?: $parentNode->nodeName);
        $childLocalName = strtolower($childNode->localName ?: $childNode->nodeName);

        if ($parentLocalName === 'entity' && $childLocalName === 'dmpnsbuvep2bl') {
            foreach ($parentNode->childNodes as $siblingNode) {
                if (!$siblingNode instanceof DOMElement) {
                    continue;
                }

                $siblingLocalName = strtolower($siblingNode->localName ?: $siblingNode->nodeName);
                if ($siblingLocalName !== 'dmpnsobjmbl') {
                    continue;
                }

                if ($siblingNode->nextSibling) {
                    $parentNode->insertBefore($childNode, $siblingNode->nextSibling);
                } else {
                    $parentNode->appendChild($childNode);
                }
                return;
            }
        }

        $parentNode->appendChild($childNode);
    }

    private function elementHasChildElements(DOMElement $element): bool
    {
        foreach ($element->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                return true;
            }
        }

        return false;
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
        $keyFields = $skipDetails ? [] : $this->extractKeyFields($dom);

        return [
            'formatted' => $formatted,
            'tree' => $tree,
            'rows' => $rows,
            'keyFields' => $keyFields,
        ];
    }

    private function extractKeyFields(DOMDocument $dom): array
    {
        $targets = [
            'KADTER',
            'KADGRUPA',
            'ZEMENR',
            'ZDBUVENR',
            'TGNR',
            'APZIMKOP',
            'ADRESE',
            'PILNADRESE',
            'PK_BUVEGRP',
            'GADS',
            'INBUVE',
            'EFEKTIV',
            'ATSAVIN',
            'PK_BSERIJA',
            'PIEZIMES',
        ];
        $fields = [];
        $xpath = new \DOMXPath($dom);

        foreach ($targets as $name) {
            $nodes = $xpath->query('//*[local-name()="' . $name . '"]');
            if (!$nodes) {
                continue;
            }
            foreach ($nodes as $node) {
                if (!$node instanceof DOMElement) {
                    continue;
                }
                $fields[] = [
                    'name' => $name,
                    'value' => trim($node->textContent ?? ''),
                    'path' => $this->getElementPath($node),
                ];
            }
        }

        return $fields;
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

    private function createFailedPostLog(
        Request $request,
        string $postUrl,
        ?string $username,
        ?string $xmlPayload,
        ?string $baseRequestXml,
        string $errorMessage
    ): RestActionLog {
        $logData = [
            'user_id' => $request->user()->id,
            'method' => 'POST',
            'url' => $postUrl,
            'status_code' => null,
            'success' => false,
            'request_xml' => $xmlPayload,
            'response_xml' => null,
            'error_message' => $errorMessage,
            'auth_username' => $username,
        ];

        if (Schema::hasColumn('rest_action_logs', 'base_request_xml')) {
            $logData['base_request_xml'] = $baseRequestXml;
        }

        return RestActionLog::create($logData);
    }

    private function logBuveUpdateAttempt(
        Request $request,
        ?string $username,
        string $updateUrl,
        ?int $statusCode,
        bool $success,
        ?string $requestXml,
        ?string $responseXml,
        ?string $errorMessage,
        ?string $baseRequestXml = null
    ): void {
        $logData = [
            'user_id' => $request->user()->id,
            'method' => 'POST',
            'url' => $updateUrl,
            'status_code' => $statusCode,
            'success' => $success,
            'request_xml' => $requestXml,
            'response_xml' => $responseXml,
            'error_message' => $errorMessage,
            'auth_username' => $username,
        ];

        if (Schema::hasColumn('rest_action_logs', 'base_request_xml')) {
            $logData['base_request_xml'] = $baseRequestXml;
        }

        RestActionLog::create($logData);
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
