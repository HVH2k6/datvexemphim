/* =====================================================================
   Biểu đồ trang quản trị (Chart.js 4)
   - 1 chuỗi dữ liệu: không cần chú thích, tiêu đề panel đã gọi tên
   - Màu theo bảng phân loại đã kiểm tra mù màu (thứ tự cố định, không xoay vòng)
   - Lưới / trục nhạt, chữ dùng màu chữ, không dùng màu chuỗi
   ===================================================================== */
window.AdminCharts = (function () {
    'use strict';

    var SERIES = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4'];
    var OTHER = '#9ca3af';
    var INK = '#374151';
    var MUTED = '#6b7280';
    var GRID = '#eceff3';

    function money(v) { return Math.round(v).toLocaleString('vi-VN') + 'đ'; }
    function short(v) {
        if (Math.abs(v) >= 1e9) return (v / 1e9).toLocaleString('vi-VN', { maximumFractionDigits: 1 }) + ' tỷ';
        if (Math.abs(v) >= 1e6) return (v / 1e6).toLocaleString('vi-VN', { maximumFractionDigits: 1 }) + ' tr';
        if (Math.abs(v) >= 1e3) return (v / 1e3).toLocaleString('vi-VN', { maximumFractionDigits: 0 }) + 'k';
        return String(v);
    }

    if (window.Chart) {
        Chart.defaults.font.family = "'Be Vietnam Pro', system-ui, sans-serif";
        Chart.defaults.font.size = 12;
        Chart.defaults.color = MUTED;
    }

    var tooltip = {
        backgroundColor: '#141c2b', titleColor: '#fff', bodyColor: '#e5e7eb',
        padding: 10, cornerRadius: 6, displayColors: false
    };

    function axes(format) {
        return {
            x: { grid: { display: false }, border: { color: GRID }, ticks: { color: MUTED, maxRotation: 0, autoSkip: true, autoSkipPadding: 12 } },
            y: { beginAtZero: true, grid: { color: GRID }, border: { display: false }, ticks: { color: MUTED, callback: format, maxTicksLimit: 5 } }
        };
    }

    /** Đường 1 chuỗi + nhấn vào điểm (crosshair theo trục x) */
    function line(id, labels, values, opts) {
        var el = document.getElementById(id);
        if (!el || !window.Chart) return null;
        opts = opts || {};
        return new Chart(el, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    data: values, borderColor: SERIES[0], borderWidth: 2,
                    // monotone: đường cong không vọt quá điểm dữ liệu (không tụt dưới 0)
                    cubicInterpolationMode: 'monotone',
                    pointRadius: 4, pointHoverRadius: 6, pointBackgroundColor: SERIES[0],
                    pointBorderColor: '#fff', pointBorderWidth: 2, fill: false
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: Object.assign({}, tooltip, { callbacks: { label: function (c) { return opts.label ? opts.label(c.parsed.y) : money(c.parsed.y); } } })
                },
                scales: axes(opts.tick || short)
            },
            plugins: opts.band ? [bandPlugin(opts.band)] : []
        });
    }

    /** Tô nền vùng cao điểm [fromIndex, toIndex] */
    function bandPlugin(band) {
        return {
            id: 'peakBand',
            beforeDatasetsDraw: function (chart) {
                var x = chart.scales.x, area = chart.chartArea;
                if (band.from < 0) return;
                var left = x.getPixelForValue(band.from) - 10, right = x.getPixelForValue(band.to) + 10;
                var ctx = chart.ctx;
                ctx.save();
                ctx.fillStyle = 'rgba(237, 161, 0, .12)';
                ctx.fillRect(left, area.top, right - left, area.bottom - area.top);
                ctx.fillStyle = INK;
                ctx.font = '600 11px ' + Chart.defaults.font.family;
                ctx.textAlign = 'center';
                ctx.fillText(band.label, (left + right) / 2, area.top + 12);
                ctx.restore();
            }
        };
    }

    /** Vòng tròn tỷ trọng (<= 5 phần + "Khác"), chú thích + số liệu nằm cạnh trong HTML */
    function donut(id, labels, values) {
        var el = document.getElementById(id);
        if (!el || !window.Chart) return null;
        var colors = labels.map(function (l, i) { return l === 'Khác' ? OTHER : SERIES[i % SERIES.length]; });
        var total = values.reduce(function (a, b) { return a + b; }, 0);
        return new Chart(el, {
            type: 'doughnut',
            data: { labels: labels, datasets: [{ data: values, backgroundColor: colors, borderColor: '#fff', borderWidth: 2, hoverOffset: 4 }] },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '66%',
                plugins: {
                    legend: { display: false },
                    tooltip: Object.assign({}, tooltip, {
                        callbacks: {
                            label: function (c) {
                                var pct = total ? Math.round(c.parsed / total * 1000) / 10 : 0;
                                return c.label + ': ' + money(c.parsed) + ' (' + pct + '%)';
                            }
                        }
                    })
                }
            }
        });
    }

    return {
        SERIES: SERIES, OTHER: OTHER,
        revenueLine: function (id, labels, values) { return line(id, labels, values); },
        ticketLine: function (id, labels, values, band) {
            return line(id, labels, values, {
                label: function (v) { return v.toLocaleString('vi-VN') + ' vé'; },
                tick: function (v) { return v.toLocaleString('vi-VN'); },
                band: band
            });
        },
        donut: donut
    };
})();
