(function () {
    const root = document.documentElement;
    const themeToggles = document.querySelectorAll('[data-theme-toggle]');
    let storedTheme = 'light';
    try {
        storedTheme = localStorage.getItem('theme') === 'dark' ? 'dark' : 'light';
    } catch (error) {
        storedTheme = 'light';
    }

    window.addEventListener('load', function () {
        document.body.classList.remove('is-loading');
        document.body.classList.add('loader-hidden');
    });

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

    if (localStorage.getItem('sidebarCollapsed') === '1' && !window.matchMedia('(max-width: 1080px)').matches) {
        document.body.classList.add('sidebar-collapsed');
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

    sidebarMedia.addEventListener?.('change', function (event) {
        if (!event.matches) {
            setSidebarOpen(false);
        }
    });

    const deviceForm = document.querySelector('[data-device-form]');
    const deviceType = document.querySelector('[data-device-type]');
    const deviceTypeCards = document.querySelectorAll('[data-device-type-card]');
    const printerConnection = document.querySelector('[data-printer-connection]');

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
    syncDeviceSections();

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

    if (input && preview) {
        let selectedPhotos = [];

        photoInputs.forEach(function (photoInput) {
            photoInput.addEventListener('change', function () {
                Array.from(photoInput.files || []).forEach(function (file) {
                    if (file.type.startsWith('image/')) {
                        selectedPhotos.push(file);
                    }
                });
                syncInputFiles();
                renderPreview();
            });
        });

        function renderPreview() {
            preview.innerHTML = '';
            if (emptyPreview) {
                emptyPreview.hidden = selectedPhotos.length > 0;
            }

            selectedPhotos.forEach(function (file, index) {
                if (!file.type.startsWith('image/')) {
                    return;
                }

                const card = document.createElement('figure');
                const img = document.createElement('img');
                const caption = document.createElement('figcaption');
                const remove = document.createElement('button');

                img.src = URL.createObjectURL(file);
                img.alt = file.name;
                img.onload = function () {
                    URL.revokeObjectURL(img.src);
                };

                remove.type = 'button';
                remove.textContent = 'Remover';
                remove.className = 'link-danger';
                remove.addEventListener('click', function () {
                    removeFile(index);
                });

                caption.append(file.name, remove);
                card.append(img, caption);
                preview.appendChild(card);
            });
        }

        function removeFile(removeIndex) {
            selectedPhotos = selectedPhotos.filter(function (_file, index) {
                return index !== removeIndex;
            });
            syncInputFiles();
            renderPreview();
        }

        function syncInputFiles() {
            if (typeof DataTransfer === 'undefined') {
                return;
            }

            const dataTransfer = new DataTransfer();
            selectedPhotos.forEach(function (file) {
                dataTransfer.items.add(file);
            });
            input.files = dataTransfer.files;
            photoInputs.forEach(function (photoInput) {
                if (photoInput !== input) {
                    photoInput.value = '';
                }
            });
        }
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

    const galleryModal = document.querySelector('[data-gallery-modal]');
    const galleryTitle = document.querySelector('[data-gallery-title]');

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
        });
    });

    document.querySelector('[data-gallery-close]')?.addEventListener('click', function () {
        if (galleryModal) {
            galleryModal.hidden = true;
        }
    });

    galleryModal?.addEventListener('click', function (event) {
        if (event.target === galleryModal) {
            galleryModal.hidden = true;
        }
    });

    const auditChangeModal = document.querySelector('[data-audit-change-modal]');

    if (auditChangeModal && auditChangeModal.parentElement !== document.body) {
        document.body.appendChild(auditChangeModal);
    }

    const auditChangeTitle = auditChangeModal?.querySelector('[data-audit-change-title]');
    const auditChangeDescription = auditChangeModal?.querySelector('[data-audit-change-description]');
    const auditChangeBefore = auditChangeModal?.querySelector('[data-audit-change-before]');
    const auditChangeAfter = auditChangeModal?.querySelector('[data-audit-change-after]');

    function closeAuditChangeModal() {
        if (auditChangeModal) {
            auditChangeModal.hidden = true;
            auditChangeModal.classList.remove('is-open');
        }
        document.body.classList.remove('modal-open');
    }

    document.querySelectorAll('[data-audit-change-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!auditChangeModal) {
                return;
            }

            if (auditChangeTitle) {
                auditChangeTitle.textContent = button.getAttribute('data-audit-title') || 'Alteracoes';
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

    const lightbox = document.querySelector('[data-lightbox]');
    const lightboxImg = document.querySelector('[data-lightbox-img]');
    const lightboxClose = document.querySelector('[data-lightbox-close]');

    document.querySelectorAll('[data-lightbox-src]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!lightbox || !lightboxImg) {
                return;
            }

            lightboxImg.src = button.getAttribute('data-lightbox-src') || '';
            lightboxImg.alt = button.getAttribute('data-lightbox-alt') || '';
            lightbox.hidden = false;
            document.body.classList.add('modal-open');
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
        document.body.classList.remove('modal-open');
    }

    lightboxClose?.addEventListener('click', closeLightbox);

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

            if (galleryModal && !galleryModal.hidden) {
                galleryModal.hidden = true;
                return;
            }

            if (auditChangeModal && !auditChangeModal.hidden) {
                closeAuditChangeModal();
            }
        }
    });
})();
