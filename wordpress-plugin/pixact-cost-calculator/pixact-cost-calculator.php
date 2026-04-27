<?php
/**
 * Plugin Name: Pixact Cost Calculator
 * Description: Embeds React cost calculator and saves leads via WordPress REST API.
 * Version: 1.0.0
 * Author: Pixact
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('pixact_format_lead_summary')) {
	function pixact_format_lead_summary($data): string {
		$data = is_array($data) ? $data : array();
		$answers = is_array($data['answers'] ?? null) ? $data['answers'] : array();

		$project_type = sanitize_text_field((string) ($data['project_type'] ?? $answers['project_type'] ?? $answers['projecttype'] ?? 'N/A'));
		$platform = sanitize_text_field((string) ($answers['platform'] ?? $answers['target_platform'] ?? 'Not specified'));
		$user_roles = $answers['user_roles'] ?? $answers['roles'] ?? array();
		$core_features = $answers['core_features'] ?? $answers['features'] ?? array();
		$advanced_features = $answers['advanced_features'] ?? $answers['addons'] ?? array();
		$design_level = sanitize_text_field((string) ($answers['design_level'] ?? $answers['design'] ?? 'Not specified'));
		$backend = sanitize_text_field((string) ($answers['backend'] ?? $data['complexity'] ?? 'Not specified'));
		$timeline = sanitize_text_field((string) ($data['timeline'] ?? $answers['timeline'] ?? 'Not specified'));
		$estimate_min = isset($data['estimate_min']) ? (int) $data['estimate_min'] : 0;
		$estimate_max = isset($data['estimate_max']) ? (int) $data['estimate_max'] : 0;

		$format_list = static function ($items): string {
			if (!is_array($items) || empty($items)) {
				return "- Not specified";
			}
			$sanitized = array();
			foreach ($items as $item) {
				$value = sanitize_text_field((string) $item);
				if ($value !== '') {
					$sanitized[] = '- ' . $value;
				}
			}
			return !empty($sanitized) ? implode("\n", $sanitized) : "- Not specified";
		};

		$format_inline = static function ($items): string {
			if (!is_array($items) || empty($items)) {
				return 'Not specified';
			}
			$sanitized = array();
			foreach ($items as $item) {
				$value = sanitize_text_field((string) $item);
				if ($value !== '') {
					$sanitized[] = $value;
				}
			}
			return !empty($sanitized) ? implode(', ', $sanitized) : 'Not specified';
		};

		return implode(
			"\n\n",
			array(
				'Project Type:' . "\n" . ($project_type !== '' ? $project_type : 'N/A'),
				'Platform:' . "\n" . ($platform !== '' ? $platform : 'Not specified'),
				'User Roles:' . "\n" . $format_inline($user_roles),
				'Core Features:' . "\n" . $format_list($core_features),
				'Advanced Features:' . "\n" . $format_list($advanced_features),
				'Design Level:' . "\n" . ($design_level !== '' ? $design_level : 'Not specified'),
				'Backend:' . "\n" . ($backend !== '' ? $backend : 'Not specified'),
				'Timeline:' . "\n" . ($timeline !== '' ? $timeline : 'Not specified'),
				'Estimated Cost:' . "\n" . '$' . number_format($estimate_min) . ' - $' . number_format($estimate_max),
			)
		);
	}
}

require_once plugin_dir_path(__FILE__) . 'includes/post-type.php';
require_once plugin_dir_path(__FILE__) . 'includes/email.php';
require_once plugin_dir_path(__FILE__) . 'includes/rest-api.php';

final class Pixact_Cost_Calculator_Plugin {
	private const MENU_SLUG = 'pixact-calculator-dashboard';
	private const CAPABILITY = 'manage_options';
	private const GENERAL_SETTINGS_GROUP = 'pixact_calculator_general_settings_group';
	private const GENERAL_SETTINGS_OPTION = 'pixact_calculator_general_settings';
	private const ADMIN_NOTIFICATION_EMAIL_OPTION = 'pixact_admin_notification_email';
	private const AI_SETTINGS_GROUP = 'pixact_calculator_ai_settings_group';
	private const AI_SETTINGS_OPTION = 'pixact_calculator_ai_settings';
	private const EMAIL_SETTINGS_OPTION = 'pixact_calculator_email_settings';
	private const INTEGRATIONS_SETTINGS_OPTION = 'pixact_calculator_integrations_settings';
	private const PRICING_RULES_OPTION = 'pixact_pricing_rules';
	private const CALCULATOR_CONFIG_OPTION = 'pixact_calculator_config';
	private Pixact_Cost_Calculator_Post_Type $post_type;
	private Pixact_Cost_Calculator_REST_API $rest_api;
	private bool $shortcode_rendered = false;
	private bool $assets_printed = false;
	private static bool $admin_ui_styles_printed = false;

	public function __construct() {
		$email_service = new Pixact_Cost_Calculator_Email(array($this, 'get_email_settings'));
		$this->post_type = new Pixact_Cost_Calculator_Post_Type();
		$this->rest_api = new Pixact_Cost_Calculator_REST_API(
			$email_service,
			array($this, 'get_pricing_rules'),
			array($this, 'get_calculator_flow_config'),
			array($this, 'get_ai_settings'),
			array($this, 'get_integrations_settings')
		);
	}

	public static function activate(): void {
		$plugin = new self();

		$plugin->post_type->register();

		if (false === get_option(self::PRICING_RULES_OPTION, false)) {
			update_option(
				self::PRICING_RULES_OPTION,
				wp_json_encode($plugin->get_default_pricing_rules())
			);
		}

		if (false === get_option(self::CALCULATOR_CONFIG_OPTION, false)) {
			update_option(
				self::CALCULATOR_CONFIG_OPTION,
				wp_json_encode($plugin->get_default_calculator_flow_config())
			);
		}

		if (false === get_option(self::EMAIL_SETTINGS_OPTION, false)) {
			update_option(
				self::EMAIL_SETTINGS_OPTION,
				$plugin->get_default_email_settings()
			);
		}

		if (false === get_option(self::INTEGRATIONS_SETTINGS_OPTION, false)) {
			update_option(
				self::INTEGRATIONS_SETTINGS_OPTION,
				$plugin->get_default_integrations_settings()
			);
		}
		if (false === get_option(self::ADMIN_NOTIFICATION_EMAIL_OPTION, false)) {
			update_option(self::ADMIN_NOTIFICATION_EMAIL_OPTION, '');
		}

		flush_rewrite_rules();
	}

	public function bootstrap(): void {
		add_action('init', array($this->post_type, 'register'));
		add_action('rest_api_init', array($this->rest_api, 'register_routes'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
		add_action('wp_footer', array($this, 'print_shortcode_assets'), 1);
		add_action('admin_menu', array($this, 'register_admin_menu'));
		add_action('admin_init', array($this, 'register_general_settings'));
		add_action('admin_init', array($this, 'register_ai_settings'));
		add_shortcode('pixact-cost-calculator', array($this, 'render_shortcode'));
	}

	public function render_shortcode(): string {
		$this->shortcode_rendered = true;
		$root_html = '<div id="pixact-calculator-root"></div>';
		return $root_html . $this->render_inline_assets_markup();
	}

	public function enqueue_assets(): void {
		if (!$this->should_enqueue()) {
			return;
		}
		$this->assets_printed = true;

		$js_file = 'assets/calculator/assets/index.js';
		$css_file = 'assets/calculator/assets/index.css';

		wp_enqueue_script(
			'pixact-calculator-js',
			plugin_dir_url(__FILE__) . $js_file,
			array(),
			null,
			true
		);
		wp_script_add_data('pixact-calculator-js', 'type', 'module');

		wp_enqueue_style(
			'pixact-calculator-css',
			plugin_dir_url(__FILE__) . $css_file
		);

		wp_localize_script(
			'pixact-calculator-js',
			'PixactCalculator',
			array(
				'restUrl' => site_url('/wp-json/pixact/v1/'),
				'nonce' => wp_create_nonce('wp_rest'),
				'siteUrl' => site_url('/'),
				'aiEnabled' => !empty($this->get_ai_settings()['enabled']),
			)
		);
	}

	public function print_shortcode_assets(): void {
		if (!$this->shortcode_rendered || $this->assets_printed || is_admin()) {
			return;
		}
		$this->assets_printed = true;
		echo $this->render_inline_assets_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private function render_inline_assets_markup(): string {
		$base_url = plugin_dir_url(__FILE__) . 'assets/calculator/assets/';
		$js_url = $base_url . 'index.js';
		$css_url = $base_url . 'index.css';

		$config = array(
			'restUrl' => site_url('/wp-json/pixact/v1/'),
			'nonce' => wp_create_nonce('wp_rest'),
			'siteUrl' => site_url('/'),
			'aiEnabled' => !empty($this->get_ai_settings()['enabled']),
		);

		return sprintf(
			'<link rel="stylesheet" href="%1$s" /><script>window.PixactCalculator=%2$s;</script><script type="module" src="%3$s"></script>',
			esc_url($css_url),
			wp_json_encode($config),
			esc_url($js_url)
		);
	}

	private function should_enqueue(): bool {
		if (is_admin()) {
			return false;
		}

		if ($this->shortcode_rendered) {
			return true;
		}

		if (!is_singular()) {
			return false;
		}

		$post = get_post();
		if (!$post || !isset($post->post_content)) {
			return false;
		}

		return has_shortcode((string) $post->post_content, 'pixact_cost_calculator');
	}

	public function register_admin_menu(): void {
		add_menu_page(
			'Pixact Calculator Dashboard',
			'Pixact Calculator',
			self::CAPABILITY,
			self::MENU_SLUG,
			array($this, 'render_dashboard_page'),
			'dashicons-calculator',
			56
		);

		add_submenu_page(
			self::MENU_SLUG,
			'Dashboard',
			'Dashboard',
			self::CAPABILITY,
			self::MENU_SLUG,
			array($this, 'render_dashboard_page')
		);

		add_submenu_page(
			self::MENU_SLUG,
			'Leads',
			'Leads',
			self::CAPABILITY,
			'pixact-calculator-leads',
			array($this, 'render_leads_page')
		);

		add_submenu_page(
			self::MENU_SLUG,
			'Calculator Builder',
			'Calculator Builder',
			self::CAPABILITY,
			'pixact-calculator-builder',
			array($this, 'render_calculator_builder_page')
		);

		add_submenu_page(
			self::MENU_SLUG,
			'Pricing Rules',
			'Pricing Rules',
			self::CAPABILITY,
			'pixact-calculator-pricing-rules',
			array($this, 'render_pricing_rules_page')
		);

		add_submenu_page(
			self::MENU_SLUG,
			'Email Settings',
			'Email Settings',
			self::CAPABILITY,
			'pixact-calculator-email-settings',
			array($this, 'render_email_settings_page')
		);

		add_submenu_page(
			self::MENU_SLUG,
			'AI Settings',
			'AI Settings',
			self::CAPABILITY,
			'pixact-calculator-ai-settings',
			array($this, 'render_ai_settings_page')
		);

		add_submenu_page(
			self::MENU_SLUG,
			'Integrations',
			'Integrations',
			self::CAPABILITY,
			'pixact-calculator-integrations',
			array($this, 'render_integrations_page')
		);
	}

	public function render_dashboard_page(): void {
		$this->print_admin_ui_styles_once();
		$lead_counts = wp_count_posts(Pixact_Cost_Calculator_Post_Type::POST_TYPE);
		$total_leads = isset($lead_counts->publish) ? (int) $lead_counts->publish : 0;
		$latest_lead_query = new WP_Query(
			array(
				'post_type' => Pixact_Cost_Calculator_Post_Type::POST_TYPE,
				'post_status' => 'publish',
				'posts_per_page' => 1,
				'orderby' => 'date',
				'order' => 'DESC',
				'fields' => 'ids',
			)
		);
		$latest_lead_id = !empty($latest_lead_query->posts) ? (int) $latest_lead_query->posts[0] : 0;
		$last_submission = $latest_lead_id > 0 ? get_the_date('Y-m-d H:i', $latest_lead_id) : 'No submissions yet';
		wp_reset_postdata();

		$ai_settings = $this->get_ai_settings();
		$ai_status = !empty($ai_settings['enabled']) ? 'Enabled' : 'Disabled';
		$quick_links = array(
			'View Leads' => add_query_arg(array('page' => 'pixact-calculator-leads'), admin_url('admin.php')),
			'Calculator Builder' => add_query_arg(array('page' => 'pixact-calculator-builder'), admin_url('admin.php')),
			'Pricing Rules' => add_query_arg(array('page' => 'pixact-calculator-pricing-rules'), admin_url('admin.php')),
			'Email Settings' => add_query_arg(array('page' => 'pixact-calculator-email-settings'), admin_url('admin.php')),
		);
		?>
		<div class="wrap pixact-admin-shell">
			<h1><?php echo esc_html('Pixact Calculator - Dashboard'); ?></h1>
			<p class="description">Quick operational overview and shortcuts for day-to-day management.</p>
			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-top:16px;">
				<div class="pixact-admin-section" style="margin-top:0;">
					<h2 style="font-size:16px;">Total Leads</h2>
					<p style="font-size:28px;font-weight:700;margin:8px 0 0;"><?php echo esc_html((string) $total_leads); ?></p>
				</div>
				<div class="pixact-admin-section" style="margin-top:0;">
					<h2 style="font-size:16px;">Last Submission</h2>
					<p style="font-size:15px;font-weight:600;margin:10px 0 0;"><?php echo esc_html($last_submission); ?></p>
				</div>
				<div class="pixact-admin-section" style="margin-top:0;">
					<h2 style="font-size:16px;">AI Status</h2>
					<p style="font-size:15px;font-weight:600;margin:10px 0 0;"><?php echo esc_html($ai_status); ?></p>
					<p class="description" style="margin-top:8px;">Model: <?php echo esc_html((string) ($ai_settings['model'] ?? 'gpt-4o-mini')); ?></p>
				</div>
			</div>
			<div class="pixact-admin-section">
				<h2>Quick Links</h2>
				<p class="description">Jump directly to common admin actions.</p>
				<div style="display:flex;flex-wrap:wrap;gap:8px;">
					<?php foreach ($quick_links as $label => $url) : ?>
						<a class="button button-secondary" href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	private function print_admin_ui_styles_once(): void {
		if (self::$admin_ui_styles_printed) {
			return;
		}
		self::$admin_ui_styles_printed = true;
		?>
		<style>
			.pixact-admin-shell { max-width: 1100px; }
			.pixact-admin-section {
				background: #fff;
				border: 1px solid #dcdcde;
				border-radius: 8px;
				padding: 18px;
				margin-top: 16px;
			}
			.pixact-admin-section h2 { margin-top: 0; margin-bottom: 6px; }
			.pixact-admin-section .description { margin-top: 0; margin-bottom: 12px; }
		</style>
		<?php
	}

	public function render_leads_page(): void {
		$this->handle_lead_bulk_action();
		$this->handle_lead_delete_action();
		$this->handle_lead_contact_action();
		$this->handle_lead_export_csv_action();

		$view_lead_id = isset($_GET['lead_id']) ? (int) $_GET['lead_id'] : 0;
		if ($view_lead_id > 0) {
			$this->render_lead_details_page($view_lead_id);
			return;
		}

		$selected_date = isset($_GET['filter_date']) ? sanitize_text_field((string) $_GET['filter_date']) : '';
		$selected_project_type = isset($_GET['filter_project_type']) ? sanitize_key((string) $_GET['filter_project_type']) : '';
		$selected_status = isset($_GET['filter_status']) ? sanitize_key((string) $_GET['filter_status']) : '';
		if (!in_array($selected_status, array('', 'new', 'contacted'), true)) {
			$selected_status = '';
		}
		$current_page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
		$per_page = 15;
		$query_args = array(
			'post_type' => Pixact_Cost_Calculator_Post_Type::POST_TYPE,
			'post_status' => 'publish',
			'posts_per_page' => $per_page,
			'paged' => $current_page,
			'orderby' => 'date',
			'order' => 'DESC',
		);

		$meta_query = $this->build_leads_list_meta_query($selected_project_type, $selected_status);
		if (!empty($meta_query)) {
			$query_args['meta_query'] = $meta_query;
		}

		if ($selected_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
			$query_args['date_query'] = array(
				array(
					'after' => $selected_date . ' 00:00:00',
					'before' => $selected_date . ' 23:59:59',
					'inclusive' => true,
				),
			);
		}

		$lead_query = new WP_Query(
			$query_args
		);

		$list_context = array();
		if ($selected_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
			$list_context['filter_date'] = $selected_date;
		}
		if ($selected_project_type !== '') {
			$list_context['filter_project_type'] = $selected_project_type;
		}
		if ($selected_status !== '') {
			$list_context['filter_status'] = $selected_status;
		}
		$pagination_base = add_query_arg(
			array_merge(
				array(
					'page' => 'pixact-calculator-leads',
					'paged' => '%#%',
				),
				$list_context
			),
			admin_url('admin.php')
		);
		?>
		<div class="wrap">
			<h1><?php echo esc_html('Pixact Calculator - Leads'); ?></h1>
			<p class="description">Review, inspect, and manage calculator lead submissions.</p>
			<?php if (!empty($_GET['deleted'])) : ?>
				<div class="notice notice-success is-dismissible"><p>Lead deleted successfully.</p></div>
			<?php endif; ?>
			<?php if (!empty($_GET['contacted'])) : ?>
				<div class="notice notice-success is-dismissible"><p>Lead marked as contacted.</p></div>
			<?php endif; ?>
			<?php if (isset($_GET['bulk_deleted']) && (int) $_GET['bulk_deleted'] > 0) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html(sprintf((int) $_GET['bulk_deleted'] === 1 ? '%d lead deleted.' : '%d leads deleted.', (int) $_GET['bulk_deleted'])); ?></p></div>
			<?php endif; ?>
			<?php if (isset($_GET['bulk_contacted']) && (int) $_GET['bulk_contacted'] > 0) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html(sprintf((int) $_GET['bulk_contacted'] === 1 ? '%d lead marked as contacted.' : '%d leads marked as contacted.', (int) $_GET['bulk_contacted'])); ?></p></div>
			<?php endif; ?>
			<?php if (!empty($_GET['bulk_err'])) : ?>
				<div class="notice notice-warning is-dismissible"><p><?php echo esc_html('Select at least one lead and a bulk action, then click Apply.'); ?></p></div>
			<?php endif; ?>

			<form method="get" style="margin: 12px 0;">
				<input type="hidden" name="page" value="pixact-calculator-leads" />
				<label for="pixact-filter-date"><strong>Date:</strong></label>
				<input id="pixact-filter-date" type="date" name="filter_date" value="<?php echo esc_attr($selected_date); ?>" />
				<label for="pixact-filter-project" style="margin-left: 12px;"><strong>Project Type:</strong></label>
				<select id="pixact-filter-project" name="filter_project_type">
					<option value="">All</option>
					<option value="app" <?php selected($selected_project_type, 'app'); ?>>App</option>
					<option value="website" <?php selected($selected_project_type, 'website'); ?>>Website</option>
					<option value="saas" <?php selected($selected_project_type, 'saas'); ?>>SaaS</option>
				</select>
				<label for="pixact-filter-status" style="margin-left: 12px;"><strong>Status:</strong></label>
				<select id="pixact-filter-status" name="filter_status">
					<option value="" <?php selected($selected_status, ''); ?>><?php echo esc_html('All'); ?></option>
					<option value="new" <?php selected($selected_status, 'new'); ?>><?php echo esc_html('New'); ?></option>
					<option value="contacted" <?php selected($selected_status, 'contacted'); ?>><?php echo esc_html('Contacted'); ?></option>
				</select>
				<?php submit_button('Apply Filters', 'secondary', '', false, array('style' => 'margin-left: 12px;')); ?>
				<a class="button" href="<?php echo esc_url(add_query_arg(array('page' => 'pixact-calculator-leads'), admin_url('admin.php'))); ?>" style="margin-left: 8px;">Reset</a>
				<?php
				$csv_url = wp_nonce_url(
					add_query_arg(
						array(
							'page' => 'pixact-calculator-leads',
							'action' => 'export_csv',
							'filter_date' => $selected_date,
							'filter_project_type' => $selected_project_type,
							'filter_status' => $selected_status,
						),
						admin_url('admin.php')
					),
					'pixact_export_csv'
				);
				?>
				<a class="button button-primary" href="<?php echo esc_url($csv_url); ?>" style="margin-left: 8px;">Export CSV</a>
			</form>

			<form
				method="post"
				action="<?php echo esc_url(admin_url('admin.php')); ?>"
				class="pixact-leads-bulk-form"
				onsubmit="var s=document.getElementById('pixact-bulk-action');if(s&amp;&amp;s.value==='delete'){return confirm('Delete the selected leads permanently?');}return true;"
			>
				<?php wp_nonce_field('pixact_leads_bulk', 'pixact_leads_bulk_nonce'); ?>
				<input type="hidden" name="page" value="pixact-calculator-leads" />
				<input type="hidden" name="filter_date" value="<?php echo esc_attr($selected_date); ?>" />
				<input type="hidden" name="filter_project_type" value="<?php echo esc_attr($selected_project_type); ?>" />
				<input type="hidden" name="filter_status" value="<?php echo esc_attr($selected_status); ?>" />
				<input type="hidden" name="paged" value="<?php echo esc_attr((string) $current_page); ?>" />
				<div class="tablenav top" style="margin-bottom: 8px;">
					<div class="alignleft actions bulkactions">
						<label for="pixact-bulk-action" class="screen-reader-text"><?php echo esc_html('Bulk actions'); ?></label>
						<select name="bulk_action" id="pixact-bulk-action">
							<option value="-1"><?php echo esc_html('Bulk actions'); ?></option>
							<option value="mark_contacted"><?php echo esc_html('Mark contacted'); ?></option>
							<option value="delete"><?php echo esc_html('Delete'); ?></option>
						</select>
						<?php submit_button('Apply', 'secondary', 'pixact_leads_bulk_submit', false, array('id' => 'pixact-leads-bulk-submit')); ?>
					</div>
					<br class="clear" />
				</div>

			<table class="wp-list-table widefat fixed striped table-view-list">
				<thead>
					<tr>
						<th scope="col" class="manage-column column-cb check-column"><input type="checkbox" id="pixact-leads-check-all" /></th>
						<th scope="col">Name</th>
						<th scope="col">Email</th>
						<th scope="col">Phone</th>
						<th scope="col">Project Type</th>
						<th scope="col">Estimate Range</th>
						<th scope="col">Timeline</th>
						<th scope="col">Complexity</th>
						<th scope="col">Status</th>
						<th scope="col">Date</th>
						<th scope="col">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if ($lead_query->have_posts()) : ?>
						<?php while ($lead_query->have_posts()) : $lead_query->the_post(); ?>
							<?php
							$lead_id = get_the_ID();
							$name = (string) get_post_meta($lead_id, 'name', true);
							$email = (string) get_post_meta($lead_id, 'email', true);
							$phone = (string) get_post_meta($lead_id, 'phone', true);
							$project_type = (string) get_post_meta($lead_id, 'project_type', true);
							$timeline = (string) get_post_meta($lead_id, 'timeline', true);
							$complexity = (string) get_post_meta($lead_id, 'complexity', true);
							$estimate_min = (int) get_post_meta($lead_id, 'estimate_min', true);
							$estimate_max = (int) get_post_meta($lead_id, 'estimate_max', true);
							$is_contacted = (int) get_post_meta($lead_id, 'contacted', true) === 1;
							$view_url = add_query_arg(
								array(
									'page' => 'pixact-calculator-leads',
									'lead_id' => $lead_id,
								),
								admin_url('admin.php')
							);
							$delete_url = wp_nonce_url(
								add_query_arg(
									array_merge(
										array(
											'page' => 'pixact-calculator-leads',
											'action' => 'delete_lead',
											'lead_id' => $lead_id,
										),
										$list_context,
										$current_page > 1 ? array('paged' => $current_page) : array()
									),
									admin_url('admin.php')
								),
								'pixact_delete_lead_' . $lead_id
							);
							$contact_url = wp_nonce_url(
								add_query_arg(
									array_merge(
										array(
											'page' => 'pixact-calculator-leads',
											'action' => 'mark_contacted',
											'lead_id' => $lead_id,
										),
										$list_context,
										$current_page > 1 ? array('paged' => $current_page) : array()
									),
									admin_url('admin.php')
								),
								'pixact_mark_contacted_' . $lead_id
							);
							?>
							<tr>
								<th scope="row" class="check-column">
									<input class="pixact-lead-cb" type="checkbox" name="lead_ids[]" value="<?php echo esc_attr((string) $lead_id); ?>" />
								</th>
								<td><?php echo esc_html($name ?: 'N/A'); ?></td>
								<td><?php echo esc_html($email ?: 'N/A'); ?></td>
								<td><?php echo esc_html($phone ?: 'N/A'); ?></td>
								<td><?php echo esc_html($project_type ?: 'N/A'); ?></td>
								<td><?php echo esc_html($this->format_estimate_range($estimate_min, $estimate_max)); ?></td>
								<td><?php echo esc_html($timeline ?: 'N/A'); ?></td>
								<td><?php echo esc_html($complexity ?: 'N/A'); ?></td>
								<td><?php echo esc_html($is_contacted ? 'Contacted' : 'New'); ?></td>
								<td><?php echo esc_html(get_the_date('Y-m-d H:i')); ?></td>
								<td>
									<a class="button button-small" href="<?php echo esc_url($view_url); ?>">View details</a>
									<?php if (!$is_contacted) : ?>
										<a class="button button-small" href="<?php echo esc_url($contact_url); ?>">Mark contacted</a>
									<?php endif; ?>
									<a
										class="button button-small button-link-delete"
										href="<?php echo esc_url($delete_url); ?>"
										onclick="return confirm('Delete this lead permanently?');"
									>
										Delete
									</a>
								</td>
							</tr>
						<?php endwhile; ?>
					<?php else : ?>
						<tr>
							<td colspan="11">No leads found.</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
			<?php
			if ((int) $lead_query->max_num_pages > 1) {
				$pagination = paginate_links(
					array(
						'base' => $pagination_base,
						'format' => '',
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
						'total' => (int) $lead_query->max_num_pages,
						'current' => $current_page,
						'type' => 'list',
					)
				);
				if ($pagination) {
					echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post($pagination) . '</div></div>';
				}
			}
			?>
			<script>
				(function () {
					var all = document.getElementById('pixact-leads-check-all');
					if (!all) return;
					all.addEventListener('change', function () {
						document.querySelectorAll('.pixact-lead-cb').forEach(function (cb) {
							cb.checked = all.checked;
						});
					});
				})();
			</script>
			</form>
		</div>
		<?php
		wp_reset_postdata();
	}

	public function render_calculator_builder_page(): void {
		$this->print_admin_ui_styles_once();
		$notice = '';
		if (
			isset($_POST['pixact_calculator_config_nonce']) &&
			wp_verify_nonce(sanitize_text_field((string) $_POST['pixact_calculator_config_nonce']), 'pixact_save_calculator_config') &&
			current_user_can(self::CAPABILITY)
		) {
			$raw_json = isset($_POST['calculator_config_json']) ? wp_unslash((string) $_POST['calculator_config_json']) : '';
			$decoded = json_decode($raw_json, true);
			if (is_array($decoded)) {
				$sanitized = $this->sanitize_calculator_flow_config($decoded);
				update_option(self::CALCULATOR_CONFIG_OPTION, wp_json_encode($sanitized));
				$notice = 'Calculator settings saved.';
			} else {
				$notice = 'Invalid builder payload. Please try again.';
			}
		}

		$config = $this->get_calculator_flow_config();
		?>
		<div class="wrap pixact-admin-shell">
			<h1><?php echo esc_html('Pixact Calculator - Calculator Builder'); ?></h1>
			<p class="description">Manage the calculator journey content and feature toggles from one screen.</p>
			<?php if ($notice !== '') : ?>
				<div class="notice <?php echo esc_attr(strpos($notice, 'Invalid') === false ? 'notice-success' : 'notice-error'); ?> is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
			<?php endif; ?>
			<form method="post" class="pixact-admin-section">
				<?php wp_nonce_field('pixact_save_calculator_config', 'pixact_calculator_config_nonce'); ?>
				<input type="hidden" id="pixact-calculator-config-json" name="calculator_config_json" value="" />
				<h2>Step Builder</h2>
				<p class="description">Add, edit, remove, and reorder steps. Drag step cards to change order.</p>
				<div id="pixact-builder-steps" class="space-y-3"></div>
				<p><button type="button" class="button button-secondary" id="pixact-add-step">Add Step</button></p>
				<?php submit_button('Save Calculator Settings'); ?>
			</form>
			<script>
				(() => {
					const initialConfig = <?php echo wp_json_encode($config); ?>;
					const root = document.getElementById('pixact-builder-steps');
					const hiddenInput = document.getElementById('pixact-calculator-config-json');
					const addStepBtn = document.getElementById('pixact-add-step');
					if (!root || !hiddenInput || !addStepBtn) return;

					let dragIndex = -1;
					let state = Array.isArray(initialConfig.steps) ? initialConfig.steps : [];

					const optionRow = (option = {}) => `
						<div class="pixact-option-row" draggable="true" style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr)) auto;gap:8px;margin-top:8px;cursor:move;">
							<input type="text" class="regular-text pixact-opt-label" placeholder="Label" value="${option.label || ''}">
							<input type="text" class="regular-text pixact-opt-value" placeholder="Value" value="${option.value || ''}">
							<select class="pixact-opt-price">
								${['none','low','medium','high'].map(v => `<option value="${v}" ${option.priceModifier===v?'selected':''}>${v}</option>`).join('')}
							</select>
							<input type="text" class="regular-text pixact-opt-tags" placeholder="tags,comma,separated" value="${Array.isArray(option.tags)?option.tags.join(', '):(option.tags||'')}">
							<button type="button" class="button-link-delete pixact-remove-option">Remove</button>
						</div>
					`;

					const stepCard = (step = {}, index = 0) => `
						<div class="postbox pixact-step-card" draggable="true" data-index="${index}" style="padding:12px;margin-bottom:12px;">
							<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
								<strong>Step ${index + 1}</strong>
								<button type="button" class="button-link-delete pixact-remove-step">Remove Step</button>
							</div>
							<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:10px;">
								<label>ID<input type="text" class="regular-text pixact-step-id" value="${step.id || ''}" placeholder="platform"></label>
								<label>Type
									<select class="pixact-step-type">
										<option value="single" ${(step.type || 'single') === 'single' ? 'selected' : ''}>single</option>
										<option value="multi" ${(step.type || 'single') === 'multi' ? 'selected' : ''}>multi</option>
									</select>
								</label>
							</div>
							<p style="margin-top:8px;"><label>Title<input type="text" class="large-text pixact-step-title" value="${step.title || ''}"></label></p>
							<p style="margin-top:8px;"><label>Helper<input type="text" class="large-text pixact-step-helper" value="${step.helper || ''}"></label></p>
							<p style="margin-top:8px;"><label>Explanation<textarea class="large-text pixact-step-explanation" rows="2">${step.explanation || ''}</textarea></label></p>
							<div style="margin-top:10px;">
								<strong>Options</strong>
								<div class="pixact-options">${Array.isArray(step.options) ? step.options.map((opt) => optionRow(opt)).join('') : ''}</div>
								<p style="margin-top:8px;"><button type="button" class="button button-secondary pixact-add-option">Add Option</button></p>
							</div>
						</div>
					`;

					const bindEvents = () => {
						let draggingOptionRow = null;

						root.querySelectorAll('.pixact-remove-step').forEach((btn, idx) => {
							btn.addEventListener('click', () => {
								state.splice(idx, 1);
								render();
							});
						});

						root.querySelectorAll('.pixact-add-option').forEach((btn) => {
							btn.addEventListener('click', (e) => {
								const step = e.target.closest('.pixact-step-card');
								const optionsWrap = step.querySelector('.pixact-options');
								optionsWrap.insertAdjacentHTML('beforeend', optionRow({}));
								bindEvents();
								sync();
							});
						});

						root.querySelectorAll('.pixact-remove-option').forEach((btn) => {
							btn.addEventListener('click', (e) => {
								const row = e.target.closest('.pixact-option-row');
								if (row) row.remove();
								sync();
							});
						});

						root.querySelectorAll('.pixact-option-row').forEach((row) => {
							row.addEventListener('dragstart', () => {
								draggingOptionRow = row;
								row.style.opacity = '0.55';
							});

							row.addEventListener('dragend', () => {
								row.style.opacity = '1';
								draggingOptionRow = null;
							});

							row.addEventListener('dragover', (e) => {
								e.preventDefault();
							});

							row.addEventListener('drop', (e) => {
								e.preventDefault();
								if (!draggingOptionRow || draggingOptionRow === row) return;
								const sourceStep = draggingOptionRow.closest('.pixact-step-card');
								const targetStep = row.closest('.pixact-step-card');
								if (!sourceStep || !targetStep || sourceStep !== targetStep) return;
								const optionsWrap = targetStep.querySelector('.pixact-options');
								if (!optionsWrap) return;
								optionsWrap.insertBefore(draggingOptionRow, row);
								sync();
							});
						});

						root.querySelectorAll('.pixact-step-card').forEach((card) => {
							card.addEventListener('dragstart', () => {
								dragIndex = Number(card.dataset.index || -1);
							});
							card.addEventListener('dragover', (e) => e.preventDefault());
							card.addEventListener('drop', () => {
								const targetIndex = Number(card.dataset.index || -1);
								if (dragIndex < 0 || targetIndex < 0 || dragIndex === targetIndex) return;
								const moved = state.splice(dragIndex, 1)[0];
								state.splice(targetIndex, 0, moved);
								render();
							});
						});

						root.querySelectorAll('input, textarea, select').forEach((field) => {
							field.addEventListener('input', sync);
							field.addEventListener('change', sync);
						});
					};

					const sync = () => {
						const steps = Array.from(root.querySelectorAll('.pixact-step-card')).map((card) => {
							const options = Array.from(card.querySelectorAll('.pixact-option-row')).map((row) => ({
								label: row.querySelector('.pixact-opt-label')?.value || '',
								value: row.querySelector('.pixact-opt-value')?.value || '',
								priceModifier: row.querySelector('.pixact-opt-price')?.value || 'none',
								tags: (row.querySelector('.pixact-opt-tags')?.value || '')
									.split(',')
									.map((tag) => tag.trim())
									.filter(Boolean),
							}));
							return {
								id: card.querySelector('.pixact-step-id')?.value || '',
								title: card.querySelector('.pixact-step-title')?.value || '',
								helper: card.querySelector('.pixact-step-helper')?.value || '',
								explanation: card.querySelector('.pixact-step-explanation')?.value || '',
								type: card.querySelector('.pixact-step-type')?.value || 'single',
								options,
							};
						});
						hiddenInput.value = JSON.stringify({ steps });
						state = steps;
					};

					const render = () => {
						root.innerHTML = state.map((step, idx) => stepCard(step, idx)).join('');
						bindEvents();
						sync();
					};

					addStepBtn.addEventListener('click', () => {
						state.push({ id: '', title: '', helper: '', explanation: '', type: 'single', options: [] });
						render();
					});

					render();
				})();
			</script>
		</div>
		<?php
	}

	public function render_email_settings_page(): void {
		$this->print_admin_ui_styles_once();
		$notice = '';
		if (
			isset($_POST['pixact_email_settings_nonce']) &&
			wp_verify_nonce(sanitize_text_field((string) $_POST['pixact_email_settings_nonce']), 'pixact_save_email_settings') &&
			current_user_can(self::CAPABILITY)
		) {
			$submitted = isset($_POST['email_settings']) ? wp_unslash($_POST['email_settings']) : array();
			$sanitized = $this->sanitize_email_settings(is_array($submitted) ? $submitted : array());
			update_option(self::EMAIL_SETTINGS_OPTION, $sanitized);
			$notice = 'Email settings saved successfully.';
		}

		$settings = $this->get_email_settings();
		$preview_vars = array(
			'{{name}}',
			'{{email}}',
			'{{phone}}',
			'{{estimate_min}}',
			'{{estimate_max}}',
			'{{timeline}}',
			'{{complexity}}',
			'{{lead_summary}}',
			'{{answers}}',
		);
		$sample_lead_data = array(
			'name' => 'John Doe',
			'email' => 'john@example.com',
			'phone' => '+1 415 555 0199',
			'project_type' => 'Mobile App',
			'estimate_min' => 8000,
			'estimate_max' => 18000,
			'timeline' => '6-8 weeks',
			'complexity' => 'Advanced',
			'answers' => array(
				'platform' => 'iOS + Android',
				'user_roles' => array('Customer', 'Admin'),
				'core_features' => array('User login', 'Payments', 'Notifications'),
				'advanced_features' => array('AI chatbot', 'Real-time tracking'),
				'design_level' => 'Modern UI',
				'backend' => 'Advanced system',
			),
		);
		$sample_lead_summary = pixact_format_lead_summary($sample_lead_data);
		$sample_preview_values = array(
			'name' => $sample_lead_data['name'],
			'email' => $sample_lead_data['email'],
			'phone' => $sample_lead_data['phone'],
			'estimate_min' => '$' . number_format((int) $sample_lead_data['estimate_min']),
			'estimate_max' => '$' . number_format((int) $sample_lead_data['estimate_max']),
			'timeline' => $sample_lead_data['timeline'],
			'complexity' => $sample_lead_data['complexity'],
			'lead_summary' => $sample_lead_summary,
			'answers' => wp_json_encode($sample_lead_data['answers'], JSON_PRETTY_PRINT),
		);
		?>
		<div class="wrap pixact-admin-shell">
			<h1><?php echo esc_html('Pixact Calculator - Email Settings'); ?></h1>
			<p class="description">Customize sender details, toggle notifications, and control both admin/user email copy.</p>
			<?php if ($notice !== '') : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
			<?php endif; ?>
			<form method="post">
				<?php wp_nonce_field('pixact_save_email_settings', 'pixact_email_settings_nonce'); ?>
				<div class="pixact-admin-section">
					<h2>Sender and Delivery</h2>
					<p class="description">Control sender identity, routing behavior, and which notifications are sent.</p>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row">Admin Email Override</th>
								<td>
									<input type="email" class="regular-text" name="email_settings[admin_email]" value="<?php echo esc_attr((string) $settings['admin_email']); ?>" />
									<p class="description">If empty, WordPress admin email is used.</p>
								</td>
							</tr>
							<tr>
								<th scope="row">From Name</th>
								<td><input type="text" class="regular-text" name="email_settings[from_name]" value="<?php echo esc_attr((string) $settings['from_name']); ?>" /></td>
							</tr>
							<tr>
								<th scope="row">From Email</th>
								<td><input type="email" class="regular-text" name="email_settings[from_email]" value="<?php echo esc_attr((string) $settings['from_email']); ?>" /></td>
							</tr>
							<tr>
								<th scope="row">Enable Admin Email</th>
								<td>
									<label><input type="checkbox" name="email_settings[enable_admin_email]" value="1" <?php checked(!empty($settings['enable_admin_email'])); ?> /> Send lead email to admin</label>
								</td>
							</tr>
							<tr>
								<th scope="row">Enable User Email</th>
								<td>
									<label><input type="checkbox" name="email_settings[enable_user_email]" value="1" <?php checked(!empty($settings['enable_user_email'])); ?> /> Send estimate email to user</label>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="pixact-admin-section">
					<h2>Message Templates</h2>
					<p class="description">Define sales messaging and template copy for admin and user notifications.</p>
					<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">Admin Email Subject</th>
							<td><input type="text" class="large-text" name="email_settings[admin_email_subject]" value="<?php echo esc_attr((string) $settings['admin_email_subject']); ?>" /></td>
						</tr>
						<tr>
							<th scope="row">User Email Subject</th>
							<td><input type="text" class="large-text" name="email_settings[user_email_subject]" value="<?php echo esc_attr((string) $settings['user_email_subject']); ?>" /></td>
						</tr>
						<tr>
							<th scope="row">User Email Template</th>
							<td>
								<textarea name="email_settings[user_email_template]" rows="10" style="width:100%;font-family:monospace;"><?php echo esc_textarea((string) $settings['user_email_template']); ?></textarea>
							</td>
						</tr>
						<tr>
							<th scope="row">Admin Email Template</th>
							<td>
								<textarea name="email_settings[admin_email_template]" rows="10" style="width:100%;font-family:monospace;"><?php echo esc_textarea((string) $settings['admin_email_template']); ?></textarea>
							</td>
						</tr>
					</tbody>
					</table>
				</div>
				<p class="description"><strong>Template variables:</strong> <?php echo esc_html(implode(', ', $preview_vars)); ?></p>
				<?php submit_button('Save Email Settings'); ?>
			</form>
			<div class="pixact-admin-section">
				<h2>Preview Variables</h2>
				<p class="description">Variables are replaced dynamically on send using lead submission data.</p>
				<pre style="background:#fff;border:1px solid #dcdcde;padding:12px;"><?php echo esc_html(implode("\n", $preview_vars)); ?></pre>
			</div>
			<div class="pixact-admin-section">
				<h2>Email Preview Renderer</h2>
				<p class="description">Use sample lead data to preview the final substituted message before saving templates.</p>
				<div style="display: flex; gap: 8px; margin-bottom: 12px;">
					<button type="button" class="button button-secondary" id="pixact-preview-admin-btn">Preview Admin Email</button>
					<button type="button" class="button button-secondary" id="pixact-preview-user-btn">Preview User Email</button>
				</div>
				<h3 style="margin-bottom: 6px;">Preview Subject</h3>
				<pre id="pixact-email-preview-subject" style="background:#fff;border:1px solid #dcdcde;padding:12px;min-height:38px;"></pre>
				<h3 style="margin-bottom: 6px;">Preview Body</h3>
				<pre id="pixact-email-preview-body" style="background:#fff;border:1px solid #dcdcde;padding:12px;min-height:220px;white-space:pre-wrap;"></pre>
				<p class="description">Sample Lead: John Doe, Mobile App, $8,000-$18,000, timeline 6-8 weeks.</p>
			</div>
			<script>
				(() => {
					const valueMap = <?php echo wp_json_encode($sample_preview_values); ?>;
					const previewSubject = document.getElementById("pixact-email-preview-subject");
					const previewBody = document.getElementById("pixact-email-preview-body");
					const adminButton = document.getElementById("pixact-preview-admin-btn");
					const userButton = document.getElementById("pixact-preview-user-btn");
					if (!previewSubject || !previewBody || !adminButton || !userButton) return;

					const applyTemplate = (template) => {
						let result = template || "";
						Object.entries(valueMap).forEach(([key, value]) => {
							const token = new RegExp(`\\{\\{${key}\\}\\}`, "g");
							result = result.replace(token, String(value ?? ""));
						});
						return result;
					};

					const renderPreview = (type) => {
						const subjectInput = document.querySelector(`input[name="email_settings[${type}_email_subject]"]`);
						const bodyInput = document.querySelector(`textarea[name="email_settings[${type}_email_template]"]`);
						const subjectTemplate = subjectInput ? subjectInput.value : "";
						const bodyTemplate = bodyInput ? bodyInput.value : "";
						previewSubject.textContent = applyTemplate(subjectTemplate);
						previewBody.textContent = applyTemplate(bodyTemplate);
					};

					adminButton.addEventListener("click", () => renderPreview("admin"));
					userButton.addEventListener("click", () => renderPreview("user"));
					renderPreview("admin");
				})();
			</script>
		</div>
		<?php
	}

	public function render_pricing_rules_page(): void {
		$this->print_admin_ui_styles_once();
		$notice = '';
		if (
			isset($_POST['pixact_pricing_rules_nonce']) &&
			wp_verify_nonce(sanitize_text_field((string) $_POST['pixact_pricing_rules_nonce']), 'pixact_save_pricing_rules') &&
			current_user_can(self::CAPABILITY)
		) {
			$submitted = isset($_POST['pricing_rules']) ? wp_unslash($_POST['pricing_rules']) : array();
			$sanitized = $this->sanitize_pricing_rules(is_array($submitted) ? $submitted : array());
			update_option(self::PRICING_RULES_OPTION, wp_json_encode($sanitized));
			$notice = 'Pricing rules saved successfully.';
		}

		$rules = $this->get_pricing_rules();
		?>
		<div class="wrap pixact-admin-shell">
			<h1><?php echo esc_html('Pixact Calculator - Pricing Rules'); ?></h1>
			<p class="description">Configure pricing centrally. These rules are served via REST and consumed by the React calculator.</p>
			<?php if ($notice !== '') : ?>
				<div class="notice <?php echo esc_attr(strpos($notice, 'Invalid') === false ? 'notice-success' : 'notice-error'); ?> is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
			<?php endif; ?>
			<form method="post" class="pixact-admin-section">
				<?php wp_nonce_field('pixact_save_pricing_rules', 'pixact_pricing_rules_nonce'); ?>
				<h2>Base Prices</h2>
				<p class="description">Set starting costs by project type.</p>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">Mobile App</th>
							<td>
								<input type="number" class="regular-text" min="0" step="1" name="pricing_rules[basePrices][app]" value="<?php echo esc_attr((string) ($rules['basePrices']['app'] ?? 0)); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row">Website</th>
							<td>
								<input type="number" class="regular-text" min="0" step="1" name="pricing_rules[basePrices][website]" value="<?php echo esc_attr((string) ($rules['basePrices']['website'] ?? 0)); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row">SaaS</th>
							<td>
								<input type="number" class="regular-text" min="0" step="1" name="pricing_rules[basePrices][saas]" value="<?php echo esc_attr((string) ($rules['basePrices']['saas'] ?? 0)); ?>" />
							</td>
						</tr>
					</tbody>
				</table>

				<h2>Feature Multipliers</h2>
				<p class="description">Weight feature scope by level.</p>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">Low</th>
							<td><input type="number" class="regular-text" min="0" step="0.01" name="pricing_rules[featureMultipliers][low]" value="<?php echo esc_attr((string) ($rules['featureMultipliers']['low'] ?? 1)); ?>" /></td>
						</tr>
						<tr>
							<th scope="row">Medium</th>
							<td><input type="number" class="regular-text" min="0" step="0.01" name="pricing_rules[featureMultipliers][medium]" value="<?php echo esc_attr((string) ($rules['featureMultipliers']['medium'] ?? 1)); ?>" /></td>
						</tr>
						<tr>
							<th scope="row">High</th>
							<td><input type="number" class="regular-text" min="0" step="0.01" name="pricing_rules[featureMultipliers][high]" value="<?php echo esc_attr((string) ($rules['featureMultipliers']['high'] ?? 1)); ?>" /></td>
						</tr>
					</tbody>
				</table>

				<h2>Global Multipliers</h2>
				<p class="description">Tune complexity and role/AI impact globally.</p>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">Backend Complexity</th>
							<td><input type="number" class="regular-text" min="0" step="0.01" name="pricing_rules[multipliers][backendComplexity]" value="<?php echo esc_attr((string) ($rules['multipliers']['backendComplexity'] ?? 1)); ?>" /></td>
						</tr>
						<tr>
							<th scope="row">User Roles</th>
							<td><input type="number" class="regular-text" min="0" step="0.01" name="pricing_rules[multipliers][userRoles]" value="<?php echo esc_attr((string) ($rules['multipliers']['userRoles'] ?? 1)); ?>" /></td>
						</tr>
						<tr>
							<th scope="row">AI Features</th>
							<td><input type="number" class="regular-text" min="0" step="0.01" name="pricing_rules[multipliers][aiFeatures]" value="<?php echo esc_attr((string) ($rules['multipliers']['aiFeatures'] ?? 1)); ?>" /></td>
						</tr>
					</tbody>
				</table>

				<h2>Timeline Adjustment (%)</h2>
				<p class="description">Percent uplift by delivery speed.</p>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">Normal</th>
							<td><input type="number" class="regular-text" step="1" name="pricing_rules[timelineAdjustment][normal]" value="<?php echo esc_attr((string) ($rules['timelineAdjustment']['normal'] ?? 0)); ?>" /></td>
						</tr>
						<tr>
							<th scope="row">Fast</th>
							<td><input type="number" class="regular-text" step="1" name="pricing_rules[timelineAdjustment][fast]" value="<?php echo esc_attr((string) ($rules['timelineAdjustment']['fast'] ?? 15)); ?>" /></td>
						</tr>
						<tr>
							<th scope="row">Urgent</th>
							<td><input type="number" class="regular-text" step="1" name="pricing_rules[timelineAdjustment][urgent]" value="<?php echo esc_attr((string) ($rules['timelineAdjustment']['urgent'] ?? 25)); ?>" /></td>
						</tr>
					</tbody>
				</table>

				<h2>Complexity Thresholds</h2>
				<p class="description">Threshold points used for complexity labeling.</p>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">Basic</th>
							<td><input type="number" class="regular-text" step="1" name="pricing_rules[complexityThresholds][basic]" value="<?php echo esc_attr((string) ($rules['complexityThresholds']['basic'] ?? 0)); ?>" /></td>
						</tr>
						<tr>
							<th scope="row">Medium</th>
							<td><input type="number" class="regular-text" step="1" name="pricing_rules[complexityThresholds][medium]" value="<?php echo esc_attr((string) ($rules['complexityThresholds']['medium'] ?? 2)); ?>" /></td>
						</tr>
						<tr>
							<th scope="row">Advanced</th>
							<td><input type="number" class="regular-text" step="1" name="pricing_rules[complexityThresholds][advanced]" value="<?php echo esc_attr((string) ($rules['complexityThresholds']['advanced'] ?? 4)); ?>" /></td>
						</tr>
						<tr>
							<th scope="row">Enterprise</th>
							<td><input type="number" class="regular-text" step="1" name="pricing_rules[complexityThresholds][enterprise]" value="<?php echo esc_attr((string) ($rules['complexityThresholds']['enterprise'] ?? 7)); ?>" /></td>
						</tr>
					</tbody>
				</table>
				<div class="pixact-admin-section" style="margin-top: 14px;">
					<h3 style="margin-top:0;">Stored JSON Preview</h3>
					<p class="description">The same JSON structure will still be saved in <code>pixact_pricing_rules</code>.</p>
					<pre style="background:#fff;border:1px solid #dcdcde;padding:12px;"><?php echo esc_html(wp_json_encode($rules, JSON_PRETTY_PRINT)); ?></pre>
				</div>
				<?php submit_button('Save Pricing Rules'); ?>
			</form>
		</div>
		<?php
	}

	public function render_ai_settings_page(): void {
		$this->print_admin_ui_styles_once();
		$notice = '';
		if (
			isset($_POST['pixact_ai_test_nonce']) &&
			wp_verify_nonce(sanitize_text_field((string) $_POST['pixact_ai_test_nonce']), 'pixact_test_ai') &&
			current_user_can(self::CAPABILITY)
		) {
			$test_result = $this->run_ai_test_request();
			$notice = $test_result ? 'AI test successful.' : 'AI test failed. Check API key/model.';
		}
		?>
		<div class="wrap pixact-admin-shell">
			<h1><?php echo esc_html('Pixact Calculator - AI Settings'); ?></h1>
			<p class="description">Configure backend AI microcopy generation and validate your connection.</p>
			<?php if (!empty($_GET['settings-updated'])) : ?>
				<div class="notice notice-success is-dismissible"><p>AI settings saved successfully.</p></div>
			<?php endif; ?>
			<?php if ($notice !== '') : ?>
				<div class="notice <?php echo esc_attr($notice === 'AI test successful.' ? 'notice-success' : 'notice-error'); ?> is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
			<?php endif; ?>
			<form method="post" action="options.php" class="pixact-admin-section">
				<?php
				settings_errors(self::AI_SETTINGS_OPTION);
				echo '<h2>Provider Settings</h2>';
				echo '<p class="description">These values are used only on the server during microcopy requests.</p>';
				settings_fields(self::AI_SETTINGS_GROUP);
				do_settings_sections('pixact-calculator-ai-settings');
				submit_button('Save AI Settings');
				?>
			</form>
			<div class="pixact-admin-section">
				<h2>Connection Test</h2>
				<p class="description">Run a lightweight request to validate API key and model settings.</p>
				<form method="post">
					<?php wp_nonce_field('pixact_test_ai', 'pixact_ai_test_nonce'); ?>
					<?php submit_button('Test AI', 'secondary', 'submit', false); ?>
				</form>
			</div>
		</div>
		<?php
	}

	public function render_general_settings_page(): void {
		$this->print_admin_ui_styles_once();
		?>
		<div class="wrap pixact-admin-shell">
			<h1><?php echo esc_html('Pixact Calculator - General Settings'); ?></h1>
			<p class="description">Configure global plugin behavior, defaults, and operational preferences.</p>
			<?php if (!empty($_GET['settings-updated'])) : ?>
				<div class="notice notice-success is-dismissible"><p>General settings saved successfully.</p></div>
			<?php endif; ?>
			<form method="post" action="options.php" class="pixact-admin-section">
				<?php
				settings_errors(self::GENERAL_SETTINGS_OPTION);
				echo '<h2>Core Settings</h2>';
				echo '<p class="description">These settings apply across calculator UX and lead handling behavior.</p>';
				settings_fields(self::GENERAL_SETTINGS_GROUP);
				do_settings_sections('pixact-calculator-general-settings');
				submit_button('Save General Settings');
				?>
			</form>
		</div>
		<?php
	}

	public function render_integrations_page(): void {
		$this->print_admin_ui_styles_once();
		$notice = '';
		if (
			isset($_POST['pixact_integrations_settings_nonce']) &&
			wp_verify_nonce(sanitize_text_field((string) $_POST['pixact_integrations_settings_nonce']), 'pixact_save_integrations_settings') &&
			current_user_can(self::CAPABILITY)
		) {
			$submitted = isset($_POST['integrations_settings']) ? wp_unslash($_POST['integrations_settings']) : array();
			$sanitized = $this->sanitize_integrations_settings(is_array($submitted) ? $submitted : array());
			update_option(self::INTEGRATIONS_SETTINGS_OPTION, $sanitized);
			$notice = 'Integrations settings saved successfully.';
		}

		$settings = $this->get_integrations_settings();
		?>
		<div class="wrap pixact-admin-shell">
			<h1><?php echo esc_html('Pixact Calculator - Integrations'); ?></h1>
			<p class="description">Configure outbound webhook delivery and prepare for CRM/automation integrations.</p>
			<?php if ($notice !== '') : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
			<?php endif; ?>
			<form method="post" class="pixact-admin-section">
				<?php wp_nonce_field('pixact_save_integrations_settings', 'pixact_integrations_settings_nonce'); ?>
				<h2>Webhook Delivery</h2>
				<p class="description">Send lead events to external systems without code changes.</p>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">Enable Webhook</th>
							<td>
								<label>
									<input
										type="checkbox"
										name="integrations_settings[webhook_enabled]"
										value="1"
										<?php checked(!empty($settings['webhook_enabled'])); ?>
									/>
									Send submitted lead data to an external webhook
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">Webhook URL</th>
							<td>
								<input
									type="url"
									class="large-text"
									name="integrations_settings[webhook_url]"
									value="<?php echo esc_attr((string) $settings['webhook_url']); ?>"
									placeholder="https://example.com/webhook"
								/>
								<p class="description">The endpoint receives a JSON payload whenever a lead is submitted.</p>
							</td>
						</tr>
					</tbody>
				</table>
				<?php submit_button('Save Integrations Settings'); ?>
			</form>
			<div class="pixact-admin-section">
				<h2>Coming Soon</h2>
				<p class="description">Planned one-click integrations for growth and sales workflows.</p>
				<ul style="list-style: disc; padding-left: 20px;">
					<li>HubSpot</li>
					<li>Zapier</li>
					<li>CRM integrations</li>
				</ul>
			</div>
		</div>
		<?php
	}

	private function render_admin_placeholder(string $title, string $description): void {
		?>
		<div class="wrap">
			<h1><?php echo esc_html('Pixact Calculator - ' . $title); ?></h1>
			<div class="notice notice-info" style="padding: 12px 14px;">
				<p style="margin: 0;">
					<strong><?php echo esc_html($title); ?>:</strong>
					<?php echo esc_html($description); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Meta query for leads list / CSV: optional project type and status (new vs contacted).
	 *
	 * @return array<int|string, mixed> Meta query for WP_Query, or empty when no filters.
	 */
	private function build_leads_list_meta_query(string $project_type, string $status): array {
		$status = sanitize_key($status);
		if (!in_array($status, array('', 'new', 'contacted'), true)) {
			$status = '';
		}
		$parts = array();
		if ($project_type !== '') {
			$parts[] = array(
				'key' => 'project_type',
				'value' => $project_type,
				'compare' => '=',
			);
		}
		if ($status === 'new') {
			$parts[] = array(
				'relation' => 'OR',
				array(
					'key' => 'contacted',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key' => 'contacted',
					'value' => 1,
					'compare' => '!=',
					'type' => 'NUMERIC',
				),
			);
		} elseif ($status === 'contacted') {
			$parts[] = array(
				'key' => 'contacted',
				'value' => 1,
				'compare' => '=',
				'type' => 'NUMERIC',
			);
		}
		if (empty($parts)) {
			return array();
		}
		if (count($parts) === 1) {
			return $parts[0];
		}
		return array_merge(array('relation' => 'AND'), $parts);
	}

	private function handle_lead_bulk_action(): void {
		if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['pixact_leads_bulk_nonce'])) {
			return;
		}
		if (!isset($_POST['page']) || sanitize_key((string) $_POST['page']) !== 'pixact-calculator-leads') {
			return;
		}
		if (
			!wp_verify_nonce(sanitize_text_field((string) $_POST['pixact_leads_bulk_nonce']), 'pixact_leads_bulk') ||
			!current_user_can(self::CAPABILITY)
		) {
			return;
		}
		$bulk = isset($_POST['bulk_action']) ? sanitize_key((string) $_POST['bulk_action']) : '';
		if ($bulk === '' || $bulk === '-1') {
			return;
		}
		$ids = isset($_POST['lead_ids']) ? array_map('intval', (array) $_POST['lead_ids']) : array();
		$ids = array_values(array_filter($ids, static function (int $id): bool {
			return $id > 0;
		}));

		$filter_date = isset($_POST['filter_date']) ? sanitize_text_field((string) $_POST['filter_date']) : '';
		$filter_project_type = isset($_POST['filter_project_type']) ? sanitize_key((string) $_POST['filter_project_type']) : '';
		$filter_status = isset($_POST['filter_status']) ? sanitize_key((string) $_POST['filter_status']) : '';
		if (!in_array($filter_status, array('', 'new', 'contacted'), true)) {
			$filter_status = '';
		}
		$paged = isset($_POST['paged']) ? max(1, (int) $_POST['paged']) : 1;

		$redirect_base = array(
			'page' => 'pixact-calculator-leads',
			'filter_date' => $filter_date,
			'filter_project_type' => $filter_project_type,
			'filter_status' => $filter_status,
			'paged' => $paged,
		);

		if (empty($ids)) {
			$redirect_base['bulk_err'] = '1';
			wp_safe_redirect(add_query_arg($redirect_base, admin_url('admin.php')));
			exit;
		}

		$cpt = Pixact_Cost_Calculator_Post_Type::POST_TYPE;
		$processed = 0;
		foreach ($ids as $lead_id) {
			if (get_post_type($lead_id) !== $cpt) {
				continue;
			}
			if ($bulk === 'mark_contacted') {
				update_post_meta($lead_id, 'contacted', 1);
				++$processed;
			} elseif ($bulk === 'delete') {
				wp_delete_post($lead_id, true);
				++$processed;
			}
		}

		if ($bulk === 'mark_contacted') {
			$redirect_base['bulk_contacted'] = $processed;
		} elseif ($bulk === 'delete') {
			$redirect_base['bulk_deleted'] = $processed;
		}

		wp_safe_redirect(add_query_arg($redirect_base, admin_url('admin.php')));
		exit;
	}

	private function handle_lead_delete_action(): void {
		$action = isset($_GET['action']) ? sanitize_key((string) $_GET['action']) : '';
		$lead_id = isset($_GET['lead_id']) ? (int) $_GET['lead_id'] : 0;
		if ($action !== 'delete_lead' || $lead_id <= 0) {
			return;
		}

		$nonce = isset($_GET['_wpnonce']) ? sanitize_text_field((string) $_GET['_wpnonce']) : '';
		if (!wp_verify_nonce($nonce, 'pixact_delete_lead_' . $lead_id)) {
			return;
		}

		if (get_post_type($lead_id) !== Pixact_Cost_Calculator_Post_Type::POST_TYPE) {
			return;
		}

		wp_delete_post($lead_id, true);

		$redirect = array(
			'page' => 'pixact-calculator-leads',
			'deleted' => 1,
		);
		if (isset($_GET['filter_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['filter_date'])) {
			$redirect['filter_date'] = sanitize_text_field((string) $_GET['filter_date']);
		}
		if (isset($_GET['filter_project_type'])) {
			$redirect['filter_project_type'] = sanitize_key((string) $_GET['filter_project_type']);
		}
		if (isset($_GET['filter_status'])) {
			$fs = sanitize_key((string) $_GET['filter_status']);
			if (in_array($fs, array('new', 'contacted'), true)) {
				$redirect['filter_status'] = $fs;
			}
		}
		if (isset($_GET['paged'])) {
			$redirect['paged'] = max(1, (int) $_GET['paged']);
		}
		wp_safe_redirect(add_query_arg($redirect, admin_url('admin.php')));
		exit;
	}

	private function handle_lead_contact_action(): void {
		$action = isset($_GET['action']) ? sanitize_key((string) $_GET['action']) : '';
		$lead_id = isset($_GET['lead_id']) ? (int) $_GET['lead_id'] : 0;
		if ($action !== 'mark_contacted' || $lead_id <= 0) {
			return;
		}

		$nonce = isset($_GET['_wpnonce']) ? sanitize_text_field((string) $_GET['_wpnonce']) : '';
		if (!wp_verify_nonce($nonce, 'pixact_mark_contacted_' . $lead_id)) {
			return;
		}

		if (get_post_type($lead_id) !== Pixact_Cost_Calculator_Post_Type::POST_TYPE) {
			return;
		}

		update_post_meta($lead_id, 'contacted', 1);
		$redirect = array(
			'page' => 'pixact-calculator-leads',
			'contacted' => 1,
			'filter_date' => isset($_GET['filter_date']) ? sanitize_text_field((string) $_GET['filter_date']) : '',
			'filter_project_type' => isset($_GET['filter_project_type']) ? sanitize_key((string) $_GET['filter_project_type']) : '',
			'paged' => isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1,
		);
		if (isset($_GET['filter_status'])) {
			$fs = sanitize_key((string) $_GET['filter_status']);
			if (in_array($fs, array('new', 'contacted'), true)) {
				$redirect['filter_status'] = $fs;
			}
		}
		wp_safe_redirect(add_query_arg($redirect, admin_url('admin.php')));
		exit;
	}

	private function handle_lead_export_csv_action(): void {
		$action = isset($_GET['action']) ? sanitize_key((string) $_GET['action']) : '';
		if ($action !== 'export_csv') {
			return;
		}
		$nonce = isset($_GET['_wpnonce']) ? sanitize_text_field((string) $_GET['_wpnonce']) : '';
		if (!wp_verify_nonce($nonce, 'pixact_export_csv')) {
			return;
		}

		$selected_date = isset($_GET['filter_date']) ? sanitize_text_field((string) $_GET['filter_date']) : '';
		$selected_project_type = isset($_GET['filter_project_type']) ? sanitize_key((string) $_GET['filter_project_type']) : '';
		$selected_status = isset($_GET['filter_status']) ? sanitize_key((string) $_GET['filter_status']) : '';
		if (!in_array($selected_status, array('', 'new', 'contacted'), true)) {
			$selected_status = '';
		}
		$query_args = array(
			'post_type' => Pixact_Cost_Calculator_Post_Type::POST_TYPE,
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'orderby' => 'date',
			'order' => 'DESC',
		);

		$meta_query = $this->build_leads_list_meta_query($selected_project_type, $selected_status);
		if (!empty($meta_query)) {
			$query_args['meta_query'] = $meta_query;
		}

		if ($selected_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
			$query_args['date_query'] = array(
				array(
					'after' => $selected_date . ' 00:00:00',
					'before' => $selected_date . ' 23:59:59',
					'inclusive' => true,
				),
			);
		}

		$lead_query = new WP_Query($query_args);

		nocache_headers();
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="pixact-leads-' . gmdate('Ymd-His') . '.csv"');
		$output = fopen('php://output', 'w');
		if ($output === false) {
			exit;
		}

		fputcsv($output, array('ID', 'Name', 'Email', 'Phone', 'Project Type', 'Timeline', 'Complexity', 'Estimate Min', 'Estimate Max', 'Status', 'Submitted'));
		while ($lead_query->have_posts()) {
			$lead_query->the_post();
			$lead_id = get_the_ID();
			fputcsv(
				$output,
				array(
					$lead_id,
					(string) get_post_meta($lead_id, 'name', true),
					(string) get_post_meta($lead_id, 'email', true),
					(string) get_post_meta($lead_id, 'phone', true),
					(string) get_post_meta($lead_id, 'project_type', true),
					(string) get_post_meta($lead_id, 'timeline', true),
					(string) get_post_meta($lead_id, 'complexity', true),
					(int) get_post_meta($lead_id, 'estimate_min', true),
					(int) get_post_meta($lead_id, 'estimate_max', true),
					((int) get_post_meta($lead_id, 'contacted', true) === 1 ? 'Contacted' : 'New'),
					(string) get_the_date('Y-m-d H:i', $lead_id),
				)
			);
		}
		wp_reset_postdata();
		fclose($output);
		exit;
	}

	private function render_lead_details_page(int $lead_id): void {
		$post = get_post($lead_id);
		if (!$post || $post->post_type !== Pixact_Cost_Calculator_Post_Type::POST_TYPE) {
			$this->render_admin_placeholder('Leads', 'Lead not found.');
			return;
		}

		$name = (string) get_post_meta($lead_id, 'name', true);
		$email = (string) get_post_meta($lead_id, 'email', true);
		$phone = (string) get_post_meta($lead_id, 'phone', true);
		$project_type = (string) get_post_meta($lead_id, 'project_type', true);
		$timeline = (string) get_post_meta($lead_id, 'timeline', true);
		$complexity = (string) get_post_meta($lead_id, 'complexity', true);
		$estimate_min = (int) get_post_meta($lead_id, 'estimate_min', true);
		$estimate_max = (int) get_post_meta($lead_id, 'estimate_max', true);
		$is_contacted = (int) get_post_meta($lead_id, 'contacted', true) === 1;
		$answers_json = (string) get_post_meta($lead_id, 'answers', true);
		$lead_summary = (string) get_post_meta($lead_id, 'lead_summary', true);
		if ($lead_summary === '') {
			$decoded_answers = json_decode($answers_json, true);
			$lead_summary = pixact_format_lead_summary(
				array(
					'project_type' => $project_type,
					'estimate_min' => $estimate_min,
					'estimate_max' => $estimate_max,
					'timeline' => $timeline,
					'complexity' => $complexity,
					'answers' => is_array($decoded_answers) ? $decoded_answers : array(),
				)
			);
		}

		$back_url = add_query_arg(array('page' => 'pixact-calculator-leads'), admin_url('admin.php'));
		?>
		<div class="wrap">
			<h1><?php echo esc_html('Lead Details'); ?></h1>
			<p><a class="button" href="<?php echo esc_url($back_url); ?>">&larr; Back to leads</a></p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr><th scope="row">Name</th><td><?php echo esc_html($name ?: 'N/A'); ?></td></tr>
					<tr><th scope="row">Email</th><td><?php echo esc_html($email ?: 'N/A'); ?></td></tr>
					<tr><th scope="row">Phone</th><td><?php echo esc_html($phone ?: 'N/A'); ?></td></tr>
					<tr><th scope="row">Project Type</th><td><?php echo esc_html($project_type ?: 'N/A'); ?></td></tr>
					<tr><th scope="row">Timeline</th><td><?php echo esc_html($timeline ?: 'N/A'); ?></td></tr>
					<tr><th scope="row">Complexity</th><td><?php echo esc_html($complexity ?: 'N/A'); ?></td></tr>
					<tr><th scope="row">Estimate Range</th><td><?php echo esc_html($this->format_estimate_range($estimate_min, $estimate_max)); ?></td></tr>
					<tr><th scope="row">Status</th><td><?php echo esc_html($is_contacted ? 'Contacted' : 'New'); ?></td></tr>
					<tr><th scope="row">Submitted</th><td><?php echo esc_html(get_the_date('Y-m-d H:i', $lead_id)); ?></td></tr>
				</tbody>
			</table>

			<div class="pixact-admin-section" style="margin-top: 20px;">
				<h2>Project Summary</h2>
				<p class="description">Human-readable lead report for non-technical review.</p>
				<div style="line-height: 1.6;"><?php echo wp_kses_post(nl2br(esc_html($lead_summary))); ?></div>
			</div>
		</div>
		<?php
	}

	private function format_estimate_range(int $estimate_min, int $estimate_max): string {
		return '$' . number_format($estimate_min) . ' - $' . number_format($estimate_max);
	}

	private function get_default_pricing_rules(): array {
		return array(
			'basePrices' => array(
				'app' => 18000,
				'website' => 9000,
				'saas' => 28000,
			),
			'featureMultipliers' => array(
				'low' => 1.00,
				'medium' => 1.20,
				'high' => 1.40,
			),
			'multipliers' => array(
				'backendComplexity' => 1.20,
				'userRoles' => 1.10,
				'aiFeatures' => 1.25,
			),
			'timelineAdjustment' => array(
				'normal' => 0,
				'fast' => 15,
				'urgent' => 25,
			),
			'complexityThresholds' => array(
				'basic' => 0,
				'medium' => 2,
				'advanced' => 4,
				'enterprise' => 7,
			),
		);
	}

	public function get_pricing_rules(): array {
		$defaults = $this->get_default_pricing_rules();
		$stored_json = get_option(self::PRICING_RULES_OPTION, '');
		if (!is_string($stored_json) || $stored_json === '') {
			return $defaults;
		}
		$decoded = json_decode($stored_json, true);
		if (!is_array($decoded)) {
			return $defaults;
		}
		return $this->sanitize_pricing_rules($decoded);
	}

	private function sanitize_pricing_rules(array $rules): array {
		$defaults = $this->get_default_pricing_rules();

		$sanitize_number_map = function (array $input, array $fallback): array {
			$output = array();
			foreach ($fallback as $key => $default_value) {
				$raw = $input[$key] ?? $default_value;
				$output[$key] = is_numeric($raw) ? (float) $raw : (float) $default_value;
			}
			return $output;
		};

		$base_prices = $sanitize_number_map(
			is_array($rules['basePrices'] ?? null) ? $rules['basePrices'] : array(),
			$defaults['basePrices']
		);
		$feature_multipliers = $sanitize_number_map(is_array($rules['featureMultipliers'] ?? null) ? $rules['featureMultipliers'] : array(), $defaults['featureMultipliers']);
		$multipliers_source = is_array($rules['multipliers'] ?? null) ? $rules['multipliers'] : array();
		$legacy_backend = is_array($rules['backendComplexityMultipliers'] ?? null) ? $rules['backendComplexityMultipliers'] : array();
		if (empty($multipliers_source) && !empty($legacy_backend)) {
			$multipliers_source = array(
				'backendComplexity' => $legacy_backend['advanced'] ?? $defaults['multipliers']['backendComplexity'],
				'userRoles' => $defaults['multipliers']['userRoles'],
				'aiFeatures' => $defaults['multipliers']['aiFeatures'],
			);
		}
		$multipliers = $sanitize_number_map($multipliers_source, $defaults['multipliers']);

		$timeline_source = is_array($rules['timelineAdjustment'] ?? null) ? $rules['timelineAdjustment'] : array();
		$legacy_timeline = is_array($rules['timelineUrgencyPercent'] ?? null) ? $rules['timelineUrgencyPercent'] : array();
		if (empty($timeline_source) && !empty($legacy_timeline)) {
			$timeline_source = array(
				'normal' => 0,
				'fast' => $legacy_timeline['accelerated'] ?? $defaults['timelineAdjustment']['fast'],
				'urgent' => $legacy_timeline['urgent'] ?? $defaults['timelineAdjustment']['urgent'],
			);
		}
		$timeline_adjustment = $sanitize_number_map($timeline_source, $defaults['timelineAdjustment']);
		$complexity_thresholds = $sanitize_number_map(is_array($rules['complexityThresholds'] ?? null) ? $rules['complexityThresholds'] : array(), $defaults['complexityThresholds']);

		return array(
			'basePrices' => $base_prices,
			'featureMultipliers' => $feature_multipliers,
			'multipliers' => $multipliers,
			'timelineAdjustment' => $timeline_adjustment,
			'complexityThresholds' => $complexity_thresholds,
		);
	}

	private function get_default_calculator_flow_config(): array {
		return array(
			'steps' => array(
				array(
					'id' => 'platform',
					'title' => 'Where do you want your product to be available?',
					'helper' => 'Most startups choose cross-platform to launch faster and reduce initial cost.',
					'explanation' => 'Platform choice affects architecture, delivery speed, and implementation effort.',
					'type' => 'single',
					'options' => array(
						array('label' => 'Mobile or Web App', 'value' => 'app', 'priceModifier' => 'high', 'tags' => array('platform')),
						array('label' => 'Marketing Website', 'value' => 'website', 'priceModifier' => 'low', 'tags' => array('platform')),
						array('label' => 'SaaS Platform', 'value' => 'saas', 'priceModifier' => 'high', 'tags' => array('platform')),
					),
				),
				array(
					'id' => 'features',
					'title' => 'How advanced should the first release be?',
					'helper' => 'A focused MVP helps teams validate faster before scaling scope.',
					'explanation' => 'Complexity reflects depth, polish, and integration effort in your first release.',
					'type' => 'single',
					'options' => array(
						array('label' => 'Lean MVP', 'value' => 'basic', 'priceModifier' => 'low', 'tags' => array('complexity')),
						array('label' => 'Growth Ready', 'value' => 'advanced', 'priceModifier' => 'medium', 'tags' => array('complexity')),
						array('label' => 'Enterprise', 'value' => 'premium', 'priceModifier' => 'high', 'tags' => array('complexity')),
					),
				),
				array(
					'id' => 'timeline',
					'title' => 'When are you aiming to launch?',
					'helper' => 'A realistic timeline improves quality and reduces rework.',
					'explanation' => 'Faster timelines can increase cost due to tighter delivery windows.',
					'type' => 'single',
					'options' => array(
						array('label' => 'Standard Timeline', 'value' => 'standard', 'priceModifier' => 'none', 'tags' => array('timeline')),
						array('label' => 'Accelerated', 'value' => 'accelerated', 'priceModifier' => 'medium', 'tags' => array('timeline')),
						array('label' => 'Urgent Launch', 'value' => 'urgent', 'priceModifier' => 'high', 'tags' => array('timeline')),
					),
				),
				array(
					'id' => 'roles',
					'title' => 'Which capabilities are important for version one?',
					'helper' => 'Prioritize core features now to shorten delivery and control cost.',
					'explanation' => 'Select optional capabilities to include in your first release scope.',
					'type' => 'multi',
					'options' => array(
						array('label' => 'Design System', 'value' => 'designSystem', 'priceModifier' => 'medium', 'tags' => array('feature')),
						array('label' => 'Analytics Stack', 'value' => 'analytics', 'priceModifier' => 'medium', 'tags' => array('feature')),
						array('label' => 'Third-Party Integrations', 'value' => 'integrations', 'priceModifier' => 'high', 'tags' => array('feature')),
						array('label' => 'AI Features', 'value' => 'ai_features', 'priceModifier' => 'high', 'tags' => array('feature', 'ai')),
					),
				),
				array(
					'id' => 'budget',
					'title' => 'Unlock final estimate',
					'helper' => 'Share your details to receive a tailored follow-up scope.',
					'explanation' => 'Provide contact details to receive a guided estimate summary.',
					'type' => 'single',
					'options' => array(),
				),
			),
		);
	}

	public function get_calculator_flow_config(): array {
		$defaults = $this->get_default_calculator_flow_config();
		$stored_json = get_option(self::CALCULATOR_CONFIG_OPTION, '');
		if (!is_string($stored_json) || $stored_json === '') {
			return $defaults;
		}
		$decoded = json_decode($stored_json, true);
		if (!is_array($decoded)) {
			return $defaults;
		}
		return $this->sanitize_calculator_flow_config($decoded);
	}

	private function sanitize_calculator_flow_config(array $config): array {
		$defaults = $this->get_default_calculator_flow_config();
		$submitted_steps = is_array($config['steps'] ?? null) ? $config['steps'] : array();
		$sanitized_steps = array();

		foreach ($submitted_steps as $step) {
			if (!is_array($step)) {
				continue;
			}
			$id = sanitize_key((string) ($step['id'] ?? ''));
			if ($id === '') {
				continue;
			}

			$type = sanitize_key((string) ($step['type'] ?? 'single'));
			if (!in_array($type, array('single', 'multi'), true)) {
				$type = 'single';
			}

			$sanitized_options = array();
			$options = is_array($step['options'] ?? null) ? $step['options'] : array();
			foreach ($options as $option) {
				if (!is_array($option)) {
					continue;
				}
				$option_value = sanitize_key((string) ($option['value'] ?? ''));
				if ($option_value === '') {
					continue;
				}

				$price_modifier = sanitize_key((string) ($option['priceModifier'] ?? 'none'));
				if (!in_array($price_modifier, array('none', 'low', 'medium', 'high'), true)) {
					$price_modifier = 'none';
				}

				$tags = array();
				$incoming_tags = is_array($option['tags'] ?? null) ? $option['tags'] : array();
				foreach ($incoming_tags as $tag) {
					$sanitized_tag = sanitize_key((string) $tag);
					if ($sanitized_tag !== '') {
						$tags[] = $sanitized_tag;
					}
				}

				$sanitized_options[] = array(
					'label' => sanitize_text_field((string) ($option['label'] ?? '')),
					'value' => $option_value,
					'priceModifier' => $price_modifier,
					'tags' => array_values(array_unique($tags)),
				);
			}

			$sanitized_steps[] = array(
				'id' => $id,
				'title' => sanitize_text_field((string) ($step['title'] ?? '')),
				'helper' => sanitize_text_field((string) ($step['helper'] ?? '')),
				'explanation' => sanitize_textarea_field((string) ($step['explanation'] ?? '')),
				'type' => $type,
				'options' => $sanitized_options,
			);
		}

		if (empty($sanitized_steps)) {
			return $defaults;
		}

		return array('steps' => $sanitized_steps);
	}

	public function register_general_settings(): void {
		register_setting(
			self::GENERAL_SETTINGS_GROUP,
			self::GENERAL_SETTINGS_OPTION,
			array(
				'type' => 'array',
				'sanitize_callback' => array($this, 'sanitize_general_settings'),
				'default' => $this->get_default_general_settings(),
			)
		);
		register_setting(
			self::GENERAL_SETTINGS_GROUP,
			self::ADMIN_NOTIFICATION_EMAIL_OPTION,
			array(
				'type' => 'string',
				'sanitize_callback' => array($this, 'sanitize_admin_notification_email'),
				'default' => '',
			)
		);

		add_settings_section(
			'pixact_general_main_section',
			'General Preferences',
			function (): void {
				echo '<p>Configure global calculator behavior and admin preferences.</p>';
			},
			'pixact-calculator-general-settings'
		);

		add_settings_field(
			'admin_email_override',
			'Admin Email Override',
			array($this, 'render_general_field_admin_email'),
			'pixact-calculator-general-settings',
			'pixact_general_main_section'
		);

		add_settings_field(
			'calculator_enabled',
			'Enable Calculator',
			array($this, 'render_general_field_enabled'),
			'pixact-calculator-general-settings',
			'pixact_general_main_section'
		);

		add_settings_field(
			'default_currency',
			'Default Currency',
			array($this, 'render_general_field_currency'),
			'pixact-calculator-general-settings',
			'pixact_general_main_section'
		);

		add_settings_field(
			'minimum_project_threshold',
			'Minimum Project Threshold',
			array($this, 'render_general_field_threshold'),
			'pixact-calculator-general-settings',
			'pixact_general_main_section'
		);

		add_settings_field(
			'debug_mode',
			'Enable Debug Mode',
			array($this, 'render_general_field_debug_mode'),
			'pixact-calculator-general-settings',
			'pixact_general_main_section'
		);
		add_settings_field(
			'admin_notification_email',
			'Notification Email(s)',
			array($this, 'render_general_field_admin_notification_email'),
			'pixact-calculator-general-settings',
			'pixact_general_main_section'
		);
	}

	public function register_ai_settings(): void {
		register_setting(
			self::AI_SETTINGS_GROUP,
			self::AI_SETTINGS_OPTION,
			array(
				'type' => 'array',
				'sanitize_callback' => array($this, 'sanitize_ai_settings'),
				'default' => $this->get_default_ai_settings(),
			)
		);

		add_settings_section(
			'pixact_ai_main_section',
			'AI Microcopy Settings',
			function (): void {
				echo '<p>Configure OpenAI settings for backend-generated microcopy.</p>';
			},
			'pixact-calculator-ai-settings'
		);

		add_settings_field('api_key', 'OpenAI API Key', array($this, 'render_ai_field_api_key'), 'pixact-calculator-ai-settings', 'pixact_ai_main_section');
		add_settings_field('enabled', 'Enable AI Microcopy', array($this, 'render_ai_field_enabled'), 'pixact-calculator-ai-settings', 'pixact_ai_main_section');
		add_settings_field('model', 'Model', array($this, 'render_ai_field_model'), 'pixact-calculator-ai-settings', 'pixact_ai_main_section');
		add_settings_field('temperature', 'Temperature', array($this, 'render_ai_field_temperature'), 'pixact-calculator-ai-settings', 'pixact_ai_main_section');
		add_settings_field('max_tokens', 'Max Tokens', array($this, 'render_ai_field_max_tokens'), 'pixact-calculator-ai-settings', 'pixact_ai_main_section');
		add_settings_field('system_prompt', 'System Prompt', array($this, 'render_ai_field_system_prompt'), 'pixact-calculator-ai-settings', 'pixact_ai_main_section');
	}

	private function get_default_ai_settings(): array {
		return array(
			'api_key' => '',
			'enabled' => 0,
			'model' => 'gpt-4o-mini',
			'temperature' => 0.4,
			'max_tokens' => 300,
			'system_prompt' => 'You are a senior product consultant. Return only strict JSON with keys: helper_text, explanation, recommendation. Keep tone concise, practical, and non-salesy.',
		);
	}

	public function get_ai_settings(): array {
		$saved = get_option(self::AI_SETTINGS_OPTION, array());
		$defaults = $this->get_default_ai_settings();
		if (!is_array($saved)) {
			return $defaults;
		}
		return wp_parse_args($saved, $defaults);
	}

	public function sanitize_ai_settings($input): array {
		$defaults = $this->get_default_ai_settings();
		$input = is_array($input) ? $input : array();
		$api_key = sanitize_text_field((string) ($input['api_key'] ?? $defaults['api_key']));
		$model = sanitize_text_field((string) ($input['model'] ?? $defaults['model']));
		$temp_raw = $input['temperature'] ?? $defaults['temperature'];
		$temperature = is_numeric($temp_raw) ? (float) $temp_raw : (float) $defaults['temperature'];
		$max_tokens_raw = $input['max_tokens'] ?? $defaults['max_tokens'];
		$max_tokens = is_numeric($max_tokens_raw) ? (int) $max_tokens_raw : (int) $defaults['max_tokens'];
		if ($temperature < 0) $temperature = 0;
		if ($temperature > 1) $temperature = 1;
		if ($max_tokens < 64) $max_tokens = 64;
		if ($max_tokens > 2000) $max_tokens = 2000;
		$system_prompt = sanitize_textarea_field((string) ($input['system_prompt'] ?? $defaults['system_prompt']));
		$system_prompt = trim($system_prompt);
		if (function_exists('mb_substr')) {
			$system_prompt = mb_substr($system_prompt, 0, 4000, 'UTF-8');
		} else {
			$system_prompt = substr($system_prompt, 0, 4000);
		}
		if ($system_prompt === '') {
			$system_prompt = (string) $defaults['system_prompt'];
		}
		return array(
			'api_key' => $api_key,
			'enabled' => !empty($input['enabled']) ? 1 : 0,
			'model' => $model !== '' ? $model : $defaults['model'],
			'temperature' => $temperature,
			'max_tokens' => $max_tokens,
			'system_prompt' => $system_prompt,
		);
	}

	public function render_ai_field_api_key(): void {
		$settings = $this->get_ai_settings();
		?>
		<input
			type="password"
			class="regular-text"
			name="<?php echo esc_attr(self::AI_SETTINGS_OPTION); ?>[api_key]"
			value="<?php echo esc_attr((string) $settings['api_key']); ?>"
			autocomplete="off"
		/>
		<p class="description">Stored securely in WordPress options and used only in backend requests.</p>
		<?php
	}

	public function render_ai_field_enabled(): void {
		$settings = $this->get_ai_settings();
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr(self::AI_SETTINGS_OPTION); ?>[enabled]"
				value="1"
				<?php checked(!empty($settings['enabled'])); ?>
			/>
			Enable AI microcopy generation
		</label>
		<?php
	}

	public function render_ai_field_model(): void {
		$settings = $this->get_ai_settings();
		$current_model = (string) $settings['model'];
		$models = array(
			'gpt-4o-mini' => 'gpt-4o-mini (default)',
			'gpt-4.1-mini' => 'gpt-4.1-mini',
			'gpt-4.1' => 'gpt-4.1',
		);
		?>
		<select name="<?php echo esc_attr(self::AI_SETTINGS_OPTION); ?>[model]">
			<?php foreach ($models as $model_value => $label) : ?>
				<option value="<?php echo esc_attr($model_value); ?>" <?php selected($current_model, $model_value); ?>>
					<?php echo esc_html($label); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">Select model used for backend microcopy generation.</p>
		<?php
	}

	public function render_ai_field_temperature(): void {
		$settings = $this->get_ai_settings();
		?>
		<input
			type="number"
			class="small-text"
			min="0"
			max="1"
			step="0.1"
			name="<?php echo esc_attr(self::AI_SETTINGS_OPTION); ?>[temperature]"
			value="<?php echo esc_attr((string) $settings['temperature']); ?>"
		/>
		<p class="description">Optional. Lower values are more deterministic.</p>
		<?php
	}

	public function render_ai_field_max_tokens(): void {
		$settings = $this->get_ai_settings();
		?>
		<input
			type="number"
			class="small-text"
			min="64"
			max="2000"
			step="1"
			name="<?php echo esc_attr(self::AI_SETTINGS_OPTION); ?>[max_tokens]"
			value="<?php echo esc_attr((string) $settings['max_tokens']); ?>"
		/>
		<p class="description">Maximum tokens allocated to generated microcopy response.</p>
		<?php
	}

	public function render_ai_field_system_prompt(): void {
		$settings = $this->get_ai_settings();
		?>
		<textarea
			name="<?php echo esc_attr(self::AI_SETTINGS_OPTION); ?>[system_prompt]"
			rows="8"
			class="large-text"
			style="width: 100%; max-width: 720px; font-family: monospace;"
		><?php echo esc_textarea((string) $settings['system_prompt']); ?></textarea>
		<p class="description">Instructions sent as the system message for microcopy requests. Must require JSON output with keys helper_text, explanation, and recommendation. Max 4000 characters.</p>
		<?php
	}

	private function run_ai_test_request(): bool {
		$settings = $this->get_ai_settings();
		if (empty($settings['api_key'])) {
			return false;
		}
		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Authorization' => 'Bearer ' . (string) $settings['api_key'],
				),
				'body' => wp_json_encode(
					array(
						'model' => (string) ($settings['model'] ?? 'gpt-4o-mini'),
						'temperature' => (float) ($settings['temperature'] ?? 0.4),
						'max_tokens' => (int) ($settings['max_tokens'] ?? 300),
						'messages' => array(
							array('role' => 'user', 'content' => 'Return JSON: {"ok":true}'),
						),
						'response_format' => array('type' => 'json_object'),
					)
				),
			)
		);
		if (is_wp_error($response)) {
			return false;
		}
		$status = (int) wp_remote_retrieve_response_code($response);
		return $status >= 200 && $status < 300;
	}

	private function get_default_email_settings(): array {
		return array(
			'admin_email' => '',
			'from_name' => 'Pixact Technologies',
			'from_email' => '',
			'enable_user_email' => 1,
			'enable_admin_email' => 1,
			'admin_email_subject' => 'New Calculator Lead: {{name}}',
			'user_email_subject' => 'Your estimate from Pixact',
			'user_email_template' => implode(
				"\n",
				array(
					'Hi {{name}},',
					'',
					'Thanks for using our calculator.',
					'Estimated range: {{estimate_min}} - {{estimate_max}}',
					'',
					'Project Summary:',
					'{{lead_summary}}',
					'',
					'We can walk you through next steps on a quick call.',
				)
			),
			'admin_email_template' => implode(
				"\n",
				array(
					'New Inquiry Received',
					'',
					'Name: {{name}}',
					'Email: {{email}}',
					'Phone: {{phone}}',
					'',
					'Project Summary:',
					'{{lead_summary}}',
				)
			),
		);
	}

	public function get_email_settings(): array {
		$saved = get_option(self::EMAIL_SETTINGS_OPTION, array());
		$defaults = $this->get_default_email_settings();
		if (!is_array($saved)) {
			return $defaults;
		}
		return wp_parse_args($saved, $defaults);
	}

	private function sanitize_email_settings(array $input): array {
		$defaults = $this->get_default_email_settings();
		$admin_email = sanitize_email((string) ($input['admin_email'] ?? $defaults['admin_email']));
		$from_email = sanitize_email((string) ($input['from_email'] ?? $defaults['from_email']));
		if ($admin_email !== '' && !is_email($admin_email)) {
			$admin_email = '';
		}
		if ($from_email !== '' && !is_email($from_email)) {
			$from_email = '';
		}
		return array(
			'admin_email' => $admin_email,
			'from_name' => sanitize_text_field((string) ($input['from_name'] ?? $defaults['from_name'])),
			'from_email' => $from_email,
			'enable_user_email' => !empty($input['enable_user_email']) ? 1 : 0,
			'enable_admin_email' => !empty($input['enable_admin_email']) ? 1 : 0,
			'admin_email_subject' => sanitize_text_field((string) ($input['admin_email_subject'] ?? $defaults['admin_email_subject'])),
			'user_email_subject' => sanitize_text_field((string) ($input['user_email_subject'] ?? $defaults['user_email_subject'])),
			'user_email_template' => sanitize_textarea_field((string) ($input['user_email_template'] ?? $defaults['user_email_template'])),
			'admin_email_template' => sanitize_textarea_field((string) ($input['admin_email_template'] ?? $defaults['admin_email_template'])),
		);
	}

	public function get_default_integrations_settings(): array {
		return array(
			'webhook_enabled' => 0,
			'webhook_url' => '',
		);
	}

	public function get_integrations_settings(): array {
		$saved = get_option(self::INTEGRATIONS_SETTINGS_OPTION, array());
		$defaults = $this->get_default_integrations_settings();
		if (!is_array($saved)) {
			return $defaults;
		}
		return wp_parse_args($saved, $defaults);
	}

	private function sanitize_integrations_settings(array $input): array {
		$defaults = $this->get_default_integrations_settings();
		$webhook_url = esc_url_raw((string) ($input['webhook_url'] ?? $defaults['webhook_url']));
		if ($webhook_url !== '' && !wp_http_validate_url($webhook_url)) {
			$webhook_url = '';
		}
		return array(
			'webhook_enabled' => !empty($input['webhook_enabled']) ? 1 : 0,
			'webhook_url' => $webhook_url,
		);
	}

	public function sanitize_general_settings($input): array {
		$defaults = $this->get_default_general_settings();
		$input = is_array($input) ? $input : array();

		$admin_email = sanitize_email((string) ($input['admin_email'] ?? $defaults['admin_email']));
		if ($admin_email === '' || !is_email($admin_email)) {
			$admin_email = $defaults['admin_email'];
		}

		$currency = sanitize_text_field((string) ($input['default_currency'] ?? $defaults['default_currency']));
		$currency = strtoupper(preg_replace('/[^A-Z]/', '', $currency));
		if ($currency === '') {
			$currency = $defaults['default_currency'];
		}

		$threshold_raw = $input['minimum_project_threshold'] ?? $defaults['minimum_project_threshold'];
		$threshold = is_numeric($threshold_raw) ? (float) $threshold_raw : (float) $defaults['minimum_project_threshold'];
		if ($threshold < 0) {
			$threshold = 0;
		}

		return array(
			'admin_email' => $admin_email,
			'calculator_enabled' => !empty($input['calculator_enabled']) ? 1 : 0,
			'default_currency' => $currency,
			'minimum_project_threshold' => $threshold,
			'debug_mode' => !empty($input['debug_mode']) ? 1 : 0,
		);
	}

	private function get_default_general_settings(): array {
		return array(
			'admin_email' => (string) get_option('admin_email'),
			'calculator_enabled' => 1,
			'default_currency' => 'USD',
			'minimum_project_threshold' => 0,
			'debug_mode' => 0,
		);
	}

	private function get_general_settings(): array {
		$saved = get_option(self::GENERAL_SETTINGS_OPTION, array());
		$defaults = $this->get_default_general_settings();
		if (!is_array($saved)) {
			return $defaults;
		}
		return wp_parse_args($saved, $defaults);
	}

	public function render_general_field_admin_email(): void {
		$settings = $this->get_general_settings();
		?>
		<input
			type="email"
			class="regular-text"
			name="<?php echo esc_attr(self::GENERAL_SETTINGS_OPTION); ?>[admin_email]"
			value="<?php echo esc_attr((string) $settings['admin_email']); ?>"
		/>
		<p class="description">Overrides default WordPress admin email for calculator notifications.</p>
		<?php
	}

	public function render_general_field_enabled(): void {
		$settings = $this->get_general_settings();
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr(self::GENERAL_SETTINGS_OPTION); ?>[calculator_enabled]"
				value="1"
				<?php checked(!empty($settings['calculator_enabled'])); ?>
			/>
			Enable calculator on frontend pages
		</label>
		<?php
	}

	public function render_general_field_currency(): void {
		$settings = $this->get_general_settings();
		?>
		<input
			type="text"
			class="regular-text"
			maxlength="5"
			name="<?php echo esc_attr(self::GENERAL_SETTINGS_OPTION); ?>[default_currency]"
			value="<?php echo esc_attr((string) $settings['default_currency']); ?>"
		/>
		<p class="description">Use ISO code like USD, EUR, GBP.</p>
		<?php
	}

	public function render_general_field_threshold(): void {
		$settings = $this->get_general_settings();
		?>
		<input
			type="number"
			class="small-text"
			min="0"
			step="1"
			name="<?php echo esc_attr(self::GENERAL_SETTINGS_OPTION); ?>[minimum_project_threshold]"
			value="<?php echo esc_attr((string) $settings['minimum_project_threshold']); ?>"
		/>
		<p class="description">Minimum project estimate threshold for qualification.</p>
		<?php
	}

	public function render_general_field_debug_mode(): void {
		$settings = $this->get_general_settings();
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr(self::GENERAL_SETTINGS_OPTION); ?>[debug_mode]"
				value="1"
				<?php checked(!empty($settings['debug_mode'])); ?>
			/>
			Enable debug mode for troubleshooting
		</label>
		<?php
	}

	public function sanitize_admin_notification_email($input): string {
		$raw = is_string($input) ? $input : '';
		$parts = array_filter(array_map('trim', explode(',', $raw)));
		$emails = array();
		foreach ($parts as $part) {
			$email = sanitize_email($part);
			if ($email !== '' && is_email($email)) {
				$emails[] = $email;
			}
		}
		return implode(', ', array_values(array_unique($emails)));
	}

	public function render_general_field_admin_notification_email(): void {
		$value = (string) get_option(self::ADMIN_NOTIFICATION_EMAIL_OPTION, '');
		?>
		<input
			type="text"
			class="regular-text"
			name="<?php echo esc_attr(self::ADMIN_NOTIFICATION_EMAIL_OPTION); ?>"
			value="<?php echo esc_attr($value); ?>"
			placeholder="office@company.com, sales@company.com"
		/>
		<p class="description">Comma-separated notification recipients. Falls back to WordPress admin email when empty.</p>
		<?php
	}
}

register_activation_hook(__FILE__, array('Pixact_Cost_Calculator_Plugin', 'activate'));

(new Pixact_Cost_Calculator_Plugin())->bootstrap();
