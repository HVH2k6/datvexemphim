// Trang chi tiết phim: chọn thành phố / rạp / ngày -> tải suất chiếu qua AJAX; trailer
(function ($) {
    'use strict';

    // ---------- Trailer: nạp iframe khi mở, xóa khi đóng để dừng video ----------
    var modal = document.getElementById('trailerModal');
    if (modal) {
        var frame = document.getElementById('trailerFrame');
        modal.addEventListener('show.bs.modal', function () { frame.src = frame.dataset.src; });
        modal.addEventListener('hidden.bs.modal', function () { frame.src = 'about:blank'; });
    }

    var $root = $('#showtimeFinder');
    if (!$root.length) {
        return;
    }

    var movieId = $root.data('movie-id');
    var $city = $('#citySelect');
    var $cinema = $('#cinemaSelect');
    var $results = $('#showtimeResults');
    var state = { date: $root.find('.date-btn.active').data('date'), cityId: '', cinemaId: '' };
    var request = null;

    var ROOM_LABEL = { imax: 'IMAX', '4dx': '4DX', gold_class: 'Gold Class' };
    var LOW_SEATS = 20;

    function formatLabel(st) {
        var lang = st.language === 'dub' ? 'Lồng tiếng' : 'Phụ đề';
        return (ROOM_LABEL[st.roomType] ? ROOM_LABEL[st.roomType] + ' ' : '') + st.format + ' ' + lang;
    }

    function seatBadge(st) {
        if (st.availableSeats <= 0) {
            return '<span class="seat-badge seat-badge-out">Hết ghế</span>';
        }
        if (st.availableSeats <= LOW_SEATS) {
            return '<span class="seat-badge seat-badge-low">Sắp hết · ' + st.availableSeats + '</span>';
        }
        return '<span class="seat-badge">' + st.availableSeats + ' ghế</span>';
    }

    function renderShowtimes(groups) {
        if (!groups.length) {
            $results.html('<div class="panel text-center text-muted py-5">Không có suất chiếu trong ngày này. Hãy chọn ngày hoặc rạp khác.</div>');
            return;
        }

        var html = groups.map(function (group) {
            var rows = group.showtimes.map(function (st) {
                var soldOut = st.availableSeats <= 0;
                var action = soldOut
                    ? '<button type="button" class="btn btn-sm btn-light px-3" disabled>Hết ghế</button>'
                    : '<a class="btn btn-sm btn-primary px-3" href="' + App.escapeHtml($root.data('seats-url') + '/' + st.id) + '"'
                        + ' aria-label="Chọn suất ' + App.escapeHtml(st.time + ', ' + formatLabel(st) + ', ' + st.roomName) + '">CHỌN SUẤT</a>';

                return '<tr>'
                    + '<th scope="row" class="st-time">' + App.escapeHtml(st.time) + '</th>'
                    + '<td><span class="format-chip">' + App.escapeHtml(formatLabel(st)) + '</span></td>'
                    + '<td>' + App.escapeHtml(st.roomName) + '</td>'
                    + '<td>' + App.escapeHtml(App.formatMoney(st.fromPrice)) + '</td>'
                    + '<td>' + seatBadge(st) + '</td>'
                    + '<td class="text-end">' + action + '</td>'
                    + '</tr>';
            }).join('');

            return '<div class="panel showtime-group">'
                + '<div class="showtime-group-head"><div>'
                + '<h3 class="h6 mb-0">' + App.escapeHtml(group.cinemaName) + '</h3>'
                + '<p class="small text-muted mb-0">' + App.escapeHtml(group.cinemaAddress) + '</p>'
                + '</div><span class="small text-muted">' + group.showtimes.length + ' suất</span></div>'
                + '<div class="table-responsive"><table class="table showtime-table mb-0">'
                + '<thead><tr><th scope="col">Suất</th><th scope="col">Định dạng</th><th scope="col">Phòng</th>'
                + '<th scope="col">Giá</th><th scope="col">Ghế trống</th><th scope="col"><span class="visually-hidden">Chọn</span></th></tr></thead>'
                + '<tbody>' + rows + '</tbody></table></div></div>';
        }).join('');

        $results.html(html);
    }

    function loadShowtimes() {
        if (request) {
            request.abort();
        }
        $results.html('<div class="panel text-muted">Đang tải lịch chiếu...</div>');

        request = $.get($root.data('showtimes-url'), {
            movieId: movieId,
            date: state.date,
            cityId: state.cityId || undefined,
            cinemaId: state.cinemaId || undefined
        }).done(function (res) {
            renderShowtimes(res.data || []);
        }).fail(function (xhr, status) {
            if (status !== 'abort') {
                $results.html('<div class="alert alert-danger">' + App.escapeHtml(App.errorMessage(xhr)) + '</div>');
            }
        });
    }

    $root.on('click', '.date-btn', function () {
        $root.find('.date-btn').removeClass('active').attr('aria-pressed', 'false');
        $(this).addClass('active').attr('aria-pressed', 'true');
        state.date = $(this).data('date');
        loadShowtimes();
    });

    $city.on('change', function () {
        state.cityId = $city.val();
        state.cinemaId = '';
        $cinema.html('<option value="">Tất cả rạp</option>').prop('disabled', true);

        if (state.cityId) {
            $.get($root.data('cinemas-url'), { cityId: state.cityId }).done(function (res) {
                (res.data || []).forEach(function (cinema) {
                    $cinema.append($('<option>').val(cinema.id).text(cinema.name));
                });
                $cinema.prop('disabled', !(res.data || []).length);
            });
        }
        loadShowtimes();
    });

    $cinema.on('change', function () {
        state.cinemaId = $cinema.val();
        loadShowtimes();
    });

    loadShowtimes();
})(jQuery);
