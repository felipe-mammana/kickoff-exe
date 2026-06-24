(function () {
    function getTableData(tableSelector) {
        const table = document.querySelector(tableSelector);
        if (!table) {
            return { headers: [], rows: [] };
        }

        const headers = Array.from(table.querySelectorAll('thead th')).map(function (cell) {
            return cell.textContent.trim();
        });

        const rows = Array.from(table.querySelectorAll('tbody tr'))
            .filter(function (row) {
                return !row.hidden && row.offsetParent !== null;
            })
            .map(function (row) {
                return Array.from(row.querySelectorAll('td')).map(function (cell) {
                    return cell.textContent.replace(/\s+/g, ' ').trim();
                });
            });

        return { headers: headers, rows: rows };
    }

    function downloadFile(content, filename, mimeType) {
        const blob = new Blob([content], { type: mimeType });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');

        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    }

    function exportTableToCSV(tableSelector, filename) {
        const tableData = getTableData(tableSelector);
        const escapeCell = function (value) {
            return '"' + String(value).replace(/"/g, '""').replace(/\r?\n/g, ' ') + '"';
        };
        const lines = [tableData.headers.map(escapeCell).join(';')].concat(
            tableData.rows.map(function (row) {
                return row.map(escapeCell).join(';');
            })
        );

        downloadFile('\uFEFF' + lines.join('\r\n'), filename, 'text/csv;charset=utf-8');
    }

    function exportTableToJSON(tableSelector, filename, metadata) {
        const tableData = getTableData(tableSelector);
        const rows = tableData.rows.map(function (row) {
            return tableData.headers.reduce(function (record, header, index) {
                record[header || ('coluna_' + index)] = row[index] || '';
                return record;
            }, {});
        });

        downloadFile(JSON.stringify(Object.assign({
            exportado_em: new Date().toISOString(),
            total_registros: rows.length,
            dados: rows,
        }, metadata || {}), null, 2), filename, 'application/json;charset=utf-8');
    }

    function toast(message, type) {
        const stack = document.querySelector('[data-toast-stack]') || createToastStack();
        const item = document.createElement('div');
        item.className = 'toast toast-' + (type || 'info');
        item.textContent = message;
        stack.appendChild(item);
        window.setTimeout(function () {
            item.classList.add('is-hiding');
            window.setTimeout(function () {
                item.remove();
            }, 220);
        }, 2800);
    }

    function createToastStack() {
        const stack = document.createElement('div');
        stack.className = 'toast-stack';
        stack.setAttribute('data-toast-stack', '');
        document.body.appendChild(stack);
        return stack;
    }

    function syncExportLinks() {
        const filters = Array.from(document.querySelectorAll('[data-export-filter]')).reduce(function (params, field) {
            if (field.value.trim() !== '') {
                params.set(field.getAttribute('data-export-filter'), field.value.trim());
            }
            return params;
        }, new URLSearchParams());

        document.querySelectorAll('[data-export-base]').forEach(function (link) {
            const url = new URL(link.getAttribute('data-export-base'), window.location.origin);
            filters.forEach(function (value, key) {
                url.searchParams.set(key, value);
            });
            link.href = url.pathname + url.search;
        });
    }

    document.querySelectorAll('[data-export-filter]').forEach(function (field) {
        field.addEventListener('input', syncExportLinks);
        field.addEventListener('change', syncExportLinks);
    });
    syncExportLinks();

    document.querySelectorAll('[data-export-link]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            if (link.classList.contains('disabled') || link.getAttribute('aria-disabled') === 'true') {
                event.preventDefault();
                toast('Nenhum dado disponivel para exportar.', 'warning');
                return;
            }

            const label = link.querySelector('span')?.textContent || 'Exportar';
            link.classList.add('is-loading');
            link.setAttribute('aria-busy', 'true');
            toast('Exportando arquivo...', 'info');
            window.setTimeout(function () {
                link.classList.remove('is-loading');
                link.removeAttribute('aria-busy');
                toast(label + ' concluido com sucesso.', 'success');
            }, 1200);
        });
    });

    window.exportTableToCSV = exportTableToCSV;
    window.exportTableToJSON = exportTableToJSON;
    window.downloadFile = downloadFile;
    window.getTableData = getTableData;
})();
