(function () {
    const changeMap = {};
    const changeTracker = document.getElementById("change-tracker");
    const originalXmlElement = document.getElementById("original-xml");
    const originalXml = originalXmlElement ? originalXmlElement.value : "";
    const logIdElement = document.getElementById("rest-tool-log-id");
    const rawLogId = logIdElement ? logIdElement.value.trim() : "";
    const logId = /^[0-9]+$/.test(rawLogId) ? rawLogId : "";
    const changeKey = logId ? `restTool.changeMap.${logId}` : null;
    const generatedXmlArea = document.getElementById("generated-xml");
    const editor = document.getElementById("selected-node-editor");
    const editorPath = document.getElementById("selected-node-path");
    const editorOriginal = document.getElementById("selected-node-original");
    const editorInput = document.getElementById("selected-node-input");
    const editorApply = document.getElementById("selected-node-apply");
    const editorClose = document.getElementById("close-node-editor");

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

    function applyChangesToInputs() {
        Object.keys(changeMap).forEach((path) => {
            const value = changeMap[path].value ?? "";
            document
                .querySelectorAll(
                    `.xml-edit-input[data-path="${CSS.escape(path)}"]`,
                )
                .forEach((input) => {
                    if ("value" in input) {
                        input.value = value;
                    } else {
                        input.textContent = value;
                    }
                });

            document
                .querySelectorAll(
                    `.node-value[data-node-path="${CSS.escape(path)}"]`,
                )
                .forEach((nodeValue) => {
                    nodeValue.textContent = value;
                    nodeValue.setAttribute("data-node-value", value);
                });
        });
    }

    function loadChangeMap() {
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
            return;
        }

        try {
            const parsed = JSON.parse(stored);
            Object.keys(parsed || {}).forEach((key) => {
                changeMap[key] = parsed[key];
            });
            renderChangeTracker();
            applyChangesToInputs();
        } catch (e) {
            renderChangeTracker();
        }
    }

    function updateChange(path, original, value) {
        if (!path) {
            return;
        }
        if (value === original || value === "") {
            delete changeMap[path];
        } else {
            changeMap[path] = { original, value };
        }
        renderChangeTracker();
        saveChangeMap();
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

            document
                .querySelectorAll(
                    `.xml-edit-input[data-path="${CSS.escape(path)}"]`,
                )
                .forEach((input) => {
                    if ("value" in input) {
                        input.value = value;
                    } else {
                        input.textContent = value;
                    }
                });
        }
    });

    loadChangeMap();

    let currentPath = null;
    let currentOriginal = "";

    function openEditor(path, originalValue, originalFixed) {
        if (!editor || !editorInput || !editorPath || !editorOriginal) {
            return;
        }
        currentPath = path;
        currentOriginal = originalFixed || originalValue || "";
        editorPath.textContent = path;
        editorOriginal.textContent = currentOriginal;
        editorInput.value = changeMap[path]?.value || originalValue || "";
        editor.classList.remove("hidden");
    }

    document.addEventListener("click", (event) => {
        const target = event.target?.closest?.(".node-label, .node-value");
        if (!target) {
            return;
        }
        openEditor(
            target.dataset.nodePath,
            target.dataset.nodeValue,
            target.dataset.nodeOriginal,
        );
    });

    if (editorApply) {
        editorApply.addEventListener("click", () => {
            const path =
                currentPath || (editorPath ? editorPath.textContent : null);
            if (!path) {
                return;
            }
            const original =
                currentOriginal ||
                (editorOriginal ? editorOriginal.textContent : "");
            const value = editorInput ? editorInput.value : "";
            updateChange(path, original, value);

            document
                .querySelectorAll(
                    `.xml-edit-input[data-path="${CSS.escape(path)}"]`,
                )
                .forEach((input) => {
                    if ("value" in input) {
                        input.value = value;
                    } else {
                        input.textContent = value;
                    }
                });

            document
                .querySelectorAll(
                    `.node-value[data-node-path="${CSS.escape(path)}"]`,
                )
                .forEach((nodeValue) => {
                    nodeValue.textContent = value;
                    nodeValue.setAttribute("data-node-value", value);
                });
        });
    }

    if (editorClose) {
        editorClose.addEventListener("click", () => {
            if (editor) {
                editor.classList.add("hidden");
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
        });
    }

    const formattedXml = document.getElementById("formatted-xml");
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

    function generateMinimalXml() {
        if (!originalXml) {
            if (generatedXmlArea) {
                generatedXmlArea.value = "";
            }
            return;
        }

        if (Object.keys(changeMap).length === 0) {
            if (generatedXmlArea) {
                generatedXmlArea.value = "";
            }
            return;
        }

        const parser = new DOMParser();
        const originalDoc = parser.parseFromString(
            originalXml,
            "application/xml",
        );
        if (originalDoc.querySelector("parsererror")) {
            if (generatedXmlArea) {
                generatedXmlArea.value = "";
            }
            return;
        }

        const root = originalDoc.documentElement;
        const minimalDoc = document.implementation.createDocument(
            root.namespaceURI,
            root.nodeName,
            null,
        );
        const minimalRoot = minimalDoc.documentElement;
        Array.from(root.attributes || []).forEach((attr) => {
            minimalRoot.setAttribute(attr.name, attr.value);
        });

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

        const serializer = new XMLSerializer();
        const xmlString = serializer.serializeToString(minimalDoc);
        if (generatedXmlArea) {
            generatedXmlArea.value = xmlString;
        }
    }

    const generateButton = document.getElementById("generate-minimal-xml");
    if (generateButton) {
        generateButton.addEventListener("click", generateMinimalXml);
    }
})();
