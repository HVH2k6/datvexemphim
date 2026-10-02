<?php
/**
 * App config
 */

return [
    'name'     => getenv('APP_NAME') ?: 'CineHub',
    'url'      => getenv('APP_URL') ?: 'http://booking-ticket-movie.test',
    'debug'    => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN),
    'timezone' => 'Asia/Ho_Chi_Minh',
];
