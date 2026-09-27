jQuery(document).ready(function($) {
    let rowCounter = parseInt($('#hodima-admin-wrap').attr('data-row-count')) || 0;
    let activeFieldId = null;

    // ---------- Helpers ----------
    function reindexRows() {
        $('#hodima-rows-body tr').each(function(rowIndex) {
            $(this).find('textarea').each(function() {
                $(this).attr('name', 'hodima_table_rows[' + rowIndex + '][]');
            });
        });
        rowCounter = $('#hodima-rows-body tr').length;
    }

    function randomId(prefix) {
        return prefix + '_' + Math.floor(Math.random() * 1000000);
    }

    function parseCSV(text) {
        // Remove BOM
        text = text.replace(/^\uFEFF/, '');

        let rows = [];
        let row = [];
        let cell = '';
        let inQuotes = false;

        for (let i = 0; i < text.length; i++) {
            let char = text[i];
            let next = text[i + 1];

            if (char === '"' && inQuotes && next === '"') {
                cell += '"';
                i++;
            } else if (char === '"') {
                inQuotes = !inQuotes;
            } else if (char === ',' && !inQuotes) {
                row.push(cell);
                cell = '';
            } else if ((char === '\n' || char === '\r') && !inQuotes) {
                if (char === '\r' && next === '\n') i++;
                row.push(cell);
                rows.push(row);
                row = [];
                cell = '';
            } else {
                cell += char;
            }
        }
        if (cell.length || row.length) {
            row.push(cell);
            rows.push(row);
        }

        // trim trailing empty rows
        rows = rows.filter(r => r.some(c => c.trim() !== ''));
        return rows;
    }

    function rebuildTableFromCSV(rows) {
        if (!rows || !rows.length) return;

        const headers = rows[0];
        const bodyRows = rows.slice(1);

        // Build Thead
        let $thead = $('#hodima-headers-row');
        $thead.find('th:not(:first)').remove();

        headers.forEach((h, colIndex) => {
            let headerId = randomId('hodima_header');
            $thead.append(
                '<th>' +
                    '<div class="hodima-col-header-inner">' +
                        '<div class="hodima-col-actions">' +
                            '<span class="hodima-icon-btn hodima-remove-col" data-index="'+colIndex+'" title="حذف ستون">' +
                                '<span class="dashicons dashicons-trash"></span>' +
                            '</span>' +
                        '</div>' +
                        '<input type="text" id="'+headerId+'" name="hodima_table_headers[]" placeholder="عنوان ستون" value="'+$('<div>').text(h).html()+'">' +
                    '</div>' +
                '</th>'
            );
        });

        // Build Tbody
        let $tbody = $('#hodima-rows-body');
        $tbody.empty();

        bodyRows.forEach((row, rowIndex) => {
            let tr = '<tr>' +
                        '<td class="hodima-col-ops">' +
                            '<div class="hodima-row-actions">' +
                                '<span class="dashicons dashicons-menu hodima-drag-handle" title="جابجایی ردیف"></span>' +
                                ' <span class="hodima-icon-btn hodima-remove-row" title="حذف ردیف"><span class="dashicons dashicons-trash"></span></span>' +
                            '</div>' +
                        '</td>';

            for (let c = 0; c < headers.length; c++) {
                let val = (row[c] !== undefined) ? row[c] : '';
                let cellId = randomId('hodima_cell');
                tr += '<td>' +
                        '<textarea id="'+cellId+'" rows="2" name="hodima_table_rows['+rowIndex+'][]" class="hodima-cell-textarea" placeholder="مقدار">'+$('<div>').text(val).html()+'</textarea>' +
                      '</td>';
            }

            tr += '</tr>';
            $tbody.append(tr);
        });

        reindexRows();
        $("#hodima-rows-body").sortable('refresh');
    }

    // ---------- Sortable ----------
    $("#hodima-rows-body").sortable({
        handle: ".hodima-drag-handle",
        placeholder: "ui-state-highlight",
        helper: function(e, tr) {
            var $originals = tr.children();
            var $helper = tr.clone();
            $helper.children().each(function(index) {
                $(this).width($originals.eq(index).width());
            });
            return $helper;
        },
        stop: function() {
            reindexRows();
        }
    });

    // ---------- Active field for wpLink ----------
    $(document).on('focus click', '#hodima-table-builder input[type="text"], #hodima-table-builder textarea', function() {
        activeFieldId = $(this).attr('id');
    });

    $('#hodima-global-link-btn').on('click', function(e) {
        e.preventDefault();
        if (!activeFieldId) {
            alert('لطفاً ابتدا داخل یک سلول یا هدر کلیک کنید تا انتخاب شود، سپس روی این دکمه کلیک کنید.');
            return;
        }
        if (typeof wpLink !== 'undefined') {
            wpLink.open(activeFieldId);
        }
    });

    $(document).on('keydown', '#hodima-table-builder input[type="text"], #hodima-table-builder textarea', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            activeFieldId = $(this).attr('id');
            if (typeof wpLink !== 'undefined') {
                wpLink.open(activeFieldId);
            }
        }
    });

    // ---------- Add Column ----------
    $('#hodima-add-col-btn').on('click', function(e) {
        e.preventDefault();
        let colIndex = $('#hodima-headers-row th').length - 1;
        let headerId = 'hodima_header_new_' + Math.floor(Math.random() * 10000) + '_' + colIndex;

        $('#hodima-headers-row').append(
            '<th>' +
                '<div class="hodima-col-header-inner">' +
                    '<div class="hodima-col-actions">' +
                        '<span class="hodima-icon-btn hodima-remove-col" data-index="'+colIndex+'" title="حذف ستون"><span class="dashicons dashicons-trash"></span></span>' +
                    '</div>' +
                    '<input type="text" id="'+headerId+'" name="hodima_table_headers[]" placeholder="عنوان ستون">' +
                '</div>' +
            '</th>'
        );

        $('#hodima-rows-body tr').each(function() {
            let rowIndex = $(this).index();
            let cellId = 'hodima_cell_new_' + Math.floor(Math.random() * 100000) + '_' + rowIndex + '_' + colIndex;
            $(this).append('<td><textarea id="'+cellId+'" rows="2" name="hodima_table_rows[' + rowIndex + '][]" class="hodima-cell-textarea" placeholder="مقدار"></textarea></td>');
        });

        reindexRows();
    });

    $(document).on('click', '.hodima-remove-col', function() {
        if ($('#hodima-headers-row th').length > 2) {
            let index = $(this).closest('th').index();
            $('#hodima-headers-row th').eq(index).remove();
            $('#hodima-rows-body tr').each(function() {
                $(this).find('td').eq(index).remove();
            });
        } else {
            alert('حداقل یک ستون باید بماند.');
        }
    });

    // ---------- Add Row ----------
    $('#hodima-add-row-btn').on('click', function(e) {
        e.preventDefault();
        let colCount = $('#hodima-headers-row th').length - 1;
        let newRow = '<tr>' +
                        '<td class="hodima-col-ops">' +
                            '<div class="hodima-row-actions">' +
                                '<span class="dashicons dashicons-menu hodima-drag-handle" title="جابجایی ردیف"></span> ' +
                                '<span class="hodima-icon-btn hodima-remove-row" title="حذف ردیف"><span class="dashicons dashicons-trash"></span></span>' +
                            '</div>' +
                        '</td>';

        for (let i = 0; i < colCount; i++) {
            let cellId = 'hodima_cell_new_' + Math.floor(Math.random() * 100000) + '_' + rowCounter + '_' + i;
            newRow += '<td><textarea id="'+cellId+'" rows="2" name="hodima_table_rows[' + rowCounter + '][]" class="hodima-cell-textarea" placeholder="مقدار"></textarea></td>';
        }
        newRow += '</tr>';

        $('#hodima-rows-body').append(newRow);
        rowCounter++;

        reindexRows();
    });

    $(document).on('click', '.hodima-remove-row', function() {
        if ($('#hodima-rows-body tr').length > 1) {
            $(this).closest('tr').remove();
        } else {
            $(this).closest('tr').find('textarea').val('');
        }
        reindexRows();
    });

    // ---------- Export CSV ----------
    // اکسل و لیبره‌آفیس هر سلولی که با = + - @ شروع شود را فرمول اجرا
    // می‌کنند. چون محتوای جدول از ورودی کاربر می‌آید، باید خنثی شود.
    function csvSafe(value) {
        var text = String(value == null ? '' : value);
        if (text && '=+-@\t\r'.indexOf(text.charAt(0)) !== -1) {
            text = "'" + text;
        }
        return '"' + text.replace(/"/g, '""') + '"';
    }

    $('#hodima-export-csv').on('click', function () {

        var csv = [];
        var headers = [];

        $('#hodima-headers-row input').each(function () {
            headers.push(csvSafe($(this).val()));
        });
        csv.push(headers.join(','));

        $('#hodima-rows-body tr').each(function () {
            var row = [];
            $(this).find('textarea').each(function () {
                row.push(csvSafe($(this).val()));
            });
            csv.push(row.join(','));
        });

        // BOM لازم است وگرنه اکسل فایل فارسی را به‌هم‌ریخته باز می‌کند.
        var blob = new Blob(['\uFEFF' + csv.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        var url  = URL.createObjectURL(blob);
        var link = document.createElement('a');

        link.href = url;
        link.download = 'hodima-table-' + new Date().toISOString().slice(0, 10) + '.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        // بدون revoke، هر بار خروجی گرفتن یک شیء در حافظه مرورگر می‌ماند.
        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    });

    // ---------- Import CSV ----------
    $('#hodima-import-csv-btn').click(function() { $('#hodima-import-csv').click(); });
    $('#hodima-import-csv').on('change', function (e) {

        var input = e.target;
        var file  = input.files && input.files[0];

        if (!file) return;

        // بدون سقف، یک فایل چندمگابایتی مرورگر را قفل می‌کند.
        if (file.size > 2 * 1024 * 1024) {
            alert('حجم فایل بیش از ۲ مگابایت است.');
            input.value = '';
            return;
        }

        var reader = new FileReader();

        reader.onload = function (ev) {

            var rows = parseCSV(String(ev.target.result || ''));

            if (!rows.length) {
                alert('فایل CSV معتبر نیست یا خالی است.');
                input.value = '';
                return;
            }

            // سقف‌ها باید با سمت سرور یکی باشد، وگرنه کاربر جدولی می‌سازد
            // که هنگام ذخیره بی‌صدا بریده می‌شود.
            var limits  = (typeof hodimaTableLimits !== 'undefined') ? hodimaTableLimits : { maxCols: 20, maxRows: 100 };
            var maxCols = parseInt(limits.maxCols, 10) || 20;
            var maxRows = parseInt(limits.maxRows, 10) || 100;

            if (rows.length > maxRows + 1 || rows[0].length > maxCols) {
                if (!confirm('فایل از سقف مجاز (' + maxCols + ' ستون و ' + maxRows + ' ردیف) بزرگ‌تر است و بریده می‌شود. ادامه می‌دهید؟')) {
                    input.value = '';
                    return;
                }
                rows = rows.slice(0, maxRows + 1).map(function (r) { return r.slice(0, maxCols); });
            }

            rebuildTableFromCSV(rows);

            // بدون ریست، انتخاب دوباره همان فایل رویداد change را صادر نمی‌کند.
            input.value = '';
        };

        reader.onerror = function () {
            alert('خواندن فایل ممکن نشد.');
            input.value = '';
        };

        reader.readAsText(file, 'UTF-8');
    });
});
