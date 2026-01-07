<?php
/**
 * Global helper functions used by your custom single-sc_event template.
 *
 * These were previously provided via Code Snippets (notably snippet #163).
 * They are kept as GLOBAL functions for backwards compatibility.
 */

if ( ! defined('ABSPATH') ) exit;

if ( ! function_exists('tc_sc_event_tr') ) {
    function tc_sc_event_tr( string $text ) : string {
        if ( function_exists('qtranxf_useCurrentLanguageIfNotFoundUseDefaultLanguage') ) {
            return (string) qtranxf_useCurrentLanguageIfNotFoundUseDefaultLanguage($text);
        }
        return $text;
    }
}

if ( ! function_exists('tc_sc_event_dates') ) {
    function tc_sc_event_dates( int $event_id ) : array {
        $event_id = (int) $event_id;
        if ( $event_id <= 0 ) return [];

        $start_ts = (int) get_post_meta($event_id, 'sc_event_date_time', true);
$end_ts   = (int) get_post_meta($event_id, 'sc_event_end_date_time', true);

// Sugar Calendar "all day" can be stored either via sc_event_all_day meta OR via implicit times
// (00:00 start + 00:00 next-day end, or 00:00 start + 23:59 end on the last day).
$all_day_meta = get_post_meta($event_id, 'sc_event_all_day', true);
$is_all_day = in_array( (string) $all_day_meta, array('1','yes','true'), true );

// Heuristic fallback when meta isn't set but the stored times *behave* like all-day.
// IMPORTANT: We do NOT rely on duration modulo 1 day because timestamps may be stored in UTC
// while displayed in site timezone (DST/offsets can make the duration "not divisible").
if ( ! $is_all_day && $start_ts > 0 && $end_ts > 0 ) {
    $start_hi = date_i18n( 'H:i', $start_ts );
    $end_hi   = date_i18n( 'H:i', $end_ts );

    // Two common storage patterns for all-day multi-day events:
    // 1) start 00:00, end 00:00 on the day AFTER the last day (exclusive boundary)
    // 2) start 00:00, end 23:59 on the last day (inclusive)
    if ( $start_hi === '00:00' ) {
        if ( $end_hi === '00:00' ) {
            // Must span at least 1 day.
            $start_ymd = date_i18n( 'Y-m-d', $start_ts );
            $end_ymd   = date_i18n( 'Y-m-d', $end_ts );
            if ( $end_ymd !== $start_ymd ) {
                $is_all_day = true;
            }
        } elseif ( $end_hi === '23:59' ) {
            $is_all_day = true;
        }
    }
}


        if ( $start_ts <= 0 ) return [];

        // NOTE: Sugar Calendar stores timestamps in UTC (unix seconds).
        // For display we want to mirror SC's behaviour:
        // - timed single-day:  11/02/2026 10:00 – 13:00
        // - timed multi-day:   11/02/2026 10:00 – 12/02/2026 13:00
        // - all-day single:    11/02/2026
        // - all-day multi:     11/02/2026 – 15/02/2026 (end is inclusive)

        $date_fmt = (string) apply_filters( 'tc_sc_event_date_format', 'd/m/Y', $event_id );
        $time_fmt = (string) apply_filters( 'tc_sc_event_time_format', 'H:i',   $event_id );

        $start_date = date_i18n( $date_fmt, $start_ts );
        $start_time = date_i18n( $time_fmt, $start_ts );

        $end_date = $end_ts ? date_i18n( $date_fmt, $end_ts ) : '';
        $end_time = $end_ts ? date_i18n( $time_fmt, $end_ts ) : '';

        // For all-day events we need an "inclusive" end date for display.
        // We support both storage patterns:
        // - exclusive end boundary at 00:00 of the next day
        // - inclusive end boundary at 23:59 of the last day
        $end_inclusive_date = '';
        if ( $end_ts ) {
            $end_hi = date_i18n( $time_fmt, $end_ts );
            if ( $end_hi === '00:00' ) {
                // Exclusive boundary => subtract one day for display.
                $end_inclusive_date = date_i18n( $date_fmt, max( 0, (int) $end_ts - DAY_IN_SECONDS ) );
            } else {
                // Inclusive (e.g. 23:59) or any other => use same date.
                $end_inclusive_date = date_i18n( $date_fmt, (int) $end_ts );
            }
        }

        $same_day_timed = ( $end_ts && date_i18n( 'Y-m-d', $start_ts ) === date_i18n( 'Y-m-d', $end_ts ) );

        // Back-compat strings (used elsewhere)
        $display_start = $start_date;
        $display_end   = $end_date;
        if ( $same_day_timed ) {
            $display_end = '';
        }

        // Header-friendly formatted string
        $display_header_date = '';
        if ( $is_all_day ) {
            // Single-day all-day (common: end is next day 00:00)
            if ( $end_inclusive_date === '' || $end_inclusive_date === $start_date ) {
                $display_header_date = $start_date;
            } else {
                $display_header_date = $start_date . ' – ' . $end_inclusive_date;
            }
        } else {
            // Timed
            if ( ! $end_ts ) {
                $display_header_date = $start_date . ' ' . $start_time;
            } elseif ( $same_day_timed ) {
                // Same date -> show only times on the right.
                $display_header_date = $start_date . ' ' . $start_time . ' – ' . $end_time;
            } else {
                $display_header_date = $start_date . ' ' . $start_time . ' – ' . $end_date . ' ' . $end_time;
            }
        }

        // Availability/range in whole days: start day 00:00 (UTC) +1 day exclusive
        $start_day_utc = (int) strtotime(gmdate('Y-m-d 00:00:00', $start_ts) . ' UTC');
        $end_excl_utc  = $start_day_utc + DAY_IN_SECONDS;

        return [
            'start_ts' => $start_ts,
            'end_ts'   => $end_ts,
            'is_all_day' => $is_all_day,
            'display_start' => $display_start,
            'display_end'   => $display_end,
            'display_header_date' => $display_header_date,
            'range_start_ts' => $start_day_utc,
            'range_end_exclusive_ts' => $end_excl_utc,
        ];
    }
}

if ( ! function_exists('tc_sc_event_get_logo_html') ) {
    function tc_sc_event_get_logo_html( int $event_id ) : string {
        $event_id = (int) $event_id;
        if ( $event_id <= 0 ) return '';

        $mode = (string) get_post_meta($event_id, 'tc_header_logo_mode', true);
        if ( $mode === '' ) $mode = 'none';

        if ( $mode === 'media' ) {
            $logo_id = (int) get_post_meta($event_id, 'tc_header_logo_id', true);
            if ( $logo_id > 0 ) {
                $src = wp_get_attachment_image_url($logo_id, 'full');
                if ( $src ) {
                    return '<div class="tc-event-header-logo"><img class="tc-event-header-logo-img tc-header-logo-img" src="' . esc_url($src) . '" alt="" loading="lazy" decoding="async" /></div>';
                }
            }
            return '';
        }

        if ( $mode === 'url' ) {
            $url = trim((string) get_post_meta($event_id, 'tc_header_logo_url', true));
            if ( $url !== '' ) {
                return '<div class="tc-event-header-logo"><img class="tc-event-header-logo-img tc-header-logo-img" src="' . esc_url($url) . '" alt="" loading="lazy" decoding="async" /></div>';
            }
            return '';
        }

        return '';
    }
}

if ( ! function_exists('tc_sc_event_render_details_bar') ) {
    function tc_sc_event_render_details_bar( int $event_id ) : string {
        $event_id = (int) $event_id;
        if ( $event_id <= 0 ) return '';

        $d = tc_sc_event_dates($event_id);
        if ( empty($d) ) return '';

        // This string is used in the HEADER, and should mirror Sugar Calendar formatting
        // (single day timed vs all-day vs multi-day).
        $range = $d['display_header_date'] ?? '';

        // Terms (Calendar categories)
        $terms      = get_the_terms($event_id, 'sc_event_category');
        $term_links = [];
        if ( ! empty($terms) && ! is_wp_error($terms) ) {
            foreach ( $terms as $t ) {
                $term_links[] = '<a href="' . esc_url(get_term_link($t)) . '">' . esc_html($t->name) . '</a>';
            }
        }

        // Google Calendar dates:
        // - all-day: YYYYMMDD/YYYYMMDD (END exclusive => +1 day)
        // - timed:   YYYYMMDDTHHMMSSZ/YYYYMMDDTHHMMSSZ
        $google_dates = '';
        if ( ! empty( $d['start_ts'] ) ) {
            if ( ! empty( $d['is_all_day'] ) ) {
                $start_ymd = gmdate('Y-m-d', (int) $d['start_ts']);
                $end_ymd   = ! empty($d['end_ts']) ? gmdate('Y-m-d', (int) $d['end_ts']) : $start_ymd;
                $google_start = str_replace('-', '', $start_ymd);
                $end_excl     = gmdate('Y-m-d', strtotime($end_ymd . ' +1 day'));
                $google_end   = str_replace('-', '', $end_excl);
                $google_dates = $google_start . '/' . $google_end;
            } else {
                $google_dates = gmdate('Ymd\\THis\\Z', (int) $d['start_ts']);
                $google_dates .= '/' . gmdate('Ymd\\THis\\Z', (int) ( ! empty($d['end_ts']) ? $d['end_ts'] : ((int)$d['start_ts'] + HOUR_IN_SECONDS) ) );
            }
        }

        $title = tc_sc_event_tr( get_the_title( $event_id ) );

        $google_url = '';
        if ( $google_dates !== '' ) {
            $google_url = add_query_arg(
                array(
                    'action' => 'TEMPLATE',
                    'text'   => $title,
                    'dates'  => $google_dates,
                ),
                'https://calendar.google.com/calendar/render'
            );
        }

        // ICS download (Outlook/Apple/Download)
        $ics_url = trailingslashit( get_permalink( $event_id ) ) . 'ics/?download=1';

        $label_calendar = tc_sc_event_tr('[:en]Calendar[:es]Calendario[:]');
        $label_download = tc_sc_event_tr('[:en]Download[:es]Descargar[:]');

        ob_start();
        ?>
        <div class="tc-sc-header-details tc-sc_event_details--header" id="sc_event_details_<?php echo (int) $event_id; ?>">

            <div class="tc-sc-header-details__date">
                <?php echo esc_html( $range ); ?>
            </div>

            <div class="tc-sc-header-details__links">
                <?php if ( ! empty( $google_url ) ) : ?>
                    <a href="<?php echo esc_url( $google_url ); ?>" target="_blank" rel="noopener">Google Calendar</a>
                <?php endif; ?>

                <?php if ( ! empty( $ics_url ) ) : ?>
                    <?php if ( ! empty( $google_url ) ) : ?><span class="tc-sc-sep">·</span><?php endif; ?>
                    <a href="<?php echo esc_url( $ics_url ); ?>" target="_blank" rel="noopener">Microsoft Outlook</a>
                    <span class="tc-sc-sep">·</span>
                    <a href="<?php echo esc_url( $ics_url ); ?>" target="_blank" rel="noopener">Apple Calendar</a>
                    <span class="tc-sc-sep">·</span>
                    <a href="<?php echo esc_url( $ics_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $label_download ); ?></a>
                <?php endif; ?>
            </div>

            <?php if ( ! empty( $term_links ) ) : ?>
                <div class="tc-sc-header-details__terms">
                    <span class="tc-sc-header-details__terms-label"><?php echo esc_html( $label_calendar ); ?>:</span>
                    <?php echo wp_kses_post( implode( ', ', $term_links ) ); ?>
                </div>
            <?php endif; ?>

        </div>
        <?php
        return (string) ob_get_clean();
    }
}
