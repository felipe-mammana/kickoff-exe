(function () {
    let terminationBusy = false;
    let terminationTrigger = null;
    function closeTermination() {
        const dialog = document.querySelector('.session-termination-modal');
        if (dialog) { dialog.close(); dialog.remove(); }
        document.body.classList.remove('termination-modal-open');
        terminationTrigger?.focus();
    }
    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-termination-close]')) {
            event.preventDefault();
            if (!terminationBusy) closeTermination();
        }
    });
    document.addEventListener('submit', async function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.action.includes('route=sessions.terminate')) return;
        event.preventDefault();
        if (terminationBusy) return;
        const currentDialog = document.querySelector('.session-termination-modal');
        if (!currentDialog) terminationTrigger = event.submitter;
        terminationBusy = true;
        const button = event.submitter;
        const data = new FormData(form);
        if (button) button.disabled = true;
        try {
            const response = await fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin', cache: 'no-store' });
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const next = page.querySelector('.session-termination-modal');
            if (!next) {
                // Preserve server-rendered flash messages on the returned sessions page.
                const panel = page.querySelector('.sessions-panel');
                if (panel && document.querySelector('.sessions-panel')) {
                    closeTermination();
                    document.querySelector('.sessions-panel').replaceWith(panel);
                    page.querySelectorAll('.alert').forEach(function (alert) { panel.prepend(alert); });
                } else window.location.assign(response.url);
                return;
            }
            if (currentDialog) { currentDialog.close(); currentDialog.remove(); }
            next.removeAttribute('open');
            document.body.append(next);
            document.body.classList.add('termination-modal-open');
            next.addEventListener('cancel', function (event) { event.preventDefault(); if (!terminationBusy) closeTermination(); });
            next.showModal();
            next.querySelector('input[name="admin_password"]')?.focus();
        } catch (error) {
            let message = form.querySelector('[data-termination-error]');
            if (!message) { message = document.createElement('p'); message.dataset.terminationError = ''; message.setAttribute('role', 'alert'); form.append(message); }
            message.textContent = 'Não foi possível confirmar a operação. Atualize a lista antes de tentar novamente.';
        } finally {
            terminationBusy = false;
            if (button) button.disabled = false;
        }
    });
    const vaultRoute = new URLSearchParams(location.search).get('route') || '';
    if (vaultRoute.startsWith('vault.') || vaultRoute === 'settings.vault') {
        const lockAt = Date.now() + 1200000;
        function expireVault() {
            if (Date.now() >= lockAt) location.replace('/?route=vault.index');
        }
        setTimeout(expireVault, 1200100);
        document.addEventListener('visibilitychange', expireVault);
        window.addEventListener('pageshow', function (event) { if (event.persisted) location.reload(); });
    }
    document.querySelectorAll('[data-password-requirements]').forEach(function (list) {
        const input = list.parentElement.querySelector('input[name="password"]');
        if (!input) return;
        function updateRequirements() {
            const value = input.value;
            const rules = {
                upper: /\p{Lu}/u.test(value),
                lower: /\p{Ll}/u.test(value),
                number: /[0-9]/.test(value),
                special: /[^\p{L}\p{N}\s]/u.test(value),
                length: Array.from(value).length >= 8,
            };
            list.querySelectorAll('[data-password-rule]').forEach(function (item) {
                const satisfied = rules[item.dataset.passwordRule];
                item.classList.toggle('is-satisfied', satisfied);
                item.querySelector('.password-requirement-symbol').textContent = satisfied ? '\u2713' : '\u2715';
                item.querySelector('.password-requirement-status').textContent = satisfied ? 'Atendido' : 'Pendente';
            });
            input.setCustomValidity(new TextEncoder().encode(value).length > 72 ? 'A senha deve ter no máximo 72 bytes.' : '');
        }
        input.addEventListener('input', updateRequirements);
        input.addEventListener('change', updateRequirements);
        input.form?.addEventListener('reset', function () { setTimeout(updateRequirements, 0); });
        updateRequirements();
    });
    const root = document.documentElement;
    const themeToggles = document.querySelectorAll('[data-theme-toggle]');
    let storedTheme = 'light';
    try {
        storedTheme = localStorage.getItem('theme') === 'dark' ? 'dark' : 'light';
    } catch (error) {
        storedTheme = 'light';
    }

    function setTheme(theme) {
        root.setAttribute('data-theme', theme);
        try {
            localStorage.setItem('theme', theme);
        } catch (error) {
            return;
        }
    }

    setTheme(storedTheme);

    themeToggles.forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            setTheme(root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
        });
    });

    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    const sidebarMedia = window.matchMedia('(max-width: 1080px)');

    function setSidebarOpen(open) {
        document.body.classList.toggle('sidebar-open', open);
        sidebarToggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
        sidebarToggle?.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
    }

    sidebarToggle?.addEventListener('click', function () {
        if (window.matchMedia('(max-width: 1080px)').matches) {
            setSidebarOpen(!document.body.classList.contains('sidebar-open'));
            return;
        }

        document.body.classList.toggle('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', document.body.classList.contains('sidebar-collapsed') ? '1' : '0');
    });

    try {
        const storedSidebar = localStorage.getItem('sidebarCollapsed');
        if (storedSidebar !== null && !window.matchMedia('(max-width: 1080px)').matches) {
            document.body.classList.toggle('sidebar-collapsed', storedSidebar === '1');
        }
    } catch (error) {
        // Mantem o padrao vindo do servidor quando o navegador bloqueia localStorage.
    }

    document.querySelectorAll('[data-sidebar] a').forEach(function (link) {
        link.addEventListener('click', function () {
            setSidebarOpen(false);
        });
    });

    document.querySelectorAll('[data-sidebar-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            setSidebarOpen(false);
        });
    });

    document.querySelectorAll('[data-settings-topic-toggle]').forEach(function (toggle) {
        const panel = toggle.closest('[data-settings-topic]');
        const body = panel?.querySelector('[data-settings-topic-body]');
        const label = toggle.querySelector('span');

        if (!body) {
            return;
        }

        function setTopicOpen(open) {
            body.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (label) {
                label.textContent = open ? 'Fechar configurações' : 'Abrir configurações';
            }
        }

        toggle.addEventListener('click', function () {
            setTopicOpen(body.hidden);
        });

        setTopicOpen(false);
    });

    const vaultCategoryList = document.querySelector('[data-vault-category-list]');
    const vaultCategoryTemplate = document.querySelector('[data-vault-category-template]');
    const vaultSubcategoryTemplate = document.querySelector('[data-vault-subcategory-template]');
    const vaultCustomFieldTemplate = document.querySelector('[data-vault-custom-field-template]');
    let vaultDynamicIndex = Date.now();

    function updateVaultIconPreview(select) {
        const preview = select.closest('[data-vault-default-category], [data-vault-default-subcategory]')?.querySelector('.vault-default-icon-preview');
        const markup = select.selectedOptions?.[0]?.dataset.iconMarkup || '';
        if (preview && markup) {
            preview.innerHTML = markup;
        }
    }

    function bindVaultDefaultCategoryControls(scope) {
        scope.querySelectorAll('[data-vault-icon-select]').forEach(function (select) {
            updateVaultIconPreview(select);
            select.addEventListener('change', function () {
                updateVaultIconPreview(select);
            });
        });

        scope.querySelectorAll('[data-vault-add-subcategory]').forEach(function (button) {
            button.addEventListener('click', function () {
                const category = button.closest('[data-vault-default-category]');
                const list = category?.querySelector('[data-vault-subcategory-list]');
                const categoryInput = category?.querySelector('input[name$="[name]"]');
                const categoryMatch = categoryInput?.name.match(/vault_categories\[([^\]]+)\]/);
                if (!list || !vaultSubcategoryTemplate || !categoryMatch) {
                    return;
                }

                const categoryIndex = categoryMatch[1];
                const childIndex = 'new_' + vaultDynamicIndex++;
                const html = vaultSubcategoryTemplate.innerHTML
                    .replaceAll('__CATEGORY__', categoryIndex)
                    .replaceAll('__CHILD__', childIndex);
                list.insertAdjacentHTML('beforeend', html);
                const added = list.lastElementChild;
                if (added) {
                    bindVaultDefaultCategoryControls(added);
                    added.querySelector('input')?.focus();
                }
            });
        });

        scope.querySelectorAll('[data-vault-add-custom-field]').forEach(function (button) {
            if (button.dataset.bound === 'true') {
                return;
            }
            button.dataset.bound = 'true';
            button.addEventListener('click', function () {
                const fieldBlock = button.closest('[data-vault-custom-field-list]');
                const list = fieldBlock?.querySelector('.vault-default-field-list');
                const owner = button.closest('[data-vault-default-subcategory], [data-vault-default-category]');
                const ownerNameInput = owner?.querySelector(':scope > .vault-default-subcategory-main input[name$="[name]"], :scope > .vault-default-category-main input[name$="[name]"]');
                const ownerName = ownerNameInput?.name || '';
                const baseName = ownerName.replace(/\[name\]$/, '');
                if (!list || !vaultCustomFieldTemplate || !baseName) {
                    return;
                }

                const fieldIndex = 'new_' + vaultDynamicIndex++;
                const html = vaultCustomFieldTemplate.innerHTML
                    .replaceAll('__BASE__', baseName)
                    .replaceAll('__FIELD__', fieldIndex);
                list.insertAdjacentHTML('beforeend', html);
                const added = list.lastElementChild;
                if (added) {
                    bindVaultDefaultCategoryControls(added);
                    added.querySelector('input[type="text"]')?.focus();
                }
            });
        });

        scope.querySelectorAll('[data-vault-remove-category]').forEach(function (button) {
            button.addEventListener('click', function () {
                button.closest('[data-vault-default-category]')?.remove();
            });
        });

        scope.querySelectorAll('[data-vault-remove-subcategory]').forEach(function (button) {
            button.addEventListener('click', function () {
                button.closest('[data-vault-default-subcategory]')?.remove();
            });
        });

        scope.querySelectorAll('[data-vault-remove-custom-field]').forEach(function (button) {
            if (button.dataset.bound === 'true') {
                return;
            }
            button.dataset.bound = 'true';
            button.addEventListener('click', function () {
                button.closest('[data-vault-custom-field]')?.remove();
            });
        });
    }

    document.querySelector('[data-vault-add-category]')?.addEventListener('click', function () {
        if (!vaultCategoryList || !vaultCategoryTemplate) {
            return;
        }

        const categoryIndex = 'new_' + vaultDynamicIndex++;
        const html = vaultCategoryTemplate.innerHTML.replaceAll('__CATEGORY__', categoryIndex);
        vaultCategoryList.insertAdjacentHTML('beforeend', html);
        const added = vaultCategoryList.lastElementChild;
        if (added) {
            bindVaultDefaultCategoryControls(added);
            added.querySelector('input')?.focus();
        }
    });

    if (vaultCategoryList) {
        bindVaultDefaultCategoryControls(vaultCategoryList);
    }

    document.querySelectorAll('.company-attachment-form').forEach(function (form) {
        const input = form.querySelector('[data-attachment-input]');
        const selected = form.querySelector('[data-attachment-selected]');
        const name = form.querySelector('[data-attachment-name]');
        const clear = form.querySelector('[data-attachment-clear]');

        function syncAttachmentSelection() {
            const file = input?.files?.[0] || null;
            if (!selected || !name) {
                return;
            }

            if (!file) {
                selected.hidden = true;
                name.textContent = 'Arquivo selecionado';
                return;
            }

            selected.hidden = false;
            name.textContent = file.name + ' - ' + formatBytes(file.size);
        }

        input?.addEventListener('change', syncAttachmentSelection);
        clear?.addEventListener('click', function () {
            if (input) {
                input.value = '';
            }
            syncAttachmentSelection();
        });
        syncAttachmentSelection();
    });

    document.querySelectorAll('[data-attachment-form-toggle]').forEach(function (toggle) {
        const panel = toggle.closest('.company-attachments-panel')?.querySelector('[data-attachment-form-panel]');
        if (!panel) {
            return;
        }

        function setAttachmentPanelOpen(open) {
            panel.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            const label = toggle.querySelector('span');
            if (label) {
                label.textContent = open ? 'Fechar envio' : 'Adicionar anexo';
            }
        }

        toggle.addEventListener('click', function () {
            setAttachmentPanelOpen(panel.hidden);
        });

        setAttachmentPanelOpen(false);
    });

    sidebarMedia.addEventListener?.('change', function (event) {
        if (!event.matches) {
            setSidebarOpen(false);
        }
    });

    const deviceForm = document.querySelector('[data-device-form]');
    const deviceType = document.querySelector('[data-device-type]');
    const deviceTypeCards = document.querySelectorAll('[data-device-type-card]');
    const printerConnection = document.querySelector('[data-printer-connection]');
    const tagMaskWrap = document.querySelector('[data-tag-mask-wrap]');
    const tagMask = document.querySelector('[data-company-tag-code]');
    const tagFullInput = document.querySelector('[data-tag-full]');
    const tagPrefix = document.querySelector('[data-tag-prefix]');
    const tagNumber = document.querySelector('[data-tag-number]');
    const tagFreeInput = document.querySelector('[data-tag-free]');
    const tagLabel = document.querySelector('[data-tag-label]');
    const deviceRequiredFields = {
        notebook: ['tag', 'old_hostname', 'new_hostname', 'employee_name', 'department', 'machine_password'],
        cpu: ['tag', 'old_hostname', 'new_hostname', 'employee_name', 'department', 'machine_password'],
        roteador: ['tag', 'computer_model', 'admin_user', 'admin_password', 'ip_address'],
        access_point: ['install_location', 'tag', 'computer_model'],
        modem: ['tag', 'computer_model', 'admin_user', 'admin_password', 'carrier'],
        impressora: ['tag', 'brand', 'computer_model', 'printer_connection_type'],
        outros: ['tag', 'computer_model'],
    };
    const tagPrefixes = {
        notebook: 'N',
        cpu: 'C',
        roteador: 'R',
        impressora: 'I',
        modem: 'LINK',
        access_point: null,
        outros: null,
    };
    const draftKey = deviceForm ? 'machine-form-draft:' + location.pathname + location.search + ':' + (deviceForm.getAttribute('action') || '') : null;
    const photoDraftKey = draftKey ? draftKey + ':photos' : null;

    function formatBytes(bytes) {
        const value = Number(bytes) || 0;
        if (value >= 1024 * 1024) {
            return (value / (1024 * 1024)).toFixed(value >= 10 * 1024 * 1024 ? 0 : 1).replace('.', ',') + ' MB';
        }
        if (value >= 1024) {
            return Math.round(value / 1024) + ' KB';
        }

        return value + ' B';
    }

    function currentTagPrefix() {
        const selected = deviceType?.value || 'notebook';
        const companyCode = (tagMask?.getAttribute('data-company-tag-code') || 'EMP').toUpperCase();
        const typePrefix = tagPrefixes[selected];

        if (typePrefix === undefined || typePrefix === null) {
            return null;
        }

        return typePrefix === 'LINK' ? 'LINK' : typePrefix + companyCode;
    }

    function syncTagMask(options) {
        if (!tagFullInput) {
            return;
        }

        const nextPrefix = currentTagPrefix();

        if (nextPrefix === null) {
            if (tagMaskWrap) {
                tagMaskWrap.hidden = true;
            }
            if (tagNumber) {
                tagNumber.disabled = true;
            }
            if (tagFreeInput) {
                tagFreeInput.hidden = false;
                tagFreeInput.disabled = false;
                if (!tagFreeInput.value && tagFullInput.value) {
                    tagFreeInput.value = tagFullInput.value;
                }
                tagFullInput.value = tagFreeInput.value;
            }
            if (tagLabel) {
                tagLabel.textContent = 'Etiqueta';
            }
        } else {
            if (tagFreeInput) {
                tagFreeInput.hidden = true;
                tagFreeInput.disabled = true;
            }
            if (tagMaskWrap) {
                tagMaskWrap.hidden = false;
            }
            if (tagNumber) {
                tagNumber.disabled = false;
            }
            if (tagLabel) {
                tagLabel.textContent = 'Número da etiqueta';
            }

            const previousPrefix = tagPrefix?.textContent || nextPrefix;
            let number = tagNumber ? tagNumber.value.replace(/\D/g, '') : '';

            if (!number && tagFullInput.value) {
                const currentValue = tagFullInput.value.toUpperCase();
                if (currentValue.startsWith(nextPrefix)) {
                    number = currentValue.slice(nextPrefix.length).replace(/\D/g, '');
                } else if (currentValue.startsWith(previousPrefix)) {
                    number = currentValue.slice(previousPrefix.length).replace(/\D/g, '');
                } else {
                    number = currentValue.replace(/\D/g, '');
                }
            }

            if (tagPrefix) {
                tagPrefix.textContent = nextPrefix;
            }
            if (tagNumber) {
                tagNumber.value = number;
            }
            tagFullInput.value = number ? nextPrefix + number : '';
        }

        if (!options?.silent) {
            saveFormDraft();
        }
    }

    function syncDeviceSections() {
        if (!deviceForm || !deviceType) {
            return;
        }

        const selected = deviceType.value;
        deviceTypeCards.forEach(function (card) {
            card.classList.toggle('active', card.getAttribute('data-device-type-card') === selected);
        });

        deviceForm.querySelectorAll('[data-device-section]').forEach(function (section) {
            const allowed = (section.getAttribute('data-device-section') || '').split(/\s+/);
            const visible = allowed.includes(selected);

            section.hidden = !visible;
            section.querySelectorAll('input, select, textarea').forEach(function (field) {
                if (field === deviceType || field.name === 'csrf_token' || field.name === 'company_id') {
                    return;
                }

                field.disabled = !visible;
            });
        });

        syncPrinterFields();
        syncTagMask({ silent: true });
    }

    function formFields() {
        if (!deviceForm) {
            return [];
        }

        return Array.from(deviceForm.querySelectorAll('input, select, textarea')).filter(function (field) {
            return field.name && !field.disabled && field.type !== 'file' && field.name !== 'csrf_token';
        });
    }

    function saveFormDraft() {
        if (!draftKey) {
            return;
        }

        const draft = {};
        formFields().forEach(function (field) {
            draft[field.name] = field.type === 'checkbox' ? field.checked : field.value;
        });

        try {
            localStorage.setItem(draftKey, JSON.stringify(draft));
        } catch (error) {
            return;
        }
    }

    function restoreFormDraft() {
        if (!draftKey) {
            return;
        }

        let draft = null;
        try {
            draft = JSON.parse(localStorage.getItem(draftKey) || 'null');
        } catch (error) {
            draft = null;
        }

        if (!draft) {
            return;
        }

        if (deviceType && draft.device_type) {
            deviceType.value = draft.device_type;
            syncDeviceSections();
        }

        formFields().forEach(function (field) {
            if (!Object.prototype.hasOwnProperty.call(draft, field.name)) {
                return;
            }

            if (field.type === 'checkbox') {
                field.checked = Boolean(draft[field.name]);
                return;
            }

            if (!field.value) {
                field.value = draft[field.name] || '';
            }
        });
    }

    function clearClientErrors() {
        deviceForm?.querySelectorAll('[data-client-error]').forEach(function (error) {
            error.remove();
        });
        deviceForm?.querySelectorAll('.has-error').forEach(function (field) {
            field.classList.remove('has-error');
        });
    }

    function markClientError(field, message) {
        const wrapper = field.closest('.field') || field.closest('label');
        if (!wrapper) {
            return;
        }

        wrapper.classList.add('has-error');
        const error = document.createElement('small');
        error.dataset.clientError = 'true';
        error.textContent = message;
        wrapper.appendChild(error);
    }

    function validateDeviceForm() {
        if (!deviceForm || !deviceType) {
            return true;
        }

        clearClientErrors();
        const required = deviceRequiredFields[deviceType.value] || deviceRequiredFields.notebook;
        let firstInvalid = null;

        required.forEach(function (name) {
            let field = deviceForm.querySelector('[name="' + name + '"]:not(:disabled)');
            if (name === 'tag') {
                const prefix = currentTagPrefix();
                if (prefix !== null) {
                    field = tagNumber;
                } else if (tagFreeInput && !tagFreeInput.hidden) {
                    field = tagFreeInput;
                }
            }
            if (!field || String(field.value || '').trim() !== '') {
                return;
            }

            firstInvalid = firstInvalid || field;
            markClientError(field, name === 'tag' && currentTagPrefix() !== null ? 'Informe o número da etiqueta.' : 'Campo obrigatório.');
        });

        if (firstInvalid) {
            firstInvalid.focus();
            return false;
        }

        return true;
    }

    function syncPrinterFields() {
        const selected = printerConnection?.value || '';
        const isPrinter = deviceType?.value === 'impressora';

        document.querySelectorAll('[data-printer-network]').forEach(function (network) {
            const visible = isPrinter && selected === 'rede';
            network.hidden = !visible;
            network.querySelectorAll('input').forEach(function (field) {
                field.disabled = !visible;
            });
        });

        document.querySelectorAll('[data-printer-usb]').forEach(function (usb) {
            const visible = isPrinter && selected === 'usb';
            usb.hidden = !visible;
            usb.querySelectorAll('input').forEach(function (field) {
                field.disabled = !visible;
            });
        });
    }

    deviceType?.addEventListener('change', syncDeviceSections);
    tagNumber?.addEventListener('input', function () {
        tagNumber.value = tagNumber.value.replace(/\D/g, '');
        syncTagMask();
    });
    tagFreeInput?.addEventListener('input', function () {
        if (tagFullInput) {
            tagFullInput.value = tagFreeInput.value;
        }
        saveFormDraft();
    });
    deviceTypeCards.forEach(function (card) {
        card.addEventListener('click', function () {
            if (!deviceType) {
                return;
            }

            deviceType.value = card.getAttribute('data-device-type-card') || deviceType.value;
            deviceType.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
    printerConnection?.addEventListener('change', syncPrinterFields);
    restoreFormDraft();
    syncDeviceSections();
    deviceForm?.addEventListener('input', saveFormDraft);
    deviceForm?.addEventListener('change', saveFormDraft);

    deviceForm?.addEventListener('submit', function (event) {
        saveFormDraft();
        if (!validateDeviceForm()) {
            event.preventDefault();
        }
    });

    document.querySelectorAll('[data-password-toggle]').forEach(function (toggle) {
        toggle.addEventListener('click', function (event) {
            const wrapper = event.currentTarget.closest('.password-wrap');
            const input = wrapper?.querySelector('[data-password-input]');
            if (!input) {
                return;
            }

            const shouldShow = input.type === 'password';
            input.type = shouldShow ? 'text' : 'password';

            const nextLabel = shouldShow ? 'Ocultar senha' : 'Mostrar senha';
            event.currentTarget.setAttribute('aria-label', nextLabel);
            event.currentTarget.setAttribute('title', nextLabel);

            if (!event.currentTarget.querySelector('.icon')) {
                event.currentTarget.textContent = shouldShow ? 'Ocultar' : 'Ver';
            }
        });
    });

    document.querySelector('[data-password-toggle-legacy]')?.addEventListener('click', function (event) {
        const input = document.querySelector('[data-password-input]');
        if (!input) {
            return;
        }

        const shouldShow = input.type === 'password';
        input.type = shouldShow ? 'text' : 'password';
        event.currentTarget.textContent = shouldShow ? 'Ocultar' : 'Ver';
    });

    const photoInputs = document.querySelectorAll('[data-photo-input]');
    const input = document.querySelector('[data-photo-primary]') || photoInputs[0];
    const preview = document.querySelector('[data-photo-preview]');
    const emptyPreview = document.querySelector('[data-photo-empty]');
    let selectedPhotos = [];
    const photoTopics = [
        ['equipamento', 'Equipamento'],
        ['local', 'Local'],
        ['ambiente', 'Ambiente'],
        ['outras', 'Outras'],
    ];

    if (input && preview) {
        function openDraftDb() {
            return new Promise(function (resolve, reject) {
                if (!('indexedDB' in window)) {
                    reject(new Error('IndexedDB indisponivel.'));
                    return;
                }

                const request = indexedDB.open('exeKickoffDrafts', 1);
                request.onupgradeneeded = function () {
                    request.result.createObjectStore('photoDrafts', { keyPath: 'key' });
                };
                request.onsuccess = function () {
                    resolve(request.result);
                };
                request.onerror = function () {
                    reject(request.error);
                };
            });
        }

        function savePhotoDraft() {
            if (!photoDraftKey) {
                return;
            }

            openDraftDb().then(function (db) {
                const transaction = db.transaction('photoDrafts', 'readwrite');
                transaction.objectStore('photoDrafts').put({
                    key: photoDraftKey,
                    photos: selectedPhotos,
                });
                transaction.oncomplete = function () {
                    db.close();
                };
            }).catch(function () {
                return;
            });
        }

        function restorePhotoDraft() {
            if (!photoDraftKey) {
                return;
            }

            openDraftDb().then(function (db) {
                const transaction = db.transaction('photoDrafts', 'readonly');
                const request = transaction.objectStore('photoDrafts').get(photoDraftKey);

                request.onsuccess = function () {
                    const photos = request.result?.photos || [];
                    selectedPhotos = photos.filter(function (photo) {
                        return photo.file instanceof File && photo.file.type.startsWith('image/');
                    }).map(function (photo) {
                        return {
                            file: photo.file,
                            topic: photo.topic || 'equipamento',
                        };
                    });
                    syncInputFiles();
                    renderPreview();
                };
                transaction.oncomplete = function () {
                    db.close();
                };
            }).catch(function () {
                return;
            });
        }

        photoInputs.forEach(function (photoInput) {
            photoInput.addEventListener('change', function () {
                Array.from(photoInput.files || []).forEach(function (file) {
                    if (file.type.startsWith('image/')) {
                        selectedPhotos.push({
                            file: file,
                            topic: 'equipamento',
                        });
                    }
                });
                syncInputFiles();
                renderPreview();
                savePhotoDraft();
            });
        });

        function renderPreview() {
            preview.innerHTML = '';
            if (emptyPreview) {
                emptyPreview.hidden = selectedPhotos.length > 0;
            }

            selectedPhotos.forEach(function (photo, index) {
                const file = photo.file;
                if (!file.type.startsWith('image/')) {
                    return;
                }

                const card = document.createElement('figure');
                const img = document.createElement('img');
                const caption = document.createElement('figcaption');
                const meta = document.createElement('div');
                const name = document.createElement('span');
                const topic = document.createElement('select');
                const remove = document.createElement('button');

                img.src = URL.createObjectURL(file);
                img.alt = file.name;
                img.onload = function () {
                    URL.revokeObjectURL(img.src);
                };

                name.textContent = file.name;
                topic.name = 'photos_topic[]';
                topic.setAttribute('aria-label', 'Tópico da foto ' + file.name);
                photoTopics.forEach(function (option) {
                    const item = document.createElement('option');
                    item.value = option[0];
                    item.textContent = option[1];
                    item.selected = photo.topic === option[0];
                    topic.appendChild(item);
                });
                topic.addEventListener('change', function () {
                    selectedPhotos[index].topic = topic.value;
                    savePhotoDraft();
                });

                remove.type = 'button';
                remove.textContent = 'Remover';
                remove.className = 'link-danger';
                remove.addEventListener('click', function () {
                    removeFile(index);
                });

                meta.className = 'preview-meta';
                meta.append(name, topic);
                caption.append(meta, remove);
                card.append(img, caption);
                preview.appendChild(card);
            });
        }

        function removeFile(removeIndex) {
            selectedPhotos = selectedPhotos.filter(function (_photo, index) {
                return index !== removeIndex;
            });
            syncInputFiles();
            renderPreview();
            savePhotoDraft();
        }

        function syncInputFiles() {
            if (typeof DataTransfer === 'undefined') {
                return;
            }

            const dataTransfer = new DataTransfer();
            selectedPhotos.forEach(function (photo) {
                dataTransfer.items.add(photo.file);
            });
            input.files = dataTransfer.files;
            photoInputs.forEach(function (photoInput) {
                if (photoInput !== input) {
                    photoInput.value = '';
                }
            });
        }

        restorePhotoDraft();
    }

    const companySearch = document.querySelector('[data-company-search]');
    const companyStatus = document.querySelector('[data-company-status]');
    const companyRows = document.querySelectorAll('[data-company-row]');

    function filterCompanies() {
        const query = (companySearch?.value || '').trim().toLowerCase();
        const status = companyStatus?.value || '';

        companyRows.forEach(function (row) {
            const rowName = row.getAttribute('data-company-name') || '';
            const rowStatus = row.getAttribute('data-company-status') || '';
            const matchesName = !query || rowName.includes(query);
            const matchesStatus = !status || rowStatus === status;

            row.hidden = !(matchesName && matchesStatus);
        });
    }

    companySearch?.addEventListener('input', filterCompanies);
    companyStatus?.addEventListener('change', filterCompanies);

    document.querySelectorAll('[data-submit-on-change]').forEach(function (field) {
        field.addEventListener('change', function () {
            field.form?.submit();
        });
    });

    document.querySelectorAll('[data-row-href]').forEach(function (row) {
        function shouldIgnoreRowNavigation(target) {
            return Boolean(target.closest('a, button, input, select, textarea, label, [data-gallery-open]'));
        }

        row.addEventListener('click', function (event) {
            if (shouldIgnoreRowNavigation(event.target)) {
                return;
            }

            const href = row.getAttribute('data-row-href');
            if (href) {
                window.location.href = href;
            }
        });

        row.addEventListener('keydown', function (event) {
            if (!['Enter', ' '].includes(event.key) || shouldIgnoreRowNavigation(event.target)) {
                return;
            }

            const href = row.getAttribute('data-row-href');
            if (href) {
                event.preventDefault();
                window.location.href = href;
            }
        });
    });

    const galleryModal = document.querySelector('[data-gallery-modal]');
    const galleryTitle = document.querySelector('[data-gallery-title]');
    const companyModal = document.querySelector('[data-company-modal]');
    const companyModalFocus = document.querySelector('[data-company-modal-focus]');
    const userModals = document.querySelectorAll('[data-user-modal]');
    const userModalFocus = document.querySelector('[data-user-modal-focus]');
    const vaultModals = document.querySelectorAll('[data-vault-modal]');
    const confirmModal = document.querySelector('[data-confirm-modal]');
    const confirmMessage = confirmModal?.querySelector('[data-confirm-message]');
    const confirmSubmit = confirmModal?.querySelector('[data-confirm-submit]');
    const confirmIcon = confirmModal?.querySelector('[data-confirm-icon]');
    let pendingConfirmForm = null;

    if (galleryModal && galleryModal.parentElement !== document.body) {
        document.body.appendChild(galleryModal);
    }

    if (companyModal && companyModal.parentElement !== document.body) {
        document.body.appendChild(companyModal);
    }

    userModals.forEach(function (modal) {
        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
        if (!modal.hidden) {
            document.body.classList.add('modal-open');
        }
    });

    vaultModals.forEach(function (modal) {
        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
        if (!modal.hidden) {
            document.body.classList.add('modal-open');
        }
    });


    function closeGalleryModal() {
        if (galleryModal) {
            galleryModal.hidden = true;
        }
        if ((!companyModal || companyModal.hidden) && !hasOpenUserModal() && !hasOpenVaultModal() && (!confirmModal || confirmModal.hidden) && (!lightbox || lightbox.hidden)) {
            document.body.classList.remove('modal-open');
        }
    }

    function closeCompanyModal() {
        if (companyModal) {
            companyModal.hidden = true;
        }
        if ((!galleryModal || galleryModal.hidden) && !hasOpenUserModal() && !hasOpenVaultModal() && (!confirmModal || confirmModal.hidden) && (!lightbox || lightbox.hidden)) {
            document.body.classList.remove('modal-open');
        }
    }

    function hasOpenUserModal() {
        return Array.from(userModals).some(function (modal) {
            return !modal.hidden;
        });
    }

    function hasOpenVaultModal() {
        return Array.from(vaultModals).some(function (modal) {
            return !modal.hidden;
        });
    }

    function closeUserModals() {
        userModals.forEach(function (modal) {
            modal.hidden = true;
        });

        if ((!galleryModal || galleryModal.hidden) && (!companyModal || companyModal.hidden) && !hasOpenVaultModal() && (!confirmModal || confirmModal.hidden) && (!lightbox || lightbox.hidden)) {
            document.body.classList.remove('modal-open');
        }
    }

    function closeVaultModals() {
        vaultModals.forEach(function (modal) {
            modal.hidden = true;
        });

        if ((!galleryModal || galleryModal.hidden) && (!companyModal || companyModal.hidden) && !hasOpenUserModal() && (!confirmModal || confirmModal.hidden) && (!lightbox || lightbox.hidden)) {
            document.body.classList.remove('modal-open');
        }
    }

    function closeConfirmModal() {
        pendingConfirmForm = null;
        if (confirmModal) {
            confirmModal.hidden = true;
        }
        if (confirmSubmit) {
            confirmSubmit.disabled = false;
        }
        if ((!galleryModal || galleryModal.hidden) && (!companyModal || companyModal.hidden) && !hasOpenUserModal() && !hasOpenVaultModal() && (!lightbox || lightbox.hidden)) {
            document.body.classList.remove('modal-open');
        }
    }

    function setConfirmVariant(variant) {
        if (!confirmSubmit) {
            return;
        }

        const svg = function (body) {
            return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + body + '</svg>';
        };

        confirmSubmit.classList.remove('btn-primary', 'btn-warning', 'btn-danger');
        if (variant === 'primary') {
            confirmSubmit.classList.add('btn-primary');
            if (confirmIcon) {
                confirmIcon.innerHTML = svg('<path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"></path><path d="m9 11 3 3L22 4"></path>');
            }
            return;
        }

        if (variant === 'warning') {
            confirmSubmit.classList.add('btn-warning');
            if (confirmIcon) {
                confirmIcon.innerHTML = svg('<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path>');
            }
            return;
        }

        confirmSubmit.classList.add('btn-danger');
        if (confirmIcon) {
            confirmIcon.innerHTML = svg('<path d="M3 6h18"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path>');
        }
    }

    function openVaultModal(name) {
        const modal = document.querySelector('[data-vault-modal="' + name + '"]');
        if (!modal) {
            return null;
        }

        vaultModals.forEach(function (item) {
            item.hidden = item !== modal;
        });
        modal.hidden = false;
        document.body.classList.add('modal-open');

        return modal;
    }

    function openUserModal(name) {
        const modal = document.querySelector('[data-user-modal="' + name + '"]');
        if (!modal) {
            return null;
        }

        userModals.forEach(function (item) {
            item.hidden = item !== modal;
        });
        modal.hidden = false;
        document.body.classList.add('modal-open');

        return modal;
    }

    function syncRoleOptionCards(scope) {
        scope.querySelectorAll('.role-option-card').forEach(function (card) {
            const input = card.querySelector('input[type="radio"]');
            card.classList.toggle('is-selected', !!input?.checked);
        });
    }

    function parseJson(value, fallback) {
        try {
            return JSON.parse(value || '');
        } catch (error) {
            return fallback;
        }
    }

    const vaultCustomFieldConfig = parseJson(document.querySelector('[data-vault-custom-field-config]')?.textContent || '{}', {});

    function vaultCustomValuesFrom(container, fallback) {
        return parseJson(container?.getAttribute('data-vault-custom-values') || '', fallback || {});
    }

    function renderVaultCustomFields(container, categoryId, values) {
        if (!container) {
            return;
        }

        const existingErrors = Array.from(container.querySelectorAll('small'));
        container.replaceChildren();
        existingErrors.forEach(function (error) {
            container.appendChild(error);
        });

        const fields = vaultCustomFieldConfig[String(categoryId || '')] || [];
        if (!fields.length) {
            container.hidden = true;
            return;
        }

        container.hidden = false;

        const head = document.createElement('div');
        head.className = 'vault-custom-fields-head';
        const title = document.createElement('strong');
        title.textContent = 'Campos da categoria';
        const description = document.createElement('span');
        description.textContent = 'Preencha as informações específicas deste tipo de credencial.';
        head.append(title, description);
        container.appendChild(head);

        const grid = document.createElement('div');
        grid.className = 'vault-custom-fields-grid';

        fields.forEach(function (field) {
            const key = String(field.key || '');
            if (!key) {
                return;
            }

            const label = document.createElement('label');
            label.className = 'field';
            const labelText = document.createElement('span');
            labelText.textContent = String(field.label || 'Campo') + (field.required ? ' *' : '');

            let input;
            if (field.type === 'textarea') {
                input = document.createElement('textarea');
                input.rows = 3;
            } else {
                input = document.createElement('input');
                input.type = field.type === 'number' ? 'text' : (field.type || 'text');
                if (field.type === 'number') {
                    input.inputMode = 'decimal';
                }
            }

            input.name = 'custom_fields[' + key + ']';
            input.value = values && Object.prototype.hasOwnProperty.call(values, key) ? values[key] : '';
            input.required = Boolean(field.required);
            input.maxLength = field.type === 'textarea' ? 5000 : 255;
            label.append(labelText, input);
            grid.appendChild(label);
        });

        container.appendChild(grid);
    }

    function syncVaultFormCustomFields(form, values) {
        const category = form?.querySelector('[data-vault-create-category], [data-vault-edit-category]');
        const container = form?.querySelector('[data-vault-custom-fields]');
        renderVaultCustomFields(container, category?.value || '', values || vaultCustomValuesFrom(container, {}));
    }

    function syncVaultCategorySearchInput(select) {
        const combobox = select?.closest('[data-vault-category-combobox]');
        const input = combobox?.querySelector('[data-vault-category-search-input]');
        const selected = select?.selectedOptions?.[0];
        if (input && selected) {
            input.value = selected.textContent.trim();
        }
    }

    function initVaultCategoryCombobox(select) {
        const combobox = select.closest('[data-vault-category-combobox]');
        const input = combobox?.querySelector('[data-vault-category-search-input]');
        const optionsBox = combobox?.querySelector('[data-vault-category-options]');

        if (!combobox || !input || !optionsBox) {
            return;
        }

        const options = Array.from(select.options).map(function (option) {
            return {
                value: option.value,
                label: option.textContent.trim()
            };
        });

        function closeOptions() {
            optionsBox.hidden = true;
            input.setAttribute('aria-expanded', 'false');
        }

        function selectOption(value, label) {
            select.value = value;
            input.value = label;
            closeOptions();
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function renderOptions() {
            const query = input.value.trim().toLowerCase();
            const matches = options.filter(function (option) {
                return option.label.toLowerCase().includes(query);
            });

            optionsBox.replaceChildren();
            if (!matches.length) {
                const empty = document.createElement('div');
                empty.className = 'vault-category-option-empty';
                empty.textContent = 'Nenhum tipo encontrado.';
                optionsBox.appendChild(empty);
            } else {
                matches.forEach(function (option) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'vault-category-option';
                    button.textContent = option.label;
                    button.addEventListener('mousedown', function (event) {
                        event.preventDefault();
                    });
                    button.addEventListener('click', function () {
                        selectOption(option.value, option.label);
                    });
                    optionsBox.appendChild(button);
                });
            }

            optionsBox.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }

        input.addEventListener('focus', renderOptions);
        input.addEventListener('input', function () {
            const exact = options.find(function (option) {
                return option.label.toLowerCase() === input.value.trim().toLowerCase();
            });
            select.value = exact ? exact.value : '';
            renderOptions();
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeOptions();
            }
        });
        input.addEventListener('blur', function () {
            setTimeout(function () {
                const selected = select.selectedOptions?.[0];
                if (selected) {
                    input.value = selected.textContent.trim();
                }
                closeOptions();
            }, 120);
        });

        syncVaultCategorySearchInput(select);
    }

    document.querySelectorAll('[data-vault-create-category], [data-vault-edit-category]').forEach(function (select) {
        initVaultCategoryCombobox(select);
        select.addEventListener('change', function () {
            const form = select.closest('form');
            const container = form?.querySelector('[data-vault-custom-fields]');
            if (container) {
                container.setAttribute('data-vault-custom-values', '{}');
            }
            syncVaultFormCustomFields(form, {});
        });
        syncVaultFormCustomFields(select.closest('form'));
    });

    function randomPasswordIndex(max) {
        if (max <= 0) {
            return 0;
        }

        if (window.crypto?.getRandomValues) {
            const values = new Uint32Array(1);
            const limit = Math.floor(0x100000000 / max) * max;
            do {
                window.crypto.getRandomValues(values);
            } while (values[0] >= limit);
            return values[0] % max;
        }

        return Math.floor(Math.random() * max);
    }

    function shufflePasswordCharacters(characters) {
        const shuffled = characters.slice();
        for (let index = shuffled.length - 1; index > 0; index -= 1) {
            const nextIndex = randomPasswordIndex(index + 1);
            const current = shuffled[index];
            shuffled[index] = shuffled[nextIndex];
            shuffled[nextIndex] = current;
        }
        return shuffled;
    }

    function createGeneratedVaultPassword(panel) {
        const groups = {
            lowercase: 'abcdefghijklmnopqrstuvwxyz',
            uppercase: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            numbers: '0123456789',
            symbols: '!@#$%&*_-+=?'
        };
        const selectedGroups = Object.entries(groups).filter(function (entry) {
            return !!panel.querySelector('[data-vault-password-option="' + entry[0] + '"]')?.checked;
        }).map(function (entry) {
            return entry[1];
        });
        const length = Number(panel.querySelector('input[type="radio"]:checked')?.value || 12);

        if (!selectedGroups.length) {
            throw new Error('Selecione pelo menos um tipo de caractere.');
        }

        const pool = selectedGroups.join('');
        const characters = selectedGroups.map(function (group) {
            return group[randomPasswordIndex(group.length)];
        });

        while (characters.length < length) {
            characters.push(pool[randomPasswordIndex(pool.length)]);
        }

        return shufflePasswordCharacters(characters).join('');
    }

    document.querySelectorAll('.vault-password-generator-field').forEach(function (field) {
        const toggle = field.querySelector('[data-vault-password-generator-toggle]');
        const panel = field.querySelector('[data-vault-password-generator]');
        const output = field.querySelector('[data-vault-generated-password]');
        const generate = field.querySelector('[data-vault-password-generate]');
        const message = field.querySelector('[data-vault-password-generator-message]');

        toggle?.addEventListener('click', function () {
            if (!panel) {
                return;
            }

            const shouldOpen = panel.hidden;
            panel.hidden = !shouldOpen;
            toggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
        });

        generate?.addEventListener('click', function () {
            if (!panel || !output) {
                return;
            }

            try {
                output.value = createGeneratedVaultPassword(panel);
                output.type = 'text';
                output.dispatchEvent(new Event('input', { bubbles: true }));
                if (message) {
                    message.hidden = false;
                    message.textContent = 'Senha gerada e preenchida no campo.';
                    message.classList.remove('error');
                }
            } catch (error) {
                if (message) {
                    message.hidden = false;
                    message.textContent = error.message || 'Não foi possível gerar a senha.';
                    message.classList.add('error');
                }
            }
        });
    });

    function resetVaultPasswordGenerators(scope, clearOutput) {
        scope?.querySelectorAll('.vault-password-generator-field').forEach(function (field) {
            const toggle = field.querySelector('[data-vault-password-generator-toggle]');
            const panel = field.querySelector('[data-vault-password-generator]');
            const output = field.querySelector('[data-vault-generated-password]');
            const message = field.querySelector('[data-vault-password-generator-message]');

            if (panel) {
                panel.hidden = true;
            }
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
            if (output) {
                output.type = 'password';
                if (clearOutput) {
                    output.value = '';
                }
            }
            if (message) {
                message.hidden = true;
                message.textContent = '';
                message.classList.remove('error');
            }
        });
    }

    function renderVaultDetailsCustomFields(container, categoryId, values) {
        if (!container) {
            return;
        }

        const list = container.querySelector('[data-vault-details-custom-field-list]');
        if (!list) {
            return;
        }

        list.replaceChildren();
        const definitions = vaultCustomFieldConfig[String(categoryId || '')] || [];
        const labels = {};
        definitions.forEach(function (field) {
            if (field.key) {
                labels[String(field.key)] = String(field.label || field.key);
            }
        });
        const entries = Object.entries(values || {}).filter(function (entry) {
            return String(entry[1] || '').trim() !== '';
        });

        if (!entries.length) {
            container.hidden = true;
            return;
        }

        container.hidden = false;
        entries.forEach(function (entry) {
            const wrapper = document.createElement('div');
            const label = document.createElement('span');
            const value = document.createElement('strong');
            label.textContent = labels[entry[0]] || entry[0].replaceAll('_', ' ');
            value.textContent = String(entry[1]);
            wrapper.append(label, value);
            list.appendChild(wrapper);
        });
    }

    document.querySelectorAll('[data-company-modal-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!companyModal) {
                return;
            }

            companyModal.hidden = false;
            document.body.classList.add('modal-open');
            setTimeout(function () {
                companyModalFocus?.focus();
            }, 50);
        });
    });

    document.querySelectorAll('[data-company-modal-close]').forEach(function (button) {
        button.addEventListener('click', closeCompanyModal);
    });

    companyModal?.addEventListener('click', function (event) {
        if (event.target === companyModal) {
            closeCompanyModal();
        }
    });

    document.querySelectorAll('[data-user-modal-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            const modalName = button.getAttribute('data-user-modal-open') || 'create';
            const modal = openUserModal(modalName);

            if (!modal) {
                return;
            }

            if (modalName === 'edit') {
                const id = modal.querySelector('[data-user-edit-id]');
                const name = modal.querySelector('[data-user-edit-name]');
                const email = modal.querySelector('[data-user-edit-email]');
                const role = button.getAttribute('data-user-role') || 'viewer';

                if (id) id.value = button.getAttribute('data-user-id') || '';
                if (name) name.value = button.getAttribute('data-user-name') || '';
                if (email) email.value = button.getAttribute('data-user-email') || '';
                modal.querySelectorAll('[data-user-edit-role-option]').forEach(function (option) {
                    option.checked = option.value === role;
                });
                syncRoleOptionCards(modal);
                setTimeout(function () {
                    name?.focus();
                }, 50);
            } else if (modalName === 'password') {
                const id = modal.querySelector('[data-user-password-id]');
                const name = modal.querySelector('[data-user-password-name]');
                const password = modal.querySelector('input[name="password"]');
                const confirmation = modal.querySelector('input[name="password_confirmation"]');

                if (id) id.value = button.getAttribute('data-user-id') || '';
                if (name) name.textContent = button.getAttribute('data-user-name') || '';
                if (password) password.value = '';
                if (confirmation) confirmation.value = '';
                setTimeout(function () {
                    password?.focus();
                }, 50);
            } else {
                setTimeout(function () {
                    userModalFocus?.focus();
                }, 50);
            }
        });
    });

    document.querySelectorAll('[data-user-modal-close]').forEach(function (button) {
        button.addEventListener('click', closeUserModals);
    });

    document.querySelectorAll('[data-email-cooldown]').forEach(function (button) {
        const label = button.querySelector('span');
        const originalText = label?.textContent || button.textContent;
        const expiresAt = Date.now() + Number(button.dataset.emailCooldown || 0) * 1000;
        function updateCooldown() {
            const remaining = Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000));
            button.disabled = remaining > 0;
            if (label) label.textContent = remaining > 0 ? 'Reenviar em ' + remaining + ' s' : originalText;
            if (remaining > 0) window.setTimeout(updateCooldown, 1000);
        }
        updateCooldown();
    });

    document.querySelectorAll('[data-account-confirmation-form]').forEach(function (form, index) {
        const modal = form.querySelector('[data-account-password-modal]');
        const password = form.querySelector('[data-account-password-input]');
        let previousFocus = null;
        if (!modal || !password) return;
        form.id = form.id || 'account-confirmation-form-' + index;
        modal.querySelectorAll('input, button[type="submit"]').forEach(function (control) {
            control.setAttribute('form', form.id);
        });
        // Animated page containers change the containing block of fixed elements.
        document.body.appendChild(modal);

        function closeModal() {
            modal.hidden = true;
            password.value = '';
            password.disabled = true;
            document.body.classList.remove('modal-open');
            previousFocus?.focus();
        }

        form.addEventListener('submit', function (event) {
            const email = form.elements.namedItem('email');
            const vaultProtection = form.elements.namedItem('vault_require_password_reveal');
            const needsPassword = (email && email.value.trim().toLowerCase() !== form.dataset.originalEmail.toLowerCase())
                || (form.dataset.vaultPasswordEnabled === '1' && !vaultProtection.checked);
            if (!needsPassword) {
                if (!form.reportValidity()) event.preventDefault();
                return;
            }
            if (!modal.hidden) {
                if (!form.reportValidity()) event.preventDefault();
                return;
            }
            event.preventDefault();
            if (!form.reportValidity()) return;
            previousFocus = document.activeElement;
            modal.hidden = false;
            password.disabled = false;
            document.body.classList.add('modal-open');
            password.focus();
        });

        modal.querySelectorAll('[data-account-password-close]').forEach(function (button) {
            button.addEventListener('click', closeModal);
        });
        modal.addEventListener('click', function (event) {
            if (event.target === modal) closeModal();
        });
        modal.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeModal();
            } else if (event.key === 'Tab') {
                const focusable = Array.from(modal.querySelectorAll('button:not([disabled]), input:not([disabled])'));
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        });
    });

    function mountTwoFactorModal(form, modal, close) {
        if (!modal) return;
        if (!form.id) form.id = 'two-factor-form-' + document.querySelectorAll('[data-two-factor-mounted]').length;
        modal.querySelectorAll('input, button[type="submit"]').forEach(function (control) {
            control.setAttribute('form', form.id);
        });
        modal.setAttribute('data-two-factor-mounted', '');
        document.body.appendChild(modal);
        modal.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                close();
                form.querySelector('button[type="button"]')?.focus();
            }
            if (event.key === 'Tab') {
                const controls = Array.from(modal.querySelectorAll('button:not(:disabled), input:not(:disabled)')).filter(el => el.type !== 'hidden');
                const first = controls[0];
                const last = controls[controls.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
            }
        });
    }

    document.querySelectorAll('[data-two-factor-disable-form]').forEach(function (form) {
        const modal = form.querySelector('[data-two-factor-disable-modal]');
        const password = form.querySelector('[data-two-factor-disable-password]');

        function closeTwoFactorDisableModal() {
            if (modal) {
                modal.hidden = true;
            }
            if (password) {
                password.value = '';
                password.disabled = true;
            }
            if ((!galleryModal || galleryModal.hidden) && (!companyModal || companyModal.hidden) && !hasOpenUserModal() && !hasOpenVaultModal() && (!confirmModal || confirmModal.hidden) && (!lightbox || lightbox.hidden)) {
                document.body.classList.remove('modal-open');
            }
        }

        form.querySelectorAll('[data-two-factor-disable-open]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (!modal) {
                    return;
                }

                if (!form.reportValidity()) {
                    return;
                }

                if (password) {
                    password.disabled = false;
                }
                modal.hidden = false;
                document.body.classList.add('modal-open');
                setTimeout(function () {
                    password?.focus();
                }, 50);
            });
        });

        form.querySelectorAll('[data-two-factor-disable-close]').forEach(function (button) {
            button.addEventListener('click', closeTwoFactorDisableModal);
        });

        modal?.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeTwoFactorDisableModal();
            }
        });
        mountTwoFactorModal(form, modal, closeTwoFactorDisableModal);
    });

    document.querySelectorAll('[data-two-factor-password-modal-form]').forEach(function (form) {
        const modal = form.querySelector('[data-two-factor-password-modal]');
        const password = form.querySelector('[data-two-factor-password-modal-input]');

        function closeTwoFactorPasswordModal() {
            if (modal) {
                modal.hidden = true;
            }
            if (password) {
                password.value = '';
                password.disabled = true;
            }
            if ((!galleryModal || galleryModal.hidden) && (!companyModal || companyModal.hidden) && !hasOpenUserModal() && !hasOpenVaultModal() && (!confirmModal || confirmModal.hidden) && (!lightbox || lightbox.hidden)) {
                document.body.classList.remove('modal-open');
            }
        }

        form.querySelectorAll('[data-two-factor-password-modal-open]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (!modal) {
                    return;
                }

                if (!form.reportValidity()) {
                    return;
                }

                if (password) {
                    password.disabled = false;
                }
                modal.hidden = false;
                document.body.classList.add('modal-open');
                setTimeout(function () {
                    password?.focus();
                }, 50);
            });
        });

        form.querySelectorAll('[data-two-factor-password-modal-close]').forEach(function (button) {
            button.addEventListener('click', closeTwoFactorPasswordModal);
        });

        modal?.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeTwoFactorPasswordModal();
            }
        });
        mountTwoFactorModal(form, modal, closeTwoFactorPasswordModal);
    });

    document.querySelectorAll('.role-option-card input[type="radio"]').forEach(function (input) {
        input.addEventListener('change', function () {
            const modal = input.closest('[data-user-modal]') || document;
            syncRoleOptionCards(modal);
        });
    });

    userModals.forEach(syncRoleOptionCards);

    userModals.forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeUserModals();
            }
        });
    });

    document.querySelectorAll('[data-vault-modal-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            const modalName = button.getAttribute('data-vault-modal-open') || 'create';
            const modal = openVaultModal(modalName);

            if (!modal) {
                return;
            }

            if (modalName === 'edit') {
                const id = modal.querySelector('[data-vault-edit-id]');
                const title = modal.querySelector('[data-vault-edit-title]');
                const category = modal.querySelector('[data-vault-edit-category]');
                const username = modal.querySelector('[data-vault-edit-username]');
                const serviceUrl = modal.querySelector('[data-vault-edit-service-url]');
                const notes = modal.querySelector('[data-vault-edit-notes]');
                const secret = modal.querySelector('[name="secret_value"]');

                if (id) id.value = button.getAttribute('data-vault-id') || '';
                if (title) title.value = button.getAttribute('data-vault-title') || '';
                if (category) category.value = button.getAttribute('data-vault-category-id') || '';
                syncVaultCategorySearchInput(category);
                if (username) username.value = button.getAttribute('data-vault-username') || '';
                if (serviceUrl) serviceUrl.value = button.getAttribute('data-vault-service-url') || '';
                if (notes) notes.value = button.getAttribute('data-vault-notes') || '';
                if (secret) secret.value = '';
                resetVaultPasswordGenerators(modal, false);
                syncVaultFormCustomFields(modal.querySelector('form'), parseJson(button.getAttribute('data-vault-custom-fields') || '{}', {}));

                setTimeout(function () {
                    title?.focus();
                }, 50);
            } else if (modalName === 'details') {
                const id = button.getAttribute('data-vault-id') || '';
                const title = modal.querySelector('[data-vault-details-title]');
                const category = modal.querySelector('[data-vault-details-category]');
                const categoryIcon = modal.querySelector('[data-vault-details-category-icon]');
                const username = modal.querySelector('[data-vault-details-username]');
                const usernameCopy = modal.querySelector('[data-vault-details-username-copy]');
                const urlLink = modal.querySelector('[data-vault-details-url-link]');
                const urlText = modal.querySelector('[data-vault-details-url-text]');
                const urlCopy = modal.querySelector('[data-vault-details-url-copy]');
                const updated = modal.querySelector('[data-vault-details-updated]');
                const notes = modal.querySelector('[data-vault-details-notes]');
                const output = modal.querySelector('[data-vault-secret-output]');
                const secretToggle = modal.querySelector('[data-vault-details-secret-toggle]');
                const secretCopy = modal.querySelector('[data-vault-details-secret-copy]');
                const customFields = modal.querySelector('[data-vault-details-custom-fields]');
                const credentialTitle = button.getAttribute('data-vault-title') || 'Detalhes da credencial';
                const credentialUsername = button.getAttribute('data-vault-username') || '';
                const credentialUrl = button.getAttribute('data-vault-service-url') || '';
                const credentialCategoryId = button.getAttribute('data-vault-category-id') || '';

                if (title) title.textContent = credentialTitle;
                if (category) category.textContent = button.getAttribute('data-vault-category-name') || 'Sem tipo';
                if (categoryIcon) {
                    categoryIcon.innerHTML = button.closest('tr')?.querySelector('.vault-type-chip .icon')?.outerHTML || '';
                }
                if (username) username.textContent = credentialUsername || '-';
                if (usernameCopy) {
                    usernameCopy.hidden = credentialUsername === '';
                    usernameCopy.setAttribute('data-copy-value', credentialUsername);
                }
                if (urlCopy) {
                    urlCopy.hidden = credentialUrl === '';
                    urlCopy.setAttribute('data-copy-value', credentialUrl);
                }
                if (urlLink && urlText) {
                    const isExternalUrl = /^https?:\/\//i.test(credentialUrl);
                    urlLink.hidden = !isExternalUrl;
                    urlText.hidden = isExternalUrl;
                    if (isExternalUrl) {
                        urlLink.href = credentialUrl;
                        urlLink.textContent = credentialUrl;
                    } else {
                        urlText.textContent = credentialUrl || '-';
                    }
                }
                if (updated) updated.textContent = button.getAttribute('data-vault-updated-at') || '-';
                if (notes) notes.textContent = button.getAttribute('data-vault-notes') || '-';
                renderVaultDetailsCustomFields(customFields, credentialCategoryId, parseJson(button.getAttribute('data-vault-custom-fields') || '{}', {}));
                if (output) {
                    output.value = '';
                    output.type = 'password';
                    delete output.dataset.loaded;
                }
                if (secretToggle) {
                    secretToggle.setAttribute('data-vault-secret-id', id);
                    secretToggle.setAttribute('title', 'Mostrar senha');
                    secretToggle.setAttribute('aria-label', 'Mostrar senha');
                }
                if (secretCopy) secretCopy.setAttribute('data-vault-secret-id', id);
            } else if (modalName === 'category') {
                const parent = modal.querySelector('[data-vault-category-parent]');
                if (parent) parent.value = '';

                const focus = modal.querySelector('[data-vault-modal-focus]');
                setTimeout(function () {
                    focus?.focus();
                }, 50);
            } else if (modalName === 'subcategory') {
                const parent = modal.querySelector('[data-vault-category-parent]');
                const parentName = modal.querySelector('[data-vault-subcategory-parent-name]');
                const parentId = button.getAttribute('data-vault-parent-id') || '';
                const label = button.getAttribute('data-vault-parent-name') || '';
                if (parent) parent.value = parentId;
                if (parentName && label) parentName.textContent = label;

                const focus = modal.querySelector('[data-vault-modal-focus]');
                setTimeout(function () {
                    focus?.focus();
                }, 50);
            } else if (modalName === 'category-info') {
                const name = modal.querySelector('[data-vault-info-name]');
                const description = modal.querySelector('[data-vault-info-description]');
                const count = modal.querySelector('[data-vault-info-count]');
                const icon = modal.querySelector('[data-vault-info-icon]');
                const cardIcon = button.closest('.vault-company-category')?.querySelector('.vault-category-icon');

                if (name) name.textContent = button.getAttribute('data-category-name') || 'Categoria';
                if (description) description.textContent = button.getAttribute('data-category-description') || 'Sem descrição.';
                if (count) count.textContent = (button.getAttribute('data-category-count') || '0') + ' item(ns)';
                if (icon && cardIcon) icon.innerHTML = cardIcon.innerHTML;
            } else if (modalName === 'create') {
                const category = modal.querySelector('[data-vault-create-category]');
                const categoryId = button.getAttribute('data-vault-category-id') || '';
                if (category && categoryId) category.value = categoryId;
                syncVaultCategorySearchInput(category);
                resetVaultPasswordGenerators(modal, false);
                syncVaultFormCustomFields(modal.querySelector('form'), {});

                const focus = modal.querySelector('[data-vault-modal-focus]');
                setTimeout(function () {
                    focus?.focus();
                }, 50);
            } else {
                const focus = modal.querySelector('[data-vault-modal-focus]');
                setTimeout(function () {
                    focus?.focus();
                }, 50);
            }
        });
    });

    document.querySelectorAll('[data-vault-modal-close]').forEach(function (button) {
        button.addEventListener('click', closeVaultModals);
    });

    vaultModals.forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeVaultModals();
            }
        });
    });

    document.querySelectorAll('[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const message = form.getAttribute('data-confirm') || 'Confirmar ação?';
            if (!confirmModal) {
                if (!window.confirm(message)) {
                    event.preventDefault();
                }
                return;
            }

            event.preventDefault();
            pendingConfirmForm = form;
            setConfirmVariant(form.getAttribute('data-confirm-variant') || 'danger');
            if (confirmMessage) {
                confirmMessage.textContent = message;
            }
            confirmModal.hidden = false;
            document.body.classList.add('modal-open');
            setTimeout(function () {
                confirmSubmit?.focus();
            }, 50);
        });
    });

    document.querySelectorAll('[data-confirm-cancel]').forEach(function (button) {
        button.addEventListener('click', closeConfirmModal);
    });

    confirmModal?.addEventListener('click', function (event) {
        if (event.target === confirmModal) {
            closeConfirmModal();
        }
    });

    confirmSubmit?.addEventListener('click', function () {
        const form = pendingConfirmForm;
        pendingConfirmForm = null;
        if (!form) {
            closeConfirmModal();
            return;
        }

        confirmSubmit.disabled = true;
        form.submit();
    });

    function copyText(value, button) {
        if (!value) {
            return;
        }

        const showCopyNotice = function () {
            let notice = document.querySelector('[data-copy-notice]');
            if (!notice) {
                notice = document.createElement('div');
                notice.className = 'copy-notice';
                notice.setAttribute('data-copy-notice', '');
                notice.setAttribute('role', 'status');
                notice.setAttribute('aria-live', 'polite');
                document.body.appendChild(notice);
            }

            notice.textContent = 'Copiado para a área de transferência';
            notice.classList.add('show');
            window.clearTimeout(notice.copyTimer);
            notice.copyTimer = window.setTimeout(function () {
                notice.classList.remove('show');
            }, 1800);
        };

        const originalTitle = button?.getAttribute('title') || 'Copiar';
        const markCopied = function () {
            showCopyNotice();

            if (!button) {
                return;
            }

            button.setAttribute('title', 'Copiado');
            button.setAttribute('aria-label', 'Copiado');
            window.setTimeout(function () {
                button.setAttribute('title', originalTitle);
                button.setAttribute('aria-label', originalTitle);
            }, 1400);
        };

        if (!navigator.clipboard) {
            window.prompt('Copie o valor abaixo:', value);
            showCopyNotice();
            return;
        }

        navigator.clipboard.writeText(value).then(markCopied).catch(function () {
            window.prompt('Copie o valor abaixo:', value);
            showCopyNotice();
        });
    }

    function fetchVaultSecret(cell, id, revealPassword, confirmedReveal) {
        const output = cell?.querySelector('[data-vault-secret-output]');
        if (!cell || !output) {
            return Promise.reject(new Error('Campo de senha inválido.'));
        }
        if (output.dataset.loaded === '1' && !revealPassword) {
            return Promise.resolve(output.value);
        }

        const csrf = cell.querySelector('[data-vault-secret-csrf]')?.value || '';
        if (!id || !csrf) {
            return Promise.reject(new Error('Não foi possível validar a sessão.'));
        }

        const payload = new URLSearchParams();
        payload.set('id', id);
        payload.set('csrf_token', csrf);
        if (revealPassword) {
            payload.set('reveal_password', revealPassword);
        }
        if (confirmedReveal || cell.dataset.vaultRevealConfirmed === '1') {
            payload.set('confirm_reveal', '1');
        }

        return fetch('/?route=vault.reveal', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
            },
            body: payload.toString(),
        }).then(function (response) {
            return response.json().then(function (json) {
                return { ok: response.ok && json.ok, json: json };
            });
        }).then(function (result) {
            if (!result.ok) {
                const error = new Error(result.json?.error?.message || 'Não foi possível revelar a credencial.');
                error.code = result.json?.error?.code || '';
                throw error;
            }

            output.value = result.json.data.value || '';
            if (output.tagName !== 'INPUT' && output.tagName !== 'TEXTAREA') {
                output.textContent = result.json.data.value || '';
            }
            output.dataset.loaded = '1';
            return output.value || output.textContent || '';
        }).catch(function (error) {
            if (error.code !== 'password_required') {
                if (error.code === 'confirmation_required') {
                    if (!window.confirm('Revelar esta senha?')) {
                        throw new Error('Revelação cancelada.');
                    }

                    cell.dataset.vaultRevealConfirmed = '1';
                    return fetchVaultSecret(cell, id, revealPassword, true);
                }

                throw error;
            }

            const password = window.prompt('Confirme sua senha para revelar esta credencial:');
            if (!password) {
                throw new Error('A senha é obrigatória para revelar esta credencial.');
            }

            return fetchVaultSecret(cell, id, password, confirmedReveal);
        });
    }

    document.querySelectorAll('[data-copy-value]').forEach(function (button) {
        button.addEventListener('click', function () {
            copyText(button.getAttribute('data-copy-value') || '', button);
        });
    });

    document.querySelectorAll('[data-vault-secret-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            const cell = button.closest('[data-vault-secret-cell]');
            const output = cell?.querySelector('[data-vault-secret-output]');

            fetchVaultSecret(cell, button.getAttribute('data-vault-secret-id') || '').then(function () {
                const shouldShow = output?.type === 'password';
                if (output) {
                    output.type = shouldShow ? 'text' : 'password';
                }
                button.setAttribute('title', shouldShow ? 'Ocultar senha' : 'Mostrar senha');
                button.setAttribute('aria-label', shouldShow ? 'Ocultar senha' : 'Mostrar senha');
            }).catch(function (error) {
                window.alert(error.message || 'Não foi possível revelar a senha.');
            });
        });
    });

    document.querySelectorAll('[data-vault-secret-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            const cell = button.closest('[data-vault-secret-cell]');

            fetchVaultSecret(cell, button.getAttribute('data-vault-secret-id') || '').then(function (value) {
                copyText(value, button);
            }).catch(function (error) {
                window.alert(error.message || 'Não foi possível copiar a senha.');
            });
        });
    });

    document.querySelectorAll('[data-gallery-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!galleryModal) {
                return;
            }

            const machineId = button.getAttribute('data-gallery-open');
            document.querySelectorAll('[data-gallery-set]').forEach(function (set) {
                set.hidden = set.getAttribute('data-gallery-set') !== machineId;
            });

            if (galleryTitle) {
                const row = button.closest('tr');
                const tag = row?.querySelector('[data-label="Etiqueta"]')?.innerText.trim().split(/\s+/)[0] || '';
                const type = row?.querySelector('[data-label="Tipo"]')?.innerText.trim() || '';
                galleryTitle.textContent = [type, tag].filter(Boolean).join(' - ') || 'Galeria de fotos';
            }

            galleryModal.hidden = false;
            galleryModal.scrollTop = 0;
            const activeGallerySet = galleryModal.querySelector('[data-gallery-set]:not([hidden])');
            if (activeGallerySet) {
                activeGallerySet.scrollTop = 0;
            }
            document.body.classList.add('modal-open');
        });
    });

    document.querySelector('[data-gallery-close]')?.addEventListener('click', closeGalleryModal);

    galleryModal?.addEventListener('click', function (event) {
        if (event.target === galleryModal) {
            closeGalleryModal();
        }
    });

    const auditChangeModal = document.querySelector('[data-audit-change-modal]');
    const credentialModal = document.querySelector('[data-credential-modal]');

    if (auditChangeModal && auditChangeModal.parentElement !== document.body) {
        document.body.appendChild(auditChangeModal);
    }
    if (credentialModal && credentialModal.parentElement !== document.body) {
        document.body.appendChild(credentialModal);
    }

    const auditChangeTitle = auditChangeModal?.querySelector('[data-audit-change-title]');
    const auditChangeDescription = auditChangeModal?.querySelector('[data-audit-change-description]');
    const auditChangeBefore = auditChangeModal?.querySelector('[data-audit-change-before]');
    const auditChangeAfter = auditChangeModal?.querySelector('[data-audit-change-after]');
    const credentialDescription = credentialModal?.querySelector('[data-credential-description]');
    const credentialField = credentialModal?.querySelector('[data-credential-field]');
    const credentialMachineId = credentialModal?.querySelector('[data-credential-machine-id]');
    const credentialCsrf = credentialModal?.querySelector('[data-credential-csrf]');
    const credentialValue = credentialModal?.querySelector('[data-credential-value]');
    const credentialError = credentialModal?.querySelector('[data-credential-error]');
    const credentialConfirm = credentialModal?.querySelector('[data-credential-confirm]');

    function closeAuditChangeModal() {
        if (auditChangeModal) {
            auditChangeModal.hidden = true;
            auditChangeModal.classList.remove('is-open');
        }
        if ((!galleryModal || galleryModal.hidden) && (!companyModal || companyModal.hidden) && !hasOpenUserModal() && !hasOpenVaultModal() && (!confirmModal || confirmModal.hidden) && (!lightbox || lightbox.hidden) && (!credentialModal || credentialModal.hidden)) {
            document.body.classList.remove('modal-open');
        }
    }

    function closeCredentialModal() {
        if (credentialModal) {
            credentialModal.hidden = true;
        }
        if ((!auditChangeModal || auditChangeModal.hidden) && (!galleryModal || galleryModal.hidden) && (!companyModal || companyModal.hidden) && !hasOpenUserModal() && !hasOpenVaultModal() && (!confirmModal || confirmModal.hidden) && (!lightbox || lightbox.hidden)) {
            document.body.classList.remove('modal-open');
        }
    }

    document.querySelectorAll('[data-audit-change-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!auditChangeModal) {
                return;
            }

            if (auditChangeTitle) {
                auditChangeTitle.textContent = button.getAttribute('data-audit-title') || 'Alterações';
            }
            if (auditChangeDescription) {
                auditChangeDescription.textContent = button.getAttribute('data-audit-description') || '';
            }
            if (auditChangeBefore) {
                auditChangeBefore.textContent = button.getAttribute('data-audit-before') || '-';
            }
            if (auditChangeAfter) {
                auditChangeAfter.textContent = button.getAttribute('data-audit-after') || '-';
            }

            auditChangeModal.hidden = false;
            auditChangeModal.classList.add('is-open');
            document.body.classList.add('modal-open');
        });
    });

    document.querySelector('[data-audit-change-close]')?.addEventListener('click', closeAuditChangeModal);

    auditChangeModal?.addEventListener('click', function (event) {
        if (event.target === auditChangeModal) {
            closeAuditChangeModal();
        }
    });

    document.querySelectorAll('[data-credential-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!credentialModal) {
                return;
            }

            const field = button.getAttribute('data-credential-field') || '';
            const label = button.getAttribute('data-credential-label') || 'Credencial';
            const machineId = button.getAttribute('data-machine-id') || credentialMachineId?.value || '';

            if (credentialField) {
                credentialField.value = field;
            }
            if (credentialMachineId) {
                credentialMachineId.value = machineId;
            }
            if (credentialDescription) {
                credentialDescription.textContent = label + ' será revelada e a ação ficará registrada na auditoria.';
            }
            if (credentialValue) {
                credentialValue.hidden = true;
                credentialValue.textContent = '';
            }
            if (credentialError) {
                credentialError.hidden = true;
                credentialError.textContent = '';
            }
            if (credentialConfirm) {
                credentialConfirm.disabled = false;
            }

            credentialModal.hidden = false;
            document.body.classList.add('modal-open');
        });
    });

    credentialConfirm?.addEventListener('click', function () {
        if (!credentialField?.value || !credentialMachineId?.value || !credentialCsrf?.value) {
            return;
        }

        credentialConfirm.disabled = true;
        if (credentialError) {
            credentialError.hidden = true;
            credentialError.textContent = '';
        }

        const payload = new URLSearchParams();
        payload.set('machine_id', credentialMachineId.value);
        payload.set('field', credentialField.value);
        payload.set('csrf_token', credentialCsrf.value);

        fetch('/?route=machines.revealCredential', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
            },
            body: payload.toString(),
        }).then(function (response) {
            return response.json().then(function (json) {
                return { ok: response.ok && json.ok, json: json };
            });
        }).then(function (result) {
            if (!result.ok) {
                throw new Error(result.json?.error?.message || 'Não foi possível revelar a credencial.');
            }

            if (credentialValue) {
                credentialValue.hidden = false;
                credentialValue.textContent = result.json.data.value || '';
            }
        }).catch(function (error) {
            if (credentialError) {
                credentialError.hidden = false;
                credentialError.textContent = error.message || 'Não foi possível revelar a credencial.';
            }
            credentialConfirm.disabled = false;
        });
    });

    document.querySelectorAll('[data-credential-close]').forEach(function (button) {
        button.addEventListener('click', closeCredentialModal);
    });

    credentialModal?.addEventListener('click', function (event) {
        if (event.target === credentialModal) {
            closeCredentialModal();
        }
    });

    const lightbox = document.querySelector('[data-lightbox]');
    const lightboxImg = document.querySelector('[data-lightbox-img]');
    const lightboxClose = document.querySelector('[data-lightbox-close]');
    const lightboxTitle = document.querySelector('[data-lightbox-title]');
    const lightboxMeta = document.querySelector('[data-lightbox-meta]');
    const lightboxDownload = document.querySelector('[data-lightbox-download]');
    const lightboxPrev = document.querySelector('[data-lightbox-prev]');
    const lightboxNext = document.querySelector('[data-lightbox-next]');
    const lightboxTriggers = Array.from(document.querySelectorAll('[data-lightbox-src]'));
    let currentLightboxIndex = 0;

    if (lightbox && lightbox.parentElement !== document.body) {
        document.body.appendChild(lightbox);
    }

    function currentLightboxItems() {
        return lightboxTriggers.filter(function (button) {
            return button.offsetParent !== null;
        });
    }

    function showLightboxItem(index) {
        if (!lightbox || !lightboxImg) {
            return;
        }

        const items = currentLightboxItems();
        if (!items.length) {
            return;
        }

        currentLightboxIndex = ((index % items.length) + items.length) % items.length;
        const button = items[currentLightboxIndex];
        const src = button.getAttribute('data-lightbox-src') || '';
        const alt = button.getAttribute('data-lightbox-alt') || 'Foto do dispositivo';
        const meta = button.getAttribute('data-lightbox-meta') || '';

        lightboxImg.src = src;
        lightboxImg.alt = alt;
        if (lightboxTitle) {
            lightboxTitle.textContent = alt;
        }
        if (lightboxMeta) {
            lightboxMeta.textContent = items.length > 1
                ? (currentLightboxIndex + 1) + ' de ' + items.length + (meta ? ' - ' + meta : '')
                : meta;
        }
        if (lightboxDownload) {
            lightboxDownload.href = src;
        }
        if (lightboxPrev) {
            lightboxPrev.hidden = items.length <= 1;
        }
        if (lightboxNext) {
            lightboxNext.hidden = items.length <= 1;
        }
    }

    lightboxTriggers.forEach(function (button) {
        button.addEventListener('click', function () {
            if (!lightbox || !lightboxImg) {
                return;
            }

            const items = currentLightboxItems();
            showLightboxItem(Math.max(0, items.indexOf(button)));
            lightbox.hidden = false;
            lightbox.scrollTop = 0;
            document.body.classList.add('modal-open');
            setTimeout(function () {
                lightboxClose?.focus();
            }, 50);
        });
    });

    function closeLightbox() {
        if (lightbox) {
            lightbox.hidden = true;
        }
        if (lightboxImg) {
            lightboxImg.src = '';
            lightboxImg.alt = '';
        }
        if ((!galleryModal || galleryModal.hidden) && (!companyModal || companyModal.hidden) && !hasOpenUserModal() && !hasOpenVaultModal() && (!auditChangeModal || auditChangeModal.hidden) && (!credentialModal || credentialModal.hidden) && (!confirmModal || confirmModal.hidden)) {
            document.body.classList.remove('modal-open');
        }
    }

    lightboxClose?.addEventListener('click', closeLightbox);
    lightboxPrev?.addEventListener('click', function () {
        showLightboxItem(currentLightboxIndex - 1);
    });
    lightboxNext?.addEventListener('click', function () {
        showLightboxItem(currentLightboxIndex + 1);
    });

    lightbox?.addEventListener('click', function (event) {
        if (event.target === lightbox) {
            closeLightbox();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            if (document.body.classList.contains('sidebar-open')) {
                setSidebarOpen(false);
                return;
            }

            if (lightbox && !lightbox.hidden) {
                closeLightbox();
                return;
            }

            if (confirmModal && !confirmModal.hidden) {
                closeConfirmModal();
                return;
            }

            if (galleryModal && !galleryModal.hidden) {
                closeGalleryModal();
                return;
            }

            if (companyModal && !companyModal.hidden) {
                closeCompanyModal();
                return;
            }

            if (hasOpenVaultModal()) {
                closeVaultModals();
                return;
            }

            if (auditChangeModal && !auditChangeModal.hidden) {
                closeAuditChangeModal();
                return;
            }

            if (credentialModal && !credentialModal.hidden) {
                closeCredentialModal();
            }
        }

        if (lightbox && !lightbox.hidden && event.key === 'ArrowLeft') {
            event.preventDefault();
            showLightboxItem(currentLightboxIndex - 1);
            return;
        }

        if (lightbox && !lightbox.hidden && event.key === 'ArrowRight') {
            event.preventDefault();
            showLightboxItem(currentLightboxIndex + 1);
        }
    });
})();
