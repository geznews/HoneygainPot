<?php
/**
 * Main plugin functionality for BTMC Gold Price.
 */

if ( ! class_exists( 'Btmc_Gold_Price' ) ) {
class Btmc_Gold_Price {
const OPTION_NAME    = 'btmc_gold_price_settings';
const TRANSIENT_NAME = 'btmc_gold_price_cache';

/**
 * Singleton instance.
 *
 * @var Btmc_Gold_Price
 */
private static $instance;

/**
 * Cached settings.
 *
 * @var array
 */
private $settings = [];

/**
 * Plugin defaults.
 *
 * @var array
 */
private $defaults = [
'endpoint'        => 'https://apics2.btmc.vn/api/price/gold',
'token'           => '',
'api_key'         => '',
'cache_ttl'       => 60,
'timeout'         => 10,
'disable_style'   => 0,
'currency_suffix' => 'VND/lượng',
];

/**
 * Returns the singleton instance.
 *
 * @return Btmc_Gold_Price
 */
public static function instance() {
if ( null === self::$instance ) {
self::$instance = new self();
}

return self::$instance;
}

/**
 * Constructor.
 */
private function __construct() {
add_action( 'init', [ $this, 'load_textdomain' ] );
add_action( 'init', [ $this, 'register_assets' ] );
add_shortcode( 'btmc_gold_price', [ $this, 'render_shortcode' ] );

if ( is_admin() ) {
require_once BTMC_GOLD_PRICE_PLUGIN_DIR . 'includes/class-btmc-gold-price-admin.php';
Btmc_Gold_Price_Admin::instance( $this );
}
}

/**
 * Load plugin translations.
 */
public function load_textdomain() {
load_plugin_textdomain( 'btmc-gold-price', false, dirname( plugin_basename( BTMC_GOLD_PRICE_PLUGIN_FILE ) ) . '/languages/' );
}

/**
 * Register plugin assets.
 */
public function register_assets() {
wp_register_style(
'btmc-gold-price',
BTMC_GOLD_PRICE_PLUGIN_URL . 'assets/css/btmc-gold-price.css',
[],
'1.0.0'
);
}

/**
 * Get settings merged with defaults.
 *
 * @return array
 */
public function get_settings() {
	if ( empty( $this->settings ) ) {
		$saved          = get_option( self::OPTION_NAME, [] );
		$this->settings = $this->normalize_settings( $saved );
	}

	return $this->settings;
}

/**
 * Update the internal settings cache.
 *
 * @param array|null $settings Settings to store or null to flush cache.
 */
public function update_settings_cache( $settings = null ) {
	if ( null === $settings ) {
		$this->settings = [];

		return;
	}

	$this->settings = $this->normalize_settings( $settings );
}

/**
 * Normalize settings array.
 *
 * @param array $settings Raw settings.
 *
 * @return array
 */
private function normalize_settings( array $settings ) {
	$settings = wp_parse_args( $settings, $this->defaults );
	$settings['cache_ttl']     = max( 0, absint( $settings['cache_ttl'] ) );
	$settings['timeout']       = max( 1, absint( $settings['timeout'] ) );
	$settings['disable_style'] = empty( $settings['disable_style'] ) ? 0 : 1;

	return $settings;
}

/**
 * Fetch price data from API.
 *
 * @param bool $force_refresh Whether to bypass the cache.
 *
 * @return array|WP_Error
 */
public function fetch_prices( $force_refresh = false ) {
	$settings = $this->get_settings();
	$endpoint = apply_filters( 'btmc_gold_price_endpoint', $settings['endpoint'], $settings );
	$cache_key = $this->get_cache_key( $endpoint );

	if ( ! $force_refresh ) {
		$cached = get_transient( $cache_key );
		if ( ! empty( $cached ) && is_array( $cached ) ) {
			return $cached;
		}
	}

	if ( empty( $endpoint ) ) {
		return new WP_Error( 'btmc_gold_price_endpoint_missing', __( 'Đường dẫn API chưa được cấu hình.', 'btmc-gold-price' ) );
	}

	$response = wp_remote_get(
		$endpoint,
		$this->build_request_args( $settings )
	);

if ( is_wp_error( $response ) ) {
return $response;
}

$status = wp_remote_retrieve_response_code( $response );

if ( 200 !== (int) $status ) {
return new WP_Error(
'btmc_gold_price_http_error',
sprintf(
/* translators: %d: HTTP status code */
__( 'Máy chủ BTMC trả về mã lỗi HTTP %d.', 'btmc-gold-price' ),
(int) $status
)
);
}

$body = wp_remote_retrieve_body( $response );
$data = json_decode( $body, true );

if ( null === $data ) {
return new WP_Error( 'btmc_gold_price_invalid_json', __( 'Không thể đọc dữ liệu JSON từ API.', 'btmc-gold-price' ) );
}

$items        = $this->extract_items( $data );
$normalized   = $this->normalize_items( $items );
$normalized   = apply_filters( 'btmc_gold_price_items', $normalized, $items, $data, $settings );
$result       = [
'items'        => $normalized,
'retrieved_at' => time(),
'source'       => $endpoint,
'raw'          => $data,
];

if ( $settings['cache_ttl'] > 0 ) {
set_transient( $cache_key, $result, (int) $settings['cache_ttl'] );
}

return $result;
}

/**
 * Build request arguments for wp_remote_get.
 *
 * @param array $settings Plugin settings.
 *
 * @return array
 */
private function build_request_args( array $settings ) {
	$headers = [
		'Accept'     => 'application/json',
		'User-Agent' => 'BTMC Gold Price Plugin/1.0.0; ' . home_url(),
	];

	if ( ! empty( $settings['token'] ) ) {
		$headers['token'] = $settings['token'];
	}

	if ( ! empty( $settings['api_key'] ) ) {
		$headers['X-Api-Key'] = $settings['api_key'];
	}

	$args = [
		'timeout' => (int) $settings['timeout'],
		'headers' => $headers,
	];

	return apply_filters( 'btmc_gold_price_request_args', $args, $settings );
}
/**
 * Extract items array from payload.
 *
 * @param mixed $payload API payload.
 *
 * @return array
 */
private function extract_items( $payload ) {
if ( empty( $payload ) ) {
return [];
}

if ( isset( $payload['data'] ) && is_array( $payload['data'] ) ) {
return $payload['data'];
}

if ( isset( $payload['Data'] ) && is_array( $payload['Data'] ) ) {
return $payload['Data'];
}

if ( isset( $payload['result'] ) && is_array( $payload['result'] ) ) {
return $payload['result'];
}

if ( array_keys( $payload ) === range( 0, count( $payload ) - 1 ) ) {
return $payload;
}

return [ $payload ];
}

/**
 * Normalize API items.
 *
 * @param array $items Raw items.
 *
 * @return array
 */
private function normalize_items( array $items ) {
$normalized = [];

foreach ( $items as $item ) {
if ( ! is_array( $item ) ) {
continue;
}

$name       = $this->pick_first( $item, [ 'name', 'product', 'type', 'brand', 'title', 'ten', 'Loai' ] );
$buy        = $this->pick_first( $item, [ 'buy', 'gia_mua', 'GiaMua', 'buyPrice', 'Buy', 'Bid', 'mua' ] );
$sell       = $this->pick_first( $item, [ 'sell', 'gia_ban', 'GiaBan', 'sellPrice', 'Sell', 'Ask', 'ban' ] );
$unit       = $this->pick_first( $item, [ 'unit', 'donvi', 'unit_name', 'don_vi' ] );
$change_buy = $this->pick_first( $item, [ 'buy_change', 'buyChange', 'change_buy', 'chenhlech_mua', 'ChangeBuy' ] );
$change_sell = $this->pick_first( $item, [ 'sell_change', 'sellChange', 'change_sell', 'chenhlech_ban', 'ChangeSell' ] );
$timestamp  = $this->pick_first( $item, [ 'updated', 'updated_at', 'last_update', 'lastUpdated', 'time', 'timestamp', 'DateTime' ] );

if ( empty( $name ) && empty( $buy ) && empty( $sell ) ) {
continue;
}

$normalized[] = [
'name'        => $name ?: __( 'Không xác định', 'btmc-gold-price' ),
'buy'         => $this->normalize_number( $buy ),
'sell'        => $this->normalize_number( $sell ),
'change_buy'  => $this->normalize_number( $change_buy ),
'change_sell' => $this->normalize_number( $change_sell ),
'unit'        => $unit,
'timestamp'   => $this->normalize_timestamp( $timestamp ),
'raw'         => $item,
];
}

return $normalized;
}

/**
 * Normalize timestamp into unix timestamp when possible.
 *
 * @param mixed $value Raw value.
 *
 * @return int|null
 */
private function normalize_timestamp( $value ) {
if ( empty( $value ) ) {
return null;
}

if ( is_numeric( $value ) ) {
$value = (int) $value;

return ( $value > 10_000_000_000 ) ? (int) ( $value / 1000 ) : $value;
}

if ( is_string( $value ) ) {
$ts = strtotime( $value );

return false !== $ts ? $ts : null;
}

return null;
}

/**
 * Attempt to convert numeric-like value to float.
 *
 * @param mixed $value Raw value.
 *
 * @return float|string|null
 */
private function normalize_number( $value ) {
if ( null === $value || '' === $value ) {
return null;
}

if ( is_numeric( $value ) ) {
return (float) $value;
}

if ( is_string( $value ) ) {
$normalized = preg_replace( '/[^0-9,.-]/', '', $value );

if ( '' === $normalized ) {
return null;
}

// Replace comma with dot when comma used as decimal separator.
if ( strpos( $normalized, ',' ) !== false && strpos( $normalized, '.' ) === false ) {
$normalized = str_replace( ',', '.', $normalized );
} elseif ( substr_count( $normalized, '.' ) > 1 && strpos( $normalized, ',' ) === false ) {
$normalized = str_replace( '.', '', $normalized );
}

if ( is_numeric( $normalized ) ) {
return (float) $normalized;
}

return $value;
}

return $value;
}

/**
 * Render shortcode output.
 *
 * @param array $atts Shortcode attributes.
 *
 * @return string
 */
public function render_shortcode( $atts ) {
$atts = shortcode_atts(
[
'product'             => '',
'view'                => 'table',
'show_change'         => 'true',
'show_updated'        => 'true',
'class'               => '',
'currency_suffix'     => '',
'force_refresh'       => 'false',
'decimals'            => '0',
'decimal_separator'   => ',',
'thousands_separator' => '.',
],
$atts,
'btmc_gold_price'
);

$settings = $this->get_settings();

if ( empty( $atts['currency_suffix'] ) ) {
$atts['currency_suffix'] = $settings['currency_suffix'];
}

$force_refresh = filter_var( $atts['force_refresh'], FILTER_VALIDATE_BOOLEAN );
$result        = $this->fetch_prices( $force_refresh );

if ( is_wp_error( $result ) ) {
return $this->render_notice( $result->get_error_message(), 'error' );
}

$items = $result['items'];

if ( ! empty( $atts['product'] ) ) {
$items = array_filter(
$items,
static function ( $item ) use ( $atts ) {
return false !== stripos( $item['name'], $atts['product'] );
}
);
}

$items = apply_filters( 'btmc_gold_price_display_items', $items, $atts, $result, $settings );

if ( empty( $items ) ) {
return $this->render_notice( __( 'Không tìm thấy dữ liệu giá vàng.', 'btmc-gold-price' ), 'warning' );
}

if ( empty( $settings['disable_style'] ) ) {
wp_enqueue_style( 'btmc-gold-price' );
}

ob_start();

$classes = [ 'btmc-gold-price' ];

if ( ! empty( $atts['class'] ) ) {
$classes[] = sanitize_html_class( $atts['class'] );
}

printf( '<div class="%s">', esc_attr( implode( ' ', $classes ) ) );

if ( 'list' === strtolower( $atts['view'] ) ) {
$this->render_list_view( $items, $atts );
} else {
$this->render_table_view( $items, $atts );
}

if ( filter_var( $atts['show_updated'], FILTER_VALIDATE_BOOLEAN ) ) {
$this->render_updated_meta( $items, $result );
}

echo '</div>';

return ob_get_clean();
}

/**
 * Render a notice block.
 *
 * @param string $message Notice message.
 * @param string $type    Notice type.
 *
 * @return string
 */
private function render_notice( $message, $type = 'info' ) {
$message = esc_html( $message );

return sprintf( '<div class="btmc-gold-price__notice btmc-gold-price__notice--%s">%s</div>', esc_attr( $type ), $message );
}

/**
 * Render table view.
 *
 * @param array $items Normalized items.
 * @param array $atts  Shortcode attributes.
 */
private function render_table_view( array $items, array $atts ) {
	$decimals            = absint( $atts['decimals'] );
	$decimal_separator   = $atts['decimal_separator'];
	$thousands_separator = $atts['thousands_separator'];
	$show_change         = filter_var( $atts['show_change'], FILTER_VALIDATE_BOOLEAN );
	$currency_suffix     = $atts['currency_suffix'];

	echo '<table class="btmc-gold-price__table">';
	echo '<thead><tr>';
	echo '<th>' . esc_html__( 'Loại vàng', 'btmc-gold-price' ) . '</th>';
	echo '<th>' . esc_html__( 'Giá mua', 'btmc-gold-price' ) . '</th>';
	echo '<th>' . esc_html__( 'Giá bán', 'btmc-gold-price' ) . '</th>';

	if ( $show_change ) {
		echo '<th>' . esc_html__( 'Chênh lệch mua', 'btmc-gold-price' ) . '</th>';
		echo '<th>' . esc_html__( 'Chênh lệch bán', 'btmc-gold-price' ) . '</th>';
	}

	echo '<th>' . esc_html__( 'Đơn vị', 'btmc-gold-price' ) . '</th>';
	echo '</tr></thead><tbody>';

	$name_label        = __( 'Loại vàng', 'btmc-gold-price' );
	$buy_label         = __( 'Giá mua', 'btmc-gold-price' );
	$sell_label        = __( 'Giá bán', 'btmc-gold-price' );
	$change_buy_label  = __( 'Chênh lệch mua', 'btmc-gold-price' );
	$change_sell_label = __( 'Chênh lệch bán', 'btmc-gold-price' );
	$unit_label        = __( 'Đơn vị', 'btmc-gold-price' );

	foreach ( $items as $item ) {
		echo '<tr>';
		printf( '<td data-label="%1$s">%2$s</td>', esc_attr( $name_label ), esc_html( $item['name'] ) );
		printf( '<td data-label="%1$s">%2$s</td>', esc_attr( $buy_label ), $this->format_price( $item['buy'], $decimals, $decimal_separator, $thousands_separator, $currency_suffix ) );
		printf( '<td data-label="%1$s">%2$s</td>', esc_attr( $sell_label ), $this->format_price( $item['sell'], $decimals, $decimal_separator, $thousands_separator, $currency_suffix ) );

		if ( $show_change ) {
			printf( '<td data-label="%1$s">%2$s</td>', esc_attr( $change_buy_label ), $this->format_change( $item['change_buy'], $decimals, $decimal_separator, $thousands_separator ) );
			printf( '<td data-label="%1$s">%2$s</td>', esc_attr( $change_sell_label ), $this->format_change( $item['change_sell'], $decimals, $decimal_separator, $thousands_separator ) );
		}

		printf( '<td data-label="%1$s">%2$s</td>', esc_attr( $unit_label ), esc_html( $item['unit'] ?: $currency_suffix ) );
		echo '</tr>';
	}

	echo '</tbody></table>';

}

/**
 * Render list view.
 *
 * @param array $items Normalized items.
 * @param array $atts  Shortcode attributes.
 */
private function render_list_view( array $items, array $atts ) {
$decimals            = absint( $atts['decimals'] );
$decimal_separator   = $atts['decimal_separator'];
$thousands_separator = $atts['thousands_separator'];
$show_change         = filter_var( $atts['show_change'], FILTER_VALIDATE_BOOLEAN );
$currency_suffix     = $atts['currency_suffix'];

echo '<ul class="btmc-gold-price__list">';

foreach ( $items as $item ) {
echo '<li class="btmc-gold-price__list-item">';
echo '<div class="btmc-gold-price__item-header">' . esc_html( $item['name'] ) . '</div>';
echo '<div class="btmc-gold-price__item-body">';
echo '<span class="btmc-gold-price__item-label">' . esc_html__( 'Mua:', 'btmc-gold-price' ) . '</span> ' . $this->format_price( $item['buy'], $decimals, $decimal_separator, $thousands_separator, $currency_suffix );
echo ' &nbsp; ';
echo '<span class="btmc-gold-price__item-label">' . esc_html__( 'Bán:', 'btmc-gold-price' ) . '</span> ' . $this->format_price( $item['sell'], $decimals, $decimal_separator, $thousands_separator, $currency_suffix );

if ( $show_change ) {
echo '<div class="btmc-gold-price__change">';
echo '<span>' . esc_html__( 'Chênh mua:', 'btmc-gold-price' ) . ' ' . $this->format_change( $item['change_buy'], $decimals, $decimal_separator, $thousands_separator ) . '</span>';
echo '<span>' . esc_html__( 'Chênh bán:', 'btmc-gold-price' ) . ' ' . $this->format_change( $item['change_sell'], $decimals, $decimal_separator, $thousands_separator ) . '</span>';
echo '</div>';
}

echo '</div>';
if ( ! empty( $item['unit'] ) ) {
echo '<div class="btmc-gold-price__unit">' . esc_html( $item['unit'] ) . '</div>';
}
echo '</li>';
}

echo '</ul>';
}

/**
 * Render update meta block.
 *
 * @param array $items  Normalized items.
 * @param array $result API result.
 */
private function render_updated_meta( array $items, array $result ) {
$timestamps = array_filter(
array_map(
static function ( $item ) {
return $item['timestamp'] ?? null;
},
$items
)
);

if ( ! empty( $timestamps ) ) {
$last_updated = max( $timestamps );
} else {
$last_updated = $result['retrieved_at'];
}

$formatted_time = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_updated );
$relative_time  = human_time_diff( $last_updated, current_time( 'timestamp' ) );

echo '<div class="btmc-gold-price__meta">';
echo '<span class="btmc-gold-price__meta-label">' . esc_html__( 'Cập nhật:', 'btmc-gold-price' ) . '</span> ';
echo '<span class="btmc-gold-price__meta-time" title="' . esc_attr( $formatted_time ) . '">' . esc_html( sprintf( __( '%s trước', 'btmc-gold-price' ), $relative_time ) ) . '</span>';
echo '</div>';
}

/**
 * Format price value.
 *
 * @param mixed  $value               Numeric value.
 * @param int    $decimals            Decimal places.
 * @param string $decimal_separator   Decimal separator.
 * @param string $thousands_separator Thousands separator.
 * @param string $suffix              Currency suffix.
 *
 * @return string
 */
private function format_price( $value, $decimals, $decimal_separator, $thousands_separator, $suffix ) {
if ( is_numeric( $value ) ) {
$formatted = number_format( (float) $value, $decimals, $decimal_separator, $thousands_separator );
} elseif ( is_string( $value ) && '' !== $value ) {
$formatted = esc_html( $value );
} else {
return '<span class="btmc-gold-price__value btmc-gold-price__value--empty">&mdash;</span>';
}

$formatted = sprintf( '<span class="btmc-gold-price__value">%s</span>', esc_html( $formatted ) );

if ( ! empty( $suffix ) ) {
$formatted .= sprintf( ' <span class="btmc-gold-price__suffix">%s</span>', esc_html( $suffix ) );
}

return $formatted;
}

/**
 * Format change value with +/- indicator.
 *
 * @param mixed  $value               Change value.
 * @param int    $decimals            Decimal places.
 * @param string $decimal_separator   Decimal separator.
 * @param string $thousands_separator Thousands separator.
 *
 * @return string
 */
private function format_change( $value, $decimals, $decimal_separator, $thousands_separator ) {
if ( is_numeric( $value ) ) {
$formatted = number_format( (float) $value, $decimals, $decimal_separator, $thousands_separator );
$class     = $value > 0 ? 'positive' : ( ( $value < 0 ) ? 'negative' : 'neutral' );
$prefix    = $value > 0 ? '+' : '';

return sprintf( '<span class="btmc-gold-price__change btmc-gold-price__change--%s">%s%s</span>', esc_attr( $class ), esc_html( $prefix ), esc_html( $formatted ) );
}

if ( is_string( $value ) && '' !== $value ) {
return sprintf( '<span class="btmc-gold-price__change">%s</span>', esc_html( $value ) );
}

return '<span class="btmc-gold-price__change btmc-gold-price__change--neutral">&mdash;</span>';
}

/**
 * Retrieve cache key based on endpoint.
 *
 * @param string $endpoint API endpoint.
 *
 * @return string
 */
private function get_cache_key( $endpoint ) {
return self::TRANSIENT_NAME . '_' . md5( $endpoint );
}

/**
 * Pick first available key from array.
 *
 * @param array $item Item data.
 * @param array $keys Possible keys.
 *
 * @return mixed|null
 */
private function pick_first( array $item, array $keys ) {
foreach ( $keys as $key ) {
if ( isset( $item[ $key ] ) && '' !== $item[ $key ] ) {
return $item[ $key ];
}

$lower = strtolower( $key );
if ( isset( $item[ $lower ] ) && '' !== $item[ $lower ] ) {
return $item[ $lower ];
}

$camel = lcfirst( str_replace( ' ', '', ucwords( str_replace( '_', ' ', $key ) ) ) );
if ( isset( $item[ $camel ] ) && '' !== $item[ $camel ] ) {
return $item[ $camel ];
}
}

return null;
}
}
}
