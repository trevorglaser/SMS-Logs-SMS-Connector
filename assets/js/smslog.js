/**
 * SMS Event Logging - Admin JS
 * Native integration with simontelephonics/smsconnector (sms_messages table).
 */
(function ($) {
    'use strict';

    var state = {
        page     : 1,
        per_page : 50,
        sort     : 'tx_rx_datetime',
        order    : 'DESC',
        filters  : {},
        total    : 0,
        pages    : 1,
    };

    var volumeChart = null;

    $(document).ready(function () {
        initDateDefaults();
        loadFilterOptions();
        loadStats();
        loadVolume();
        loadEvents();
        bindEvents();
    });

    function initDateDefaults() {
        var today = new Date();
        var from  = new Date();
        from.setDate(today.getDate() - 30);
        $('#filter-date-from').val(formatDate(from));
        $('#filter-date-to').val(formatDate(today));
    }

    // Populate adaptor and DID dropdowns from live data
    function loadFilterOptions() {
        $.get('', { action: 'get_filter_options' }, function (data) {
            var $adaptor = $('#filter-adaptor');
            (data.adaptors || []).forEach(function (a) {
                $adaptor.append('<option value="' + escHtml(a) + '">' + escHtml(a) + '</option>');
            });

            var $did = $('#filter-did');
            (data.dids || []).forEach(function (d) {
                var label = d.did + (d.provider ? ' (' + d.provider + ')' : '');
                $did.append('<option value="' + escHtml(d.id) + '">' + escHtml(label) + '</option>');
            });
        }, 'json');
    }

    function bindEvents() {
        $('#smslog-filter-form').on('submit', function (e) {
            e.preventDefault();
            state.page = 1;
            state.filters = collectFilters();
            loadStats();
            loadEvents();
        });

        $('#smslog-reset-btn').on('click', function () {
            $('#smslog-filter-form')[0].reset();
            initDateDefaults();
            state.page = 1;
            state.filters = {};
            loadStats();
            loadEvents();
        });

        $('#smslog-per-page').on('change', function () {
            state.per_page = parseInt($(this).val(), 10);
            state.page = 1;
            loadEvents();
        });

        $(document).on('click', '.smslog-sortable', function () {
            var col = $(this).data('col');
            if (state.sort === col) {
                state.order = state.order === 'DESC' ? 'ASC' : 'DESC';
            } else {
                state.sort  = col;
                state.order = 'DESC';
            }
            state.page = 1;
            loadEvents();
        });

        $(document).on('click', '.smslog-page-btn', function (e) {
            e.preventDefault();
            var p = parseInt($(this).data('page'), 10);
            if (p >= 1 && p <= state.pages) {
                state.page = p;
                loadEvents();
            }
        });

        $(document).on('click', '.smslog-detail-btn', function () {
            openDetailModal($(this).data('id'));
        });

        // Click a thread badge to filter by that thread
        $(document).on('click', '.smslog-thread-link', function (e) {
            e.preventDefault();
            var tid = $(this).data('threadid');
            $('#filter-threadid').val(tid);
            state.page = 1;
            state.filters = collectFilters();
            loadStats();
            loadEvents();
        });

        $('#smslog-export-btn').on('click', function () {
            var params = $.param($.extend({ action: 'export_csv' }, collectFilters()));
            window.location.href = '?' + params;
        });

        $('#smslog-volume-days').on('change', function () {
            loadVolume();
        });
    }

    // ─── Loaders ──────────────────────────────────────────────────────────────

    function loadStats() {
        var params = $.extend({ action: 'get_stats' }, {
            date_from : $('#filter-date-from').val(),
            date_to   : $('#filter-date-to').val(),
        });
        $.get('', params, function (d) {
            $('#stat-total').text(fmt(d.total));
            $('#stat-outbound').text(fmt(d.outbound));
            $('#stat-inbound').text(fmt(d.inbound));
            $('#stat-internal').text(fmt(d.internal));
            $('#stat-delivered').text(fmt(d.delivered));
            $('#stat-failed').text(fmt(d.failed));
            $('#stat-unread').text(fmt(d.unread));
        }, 'json');
    }

    function loadVolume() {
        var days = parseInt($('#smslog-volume-days').val(), 10);
        $.get('', { action: 'get_volume', days: days }, function (rows) {
            renderVolumeChart(rows, days);
        }, 'json');
    }

    function loadEvents() {
        var params = $.extend({
            action   : 'get_events',
            page     : state.page,
            per_page : state.per_page,
            sort     : state.sort,
            order    : state.order,
        }, state.filters);

        $('#smslog-tbody').html(
            '<tr><td colspan="11" class="text-center text-muted">' +
            '<i class="fa fa-spinner fa-spin"></i> Loading…</td></tr>'
        );

        $.get('', params, function (data) {
            state.total = data.total;
            state.pages = data.pages;
            renderTable(data.rows);
            renderPagination();
            renderSortIndicators();
            var start = ((data.page - 1) * data.per_page) + 1;
            var end   = Math.min(data.page * data.per_page, data.total);
            $('#smslog-result-count').html(
                data.total > 0
                    ? 'Showing <strong>' + start + '–' + end + '</strong> of <strong>' + fmt(data.total) + '</strong> entries'
                    : '0 entries'
            );
        }, 'json').fail(function () {
            $('#smslog-tbody').html('<tr><td colspan="11" class="text-center text-danger">Failed to load data.</td></tr>');
        });
    }

    // ─── Renderers ────────────────────────────────────────────────────────────

    function renderTable(rows) {
        if (!rows || !rows.length) {
            $('#smslog-tbody').html('<tr><td colspan="11" class="text-center text-muted">No records found.</td></tr>');
            return;
        }

        var html = '';
        rows.forEach(function (r) {
            var preview = escHtml(r.body || '').substring(0, 70);
            if ((r.body || '').length > 70) preview += '…';

            // Direction: smsconnector stores "in" / "out", dialplan-logged internal = "internal"
            var dirBadge = r.direction === 'in'
                ? '<span class="label smslog-label-inbound">In</span>'
                : r.direction === 'internal'
                    ? '<span class="label smslog-label-internal">Int</span>'
                    : '<span class="label smslog-label-outbound">Out</span>';

            var delivBadge = parseInt(r.delivered, 10) === 1
                ? '<span class="label label-success">Yes</span>'
                : '<span class="label label-warning">No</span>';

            var readBadge = parseInt(r.read, 10) === 1
                ? '<span class="label label-default">Read</span>'
                : '<span class="label label-info">Unread</span>';

            var didLabel = r.did_number
                ? escHtml(r.did_number) + (r.provider_name ? '<br><small class="text-muted">' + escHtml(r.provider_name) + '</small>' : '')
                : '<em class="text-muted">—</em>';

            var threadBtn = r.threadid
                ? '<a href="#" class="smslog-thread-link" data-threadid="' + escHtml(r.threadid) + '" title="Filter by thread"><i class="fa fa-comments"></i></a> '
                : '';

            html += '<tr>';
            html += '<td class="smslog-td-time">' + escHtml(r.tx_rx_datetime) + '</td>';
            html += '<td>' + dirBadge + '</td>';
            html += '<td class="smslog-td-num">' + escHtml(r.from) + '</td>';
            html += '<td class="smslog-td-num">' + escHtml(r.to) + '</td>';
            html += '<td class="smslog-td-cnam">' + escHtml(r.cnam || '') + '</td>';
            html += '<td class="smslog-td-body">' + preview + '</td>';
            html += '<td>' + delivBadge + '</td>';
            html += '<td>' + readBadge + '</td>';
            html += '<td><small>' + escHtml(r.adaptor || '') + '</small></td>';
            html += '<td><small>' + didLabel + '</small></td>';
            html += '<td>' + threadBtn + '<button class="btn btn-xs btn-default smslog-detail-btn" data-id="' + escHtml(r.id) + '" title="View full record"><i class="fa fa-eye"></i></button></td>';
            html += '</tr>';
        });

        $('#smslog-tbody').html(html);
    }

    function renderPagination() {
        var p     = state.page;
        var total = state.pages;
        if (total <= 1) { $('#smslog-pagination').html(''); return; }

        var html = '';
        html += '<li class="' + (p <= 1 ? 'disabled' : '') + '"><a href="#" class="smslog-page-btn" data-page="' + (p - 1) + '">&laquo;</a></li>';

        var start = Math.max(1, p - 2);
        var end   = Math.min(total, p + 2);

        if (start > 1) {
            html += '<li><a href="#" class="smslog-page-btn" data-page="1">1</a></li>';
            if (start > 2) html += '<li class="disabled"><a>…</a></li>';
        }
        for (var i = start; i <= end; i++) {
            html += '<li class="' + (i === p ? 'active' : '') + '"><a href="#" class="smslog-page-btn" data-page="' + i + '">' + i + '</a></li>';
        }
        if (end < total) {
            if (end < total - 1) html += '<li class="disabled"><a>…</a></li>';
            html += '<li><a href="#" class="smslog-page-btn" data-page="' + total + '">' + total + '</a></li>';
        }
        html += '<li class="' + (p >= total ? 'disabled' : '') + '"><a href="#" class="smslog-page-btn" data-page="' + (p + 1) + '">&raquo;</a></li>';
        $('#smslog-pagination').html(html);
    }

    function renderSortIndicators() {
        $('.smslog-sortable i').attr('class', 'fa fa-sort');
        var icon = state.order === 'ASC' ? 'fa-sort-asc' : 'fa-sort-desc';
        $('.smslog-sortable[data-col="' + state.sort + '"] i').attr('class', 'fa ' + icon);
    }

    function renderVolumeChart(rows, days) {
        var ctx = document.getElementById('smslog-volume-chart');
        if (!ctx || typeof Chart === 'undefined') return;

        var map = {};
        rows.forEach(function (r) { map[r.day] = r; });

        var labels = [], outbound = [], inbound = [], internal = [];
        for (var i = days - 1; i >= 0; i--) {
            var d = new Date();
            d.setDate(d.getDate() - i);
            var key = formatDate(d);
            labels.push(key.slice(5));
            var row = map[key] || {};
            outbound.push(parseInt(row.outbound || 0, 10));
            inbound.push(parseInt(row.inbound   || 0, 10));
            internal.push(parseInt(row.internal  || 0, 10));
        }

        if (volumeChart) { volumeChart.destroy(); }
        volumeChart = new Chart(ctx, {
            type : 'bar',
            data : {
                labels   : labels,
                datasets : [
                    { label: 'Outbound', data: outbound, backgroundColor: 'rgba(52,152,219,0.7)',  borderColor: 'rgba(52,152,219,1)',  borderWidth: 1 },
                    { label: 'Inbound',  data: inbound,  backgroundColor: 'rgba(46,204,113,0.7)',  borderColor: 'rgba(46,204,113,1)',  borderWidth: 1 },
                    { label: 'Internal', data: internal, backgroundColor: 'rgba(142,68,173,0.7)',  borderColor: 'rgba(142,68,173,1)',  borderWidth: 1 },
                ],
            },
            options : {
                responsive: true, maintainAspectRatio: false,
                legend  : { display: false },
                scales  : {
                    xAxes : [{ ticks: { maxRotation: 45, fontSize: 10 } }],
                    yAxes : [{ ticks: { beginAtZero: true, precision: 0 } }],
                },
                tooltips : { mode: 'index', intersect: false },
            },
        });
    }

    // ─── Detail Modal ─────────────────────────────────────────────────────────

    function openDetailModal(id) {
        $('#smslog-detail-body').html('<p class="text-center"><i class="fa fa-spinner fa-spin"></i></p>');
        $('#smslog-detail-modal').modal('show');

        $.get('', { action: 'get_event', id: id }, function (e) {
            if (!e || e.error) {
                $('#smslog-detail-body').html('<p class="text-danger">Event not found.</p>');
                return;
            }

            var dirLabel = e.direction === 'in' ? 'Inbound' : e.direction === 'internal' ? 'Internal' : 'Outbound';
            var delivLabel = parseInt(e.delivered, 10) === 1 ? 'Yes' : 'No';
            var readLabel  = parseInt(e.read, 10)      === 1 ? 'Read' : 'Unread';

            var fields = [
                ['ID',            e.id],
                ['Date / Time',   e.tx_rx_datetime],
                ['Direction',     dirLabel],
                ['From',          e.from],
                ['To',            e.to],
                ['CNAM',          e.cnam],
                ['Delivered',     delivLabel],
                ['Read',          readLabel],
                ['Adaptor',       e.adaptor],
                ['DID Number',    e.did_number],
                ['Provider',      e.provider_name],
                ['Thread ID',     e.threadid
                    ? e.threadid + ' <a href="#" class="smslog-thread-link" data-threadid="' + escHtml(e.threadid) + '"> <i class="fa fa-filter"></i> filter thread</a>'
                    : ''],
                ['External MsgID',e.emid],
                ['Unix Timestamp',e.timestamp],
            ];

            var html = '<table class="table table-condensed smslog-detail-table">';
            fields.forEach(function (f) {
                html += '<tr><th class="smslog-detail-th">' + f[0] + '</th><td>' + (f[1] || '<em class="text-muted">—</em>') + '</td></tr>';
            });
            html += '</table>';
            html += '<div class="smslog-detail-body-wrap"><strong>Message Body</strong>';
            html += '<div class="smslog-body-text">' + escHtml(e.body || '') + '</div></div>';

            $('#smslog-detail-body').html(html);
        }, 'json').fail(function () {
            $('#smslog-detail-body').html('<p class="text-danger">Failed to load event.</p>');
        });
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    function collectFilters() {
        return {
            date_from  : $('#filter-date-from').val(),
            date_to    : $('#filter-date-to').val(),
            direction  : $('#filter-direction').val(),
            delivered  : $('#filter-delivered').val(),
            read_flag  : $('#filter-read').val(),
            adaptor    : $('#filter-adaptor').val(),
            did        : $('#filter-did').val(),
            src        : $('#filter-src').val(),
            dst        : $('#filter-dst').val(),
            threadid   : $('#filter-threadid').val(),
            search     : $('#filter-search').val(),
        };
    }

    function escHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function fmt(n) { return parseInt(n || 0, 10).toLocaleString(); }

    function formatDate(d) {
        return d.getFullYear() + '-' +
               String(d.getMonth() + 1).padStart(2, '0') + '-' +
               String(d.getDate()).padStart(2, '0');
    }

}(jQuery));
