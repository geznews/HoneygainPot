<?php
/**
 * Admin settings for BTMC Gold Price plugin.
 */

if ( ! class_exists( 'Btmc_Gold_Price_Admin' ) ) {
class Btmc_Gold_Price_Admin {
/**
 * Singleton instance.
 *
 * @var Btmc_Gold_Price_Admin
 */
private static $instance;

/**
 * Parent plugin instance.
 *
 * @var Btmc_Gold_Price
 */
private $plugin;

/**
 * Get singleton instance.
 *
 * @param Btmc_Gold_Price $plugin Plugin instance.
 *
 * @return Btmc_Gold_Price_Admin
 */
public static function instance( Btmc_Gold_Price $plugin ) {
if ( null === self::$instance ) {
self::$instance = new self( $plugin );
}

return self::$instance;
}

/**
 * Constructor.
 *
 * @param Btmc_Gold_Price $plugin Plugin instance.
 */
private function __construct( Btmc_Gold_Price $plugin ) {
$this->plugin = $plugin;

add_action( 'admin_init', [ $this, 'register_settings' ] );
add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
}

/**
 * Register plugin settings.
 */
public function register_settings() {
register_setting( 'btmc-gold-price', Btmc_Gold_Price::OPTION_NAME, [ $this, 'sanitize_settings' ] );

add_settings_section(
'btmc_gold_price_api',
__( 'Cấu hình API', 'btmc-gold-price' ),
[ $this, 'render_api_section_description' ],
'btmc-gold-price'
);

add_settings_field(
'endpoint',
__( 'Endpoint', 'btmc-gold-price' ),
[ $this, 'render_endpoint_field' ],
'btmc-gold-price',
'btmc_gold_price_api'
);

add_settings_field(
'token',
__( 'Token', 'btmc-gold-price' ),
[ $this, 'render_token_field' ],
'btmc-gold-price',
'btmc_gold_price_api'
);

add_settings_field(
'api_key',
__( 'API Key', 'btmc-gold-price' ),
[ $this, 'render_api_key_field' ],
'btmc-gold-price',
'btmc_gold_price_api'
);

add_settings_field(
'cache_ttl',
__( 'Thời gian cache (giây)', 'btmc-gold-price' ),
[ $this, 'render_cache_field' ],
'btmc-gold-price',
'btmc_gold_price_api'
);

add_settings_field(
'timeout',
__( 'Timeout (giây)', 'btmc-gold-price' ),
[ $this, 'render_timeout_field' ],
'btmc-gold-price',
'btmc_gold_price_api'
);

add_settings_section(
'btmc_gold_price_display',
__( 'Hiển thị', 'btmc-gold-price' ),
'__return_false',
'btmc-gold-price'
);

add_settings_field(
'currency_suffix',
__( 'Hậu tố tiền tệ', 'btmc-gold-price' ),
[ $this, 'render_currency_field' ],
'btmc-gold-price',
'btmc_gold_price_display'
);

add_settings_field(
'disable_style',
__( 'Tắt CSS mặc định', 'btmc-gold-price' ),
[ $this, 'render_disable_style_field' ],
'btmc-gold-price',
'btmc_gold_price_display'
);
}

/**
 * Add settings page to menu.
 */
public function add_menu_page() {
add_options_page(
__( 'BTMC Gold Price', 'btmc-gold-price' ),
__( 'BTMC Gold Price', 'btmc-gold-price' ),
'manage_options',
'btmc-gold-price',
[ $this, 'render_settings_page' ]
);
}

/**
 * Sanitize settings input.
 *
 * @param array $input Raw input.
 *
 * @return array
 */
public function sanitize_settings( $input ) {
$previous  = $this->plugin->get_settings();
$settings  = $previous;

$settings['endpoint']        = isset( $input['endpoint'] ) ? esc_url_raw( trim( $input['endpoint'] ) ) : $settings['endpoint'];
$settings['token']           = isset( $input['token'] ) ? sanitize_text_field( $input['token'] ) : '';
$settings['api_key']         = isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '';
$settings['cache_ttl']       = isset( $input['cache_ttl'] ) ? max( 0, absint( $input['cache_ttl'] ) ) : $settings['cache_ttl'];
$settings['timeout']         = isset( $input['timeout'] ) ? max( 1, absint( $input['timeout'] ) ) : $settings['timeout'];
$settings['currency_suffix'] = isset( $input['currency_suffix'] ) ? sanitize_text_field( $input['currency_suffix'] ) : $settings['currency_suffix'];
$settings['disable_style']   = isset( $input['disable_style'] ) ? (int) (bool) $input['disable_style'] : 0;

if ( $settings['endpoint'] !== $previous['endpoint'] ) {
if ( ! empty( $previous['endpoint'] ) ) {
$old_key = Btmc_Gold_Price::TRANSIENT_NAME . '_' . md5( $previous['endpoint'] );
delete_transient( $old_key );
}

if ( ! empty( $settings['endpoint'] ) ) {
$next_key = Btmc_Gold_Price::TRANSIENT_NAME . '_' . md5( $settings['endpoint'] );
delete_transient( $next_key );
}
}

$this->plugin->update_settings_cache( $settings );

return $settings;
}

/**
 * Render API section description.
 */
public function render_api_section_description() {
echo '<p>' . esc_html__( 'Nhập endpoint và thông tin xác thực được BTMC cung cấp.', 'btmc-gold-price' ) . '</p>';
}

/**
 * Render endpoint field.
 */
public function render_endpoint_field() {
$settings = $this->plugin->get_settings();
printf(
'<input type="url" class="regular-text" name="%1$s[endpoint]" value="%2$s" placeholder="https://..." />',
esc_attr( Btmc_Gold_Price::OPTION_NAME ),
esc_attr( $settings['endpoint'] )
);
}

/**
 * Render token field.
 */
public function render_token_field() {
$settings = $this->plugin->get_settings();
printf(
'<input type="text" class="regular-text" name="%1$s[token]" value="%2$s" />',
esc_attr( Btmc_Gold_Price::OPTION_NAME ),
esc_attr( $settings['token'] )
);
echo '<p class="description">' . esc_html__( 'Một số endpoint yêu cầu header token.', 'btmc-gold-price' ) . '</p>';
}

/**
 * Render API key field.
 */
public function render_api_key_field() {
$settings = $this->plugin->get_settings();
printf(
'<input type="text" class="regular-text" name="%1$s[api_key]" value="%2$s" />',
esc_attr( Btmc_Gold_Price::OPTION_NAME ),
esc_attr( $settings['api_key'] )
);
echo '<p class="description">' . esc_html__( 'Nếu tài liệu yêu cầu X-Api-Key hãy nhập tại đây.', 'btmc-gold-price' ) . '</p>';
}

/**
 * Render cache field.
 */
public function render_cache_field() {
$settings = $this->plugin->get_settings();
printf(
'<input type="number" min="0" step="1" name="%1$s[cache_ttl]" value="%2$d" />',
esc_attr( Btmc_Gold_Price::OPTION_NAME ),
absint( $settings['cache_ttl'] )
);
echo '<p class="description">' . esc_html__( 'Nhập 0 để tắt cache.', 'btmc-gold-price' ) . '</p>';
}

/**
 * Render timeout field.
 */
public function render_timeout_field() {
$settings = $this->plugin->get_settings();
printf(
'<input type="number" min="1" step="1" name="%1$s[timeout]" value="%2$d" />',
esc_attr( Btmc_Gold_Price::OPTION_NAME ),
absint( $settings['timeout'] )
);
}

/**
 * Render currency suffix field.
 */
public function render_currency_field() {
$settings = $this->plugin->get_settings();
printf(
'<input type="text" class="regular-text" name="%1$s[currency_suffix]" value="%2$s" placeholder="VND/lượng" />',
esc_attr( Btmc_Gold_Price::OPTION_NAME ),
esc_attr( $settings['currency_suffix'] )
);
}

/**
 * Render disable style checkbox.
 */
public function render_disable_style_field() {
$settings = $this->plugin->get_settings();
printf(
'<label><input type="checkbox" name="%1$s[disable_style]" value="1" %2$s /> %3$s</label>',
esc_attr( Btmc_Gold_Price::OPTION_NAME ),
checked( 1, $settings['disable_style'], false ),
esc_html__( 'Không tải CSS mặc định trên frontend.', 'btmc-gold-price' )
);
}

/**
 * Render settings page.
 */
public function render_settings_page() {
if ( ! current_user_can( 'manage_options' ) ) {
return;
}
?>
<div class="wrap">
<h1><?php esc_html_e( 'BTMC Gold Price', 'btmc-gold-price' ); ?></h1>
<form action="options.php" method="post">
<?php
settings_fields( 'btmc-gold-price' );
do_settings_sections( 'btmc-gold-price' );
submit_button();
?>
</form>
</div>
<?php
}
}
}
