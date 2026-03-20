(function () {
    const changeMap = {};
    const changeTracker = document.getElementById("change-tracker");
    function decodeHtmlEntities(value) {
        if (!value) {
            return "";
        }
        const textarea = document.createElement("textarea");
        textarea.innerHTML = value;
        return textarea.value || "";
    }

    const originalXmlElement = document.getElementById("original-xml");
    const originalXmlRaw = originalXmlElement ? originalXmlElement.value : "";
    const originalXml = decodeHtmlEntities(originalXmlRaw);
    const logIdElement = document.getElementById("rest-tool-log-id");
    const logMethodElement = document.getElementById("rest-tool-log-method");
    const rawLogId = logIdElement ? logIdElement.value.trim() : "";
    const logId = /^[0-9]+$/.test(rawLogId) ? rawLogId : "";
    const logMethod = logMethodElement
        ? logMethodElement.value.trim().toUpperCase()
        : "";
    const allowChangePersistence = !logMethod || logMethod !== "GET";
    const changeKey =
        allowChangePersistence && logId ? `restTool.changeMap.${logId}` : null;
    const generatedXmlDraftKey = logId
        ? `restTool.generatedXmlDraft.${logId}`
        : "restTool.generatedXmlDraft.current";
    const generatedXmlLastDraftKey = "restTool.generatedXmlDraft.last";
    const formattedXml = document.getElementById("formatted-xml");
    const formattedXmlOriginal = formattedXml ? formattedXml.value : "";
    const generatedXmlArea = document.getElementById("generated-xml");
    let generatedXmlLocked = !!(
        generatedXmlArea && generatedXmlArea.value.trim() !== ""
    );
    const requestXmlField = document.getElementById("request-xml");

    const pathInputIndex = new Map();
    const pathNodeValueIndex = new Map();
    const originalValueCache = new Map();
    const originalNodeExistsCache = new Map();
    const parsedPathSegmentsCache = new Map();
    let trackerRenderQueued = false;
    let saveChangeMapTimer = null;
    let formattedXmlUpdateTimer = null;

    function scheduleChangeTrackerRender() {
        if (trackerRenderQueued) {
            return;
        }
        trackerRenderQueued = true;
        requestAnimationFrame(() => {
            trackerRenderQueued = false;
            renderChangeTracker();
        });
    }

    function scheduleSaveChangeMap() {
        if (!allowChangePersistence) {
            return;
        }
        if (saveChangeMapTimer) {
            clearTimeout(saveChangeMapTimer);
        }
        saveChangeMapTimer = setTimeout(() => {
            saveChangeMapTimer = null;
            saveChangeMap();
        }, 120);
    }

    function scheduleFormattedXmlUpdate() {
        if (!formattedXml) {
            return;
        }
        if (formattedXmlUpdateTimer) {
            clearTimeout(formattedXmlUpdateTimer);
        }
        formattedXmlUpdateTimer = setTimeout(() => {
            formattedXmlUpdateTimer = null;
            updateFormattedXmlFromChanges();
        }, 180);
    }

    function renderChangeTracker() {
        if (!changeTracker) {
            return;
        }
        const keys = Object.keys(changeMap);
        if (keys.length === 0) {
            changeTracker.innerHTML =
                '<div class="text-gray-500">No changes yet.</div>';
            return;
        }
        const items = keys.map((path) => {
            const entry = changeMap[path];
            return `<li class="py-1"><span class="font-medium text-gray-700">${path}</span><br><span class="text-xs text-gray-500">${entry.original}</span> → <span class="text-xs text-indigo-600">${entry.value}</span></li>`;
        });
        changeTracker.innerHTML = `<ul class="space-y-2">${items.join("")}</ul>`;
    }

    function saveChangeMap() {
        if (!allowChangePersistence) {
            return;
        }
        try {
            if (changeKey) {
                localStorage.setItem(changeKey, JSON.stringify(changeMap));
            }
            localStorage.setItem(
                "restTool.changeMap.last",
                JSON.stringify(changeMap),
            );
        } catch (e) {
            // ignore storage errors
        }
    }

    function addIndexedElement(indexMap, path, element) {
        if (!path || !element) {
            return;
        }
        if (!indexMap.has(path)) {
            indexMap.set(path, []);
        }
        indexMap.get(path).push(element);
    }

    function indexPathBoundElements() {
        pathInputIndex.clear();
        pathNodeValueIndex.clear();

        document
            .querySelectorAll(".xml-edit-input[data-path]")
            .forEach((element) => {
                addIndexedElement(
                    pathInputIndex,
                    element.getAttribute("data-path") || "",
                    element,
                );
            });

        document
            .querySelectorAll(".node-value[data-node-path]")
            .forEach((element) => {
                addIndexedElement(
                    pathNodeValueIndex,
                    element.getAttribute("data-node-path") || "",
                    element,
                );
            });
    }

    function getIndexedElements(indexMap, path, selector) {
        if (indexMap.has(path)) {
            return indexMap.get(path);
        }
        const matched = Array.from(document.querySelectorAll(selector));
        indexMap.set(path, matched);
        return matched;
    }

    function applyChangesToInputs(paths = null) {
        const activeElement = document.activeElement;
        const targetPaths = Array.isArray(paths)
            ? paths
            : Object.keys(changeMap);

        targetPaths.forEach((path) => {
            const value = getValueByPath(path);
            const inputSelector = `.xml-edit-input[data-path="${CSS.escape(path)}"]`;
            const nodeSelector = `.node-value[data-node-path="${CSS.escape(path)}"]`;

            getIndexedElements(pathInputIndex, path, inputSelector).forEach(
                (input) => {
                    const isActive =
                        !!activeElement &&
                        (input === activeElement ||
                            input.contains?.(activeElement) ||
                            activeElement.contains?.(input));
                    if (isActive) {
                        return;
                    }

                    if ("value" in input) {
                        input.value = value;
                    } else {
                        input.textContent = value;
                    }
                },
            );

            getIndexedElements(
                pathNodeValueIndex,
                path,
                nodeSelector,
            ).forEach((nodeValue) => {
                    const isActive =
                        !!activeElement &&
                        (nodeValue === activeElement ||
                            nodeValue.contains?.(activeElement) ||
                            activeElement.contains?.(nodeValue));
                    if (isActive) {
                        return;
                    }

                    nodeValue.textContent = value;
                    nodeValue.setAttribute("data-node-value", value);
                });
        });
    }

    function formatXmlString(xml) {
        const reg = /(>)(<)(\/*)/g;
        const padChar = "  ";
        let formatted = "";
        let pad = 0;
        xml.replace(reg, "$1\n$2$3")
            .split("\n")
            .forEach((node) => {
                if (!node.trim()) {
                    return;
                }
                let indent = 0;
                if (node.match(/.+<\/\w[^>]*>$/)) {
                    indent = 0;
                } else if (node.match(/^<\/\w/)) {
                    pad = Math.max(pad - 1, 0);
                } else if (node.match(/^<\w([^>]*[^\/])?>.*$/)) {
                    indent = 1;
                }
                formatted += `${padChar.repeat(pad)}${node}\n`;
                pad += indent;
            });
        return formatted.trim();
    }

    function buildUpdatedXmlFromChanges() {
        if (!originalXml) {
            return null;
        }
        const changePaths = Object.keys(changeMap);
        if (changePaths.length === 0) {
            return null;
        }

        const parser = new DOMParser();
        const doc = parser.parseFromString(originalXml, "application/xml");
        if (doc.querySelector("parsererror")) {
            return null;
        }
        const root = doc.documentElement;
        if (!root) {
            return null;
        }

        changePaths.forEach((path) => {
            const segments = getParsedPathSegments(path);
            if (segments.length === 0) {
                return;
            }
            if (segments[0].name !== root.nodeName) {
                return;
            }

            let node = root;
            for (let i = 1; i < segments.length; i += 1) {
                const segment = segments[i];
                const next = getNthChild(node, segment.name, segment.index);
                if (!next) {
                    return;
                }
                node = next;
            }

            node.textContent = changeMap[path].value ?? "";
        });

        const serializer = new XMLSerializer();
        return serializer.serializeToString(doc);
    }

    function updateFormattedXmlFromChanges() {
        if (!formattedXml) {
            return;
        }
        if (Object.keys(changeMap).length === 0) {
            if (formattedXmlOriginal) {
                formattedXml.value = formattedXmlOriginal;
                resizeFormattedXml();
            }
            return;
        }
        const updated = buildUpdatedXmlFromChanges();
        if (!updated) {
            return;
        }
        formattedXml.value = formatXmlString(updated);
        resizeFormattedXml();
    }

    function loadChangeMap() {
        if (!allowChangePersistence) {
            Object.keys(changeMap).forEach((key) => {
                delete changeMap[key];
            });
            renderChangeTracker();
            return;
        }
        if (!changeKey) {
            Object.keys(changeMap).forEach((key) => {
                delete changeMap[key];
            });
            renderChangeTracker();
            return;
        }
        let stored = null;
        try {
            stored = localStorage.getItem(changeKey);
        } catch (e) {
            stored = null;
        }

        if (!stored) {
            try {
                stored = localStorage.getItem("restTool.changeMap.last");
                if (stored) {
                    const parsed = JSON.parse(stored);
                    Object.keys(parsed || {}).forEach((key) => {
                        changeMap[key] = parsed[key];
                    });
                    saveChangeMap();
                    localStorage.removeItem("restTool.changeMap.last");
                }
            } catch (e) {
                stored = null;
            }

            renderChangeTracker();
            applyChangesToInputs();
            updateFormattedXmlFromChanges();
            return;
        }

        try {
            const parsed = JSON.parse(stored);
            Object.keys(parsed || {}).forEach((key) => {
                changeMap[key] = parsed[key];
            });
            renderChangeTracker();
            applyChangesToInputs();
            updateFormattedXmlFromChanges();
        } catch (e) {
            renderChangeTracker();
        }
    }

    function setChangeValue(path, original, value) {
        const existing = changeMap[path];
        if (value === original) {
            if (existing) {
                delete changeMap[path];
                return true;
            }
            return false;
        }

        if (
            existing &&
            existing.original === original &&
            existing.value === value
        ) {
            return false;
        }

        changeMap[path] = { original, value };
        return true;
    }

    function updateChange(path, original, value) {
        if (!path) {
            return;
        }

        const changedPaths = [path];
        setChangeValue(path, original, value);

        const linkedChangedPaths = updateApzimkopIfNeeded(path);
        linkedChangedPaths.forEach((linkedPath) => {
            if (!changedPaths.includes(linkedPath)) {
                changedPaths.push(linkedPath);
            }
        });

        applyChangesToInputs(changedPaths);
        scheduleChangeTrackerRender();
        scheduleSaveChangeMap();
        scheduleFormattedXmlUpdate();
    }

    let originalDocCache = null;

    function getOriginalDoc() {
        if (originalDocCache) {
            return originalDocCache;
        }
        if (!originalXml) {
            return null;
        }
        const parser = new DOMParser();
        const doc = parser.parseFromString(originalXml, "application/xml");
        if (doc.querySelector("parsererror")) {
            return null;
        }
        originalDocCache = doc;
        return doc;
    }

    function getParsedPathSegments(path) {
        if (!path) {
            return [];
        }
        if (parsedPathSegmentsCache.has(path)) {
            return parsedPathSegmentsCache.get(path);
        }
        const parsed = parsePathSegments(path);
        parsedPathSegmentsCache.set(path, parsed);
        return parsed;
    }

    function getNodeByPath(doc, path) {
        if (!doc || !path) {
            return null;
        }
        const segments = getParsedPathSegments(path);
        if (segments.length === 0) {
            return null;
        }
        const root = doc.documentElement;
        if (!root || segments[0].name !== root.nodeName) {
            return null;
        }
        let node = root;
        for (let i = 1; i < segments.length; i += 1) {
            node = getNthChild(node, segments[i].name, segments[i].index);
            if (!node) {
                return null;
            }
        }
        return node;
    }

    function getValueByPath(path) {
        if (!path) {
            return "";
        }
        if (changeMap[path]) {
            return changeMap[path].value ?? "";
        }
        const doc = getOriginalDoc();
        const node = getNodeByPath(doc, path);
        return node ? node.textContent || "" : "";
    }

    function getOriginalValueByPath(path) {
        if (!path) {
            return "";
        }
        if (originalValueCache.has(path)) {
            return originalValueCache.get(path);
        }
        const doc = getOriginalDoc();
        const node = getNodeByPath(doc, path);
        const value = node ? node.textContent || "" : "";
        originalValueCache.set(path, value);
        return value;
    }

    function updateChangeInternal(path, value) {
        const original = getOriginalValueByPath(path);
        return setChangeValue(path, original, value);
    }

    function linkedPath(basePath, name, index) {
        return `${basePath}/${name}[${index}]`;
    }

    function hasLinkedNode(path) {
        if (!path) {
            return false;
        }

        if (changeMap[path]) {
            return true;
        }

        if (originalNodeExistsCache.has(path)) {
            return originalNodeExistsCache.get(path);
        }

        const doc = getOriginalDoc();
        const exists = !!getNodeByPath(doc, path);
        originalNodeExistsCache.set(path, exists);
        return exists;
    }

    function splitApzimkopByCurrentValues(basePath, apzimkopValue, index) {
        const source = String(apzimkopValue || "");
        const currentKadter = getValueByPath(linkedPath(basePath, "KADTER", index));
        const currentKadgrupa = getValueByPath(linkedPath(basePath, "KADGRUPA", index));
        const currentZemenr = getValueByPath(linkedPath(basePath, "ZEMENR", index));
        const currentZdbuvenr = getValueByPath(linkedPath(basePath, "ZDBUVENR", index));

        let lenKadter = (currentKadter || "").length;
        let lenKadgrupa = (currentKadgrupa || "").length;
        let lenZemenr = (currentZemenr || "").length;
        let lenZdbuvenr = (currentZdbuvenr || "").length;

        const knownLength = lenKadter + lenKadgrupa + lenZemenr + lenZdbuvenr;
        if (knownLength === 0 && source.length >= 12) {
            lenKadter = 4;
            lenKadgrupa = 4;
            lenZemenr = 4;
            lenZdbuvenr = Math.max(0, source.length - 12);
        }

        let cursor = 0;
        const take = (requestedLength) => {
            const safeLength = Math.max(0, requestedLength || 0);
            const next = source.slice(cursor, cursor + safeLength);
            cursor += safeLength;
            return next;
        };

        const kadter = take(lenKadter);
        const kadgrupa = take(lenKadgrupa);
        const zemenr = take(lenZemenr);
        const zdbuvenr = source.slice(cursor);

        return {
            kadter,
            kadgrupa,
            zemenr,
            zdbuvenr,
        };
    }

    function updateApzimkopIfNeeded(path) {
        const changedPaths = [];

        if (!path) {
            return changedPaths;
        }

        const fieldMatch = path.match(/\/(KADTER|KADGRUPA|ZEMENR|ZDBUVENR)\[(\d+)\]$/i);
        const apzimkopMatch = path.match(/\/APZIMKOP\[(\d+)\]$/i);

        if (!fieldMatch && !apzimkopMatch) {
            return changedPaths;
        }

        const basePath = fieldMatch
            ? path.replace(/\/(KADTER|KADGRUPA|ZEMENR|ZDBUVENR)\[\d+\]$/i, "")
            : path.replace(/\/APZIMKOP\[\d+\]$/i, "");

        if (!/\/DMPNSOBJMBL\[\d+\]$/i.test(basePath)) {
            return changedPaths;
        }

        const fieldIndex = Number.parseInt(fieldMatch?.[2] || apzimkopMatch?.[1] || "1", 10);
        const index = Number.isFinite(fieldIndex) && fieldIndex > 0 ? fieldIndex : 1;

        const kadterPath = linkedPath(basePath, "KADTER", index);
        const kadgrupaPath = linkedPath(basePath, "KADGRUPA", index);
        const zemenrPath = linkedPath(basePath, "ZEMENR", index);
        const zdbuvenrPath = linkedPath(basePath, "ZDBUVENR", index);
        const apzimkopPath = linkedPath(basePath, "APZIMKOP", index);

        const linkedPaths = [
            kadterPath,
            kadgrupaPath,
            zemenrPath,
            zdbuvenrPath,
            apzimkopPath,
        ];
        if (!linkedPaths.every((linked) => hasLinkedNode(linked))) {
            return changedPaths;
        }

        if (fieldMatch) {
            const kadter = getValueByPath(kadterPath);
            const kadgrupa = getValueByPath(kadgrupaPath);
            const zemenr = getValueByPath(zemenrPath);
            const zdbuvenr = getValueByPath(zdbuvenrPath);
            const apzimkopValue = `${kadter}${kadgrupa}${zemenr}${zdbuvenr}`;
            if (updateChangeInternal(apzimkopPath, apzimkopValue)) {
                changedPaths.push(apzimkopPath);
            }
        }

        if (apzimkopMatch) {
            const apzimkopValue = getValueByPath(apzimkopPath);
            const segments = splitApzimkopByCurrentValues(
                basePath,
                apzimkopValue,
                index,
            );
            const targetFields = ["kadter", "kadgrupa", "zemenr", "zdbuvenr"];

            targetFields.forEach((name) => {
                const fieldPath = linkedPath(basePath, name.toUpperCase(), index);
                const currentValue = getValueByPath(fieldPath);
                const nextValue = segments[name] ?? "";
                if (currentValue !== nextValue) {
                    if (updateChangeInternal(fieldPath, nextValue)) {
                        changedPaths.push(fieldPath);
                    }
                }
            });
        }

        return changedPaths;
    }

    document.addEventListener("input", (event) => {
        const target = event.target;
        if (!target) {
            return;
        }

        if (target.classList?.contains("xml-edit-input")) {
            const value =
                "value" in target ? target.value : target.textContent || "";
            updateChange(
                target.dataset.path,
                target.dataset.original || "",
                value,
            );
            return;
        }

        if (
            target.matches &&
            target.matches('.node-value[contenteditable="true"]')
        ) {
            const path = target.dataset.nodePath;
            const original = target.dataset.nodeOriginal || "";
            const value = target.textContent || "";
            target.setAttribute("data-node-value", value);
            updateChange(path, original, value);
        }
    });

    indexPathBoundElements();
    loadChangeMap();

    function persistGeneratedXmlDraft() {
        if (!generatedXmlArea) {
            return;
        }

        const value = generatedXmlArea.value || "";
        const hasValue = value.trim() !== "";

        try {
            if (hasValue) {
                localStorage.setItem(generatedXmlDraftKey, value);
                localStorage.setItem(generatedXmlLastDraftKey, value);
            } else {
                localStorage.removeItem(generatedXmlDraftKey);
            }
        } catch (e) {
            // ignore storage errors
        }
    }

    function restoreGeneratedXmlDraft() {
        if (!generatedXmlArea) {
            return;
        }

        if (generatedXmlArea.value.trim() !== "") {
            generatedXmlLocked = true;
            persistGeneratedXmlDraft();
            return;
        }

        let stored = null;
        try {
            stored =
                localStorage.getItem(generatedXmlDraftKey) ||
                localStorage.getItem(generatedXmlLastDraftKey);
        } catch (e) {
            stored = null;
        }

        if (stored && stored.trim() !== "") {
            generatedXmlArea.value = stored;
            generatedXmlLocked = true;
        }
    }

    function refreshGeneratedXmlFromAuto(force = false) {
        if (!generatedXmlArea) {
            return;
        }

        if (!force && generatedXmlLocked && generatedXmlArea.value.trim() !== "") {
            return;
        }

        const payload = buildGeneratedXmlPayload();
        generatedXmlArea.value = payload || "";
        generatedXmlLocked = false;
        persistGeneratedXmlDraft();
    }

    if (generatedXmlArea) {
        restoreGeneratedXmlDraft();
        generatedXmlArea.addEventListener("input", () => {
            generatedXmlLocked = true;
            persistGeneratedXmlDraft();
        });
    }

    const postForm = document.getElementById("post-xml-form");
    if (postForm) {
        postForm.addEventListener("submit", () => {
            if (requestXmlField) {
                requestXmlField.value = originalXml || "";
            }
            if (generatedXmlArea) {
                if (generatedXmlArea.value.trim() === "") {
                    refreshGeneratedXmlFromAuto(true);
                } else {
                    generatedXmlLocked = true;
                    persistGeneratedXmlDraft();
                }
            }
            try {
                const keys = Object.keys(changeMap);
                if (keys.length === 0) {
                    localStorage.removeItem("restTool.changeMap.pendingPost");
                    return;
                }
                localStorage.setItem(
                    "restTool.changeMap.pendingPost",
                    JSON.stringify(changeMap),
                );
            } catch (e) {
                // ignore storage errors
            }
        });
    }

    const collapseKey = "restTool.collapsedPaths";
    function getCollapsed() {
        try {
            return JSON.parse(localStorage.getItem(collapseKey) || "[]");
        } catch (e) {
            return [];
        }
    }

    function setCollapsed(paths) {
        localStorage.setItem(collapseKey, JSON.stringify(paths));
    }

    function applyCollapsedState() {
        const collapsed = new Set(getCollapsed());
        document
            .querySelectorAll("[data-node-children]")
            .forEach((container) => {
                const path = container.getAttribute("data-node-children");
                if (collapsed.has(path)) {
                    container.classList.add("hidden");
                    const toggle = document.querySelector(
                        `[data-node-toggle][data-node-path="${CSS.escape(path)}"]`,
                    );
                    if (toggle) {
                        toggle.textContent = "▸";
                    }
                }
            });
    }

    document.querySelectorAll("[data-node-toggle]").forEach((toggle) => {
        toggle.addEventListener("click", () => {
            const path = toggle.getAttribute("data-node-path");
            const container = document.querySelector(
                `[data-node-children][data-node-children="${CSS.escape(path)}"]`,
            );
            if (!container) {
                return;
            }
            const collapsed = new Set(getCollapsed());
            if (container.classList.contains("hidden")) {
                container.classList.remove("hidden");
                collapsed.delete(path);
                toggle.textContent = "▾";
            } else {
                container.classList.add("hidden");
                collapsed.add(path);
                toggle.textContent = "▸";
            }
            setCollapsed(Array.from(collapsed));
        });
    });

    applyCollapsedState();

    const panelKey = "restTool.panelState";
    function getPanelState() {
        try {
            return JSON.parse(localStorage.getItem(panelKey) || "{}");
        } catch (e) {
            return {};
        }
    }

    function setPanelState(state) {
        localStorage.setItem(panelKey, JSON.stringify(state));
    }

    const panelState = getPanelState();
    document.querySelectorAll("[data-panel-toggle]").forEach((toggle) => {
        const panelId = toggle.getAttribute("data-panel-toggle");
        const panelBody = document.querySelector(
            `[data-panel-body="${CSS.escape(panelId)}"]`,
        );
        if (panelBody && panelState[panelId] === "collapsed") {
            panelBody.classList.add("hidden");
        }

        toggle.addEventListener("click", () => {
            if (!panelBody) {
                return;
            }
            const collapsed = panelBody.classList.toggle("hidden");
            panelState[panelId] = collapsed ? "collapsed" : "open";
            setPanelState(panelState);
            if (!collapsed) {
                requestAnimationFrame(() => {
                    resizeFormattedXml();
                });
            }
        });
    });

    const urlInput = document.getElementById("url");
    const postUrlInput = document.getElementById("post_url");
    const attachmentPostUrlInput = document.getElementById(
        "attachment_post_url",
    );
    const attachmentsInput = document.getElementById("attachments");
    const lastUrlKey = "restTool.lastUrl";
    if (urlInput) {
        if (!urlInput.value && localStorage.getItem(lastUrlKey)) {
            urlInput.value = localStorage.getItem(lastUrlKey);
        }
        urlInput.addEventListener("input", () => {
            localStorage.setItem(lastUrlKey, urlInput.value);
        });
    }
    if (postUrlInput) {
        if (!postUrlInput.value && localStorage.getItem(lastUrlKey)) {
            postUrlInput.value = localStorage.getItem(lastUrlKey);
        }
        postUrlInput.addEventListener("input", () => {
            localStorage.setItem(lastUrlKey, postUrlInput.value);
            if (generatedXmlArea && attachmentsInput?.files?.length) {
                refreshGeneratedXmlFromAuto();
            }
        });
    }
    if (attachmentPostUrlInput) {
        if (!attachmentPostUrlInput.value && localStorage.getItem(lastUrlKey)) {
            attachmentPostUrlInput.value = localStorage.getItem(lastUrlKey);
        }

        const normalizeAttachmentPostUrl = () => {
            if (!attachmentPostUrlInput.value) {
                return;
            }
            if (!/\/attachments\/?$/i.test(attachmentPostUrlInput.value)) {
                attachmentPostUrlInput.value =
                    attachmentPostUrlInput.value.replace(/\/+$/, "") +
                    "/attachments";
                localStorage.setItem(lastUrlKey, attachmentPostUrlInput.value);
                if (generatedXmlArea && attachmentsInput?.files?.length) {
                    refreshGeneratedXmlFromAuto();
                }
            }
        };

        normalizeAttachmentPostUrl();

        attachmentPostUrlInput.addEventListener("input", () => {
            localStorage.setItem(lastUrlKey, attachmentPostUrlInput.value);
            if (generatedXmlArea && attachmentsInput?.files?.length) {
                refreshGeneratedXmlFromAuto();
            }
        });

        attachmentPostUrlInput.addEventListener("blur", () => {
            normalizeAttachmentPostUrl();
        });
    }

    if (attachmentsInput) {
        attachmentsInput.addEventListener("change", () => {
            if (!generatedXmlArea) {
                return;
            }
            refreshGeneratedXmlFromAuto();
        });
    }

    [
        "attachment_storage_id",
        "attachment_author",
        "attachment_description",
        "attachment_comment",
    ].forEach((id) => {
        const input = document.getElementById(id);
        if (!input) {
            return;
        }
        input.addEventListener("input", () => {
            if (attachmentsInput?.files?.length && generatedXmlArea) {
                refreshGeneratedXmlFromAuto();
            }
        });
    });

    function resizeFormattedXml() {
        if (!formattedXml) {
            return;
        }
        formattedXml.style.height = "auto";
        formattedXml.style.height = `${formattedXml.scrollHeight}px`;
    }
    resizeFormattedXml();

    function resizeAutoTextareas() {
        document
            .querySelectorAll("textarea[data-autoresize]")
            .forEach((area) => {
                area.style.height = "auto";
                area.style.height = `${area.scrollHeight}px`;
            });
    }
    resizeAutoTextareas();

    function applyColumnLimits(table) {
        const limit = parseInt(table.dataset.columnsLimit || "0", 10);
        if (!limit) {
            return;
        }
        table.querySelectorAll("[data-col-index]").forEach((cell) => {
            const index = parseInt(
                cell.getAttribute("data-col-index") || "0",
                10,
            );
            if (index >= limit) {
                cell.classList.add("hidden");
            }
        });
    }

    document.querySelectorAll("table[data-columns-limit]").forEach((table) => {
        applyColumnLimits(table);
    });

    document.querySelectorAll("[data-columns-toggle]").forEach((toggle) => {
        toggle.addEventListener("click", () => {
            const tableId = toggle.getAttribute("data-columns-toggle");
            if (!tableId) {
                return;
            }
            const table = document.getElementById(tableId);
            if (!table) {
                return;
            }
            const isExpanded = toggle.getAttribute("data-expanded") === "true";
            table.querySelectorAll("[data-col-index]").forEach((cell) => {
                if (isExpanded) {
                    applyColumnLimits(table);
                    table.classList.remove("columns-expanded");
                } else {
                    cell.classList.remove("hidden");
                }
            });
            if (!isExpanded) {
                table.classList.add("columns-expanded");
            }
            toggle.textContent = isExpanded
                ? "Show all columns"
                : "Show fewer columns";
            toggle.setAttribute("data-expanded", isExpanded ? "false" : "true");
        });
    });

    function parsePathSegments(path) {
        return path
            .split("/")
            .filter(Boolean)
            .map((segment) => {
                const match = segment.match(/(.+)\[(\d+)\]/);
                return match
                    ? { name: match[1], index: parseInt(match[2], 10) }
                    : null;
            })
            .filter(Boolean);
    }

    function getNthChild(parent, name, index) {
        let count = 0;
        for (const child of parent.childNodes) {
            if (child.nodeType === 1 && child.nodeName === name) {
                count += 1;
                if (count === index) {
                    return child;
                }
            }
        }
        return null;
    }

    function ensureChild(minParent, origParent, segment) {
        const existing = Array.from(minParent.childNodes).filter(
            (child) => child.nodeType === 1 && child.nodeName === segment.name,
        );

        if (existing.length >= segment.index) {
            return existing[segment.index - 1];
        }

        for (let i = existing.length + 1; i <= segment.index; i += 1) {
            const origChild = getNthChild(origParent, segment.name, i);
            const ns = origChild ? origChild.namespaceURI : null;
            const newChild = ns
                ? minParent.ownerDocument.createElementNS(ns, segment.name)
                : minParent.ownerDocument.createElement(segment.name);

            if (origChild && origChild.attributes) {
                Array.from(origChild.attributes).forEach((attr) => {
                    newChild.setAttribute(attr.name, attr.value);
                });
            }

            minParent.appendChild(newChild);
        }

        const updated = Array.from(minParent.childNodes).filter(
            (child) => child.nodeType === 1 && child.nodeName === segment.name,
        );

        return updated[segment.index - 1];
    }

    function getElementChildren(node) {
        return Array.from(node.childNodes || []).filter(
            (child) => child.nodeType === 1,
        );
    }

    function getChildrenByName(node, name) {
        return getElementChildren(node).filter(
            (child) => child.nodeName === name,
        );
    }

    function cloneElementTree(origNode, doc) {
        const ns = origNode.namespaceURI;
        const clone = ns
            ? doc.createElementNS(ns, origNode.nodeName)
            : doc.createElement(origNode.nodeName);

        if (origNode.attributes) {
            Array.from(origNode.attributes).forEach((attr) => {
                clone.setAttribute(attr.name, attr.value);
            });
        }

        const elementChildren = getElementChildren(origNode);
        if (elementChildren.length === 0) {
            clone.textContent = origNode.textContent ?? "";
            return clone;
        }

        elementChildren.forEach((child) => {
            clone.appendChild(cloneElementTree(child, doc));
        });

        return clone;
    }

    function ensureBuveSectionOrder(entityNode) {
        if (!entityNode) {
            return;
        }

        const objNode = getChildrenByName(entityNode, "DmPNSObjMBL")[0] || null;
        const buveP2Node =
            getChildrenByName(entityNode, "dmPNSBuveP2BL")[0] ||
            getChildrenByName(entityNode, "DmPNSBuveP2BL")[0] ||
            null;

        if (!objNode || !buveP2Node || buveP2Node.parentNode !== entityNode) {
            return;
        }

        const nextSibling = objNode.nextSibling;
        if (nextSibling === buveP2Node) {
            return;
        }

        entityNode.removeChild(buveP2Node);
        if (nextSibling) {
            entityNode.insertBefore(buveP2Node, nextSibling);
            return;
        }

        entityNode.appendChild(buveP2Node);
    }

    function trimRowFields(tblNode) {
        if (!tblNode) {
            return;
        }
        const allowed = ["RN", "NPK", "RN_VEIDS", "PK_NOM", "DAUDZ"];
        getChildrenByName(tblNode, "row").forEach((row) => {
            getElementChildren(row).forEach((child) => {
                if (!allowed.includes(child.nodeName)) {
                    row.removeChild(child);
                }
            });
        });
    }

    function resolveAttachmentsPath(postUrl) {
        if (!postUrl) {
            return "";
        }
        let pathname = "";
        try {
            const parsed = new URL(postUrl);
            pathname = parsed.pathname || "";
        } catch (e) {
            pathname = postUrl;
        }
        const idx = pathname.indexOf("/attachments");
        if (idx === -1) {
            const normalized = pathname.replace(/\/+$/, "");
            if (/\/rest\/[^/]+\/[0-9]+$/.test(normalized)) {
                return `${normalized}/attachments`;
            }
            return "";
        }
        return pathname.slice(0, idx + "/attachments".length);
    }

    function formatIsoWithOffset(date) {
        const pad = (value) => String(value).padStart(2, "0");
        const year = date.getFullYear();
        const month = pad(date.getMonth() + 1);
        const day = pad(date.getDate());
        const hours = pad(date.getHours());
        const minutes = pad(date.getMinutes());
        const seconds = pad(date.getSeconds());
        const millis = String(date.getMilliseconds()).padStart(3, "0");
        const offset = -date.getTimezoneOffset();
        const sign = offset >= 0 ? "+" : "-";
        const offsetHours = pad(Math.floor(Math.abs(offset) / 60));
        const offsetMinutes = pad(Math.abs(offset) % 60);
        return `${year}-${month}-${day}T${hours}:${minutes}:${seconds}.${millis}${sign}${offsetHours}:${offsetMinutes}`;
    }

    function buildAttachmentsXmlTemplate(postUrl) {
        const attachmentsPath = resolveAttachmentsPath(postUrl);
        if (!attachmentsPath) {
            return "";
        }
        const files = attachmentsInput ? attachmentsInput.files || [] : [];
        if (!files.length) {
            return "";
        }
        const file = files[0];
        const storageIdInput = document.getElementById("attachment_storage_id");
        const authorInput = document.getElementById("attachment_author");
        const descriptionInput = document.getElementById(
            "attachment_description",
        );
        const commentInput = document.getElementById("attachment_comment");
        const storageId = storageIdInput ? storageIdInput.value.trim() : "";
        const author = authorInput ? authorInput.value.trim() : "";
        const description = descriptionInput
            ? descriptionInput.value.trim()
            : "";
        const comment = commentInput ? commentInput.value.trim() : "";
        const now = new Date();
        const created = formatIsoWithOffset(now);
        const fileAddTime = now.toISOString().slice(0, 10);

        return `<?xml version="1.0" encoding="UTF-8"?>
<resource>
    <description>Pievienotais fails</description>
    <entity xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
        <PK_STORAGE>
            <href>/rest/TdmStorageBL/${storageId || "3"}</href>
        </PK_STORAGE>
        <FILENAME>${file.name}</FILENAME>
        <AUTHOR>${author}</AUTHOR>
        <CREATED>${created}</CREATED>
        <KOMENTARS>${comment}</KOMENTARS>
        <PK_REPNOM/>
        <PK_RPDGRP/>
        <APRAKSTS>${description}</APRAKSTS>
        <FILE_ADD_TIME>${fileAddTime}</FILE_ADD_TIME>
        <CSADCREATED/>
        <DIGITSIGN/>
        <SKSTATUSS/>
        <IS_SASK/>
        <IS_CURRENT/>
    </entity>
</resource>`;
    }

    function buildMinimalXmlString() {
        if (!originalXml) {
            return "";
        }

        const parser = new DOMParser();
        const originalDoc = parser.parseFromString(
            originalXml,
            "application/xml",
        );
        if (originalDoc.querySelector("parsererror")) {
            return "";
        }

        const root = originalDoc.documentElement;
        if (!root) {
            return "";
        }
        const minimalDoc = document.implementation.createDocument(
            root.namespaceURI,
            root.nodeName,
            null,
        );
        const minimalRoot = minimalDoc.documentElement;
        Array.from(root.attributes || []).forEach((attr) => {
            minimalRoot.setAttribute(attr.name, attr.value);
        });

        const entityNode =
            root.nodeName === "entity"
                ? root
                : getChildrenByName(root, "entity")[0] || null;
        let minimalEntity = null;
        if (entityNode) {
            if (root.nodeName === "entity") {
                minimalEntity = minimalRoot;
                Array.from(entityNode.attributes || []).forEach((attr) => {
                    minimalEntity.setAttribute(attr.name, attr.value);
                });
            } else {
                minimalEntity = cloneElementTree(entityNode, minimalDoc);
                minimalRoot.appendChild(minimalEntity);
            }

            const requiredNames = [
                "PK_DOKT",
                "PK_DOK",
                "PK_OBJ",
                "COUNTER",
                "PK_ESPATS",
                "PK_KLIENTS",
                "ADRESE",
                "DOK_NR",
                "dmPNSBuveP2BL",
                "DmPNSBuveP2BL",
                "PIEZIMES",
                "tblRindas",
            ];

            const requiredDmPnsFields = [
                "KADTER",
                "KADGRUPA",
                "ZEMENR",
                "ZDBUVENR",
                "TGNR",
                "APZIMKOP",
                "PILNADRESE",
                "PK_ADR2",
                "PK_ADR",
            ];

            const requiredDmPnsBuveP2Fields = [
                "PK_BUVEGRP",
                "GADS",
                "INBUVE",
                "EFEKTIV",
                "ATSAVIN",
                "PK_BSERIJA",
            ];

            getElementChildren(minimalEntity).forEach((child) => {
                if (!requiredNames.includes(child.nodeName)) {
                    minimalEntity.removeChild(child);
                }
            });

            requiredNames.forEach((name) => {
                const existing = getChildrenByName(minimalEntity, name);
                if (existing.length > 0) {
                    return;
                }
                const originals = getChildrenByName(entityNode, name);
                originals.forEach((origChild) => {
                    minimalEntity.appendChild(
                        cloneElementTree(origChild, minimalDoc),
                    );
                });
            });

            const originalObjNode =
                getChildrenByName(entityNode, "DmPNSObjMBL")[0] || null;
            let minimalObjNode =
                getChildrenByName(minimalEntity, "DmPNSObjMBL")[0] || null;

            if (!minimalObjNode && originalObjNode) {
                minimalObjNode = cloneElementTree(originalObjNode, minimalDoc);
                minimalEntity.appendChild(minimalObjNode);
            }

            if (minimalObjNode && originalObjNode) {
                getElementChildren(minimalObjNode).forEach((child) => {
                    if (!requiredDmPnsFields.includes(child.nodeName)) {
                        minimalObjNode.removeChild(child);
                    }
                });

                requiredDmPnsFields.forEach((name) => {
                    const existing = getChildrenByName(minimalObjNode, name);
                    if (existing.length > 0) {
                        return;
                    }
                    const originals = getChildrenByName(originalObjNode, name);
                    originals.forEach((origChild) => {
                        minimalObjNode.appendChild(
                            cloneElementTree(origChild, minimalDoc),
                        );
                    });
                });
            }

            const originalBuveP2Node =
                getChildrenByName(entityNode, "dmPNSBuveP2BL")[0] ||
                getChildrenByName(entityNode, "DmPNSBuveP2BL")[0] ||
                null;
            let minimalBuveP2Node =
                getChildrenByName(minimalEntity, "dmPNSBuveP2BL")[0] ||
                getChildrenByName(minimalEntity, "DmPNSBuveP2BL")[0] ||
                null;

            if (!minimalBuveP2Node && originalBuveP2Node) {
                minimalBuveP2Node = cloneElementTree(originalBuveP2Node, minimalDoc);
                minimalEntity.appendChild(minimalBuveP2Node);
            }

            if (minimalBuveP2Node && originalBuveP2Node) {
                getElementChildren(minimalBuveP2Node).forEach((child) => {
                    if (!requiredDmPnsBuveP2Fields.includes(child.nodeName)) {
                        minimalBuveP2Node.removeChild(child);
                    }
                });

                requiredDmPnsBuveP2Fields.forEach((name) => {
                    const existing = getChildrenByName(minimalBuveP2Node, name);
                    if (existing.length > 0) {
                        return;
                    }
                    const originals = getChildrenByName(originalBuveP2Node, name);
                    originals.forEach((origChild) => {
                        minimalBuveP2Node.appendChild(
                            cloneElementTree(origChild, minimalDoc),
                        );
                    });
                });
            }

            ensureBuveSectionOrder(minimalEntity);

            getChildrenByName(minimalEntity, "tblRindas").forEach((tbl) => {
                trimRowFields(tbl);
            });
        }

        Object.keys(changeMap).forEach((path) => {
            const segments = parsePathSegments(path);
            if (segments.length === 0) {
                return;
            }
            if (segments[0].name !== root.nodeName) {
                return;
            }

            let origNode = root;
            let minNode = minimalRoot;
            for (let i = 1; i < segments.length; i += 1) {
                const segment = segments[i];
                const nextOrig = getNthChild(
                    origNode,
                    segment.name,
                    segment.index,
                );
                if (!nextOrig) {
                    return;
                }
                const nextMin = ensureChild(minNode, origNode, segment);
                origNode = nextOrig;
                minNode = nextMin;
            }

            minNode.textContent = changeMap[path].value;
        });

        if (minimalEntity) {
            ensureBuveSectionOrder(minimalEntity);
        }

        const serializer = new XMLSerializer();
        return serializer.serializeToString(minimalDoc);
    }

    function buildGeneratedXmlPayload() {
        const urlValue = postUrlInput?.value?.trim()
            ? postUrlInput.value.trim()
            : attachmentPostUrlInput?.value?.trim() || "";
        const attachmentsXml = buildAttachmentsXmlTemplate(urlValue);
        const payload = attachmentsXml || buildMinimalXmlString();
        return payload ? formatXmlString(payload) : "";
    }

    function generateMinimalXml() {
        if (!generatedXmlArea) {
            return;
        }
        refreshGeneratedXmlFromAuto(true);
    }

    const generateButton = document.getElementById("generate-minimal-xml");
    if (generateButton) {
        generateButton.addEventListener("click", generateMinimalXml);
    }
})();
