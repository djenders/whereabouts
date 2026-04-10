<?php
/**
 * Plugin Name: Whereabouts
 * Plugin URI:  https://github.com/djenders/whereabouts
 * Description: A Gutenberg block that renders a natural-language sentence about your current location, time, and weather — fully customisable via a sentence template.
 * Version:     1.3.0
 * Author:      Dennis Jenders
 * Author URI:  https://github.com/djenders
 * License:     GPL-2.0-or-later
 * Text Domain: whereabouts
 * Update URI:  https://github.com/djenders/whereabouts
 */

defined( 'ABSPATH' ) || exit;

define( 'WHEREABOUTS_VERSION',        '1.3.0' );
define( 'WHEREABOUTS_PLUGIN_DIR',     plugin_dir_path( __FILE__ ) );
define( 'WHEREABOUTS_PLUGIN_URL',     plugin_dir_url( __FILE__ ) );
define( 'WHEREABOUTS_OPTIONS_KEY',    'whereabouts_settings' );
define( 'WHEREABOUTS_CACHE_KEY',      'whereabouts_weather' );
define( 'WHEREABOUTS_CACHE_DURATION', 30 * MINUTE_IN_SECONDS );

/* ------------------------------------------------------------------ */
/*  GitHub auto-updates via Plugin Update Checker (optional)           */
/*  Drop the real library into includes/plugin-update-checker/ and     */
/*  this activates automatically. See README for instructions.         */
/* ------------------------------------------------------------------ */
$puc_loader = WHEREABOUTS_PLUGIN_DIR . 'includes/plugin-update-checker/load-v5p6.php';
if ( file_exists( $puc_loader ) ) {
    require_once $puc_loader;
    if ( class_exists( 'YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
        \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/djenders/whereabouts',
            __FILE__,
            'whereabouts'
        );
    }
}

/* ------------------------------------------------------------------ */
/*  Plugin action links (Installed Plugins page)                       */
/* ------------------------------------------------------------------ */
add_filter( 'plugin_action_links_whereabouts/whereabouts.php', function ( $links ) {
    $settings_link = sprintf(
        '<a href="%s">My Whereabouts</a>',
        admin_url( 'options-general.php?page=whereabouts' )
    );
    array_unshift( $links, $settings_link );
    return $links;
} );

/* ------------------------------------------------------------------ */
/*  Redirect to settings page after activation                         */
/* ------------------------------------------------------------------ */
register_activation_hook( __FILE__, function () {
    add_option( 'whereabouts_activation_redirect', true );
} );

add_action( 'admin_init', function () {
    if ( get_option( 'whereabouts_activation_redirect' ) ) {
        delete_option( 'whereabouts_activation_redirect' );
        if ( ! isset( $_GET['activate-multi'] ) ) {
            wp_safe_redirect( admin_url( 'options-general.php?page=whereabouts' ) );
            exit;
        }
    }
} );

/* ------------------------------------------------------------------ */
/*  Register block                                                      */
/* ------------------------------------------------------------------ */
add_action( 'init', function () {
    register_block_type( WHEREABOUTS_PLUGIN_DIR . 'block.json', [
        'render_callback' => 'whereabouts_render_block',
        'attributes'      => [
            'tagName' => [ 'type' => 'string', 'default' => 'p' ],
        ],
    ] );
} );

function whereabouts_render_block( array $attributes ): string {
    $opts        = get_option( WHEREABOUTS_OPTIONS_KEY, [] );
    $city        = sanitize_text_field( $opts['city']     ?? 'Milwaukee' );
    $lat         = floatval( $opts['lat']                 ?? 43.0389 );
    $lon         = floatval( $opts['lon']                 ?? -87.9065 );
    $now_url     = esc_url( $opts['now_url']              ?? '' );
    $clock_24    = ! empty( $opts['clock_24'] );
    $use_celsius = ! empty( $opts['use_celsius'] );
    $template    = $opts['template'] ?? "It's {time} in {city}, where it is {condition} and {temp}.";

    $allowed_tags = [ 'p', 'h1', 'h2', 'h3', 'h4', 'span' ];
    $tag          = in_array( $attributes['tagName'] ?? 'p', $allowed_tags, true )
                    ? $attributes['tagName'] : 'p';

    // Weather (cached)
    $weather     = whereabouts_get_weather( $lat, $lon, $use_celsius );
    $tz_string   = $weather['timezone'];

    // Time (always live)
    try {
        $now = new DateTime( 'now', new DateTimeZone( $tz_string ) );
    } catch ( Exception $e ) {
        $now = new DateTime( 'now' );
    }

    // Build token values
    $city_value = $now_url
        ? sprintf( '<a href="%s" class="whereabouts-city-link">%s</a>', esc_url( $now_url ), esc_html( $city ) )
        : esc_html( $city );

    $tokens = [
        '{time}'         => whereabouts_format_time( $now, $clock_24, 'default' ),
        '{time:short}'   => whereabouts_format_time( $now, $clock_24, 'short' ),
        '{time:long}'    => whereabouts_format_time( $now, $clock_24, 'long' ),
        '{city}'         => $city_value,
        '{condition}'    => esc_html( $weather['description'] ),
        '{temp}'         => whereabouts_format_temp( $weather['temp'], $use_celsius, 'symbol' ),
        '{temp:number}'  => whereabouts_format_temp( $weather['temp'], $use_celsius, 'number' ),
        '{temp:long}'    => whereabouts_format_temp( $weather['temp'], $use_celsius, 'long' ),
    ];

    $sentence = str_replace( array_keys( $tokens ), array_values( $tokens ), $template );

    return sprintf(
        '<%1$s class="whereabouts-sentence wp-block-whereabouts">%2$s</%1$s>',
        $tag,
        $sentence
    );
}

/* ------------------------------------------------------------------ */
/*  Token helpers                                                       */
/* ------------------------------------------------------------------ */
function whereabouts_format_temp( ?int $temp, bool $celsius, string $style ): string {
    if ( $temp === null ) return '';
    $unit_sym = $celsius ? '°C' : '°F';
    return match ( $style ) {
        'number' => $temp . '°',           // 41°
        'long'   => $temp . ' degrees',    // 41 degrees
        default  => $temp . $unit_sym,     // 41°F / 41°C
    };
}

function whereabouts_format_time( DateTime $dt, bool $use_24, string $style = 'default' ): string {
    if ( $use_24 ) return $dt->format( 'H:i' );

    $hour   = (int) $dt->format( 'g' );
    $minute = (int) $dt->format( 'i' );
    $ampm   = $dt->format( 'a' );
    $hour24 = (int) $dt->format( 'G' );

    // Period phrase for {time:long} digital fallback only
    $period = match( true ) {
        $hour24 >= 0  && $hour24 < 12 => 'in the morning',
        $hour24 >= 12 && $hour24 < 18 => 'in the afternoon',
        default                        => 'in the evening',
    };

    // Special labels — midnight and noon never get am/pm or period phrase
    $is_midnight = ( $hour24 === 0 );
    $is_noon     = ( $hour24 === 12 );
    $label       = $is_midnight ? 'midnight' : ( $is_noon ? 'noon' : null );

    // On the hour
    if ( $minute === 0 ) {
        if ( $label ) return $label;
        // Natural phrase — no am/pm regardless of style
        return "{$hour} o'clock";
    }

    // For past/to phrases — always clean, no am/pm, no period
    $hour_word = $label ?? (string) $hour;

    $named = [
        5  => "five past {$hour_word}",
        10 => "ten past {$hour_word}",
        15 => "quarter past {$hour_word}",
        20 => "twenty past {$hour_word}",
        25 => "twenty-five past {$hour_word}",
        30 => "half past {$hour_word}",
    ];
    if ( isset( $named[ $minute ] ) ) return $named[ $minute ];

    $next_hour24 = ( $hour24 + 1 ) % 24;
    $next_label  = match( $next_hour24 ) {
        0       => 'midnight',
        12      => 'noon',
        default => (string) ( ( $hour % 12 ) + 1 ),
    };

    $to_named = [
        35 => "twenty-five to {$next_label}",
        40 => "twenty to {$next_label}",
        45 => "quarter to {$next_label}",
        50 => "ten to {$next_label}",
        55 => "five to {$next_label}",
    ];
    if ( isset( $to_named[ $minute ] ) ) return $to_named[ $minute ];

    // Digital fallback — style determines suffix
    return match( $style ) {
        'short'  => $dt->format( 'g:i' ),                        // 9:37
        'long'   => $dt->format( 'g:i' ) . ' ' . $period,       // 9:37 in the evening
        default  => $dt->format( 'g:i a' ),                      // 9:37 pm
    };
}

/* ------------------------------------------------------------------ */
/*  Weather cache                                                       */
/* ------------------------------------------------------------------ */
function whereabouts_get_weather( float $lat, float $lon, bool $celsius ): array {
    $defaults = [
        'temp'        => null,
        'description' => 'unclear skies',
        'timezone'    => 'America/Chicago',
    ];

    $cache_key = WHEREABOUTS_CACHE_KEY . '_' . md5( "{$lat},{$lon}," . ( $celsius ? 'c' : 'f' ) );
    $cached    = get_transient( $cache_key );
    if ( false !== $cached ) return $cached;

    $url = add_query_arg( [
        'latitude'         => $lat,
        'longitude'        => $lon,
        'current'          => 'temperature_2m,weathercode',
        'temperature_unit' => $celsius ? 'celsius' : 'fahrenheit',
        'timezone'         => 'auto',
    ], 'https://api.open-meteo.com/v1/forecast' );

    $response = wp_remote_get( $url, [ 'timeout' => 8 ] );
    if ( is_wp_error( $response ) ) return $defaults;

    $body   = json_decode( wp_remote_retrieve_body( $response ), true );
    $result = $defaults;

    if ( isset( $body['current']['temperature_2m'] ) )
        $result['temp'] = (int) round( $body['current']['temperature_2m'] );
    if ( isset( $body['current']['weathercode'] ) )
        $result['description'] = whereabouts_wmo_label( (int) $body['current']['weathercode'] );
    if ( isset( $body['timezone'] ) )
        $result['timezone'] = $body['timezone'];

    set_transient( $cache_key, $result, WHEREABOUTS_CACHE_DURATION );
    return $result;
}

function whereabouts_bust_cache(): void {
    $opts = get_option( WHEREABOUTS_OPTIONS_KEY, [] );
    $lat  = floatval( $opts['lat'] ?? 43.0389 );
    $lon  = floatval( $opts['lon'] ?? -87.9065 );
    foreach ( [ 'c', 'f' ] as $unit )
        delete_transient( WHEREABOUTS_CACHE_KEY . '_' . md5( "{$lat},{$lon},{$unit}" ) );
}
add_action( 'update_option_' . WHEREABOUTS_OPTIONS_KEY, 'whereabouts_bust_cache' );

function whereabouts_wmo_label( int $code ): string {
    $map = [
        0 => 'clear and sunny', 1 => 'mostly clear', 2 => 'partly cloudy',
        3 => 'overcast', 45 => 'foggy', 48 => 'icy fog',
        51 => 'lightly drizzling', 53 => 'drizzling', 55 => 'heavily drizzling',
        61 => 'lightly raining', 63 => 'raining', 65 => 'heavily raining',
        71 => 'lightly snowing', 73 => 'snowing', 75 => 'heavily snowing',
        77 => 'snowing (graupel)', 80 => 'showery', 81 => 'rainy with showers',
        82 => 'stormy with heavy showers', 85 => 'snowy with showers',
        86 => 'heavily snowy with showers', 95 => 'thunderstormy',
        96 => 'thunderstormy with hail', 99 => 'thunderstormy with heavy hail',
    ];
    return $map[ $code ] ?? 'unclear';
}

/* ------------------------------------------------------------------ */
/*  Admin settings page                                                 */
/* ------------------------------------------------------------------ */
add_action( 'admin_menu', function () {
    add_options_page(
        'Whereabouts', 'Whereabouts', 'manage_options',
        'whereabouts', 'whereabouts_settings_page'
    );
} );

add_action( 'admin_init', function () {
    register_setting( 'whereabouts_group', WHEREABOUTS_OPTIONS_KEY, [
        'sanitize_callback' => 'whereabouts_sanitize_options',
    ] );
} );

function whereabouts_sanitize_options( $input ): array {
    return [
        'city'        => sanitize_text_field( $input['city']     ?? '' ),
        'lat'         => floatval( $input['lat']                 ?? 0 ),
        'lon'         => floatval( $input['lon']                 ?? 0 ),
        'now_url'     => esc_url_raw( $input['now_url']          ?? '' ),
        'clock_24'    => ! empty( $input['clock_24'] )    ? 1 : 0,
        'use_celsius' => ! empty( $input['use_celsius'] ) ? 1 : 0,
        'template'    => wp_kses_post( $input['template'] ?? '' ),
    ];
}

function whereabouts_settings_page(): void {
    $opts        = get_option( WHEREABOUTS_OPTIONS_KEY, [] );
    $city        = esc_attr( $opts['city']     ?? 'Milwaukee' );
    $lat         = esc_attr( $opts['lat']      ?? '43.0389' );
    $lon         = esc_attr( $opts['lon']      ?? '-87.9065' );
    $now_url     = esc_attr( $opts['now_url']  ?? '' );
    $clock_24    = ! empty( $opts['clock_24'] );
    $use_celsius = ! empty( $opts['use_celsius'] );
    $template    = esc_attr( $opts['template'] ?? "It's {time} in {city}, where it is {condition} and {temp}." );

    // Cache status
    $cache_key = WHEREABOUTS_CACHE_KEY . '_' . md5( "{$lat},{$lon}," . ( $use_celsius ? 'c' : 'f' ) );
    $cache_exp = get_option( '_transient_timeout_' . $cache_key, 0 );
    if ( $cache_exp && $cache_exp > time() )
        $cache_status = '✅ Cached — ' . human_time_diff( time(), $cache_exp ) . ' remaining';
    elseif ( $cache_exp )
        $cache_status = '⏳ Expired (refreshes on next page load)';
    else
        $cache_status = '⚪ Not yet cached';

    // Open-Meteo verify URL
    $verify_url = "https://open-meteo.com/en/docs#latitude={$lat}&longitude={$lon}";
    ?>
    <div class="wrap" id="whereabouts-admin">
        <h1>📍 Whereabouts <span style="font-size:.6em;font-weight:400;color:#777;vertical-align:middle;">v<?= WHEREABOUTS_VERSION ?></span></h1>
        <p>Configure your current location and how it's displayed. Uses <strong>Open-Meteo</strong> — free, no API key required. Weather is cached for <strong>30 minutes</strong>.</p>

        <form method="post" action="options.php">
            <?php settings_fields( 'whereabouts_group' ); ?>
            <table class="form-table" role="presentation">

                <!-- NOW PAGE URL -->
                <tr>
                    <th scope="row"><label for="wa_now_url">"/Now" Page URL</label></th>
                    <td>
                        <input type="url" id="wa_now_url"
                               name="<?= WHEREABOUTS_OPTIONS_KEY ?>[now_url]"
                               value="<?= $now_url ?>" class="regular-text"
                               placeholder="https://yoursite.com/now" />
                        <p class="description">Optional. The <code>{city}</code> token will link here.</p>
                    </td>
                </tr>

                <!-- CITY + GEOCODE -->
                <tr>
                    <th scope="row"><label for="wa_city">City Name</label></th>
                    <td>
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <input type="text" id="wa_city"
                                   name="<?= WHEREABOUTS_OPTIONS_KEY ?>[city]"
                                   value="<?= $city ?>" class="regular-text"
                                   placeholder="Milwaukee" />
                            <button type="button" id="wa-geocode-btn" class="button button-secondary">
                                🔍 Look Up Location
                            </button>
                        </div>
                        <p class="description">You can include state or country to narrow results — e.g. "Paris, TX" or "Paris, France".</p>

                        <!-- Pick list -->
                        <div id="wa-picklist" style="margin-top:10px;display:none;">
                            <p style="margin:0 0 8px;"><strong>Multiple matches — select the right one:</strong></p>
                            <div id="wa-picklist-options" style="display:flex;flex-direction:column;gap:6px;"></div>
                        </div>

                        <!-- Confirmed -->
                        <div id="wa-geocode-confirmed" style="margin-top:10px;display:none;padding:8px 12px;background:#edfaed;border:1px solid #7ad47a;border-radius:4px;">
                            ✅ <strong>Confirmed:</strong> <span id="wa-geo-label"></span>
                        </div>
                        <div id="wa-geocode-error" style="margin-top:10px;display:none;padding:8px 12px;background:#fde8e8;border:1px solid #e07070;border-radius:4px;color:#c00;"></div>
                    </td>
                </tr>

                <!-- HIDDEN LAT/LON -->
                <tr style="display:none">
                    <td colspan="2">
                        <input type="hidden" id="wa_lat" name="<?= WHEREABOUTS_OPTIONS_KEY ?>[lat]" value="<?= $lat ?>" />
                        <input type="hidden" id="wa_lon" name="<?= WHEREABOUTS_OPTIONS_KEY ?>[lon]" value="<?= $lon ?>" />
                    </td>
                </tr>

                <!-- STORED COORDS + VERIFY LINK -->
                <tr>
                    <th scope="row">Stored Coordinates</th>
                    <td>
                        <code id="wa-stored-coords">Lat: <?= $lat ?>, Lon: <?= $lon ?></code><br>
                        <a id="wa-verify-link" href="<?= esc_url( $verify_url ) ?>" target="_blank" rel="noopener"
                           style="font-size:.9em;">↗ Verify on Open-Meteo</a>
                        <p class="description">Saved and used for weather lookups.</p>
                    </td>
                </tr>

                <!-- CLOCK FORMAT -->
                <tr>
                    <th scope="row">Clock Format</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" name="<?= WHEREABOUTS_OPTIONS_KEY ?>[clock_24]" value="0" <?= $clock_24 ? '' : 'checked' ?> />
                                12-hour <em>(half past nine pm)</em>
                            </label><br />
                            <label>
                                <input type="radio" name="<?= WHEREABOUTS_OPTIONS_KEY ?>[clock_24]" value="1" <?= $clock_24 ? 'checked' : '' ?> />
                                24-hour <em>(21:30)</em>
                            </label>
                        </fieldset>
                    </td>
                </tr>

                <!-- TEMPERATURE UNIT -->
                <tr>
                    <th scope="row">Temperature Unit</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" name="<?= WHEREABOUTS_OPTIONS_KEY ?>[use_celsius]" value="0" <?= $use_celsius ? '' : 'checked' ?> />
                                Fahrenheit (°F)
                            </label><br />
                            <label>
                                <input type="radio" name="<?= WHEREABOUTS_OPTIONS_KEY ?>[use_celsius]" value="1" <?= $use_celsius ? 'checked' : '' ?> />
                                Celsius (°C)
                            </label>
                        </fieldset>
                    </td>
                </tr>

                <!-- SENTENCE TEMPLATE -->
                <tr>
                    <th scope="row"><label for="wa_template">Sentence Template</label></th>
                    <td>
                        <input type="text" id="wa_template"
                               name="<?= WHEREABOUTS_OPTIONS_KEY ?>[template]"
                               value="<?= $template ?>"
                               class="large-text"
                               spellcheck="false" />

                        <p class="description" style="margin-top:6px;">
                            Available tokens:
                            <code class="wa-token" data-token="{time}">{time}</code>
                            <code class="wa-token" data-token="{time:short}">{time:short}</code>
                            <code class="wa-token" data-token="{time:long}">{time:long}</code>
                            <code class="wa-token" data-token="{city}">{city}</code>
                            <code class="wa-token" data-token="{condition}">{condition}</code>
                            <code class="wa-token" data-token="{temp}">{temp}</code>
                            <code class="wa-token" data-token="{temp:number}">{temp:number}</code>
                            <code class="wa-token" data-token="{temp:long}">{temp:long}</code>
                            — click any token to insert it.
                        </p>

                        <!-- Live template preview -->
                        <div style="margin-top:10px;">
                            <strong style="font-size:.85em;text-transform:uppercase;letter-spacing:.05em;color:#777;">Live Preview</strong>
                            <div id="wa-template-preview" style="margin-top:6px;padding:10px 14px;background:#f9f9f9;border:1px solid #ddd;border-left:3px solid #2271b1;border-radius:3px;font-style:italic;min-height:2em;"></div>
                        </div>
                    </td>
                </tr>

            </table>

            <?php submit_button( 'Save Settings' ); ?>
        </form>

        <!-- CACHE -->
        <hr />
        <h2>Weather Cache</h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">Status</th>
                <td>
                    <code><?= esc_html( $cache_status ) ?></code>
                    <p class="description">30-minute cache, auto-cleared when settings are saved.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Force Refresh</th>
                <td>
                    <form method="post">
                        <?php wp_nonce_field( 'wa_bust_cache', 'wa_cache_nonce' ); ?>
                        <input type="hidden" name="wa_action" value="bust_cache" />
                        <button type="submit" class="button button-secondary">🔄 Clear Cache Now</button>
                    </form>
                    <p class="description">Forces the next page load to fetch fresh weather from Open-Meteo.</p>
                </td>
            </tr>
        </table>

        <!-- FULL PREVIEW -->
        <hr />
        <h2>Full Block Preview</h2>
        <p>Rendered output using your saved settings (weather may be from cache):</p>
        <div style="background:#f9f9f9;border:1px solid #ddd;border-left:4px solid #2271b1;padding:16px 20px;border-radius:4px;font-size:1.1em;font-style:italic;">
            <?php echo whereabouts_render_block( [ 'tagName' => 'p' ] ); ?>
        </div>
    </div>

    <script>
    (function () {
        /* ---- Geocoder ---- */
        const geocodeBtn  = document.getElementById('wa-geocode-btn');
        const cityInput   = document.getElementById('wa_city');
        const latInput    = document.getElementById('wa_lat');
        const lonInput    = document.getElementById('wa_lon');
        const picklist    = document.getElementById('wa-picklist');
        const pickOptions = document.getElementById('wa-picklist-options');
        const confirmed   = document.getElementById('wa-geocode-confirmed');
        const geoLabel    = document.getElementById('wa-geo-label');
        const geoError    = document.getElementById('wa-geocode-error');
        const storedCoords = document.getElementById('wa-stored-coords');
        const verifyLink  = document.getElementById('wa-verify-link');

        function confirmPlace(place, label) {
            latInput.value  = place.latitude;
            lonInput.value  = place.longitude;
            cityInput.value = place.name;

            storedCoords.textContent = `Lat: ${place.latitude}, Lon: ${place.longitude}`;
            verifyLink.href = `https://open-meteo.com/en/docs#latitude=${place.latitude}&longitude=${place.longitude}`;
            geoLabel.textContent = label;

            picklist.style.display   = 'none';
            confirmed.style.display  = 'block';
        }

        geocodeBtn.addEventListener('click', async function () {
            const rawInput = cityInput.value.trim();
            if (!rawInput) { alert('Please enter a city name first.'); return; }

            const query   = rawInput.split(',')[0].trim();
            const context = rawInput.split(',').slice(1).join(',').trim().toLowerCase();

            geocodeBtn.disabled    = true;
            geocodeBtn.textContent = '⏳ Looking up…';
            picklist.style.display   = 'none';
            confirmed.style.display  = 'none';
            geoError.style.display   = 'none';
            pickOptions.innerHTML    = '';

            try {
                const res  = await fetch(`https://geocoding-api.open-meteo.com/v1/search?name=${encodeURIComponent(query)}&count=5&language=en&format=json`);
                const data = await res.json();

                if (!data.results || data.results.length === 0)
                    throw new Error(`No results found for "${query}". Try a different spelling.`);

                let results = data.results;
                if (context) {
                    const matched   = results.filter(p => [p.admin1, p.admin2, p.country, p.country_code].filter(Boolean).some(v => v.toLowerCase().includes(context)));
                    const unmatched = results.filter(p => !matched.includes(p));
                    results = [...matched, ...unmatched];
                }

                if (results.length === 1) {
                    const place = results[0];
                    confirmPlace(place, [place.name, place.admin1, place.country].filter(Boolean).join(', '));
                } else {
                    results.forEach(place => {
                        const label = [place.name, place.admin1, place.country].filter(Boolean).join(', ');
                        const pop   = place.population ? ` · pop. ${Number(place.population).toLocaleString()}` : '';
                        const btn   = document.createElement('button');
                        btn.type      = 'button';
                        btn.className = 'button button-secondary';
                        btn.style.cssText = 'text-align:left;height:auto;padding:6px 12px;white-space:normal;';
                        btn.innerHTML = `<strong>${label}</strong><span style="color:#888;font-size:.9em;margin-left:8px;">${place.latitude}, ${place.longitude}${pop}</span>`;
                        btn.addEventListener('click', () => confirmPlace(place, label));
                        pickOptions.appendChild(btn);
                    });
                    picklist.style.display = 'block';
                }
            } catch (e) {
                geoError.textContent  = '❌ ' + e.message;
                geoError.style.display = 'block';
            } finally {
                geocodeBtn.disabled    = false;
                geocodeBtn.textContent = '🔍 Look Up Location';
            }
        });

        /* ---- Token click-to-insert ---- */
        document.querySelectorAll('.wa-token').forEach(function (chip) {
            chip.style.cursor = 'pointer';
            chip.title = 'Click to insert';
            chip.addEventListener('click', function () {
                const token    = chip.dataset.token;
                const field    = document.getElementById('wa_template');
                const start    = field.selectionStart;
                const end      = field.selectionEnd;
                const current  = field.value;
                field.value    = current.slice(0, start) + token + current.slice(end);
                field.selectionStart = field.selectionEnd = start + token.length;
                field.focus();
                field.dispatchEvent(new Event('input'));
            });
        });

        /* ---- Live template preview ---- */
        const templateInput = document.getElementById('wa_template');
        const previewBox    = document.getElementById('wa-template-preview');

        // Token sample values — mirrors what the PHP renderer produces
        const samples = {
            '{time}'        : 'half past nine',
            '{time:short}'  : 'half past nine',
            '{time:long}'   : 'half past nine',
            '{city}'        : <?= json_encode( $now_url ? '<a href="' . esc_js( $now_url ) . '" class="whereabouts-city-link">' . esc_js( $city ) . '</a>' : $city ) ?>,
            '{condition}'   : 'overcast',
            '{temp}'        : <?= json_encode( $use_celsius ? '12°C' : '54°F' ) ?>,
            '{temp:number}' : '54°',
            '{temp:long}'   : '54 degrees',
        };

        function updatePreview() {
            let text = templateInput.value;
            Object.entries(samples).forEach(([token, val]) => {
                text = text.split(token).join(val);
            });
            previewBox.innerHTML = text || '<em style="color:#aaa">Start typing your template above…</em>';
        }

        templateInput.addEventListener('input', updatePreview);
        updatePreview(); // run on load
    })();
    </script>
    <?php
}

/* ------------------------------------------------------------------ */
/*  Handle "Clear Cache Now" POST                                       */
/* ------------------------------------------------------------------ */
add_action( 'admin_init', function () {
    if (
        isset( $_POST['wa_action'], $_POST['wa_cache_nonce'] ) &&
        $_POST['wa_action'] === 'bust_cache' &&
        wp_verify_nonce( $_POST['wa_cache_nonce'], 'wa_bust_cache' ) &&
        current_user_can( 'manage_options' )
    ) {
        whereabouts_bust_cache();
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-success is-dismissible"><p>✅ Weather cache cleared — next page load fetches fresh data.</p></div>';
        } );
    }
} );

/* ------------------------------------------------------------------ */
/*  REST endpoint — Gutenberg editor preview                            */
/* ------------------------------------------------------------------ */
add_action( 'rest_api_init', function () {
    register_rest_route( 'whereabouts/v1', '/preview', [
        'methods'             => 'GET',
        'callback'            => function ( WP_REST_Request $request ) {
            $tag = sanitize_key( $request->get_param( 'tag' ) ?: 'p' );
            return [ 'html' => whereabouts_render_block( [ 'tagName' => $tag ] ) ];
        },
        'permission_callback' => fn() => current_user_can( 'edit_posts' ),
        'args'                => [
            'tag' => [ 'type' => 'string', 'default' => 'p', 'sanitize_callback' => 'sanitize_key' ],
        ],
    ] );
} );
