(function () {
    const modeInputs = document.querySelectorAll('.buve-mode-radio[name="buve_mode"]');
    const dynamicFields = document.getElementById("buve-dynamic-query-fields");
    const predefinedFields = document.getElementById(
        "buve-predefined-filter-fields",
    );

    function normalizeText(value) {
        return String(value || "").replace(/\r\n/g, "\n");
    }

    function syncBuveUpdateFieldsFromLoad() {
        const baseUrl = document.getElementById("buve_base_url")?.value || "";
        const username =
            document.getElementById("buve_auth_username")?.value || "";
        const password =
            document.getElementById("buve_auth_password")?.value || "";
        const remember = document.getElementById("buve_remember_auth")?.checked
            ? "1"
            : "0";

        [
            ["buve_update_base_url", baseUrl],
            ["buve_update_auth_username", username],
            ["buve_update_auth_password", password],
            ["buve_update_remember_auth", remember],
        ].forEach(([id, value]) => {
            const field = document.getElementById(id);
            if (field) {
                field.value = value;
            }
        });
    }

    function mirrorTextInputs(inputIds) {
        const fields = inputIds
            .map((id) => document.getElementById(id))
            .filter(Boolean);
        if (fields.length < 2) {
            return;
        }

        let syncing = false;
        fields.forEach((field) => {
            field.addEventListener("input", () => {
                if (syncing) {
                    return;
                }
                syncing = true;
                fields.forEach((other) => {
                    if (other === field) {
                        return;
                    }
                    other.value = field.value;
                });
                syncing = false;
            });
        });
    }

    function mirrorCheckboxes(inputIds) {
        const fields = inputIds
            .map((id) => document.getElementById(id))
            .filter(Boolean);
        if (fields.length < 2) {
            return;
        }

        let syncing = false;
        fields.forEach((field) => {
            field.addEventListener("change", () => {
                if (syncing) {
                    return;
                }
                syncing = true;
                fields.forEach((other) => {
                    if (other === field) {
                        return;
                    }
                    other.checked = field.checked;
                });
                syncing = false;
            });
        });
    }

    mirrorTextInputs(["url", "post_url", "attachment_post_url"]);
    mirrorTextInputs([
        "auth_username",
        "post_auth_username",
        "attachment_auth_username",
    ]);
    mirrorTextInputs([
        "auth_password",
        "post_auth_password",
        "attachment_auth_password",
    ]);
    mirrorCheckboxes(["remember_auth", "post_remember_auth", "attachment_remember_auth"]);

    [
        "buve_base_url",
        "buve_auth_username",
        "buve_auth_password",
        "buve_remember_auth",
    ].forEach((id) => {
        const field = document.getElementById(id);
        if (!field) {
            return;
        }
        const eventName = field.type === "checkbox" ? "change" : "input";
        field.addEventListener(eventName, syncBuveUpdateFieldsFromLoad);
    });

    const buveUpdateForm = document.getElementById("buve-update-form");
    if (buveUpdateForm) {
        buveUpdateForm.addEventListener("submit", () => {
            syncBuveUpdateFieldsFromLoad();
        });
    }
    syncBuveUpdateFieldsFromLoad();

    function getSelectedMode() {
        const selected = Array.from(modeInputs).find((input) => input.checked);
        return selected ? selected.value : "";
    }

    function syncModeFields() {
        const mode = getSelectedMode();
        if (dynamicFields) {
            dynamicFields.classList.toggle("hidden", mode !== "dynamic");
        }
        if (predefinedFields) {
            predefinedFields.classList.toggle("hidden", mode !== "predefined");
        }
    }

    modeInputs.forEach((input) => {
        input.addEventListener("change", syncModeFields);
    });
    syncModeFields();

    const modifiedCountElement = document.getElementById("buve-modified-count");
    const updateButton = document.getElementById("buve-update-submit");
    const modifiedRows = new Set();
    let rowLinkSyncing = false;

    function getRowInputByField(row, fieldName) {
        return row?.querySelector(
            `.buve-editable-input[data-field="${CSS.escape(fieldName)}"]`,
        );
    }

    function setLinkedValue(row, fieldName, value) {
        const target = getRowInputByField(row, fieldName);
        if (!target) {
            return;
        }
        target.value = value;
    }

    function splitApzimkopByCurrentFields(row, apzimkopValue) {
        const source = String(apzimkopValue || "");
        const currentKadter = getRowInputByField(row, "kadter")?.value || "";
        const currentKadgrupa = getRowInputByField(row, "kadgrupa")?.value || "";
        const currentZemenr = getRowInputByField(row, "zemenr")?.value || "";
        const currentZdbuvenr = getRowInputByField(row, "zdbuvenr")?.value || "";

        // Use current segment lengths as the safest split strategy for APZIMKOP edits.
        let lenKadter = currentKadter.length;
        let lenKadgrupa = currentKadgrupa.length;
        let lenZemenr = currentZemenr.length;
        let lenZdbuvenr = currentZdbuvenr.length;

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

    function refreshLinkedFields(row, sourceField) {
        if (!row || rowLinkSyncing) {
            return;
        }

        rowLinkSyncing = true;

        const kadter = getRowInputByField(row, "kadter")?.value || "";
        const kadgrupa = getRowInputByField(row, "kadgrupa")?.value || "";
        const zemenr = getRowInputByField(row, "zemenr")?.value || "";
        const zdbuvenr = getRowInputByField(row, "zdbuvenr")?.value || "";

        if (
            ["kadter", "kadgrupa", "zemenr", "zdbuvenr"].includes(
                sourceField,
            )
        ) {
            setLinkedValue(
                row,
                "apzimkop",
                `${kadter}${kadgrupa}${zemenr}${zdbuvenr}`,
            );
        } else if (sourceField === "apzimkop") {
            const apzimkop = getRowInputByField(row, "apzimkop")?.value || "";
            const segments = splitApzimkopByCurrentFields(row, apzimkop);

            ["kadter", "kadgrupa", "zemenr", "zdbuvenr"].forEach((fieldName) => {
                const currentValue = getRowInputByField(row, fieldName)?.value || "";
                const nextValue = segments[fieldName] ?? "";
                if (currentValue !== nextValue) {
                    setLinkedValue(row, fieldName, nextValue);
                }
            });
        }

        rowLinkSyncing = false;
    }

    function isRowModified(row) {
        if (!row) {
            return false;
        }

        const rowInputs = row.querySelectorAll(".buve-editable-input");
        for (const input of rowInputs) {
            const original = normalizeText(input.dataset.original || "");
            const current = normalizeText(input.value || "");
            if (current !== original) {
                return true;
            }
        }

        return false;
    }

    function syncModifiedCounters() {
        const modifiedRowsCount = modifiedRows.size;

        if (modifiedCountElement) {
            modifiedCountElement.textContent = String(modifiedRowsCount);
        }

        if (updateButton) {
            updateButton.disabled = modifiedRowsCount === 0;
        }
    }

    function refreshRowModifiedState(row) {
        if (!row) {
            return;
        }

        const rowChanged = isRowModified(row);
        row.classList.toggle("bg-amber-50", rowChanged);
        if (rowChanged) {
            modifiedRows.add(row);
        } else {
            modifiedRows.delete(row);
        }
        syncModifiedCounters();
    }

    function updateModifiedRowsState() {
        modifiedRows.clear();
        document.querySelectorAll("tr[data-buve-row]").forEach((row) => {
            const rowChanged = isRowModified(row);
            row.classList.toggle("bg-amber-50", rowChanged);
            if (rowChanged) {
                modifiedRows.add(row);
            }
        });
        syncModifiedCounters();
    }

    document.addEventListener("input", (event) => {
        const input = event.target?.closest?.(".buve-editable-input");
        if (!input) {
            return;
        }

        const row = input.closest("tr[data-buve-row]");
        const fieldName = input.dataset.field || "";
        refreshLinkedFields(row, fieldName);
        refreshRowModifiedState(row);
    });

    updateModifiedRowsState();

    function bindLoadingState(formId, buttonId, loadingText) {
        const form = document.getElementById(formId);
        const button = document.getElementById(buttonId);
        if (!form || !button) {
            return;
        }

        form.addEventListener("submit", () => {
            if (button.disabled) {
                return;
            }
            button.dataset.originalText = button.textContent || "";
            button.textContent = loadingText;
            button.disabled = true;
        });
    }

    bindLoadingState("buve-load-form", "buve-load-submit", "Loading...");
    bindLoadingState("buve-update-form", "buve-update-submit", "Updating...");
    bindLoadingState("fetch-xml-form", "fetch-xml-submit", "Fetching...");
    bindLoadingState("post-xml-form", "post-xml-submit", "Posting...");
})();
