<?php
/**
 * Plugin Name: Pixact Cost Calculator
 * Description: Lightweight WordPress plugin to embed the Pixact React cost calculator, manage pricing questions, and capture leads.
 * Version: 1.1.0
 * Author: Pixact
 */

if (!defined('ABSPATH')) {
	exit;
}

final class Pixact_Cost_Calculator_Plugin {
	const VERSION = '1.0.0';
	const SCRIPT_HANDLE = 'pixact-cost-calculator';
	const STYLE_HANDLE = 'pixact-cost-calculator';
	const REST_NAMESPACE = 'pixact/v1';
	const REST_ROUTE = '/lead';
	const REST_MICROCOPY_ROUTE = '/microcopy';
	const CPT = 'calculator_leads';
	const SETTINGS_OPTION = 'pixact_calculator_settings';
	const SETTINGS_GROUP = 'pixact_calculator_settings_group';
	const AI_SETTINGS_OPTION = 'pixact_calculator_ai_settings';
	const AI_SETTINGS_GROUP = 'pixact_calculator_ai_settings_group';
	const MIN_SECONDS_TO_SUBMIT = 3;
	const AI_FAILURE_THRESHOLD = 3;
	const AI_FAILURE_WINDOW_SECONDS = 600;
	const AI_KILL_SWITCH_SECONDS = 900;

	public function __construct() {
		add_action('init', array($this, 'register_cpt'));
		add_action('init', array($this, 'register_shortcode'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
		add_action('rest_api_init', array($this, 'register_rest_routes'));
		add_action('admin_menu', array($this, 'register_admin_menu'));
		add_action('admin_init', array($this, 'register_settings'));
		add_filter('manage_' . self::CPT . '_posts_columns', array($this, 'set_lead_columns'));
		add_action('manage_' . self::CPT . '_posts_custom_column', array($this, 'render_lead_columns'), 10, 2);
		add_action('add_meta_boxes', array($this, 'register_lead_meta_box'));
	}

	public function register_cpt() {
		register_post_type(
			self::CPT,
			array(
				'label' => 'Calculator Leads',
				'labels' => array(
					'name'          => 'Calculator Leads',
					'singular_name' => 'Calculator Lead',
				),
				'public'             => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'menu_position'      => 26,
				'supports'           => array('title'),
				'capability_type'    => 'post',
				'map_meta_cap'       => true,
				'exclude_from_search'=> true,
			)
		);
	}

	public function register_shortcode() {
		add_shortcode('pixact_cost_calculator', array($this, 'render_shortcode'));
	}

	public function render_shortcode() {
		return '<div id="pixact-calculator-root"></div>';
	}

	public function enqueue_assets() {
		if (!$this->should_enqueue()) {
			return;
		}

		$asset_base_url = plugin_dir_url(__FILE__) . 'assets/calculator/';
		$asset_base_dir = plugin_dir_path(__FILE__) . 'assets/calculator/';

		$script_candidates = glob($asset_base_dir . 'index-*.js');
		$style_candidates = glob($asset_base_dir . 'index-*.css');
		$script_path = !empty($script_candidates) ? $script_candidates[0] : '';
		$style_path = !empty($style_candidates) ? $style_candidates[0] : '';

		if (!empty($style_path) && file_exists($style_path)) {
			wp_enqueue_style(
				self::STYLE_HANDLE,
				$asset_base_url . basename($style_path),
				array(),
				filemtime($style_path)
			);
		}

		if (!empty($script_path) && file_exists($script_path)) {
			wp_enqueue_script(
				self::SCRIPT_HANDLE,
				$asset_base_url . basename($script_path),
				array(),
				filemtime($script_path),
				true
			);

			wp_localize_script(
				self::SCRIPT_HANDLE,
				'PixactCalculator',
				array(
					'restUrl' => site_url('/wp-json/pixact/v1/'),
					'nonce'   => wp_create_nonce('wp_rest'),
					'siteUrl' => site_url(),
					'aiEnabled' => !empty($this->get_ai_settings()['enabled']),
					'calculatorConfig' => $this->get_settings(),
				)
			);
		}
	}

	public function register_admin_menu() {
		add_options_page(
			'Pixact Calculator',
			'Pixact Calculator',
			'manage_options',
			'pixact-cost-calculator',
			array($this, 'render_settings_page')
		);
	}

	public function register_settings() {
		register_setting(
			self::SETTINGS_GROUP,
			self::SETTINGS_OPTION,
			array(
				'type' => 'array',
				'sanitize_callback' => array($this, 'sanitize_settings'),
				'default' => $this->get_default_settings(),
			)
		);

		register_setting(
			self::AI_SETTINGS_GROUP,
			self::AI_SETTINGS_OPTION,
			array(
				'type' => 'array',
				'sanitize_callback' => array($this, 'sanitize_ai_settings'),
				'default' => $this->get_default_ai_settings(),
			)
		);
	}

	public function render_settings_page() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$settings = $this->get_settings();
		$ai_settings = $this->get_ai_settings();
		?>
		<div class="wrap">
			<h1>Pixact Cost Calculator Settings</h1>
			<p>Configure all calculator questions, option labels, and relative prices directly from WordPress admin.</p>
			<h2>AI Microcopy Settings</h2>
			<form method="post" action="options.php">
				<?php settings_fields(self::AI_SETTINGS_GROUP); ?>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">Enable AI microcopy</th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr(self::AI_SETTINGS_OPTION); ?>[enabled]" value="1" <?php checked(!empty($ai_settings['enabled'])); ?> />
									Generate helper text and recommendations with OpenAI.
								</label>
								<p class="description">Turns AI-generated helper text on or off.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">OpenAI API Key</th>
							<td>
								<input
									type="password"
									class="regular-text"
									name="<?php echo esc_attr(self::AI_SETTINGS_OPTION); ?>[api_key]"
									value="<?php echo esc_attr($ai_settings['api_key']); ?>"
									autocomplete="off"
								/>
								<p class="description">Used only on server requests to OpenAI. Never sent to visitors.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Model</th>
							<td>
								<input
									class="regular-text"
									name="<?php echo esc_attr(self::AI_SETTINGS_OPTION); ?>[model]"
									value="<?php echo esc_attr($ai_settings['model']); ?>"
								/>
								<p class="description">Selects which OpenAI model generates microcopy. Default: gpt-4o-mini.</p>
							</td>
						</tr>
					</tbody>
				</table>
				<?php submit_button('Save AI Settings'); ?>
			</form>

			<hr style="margin: 24px 0;" />
			<h2>Calculator Configuration</h2>
			<form method="post" action="options.php">
				<?php settings_fields(self::SETTINGS_GROUP); ?>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">Step 1 Content</th>
							<td>
								<p class="description">Question + helper text shown for platform selection.</p>
								<input class="regular-text" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[ui][step1Title]" value="<?php echo esc_attr($settings['ui']['step1Title']); ?>" />
								<p><input class="regular-text" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[ui][step1Subtitle]" value="<?php echo esc_attr($settings['ui']['step1Subtitle']); ?>" /></p>
								<p><textarea class="large-text" rows="2" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[ui][step1Explanation]"><?php echo esc_textarea($settings['ui']['step1Explanation']); ?></textarea></p>
							</td>
						</tr>
						<tr>
							<th scope="row">Step 2 Content</th>
							<td>
								<p class="description">Question + helper text shown for complexity selection.</p>
								<input class="regular-text" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[ui][step2Title]" value="<?php echo esc_attr($settings['ui']['step2Title']); ?>" />
								<p><input class="regular-text" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[ui][step2Subtitle]" value="<?php echo esc_attr($settings['ui']['step2Subtitle']); ?>" /></p>
								<p><textarea class="large-text" rows="2" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[ui][step2Explanation]"><?php echo esc_textarea($settings['ui']['step2Explanation']); ?></textarea></p>
							</td>
						</tr>
						<tr>
							<th scope="row">Step 3 Content</th>
							<td>
								<p class="description">Question + helper text shown for timeline selection.</p>
								<input class="regular-text" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[ui][step3Title]" value="<?php echo esc_attr($settings['ui']['step3Title']); ?>" />
								<p><input class="regular-text" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[ui][step3Subtitle]" value="<?php echo esc_attr($settings['ui']['step3Subtitle']); ?>" /></p>
								<p><textarea class="large-text" rows="2" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[ui][step3Explanation]"><?php echo esc_textarea($settings['ui']['step3Explanation']); ?></textarea></p>
							</td>
						</tr>
						<tr>
							<th scope="row">Step 4 Content</th>
							<td>
								<p class="description">Question + helper text shown for add-on selection.</p>
								<input class="regular-text" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[ui][step4Title]" value="<?php echo esc_attr($settings['ui']['step4Title']); ?>" />
								<p><input class="regular-text" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[ui][step4Subtitle]" value="<?php echo esc_attr($settings['ui']['step4Subtitle']); ?>" /></p>
								<p><textarea class="large-text" rows="2" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[ui][step4Explanation]"><?php echo esc_textarea($settings['ui']['step4Explanation']); ?></textarea></p>
							</td>
						</tr>
						<tr>
							<th scope="row">Step 5 Content</th>
							<td>
								<p class="description">Lead capture and final estimate messaging.</p>
								<input class="regular-text" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[ui][step5Title]" value="<?php echo esc_attr($settings['ui']['step5Title']); ?>" />
								<p><input class="regular-text" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[ui][step5Subtitle]" value="<?php echo esc_attr($settings['ui']['step5Subtitle']); ?>" /></p>
								<p><textarea class="large-text" rows="2" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[ui][step5Explanation]"><?php echo esc_textarea($settings['ui']['step5Explanation']); ?></textarea></p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php $this->render_option_table('Project Types (Base Price)', 'projectOptions', $settings['projectOptions'], 'price'); ?>
				<?php $this->render_option_table('Complexity (Multiplier)', 'complexityOptions', $settings['complexityOptions'], 'multiplier'); ?>
				<?php $this->render_option_table('Timeline (Multiplier)', 'timelineOptions', $settings['timelineOptions'], 'multiplier'); ?>
				<?php $this->render_option_table('Add-ons (Fixed Price)', 'addonOptions', $settings['addonOptions'], 'price'); ?>

				<?php submit_button('Save Calculator Settings'); ?>
			</form>
		</div>
		<?php
	}

	private function render_option_table($title, $group_key, $rows, $value_key) {
		?>
		<h2><?php echo esc_html($title); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th>Key</th>
					<th>Label</th>
					<th>Description</th>
					<th>Badge</th>
					<th><?php echo esc_html(ucfirst($value_key)); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($rows as $index => $row) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html($row['value']); ?></strong>
							<input type="hidden" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[<?php echo esc_attr($group_key); ?>][<?php echo esc_attr($index); ?>][value]" value="<?php echo esc_attr($row['value']); ?>" />
						</td>
						<td><input class="regular-text" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[<?php echo esc_attr($group_key); ?>][<?php echo esc_attr($index); ?>][label]" value="<?php echo esc_attr($row['label']); ?>" /></td>
						<td><input class="regular-text" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[<?php echo esc_attr($group_key); ?>][<?php echo esc_attr($index); ?>][description]" value="<?php echo esc_attr($row['description']); ?>" /></td>
						<td><input class="regular-text" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[<?php echo esc_attr($group_key); ?>][<?php echo esc_attr($index); ?>][badge]" value="<?php echo esc_attr($row['badge']); ?>" /></td>
						<td><input class="small-text" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[<?php echo esc_attr($group_key); ?>][<?php echo esc_attr($index); ?>][<?php echo esc_attr($value_key); ?>]" value="<?php echo esc_attr($row[$value_key]); ?>" /></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function get_default_settings() {
		return array(
			'projectOptions' => array(
				array('value' => 'app', 'label' => 'Mobile or Web App', 'description' => 'Product-focused application with user interactions.', 'badge' => 'Popular', 'price' => 18000),
				array('value' => 'website', 'label' => 'Marketing Website', 'description' => 'High-converting brand or lead generation website.', 'badge' => '', 'price' => 9000),
				array('value' => 'saas', 'label' => 'SaaS Platform', 'description' => 'Subscription product with accounts, billing, and dashboards.', 'badge' => 'High ROI', 'price' => 28000),
			),
			'complexityOptions' => array(
				array('value' => 'basic', 'label' => 'Lean MVP', 'description' => 'Essential flow, focused features, quick to market.', 'badge' => '', 'multiplier' => 1),
				array('value' => 'advanced', 'label' => 'Growth Ready', 'description' => 'Richer UX, more integrations, and polished components.', 'badge' => '', 'multiplier' => 1.35),
				array('value' => 'premium', 'label' => 'Enterprise', 'description' => 'Deep custom workflows, scale-ready architecture.', 'badge' => '', 'multiplier' => 1.7),
			),
			'timelineOptions' => array(
				array('value' => 'standard', 'label' => 'Standard Timeline', 'description' => 'Balanced execution with full discovery cycle.', 'badge' => '', 'multiplier' => 1),
				array('value' => 'accelerated', 'label' => 'Accelerated', 'description' => 'Faster delivery with prioritized milestones.', 'badge' => '', 'multiplier' => 1.18),
				array('value' => 'urgent', 'label' => 'Urgent Launch', 'description' => 'High-priority execution for immediate release.', 'badge' => 'Fastest', 'multiplier' => 1.35),
			),
			'addonOptions' => array(
				array('value' => 'designSystem', 'label' => 'Design System', 'description' => 'Reusable UI foundation for scale.', 'badge' => '', 'price' => 2500),
				array('value' => 'analytics', 'label' => 'Analytics Stack', 'description' => 'Event tracking and conversion visibility.', 'badge' => '', 'price' => 1800),
				array('value' => 'integrations', 'label' => 'Third-Party Integrations', 'description' => 'CRM, payments, and workflow automation.', 'badge' => '', 'price' => 3200),
				array('value' => 'seo', 'label' => 'SEO + Content Setup', 'description' => 'Technical optimization and launch content structure.', 'badge' => '', 'price' => 1500),
			),
			'ui' => array(
				'step1Title' => 'Where do you want your product to be available?',
				'step1Subtitle' => 'Pick the platform strategy that best matches your launch goals.',
				'step1Explanation' => 'Project type sets the baseline effort. A SaaS platform includes core product architecture and usually carries a higher starting point than a brochure website.',
				'step1Microcopy' => 'Most startups choose cross-platform to launch faster and reduce initial cost.',
				'step2Title' => 'How advanced should the first release be?',
				'step2Subtitle' => 'Choose the level of depth needed for your first version.',
				'step2Explanation' => 'Higher complexity increases engineering and QA depth. It reflects integrations, edge cases, and the level of product quality expected at launch.',
				'step2Microcopy' => 'A focused MVP often helps teams validate faster before scaling scope.',
				'step3Title' => 'When are you aiming to launch?',
				'step3Subtitle' => 'Timeline affects team allocation and delivery strategy.',
				'step3Explanation' => 'Accelerated or urgent timelines require compressed planning and parallel implementation. That usually increases cost due to dedicated resourcing.',
				'step3Microcopy' => 'A realistic timeline usually improves quality and reduces rework.',
				'step4Title' => 'Add strategic extras',
				'step4Subtitle' => 'Choose add-ons to fine-tune the proposal range.',
				'step4Explanation' => 'Add-ons are fixed modules layered on top of your base scope. They can increase launch impact while keeping planning transparent.',
				'step4Microcopy' => 'Prioritizing core features now can shorten delivery and control cost.',
				'step5Title' => 'Unlock final estimate',
				'step5Subtitle' => 'Share your details to receive a tailored follow-up scope.',
				'step5Explanation' => 'Lead capture appears before the final CTA so your team can follow up with a tailored discovery call and accurate proposal.',
				'leadFramingMessage' => 'We’ve prepared a tailored estimate based on your inputs. Enter your details to view your full breakdown.',
				'resultRecommendation' => 'We recommend starting with a focused MVP to validate your core idea and scale efficiently.',
				'resultTrustLine' => 'We do not lock you into this estimate. We first show a working direction or prototype before you commit.',
				'ctaConsultation' => 'Schedule Free Consultation',
				'ctaPrototype' => 'Request Prototype Preview',
				'ctaDetailedEstimate' => 'Send Me Detailed Estimate',
			),
		);
	}

	private function get_settings() {
		$saved = get_option(self::SETTINGS_OPTION, array());
		$defaults = $this->get_default_settings();
		if (!is_array($saved)) {
			return $defaults;
		}
		$merged = wp_parse_args($saved, $defaults);
		$merged['ui'] = wp_parse_args(
			is_array($saved['ui'] ?? null) ? $saved['ui'] : array(),
			$defaults['ui']
		);

		return $merged;
	}

	public function sanitize_settings($input) {
		$defaults = $this->get_default_settings();
		$input = is_array($input) ? $input : array();
		$output = $defaults;

		$ui_keys = array_keys($defaults['ui']);
		foreach ($ui_keys as $key) {
			$output['ui'][$key] = sanitize_text_field($input['ui'][$key] ?? $defaults['ui'][$key]);
		}

		$groups = array(
			'projectOptions' => array('price'),
			'complexityOptions' => array('multiplier'),
			'timelineOptions' => array('multiplier'),
			'addonOptions' => array('price'),
		);

		foreach ($groups as $group => $numeric_keys) {
			foreach ($defaults[$group] as $index => $default_row) {
				$row_input = $input[$group][$index] ?? array();
				$output[$group][$index]['value'] = sanitize_key($default_row['value']);
				$output[$group][$index]['label'] = sanitize_text_field($row_input['label'] ?? $default_row['label']);
				$output[$group][$index]['description'] = sanitize_text_field($row_input['description'] ?? $default_row['description']);
				$output[$group][$index]['badge'] = sanitize_text_field($row_input['badge'] ?? $default_row['badge']);

				foreach ($numeric_keys as $numeric_key) {
					$raw = $row_input[$numeric_key] ?? $default_row[$numeric_key];
					$output[$group][$index][$numeric_key] = is_numeric($raw) ? (float) $raw : (float) $default_row[$numeric_key];
				}
			}
		}

		return $output;
	}

	private function get_default_ai_settings() {
		return array(
			'enabled' => 0,
			'api_key' => '',
			'model' => 'gpt-4o-mini',
		);
	}

	private function get_ai_settings() {
		$saved = get_option(self::AI_SETTINGS_OPTION, array());
		$defaults = $this->get_default_ai_settings();
		if (!is_array($saved)) {
			return $defaults;
		}

		return wp_parse_args($saved, $defaults);
	}

	public function sanitize_ai_settings($input) {
		$defaults = $this->get_default_ai_settings();
		$input = is_array($input) ? $input : array();

		return array(
			'enabled' => !empty($input['enabled']) ? 1 : 0,
			'api_key' => sanitize_text_field($input['api_key'] ?? $defaults['api_key']),
			'model' => sanitize_text_field($input['model'] ?? $defaults['model']),
		);
	}

	private function should_enqueue() {
		if (is_admin()) {
			return false;
		}

		if (!is_singular()) {
			return false;
		}

		$post = get_post();
		if (!$post) {
			return false;
		}

		return has_shortcode($post->post_content, 'pixact_cost_calculator');
	}

	public function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'handle_lead_submission'),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_MICROCOPY_ROUTE,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'handle_microcopy_generation'),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function handle_lead_submission(WP_REST_Request $request) {
		$nonce = $request->get_header('x_wp_nonce');
		if (!$nonce) {
			$nonce = $request->get_param('_wpnonce');
		}

		if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
			return new WP_REST_Response(array('message' => 'Invalid nonce'), 403);
		}

		$params = $request->get_json_params();
		if (!is_array($params)) {
			return new WP_REST_Response(array('message' => 'Invalid payload'), 400);
		}

		$answers = isset($params['answers']) && is_array($params['answers']) ? $params['answers'] : array();
		$lead = isset($answers['lead']) && is_array($answers['lead']) ? $answers['lead'] : array();

		$name = sanitize_text_field($params['name'] ?? ($lead['fullName'] ?? ''));
		$email = sanitize_email($params['email'] ?? ($lead['email'] ?? ''));
		$phone = sanitize_text_field($params['phone'] ?? ($lead['phone'] ?? ''));
		$project_type = sanitize_text_field($params['project_type'] ?? ($answers['projectType'] ?? ''));
		$timeline = sanitize_text_field($params['timeline'] ?? ($answers['timeline'] ?? ''));
		$complexity = sanitize_text_field($params['complexity'] ?? ($answers['complexity'] ?? ''));
		$estimate_min = isset($params['estimate_min']) ? (float) $params['estimate_min'] : 0;
		$estimate_max = isset($params['estimate_max']) ? (float) $params['estimate_max'] : 0;
		$estimate_range = sanitize_text_field($params['estimateRange'] ?? '');
		if (empty($estimate_range) && $estimate_min > 0 && $estimate_max > 0) {
			$estimate_range = '$' . number_format_i18n($estimate_min, 0) . ' - $' . number_format_i18n($estimate_max, 0);
		}
		$honeypot = sanitize_text_field($lead['honeypot'] ?? '');

		$started_at = absint($params['startedAt'] ?? 0);
		$submitted_at = absint($params['submittedAt'] ?? 0);

		if (!empty($honeypot)) {
			return new WP_REST_Response(array('message' => 'Spam rejected'), 400);
		}

		if ($started_at > 0 && $submitted_at > 0) {
			if ($submitted_at <= $started_at) {
				return new WP_REST_Response(array('message' => 'Invalid timing payload'), 400);
			}

			$elapsed_seconds = (int) floor(($submitted_at - $started_at) / 1000);
			if ($elapsed_seconds < self::MIN_SECONDS_TO_SUBMIT) {
				return new WP_REST_Response(array('message' => 'Submission too fast'), 400);
			}
		}

		if (empty($name) || empty($email) || empty($project_type) || empty($timeline)) {
			return new WP_REST_Response(array('message' => 'Missing required fields'), 422);
		}

		if (!is_email($email)) {
			return new WP_REST_Response(array('message' => 'Invalid email'), 422);
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::CPT,
				'post_status' => 'publish',
				'post_title'  => sprintf('%s - %s', $name, current_time('mysql')),
			),
			true
		);

		if (is_wp_error($post_id)) {
			return new WP_REST_Response(array('message' => 'Failed to save lead'), 500);
		}

		update_post_meta($post_id, 'name', $name);
		update_post_meta($post_id, 'email', $email);
		update_post_meta($post_id, 'phone', $phone);
		update_post_meta($post_id, 'project_type', $project_type);
		update_post_meta($post_id, 'estimate_range', $estimate_range);
		update_post_meta($post_id, 'timeline', $timeline);
		update_post_meta($post_id, 'complexity', $complexity);
		update_post_meta($post_id, 'answers_json', wp_json_encode($answers));

		$this->send_lead_email(
			array(
				'name'          => $name,
				'email'         => $email,
				'phone'         => $phone,
				'project_type'  => $project_type,
				'estimate_range'=> $estimate_range,
				'timeline'      => $timeline,
			)
		);

		return new WP_REST_Response(array('message' => 'Lead captured', 'leadId' => $post_id), 201);
	}

	public function handle_microcopy_generation(WP_REST_Request $request) {
		$nonce = $request->get_header('x_wp_nonce');
		if (!$nonce) {
			$nonce = $request->get_param('_wpnonce');
		}

		if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
			return new WP_REST_Response(array('message' => 'Invalid nonce'), 403);
		}

		$params = $request->get_json_params();
		if (!is_array($params)) {
			return new WP_REST_Response(array('message' => 'Invalid payload'), 400);
		}

		$raw_step = sanitize_key($params['step'] ?? '');
		$step = $this->normalize_step_name($raw_step);
		$raw_answers = isset($params['answers']) && is_array($params['answers'])
			? $this->sanitize_answers_recursive($params['answers'])
			: array();
		$answers = $this->filter_meaningful_answers($raw_answers);
		$calculator_type = sanitize_text_field($params['calculator_type'] ?? 'app-web-cost-calculator');
		$context_flags = $this->build_context_flags($answers);

		if (empty($step)) {
			return new WP_REST_Response(array('message' => 'Missing step'), 422);
		}

		$cache_key = 'pixact_mc_' . md5(wp_json_encode(array($step, $answers, $context_flags, $calculator_type)));
		$cached = get_transient($cache_key);
		if (is_array($cached) && isset($cached['helper_text'], $cached['explanation'], $cached['recommendation'])) {
			$this->log_debug('Microcopy cache hit.');
			return new WP_REST_Response($cached, 200);
		}
		$rate_key = 'pixact_mcr_' . md5($cache_key);
		if (get_transient($rate_key)) {
			$fallback = $this->get_default_microcopy_for_step($step);
			return new WP_REST_Response($fallback, 200);
		}
		set_transient($rate_key, 1, 2);

		$ai_settings = $this->get_ai_settings();
		if ($this->is_ai_killed() || empty($ai_settings['enabled']) || empty($ai_settings['api_key'])) {
			$this->log_debug('Microcopy fallback due to AI disabled or kill switch.');
			$fallback = $this->get_default_microcopy_for_step($step);
			set_transient($cache_key, $fallback, 6 * HOUR_IN_SECONDS);
			return new WP_REST_Response($fallback, 200);
		}

		$prompt = $this->build_microcopy_prompt($step, $answers, $context_flags, $calculator_type);
		$ai_response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $ai_settings['api_key'],
				),
				'body' => wp_json_encode(
					array(
						'model' => $ai_settings['model'],
						'temperature' => 0.4,
						'max_tokens' => 180,
						'messages' => array(
							array('role' => 'system', 'content' => 'You are a senior product consultant. Return strict JSON only.'),
							array('role' => 'user', 'content' => $prompt),
						),
					)
				),
			)
		);

		if (is_wp_error($ai_response) || wp_remote_retrieve_response_code($ai_response) >= 400) {
			$this->log_debug('Microcopy API request failed.');
			$this->register_ai_failure();
			$fallback = $this->get_default_microcopy_for_step($step);
			set_transient($cache_key, $fallback, 6 * HOUR_IN_SECONDS);
			return new WP_REST_Response($fallback, 200);
		}
		$this->clear_ai_failure_state();
		$this->log_debug('Microcopy API request succeeded.');

		$body = json_decode(wp_remote_retrieve_body($ai_response), true);
		$content = $body['choices'][0]['message']['content'] ?? '';
		$parsed = $this->parse_microcopy_content($content);
		if (empty($parsed['helper_text']) || empty($parsed['explanation']) || empty($parsed['recommendation'])) {
			$this->log_debug('Microcopy API parse failed, using fallback.');
			$this->register_ai_failure();
			$parsed = $this->get_default_microcopy_for_step($step);
		} else {
			$this->clear_ai_failure_state();
		}

		set_transient($cache_key, $parsed, 6 * HOUR_IN_SECONDS);
		return new WP_REST_Response($parsed, 200);
	}

	private function send_lead_email($lead_data) {
		$to = get_option('admin_email');
		$subject = 'New Pixact Calculator Lead';

		$lines = array(
			'New lead submitted from Pixact Cost Calculator.',
			'',
			'Name: ' . ($lead_data['name'] ?? ''),
			'Email: ' . ($lead_data['email'] ?? ''),
			'Phone: ' . ($lead_data['phone'] ?? ''),
			'Project Type: ' . ($lead_data['project_type'] ?? ''),
			'Timeline: ' . ($lead_data['timeline'] ?? ''),
			'Estimate Range: ' . ($lead_data['estimate_range'] ?? ''),
		);

		wp_mail($to, $subject, implode("\n", $lines));
	}

	private function sanitize_answers_recursive($value) {
		if (is_array($value)) {
			$clean = array();
			foreach ($value as $key => $item) {
				$clean[sanitize_key((string) $key)] = $this->sanitize_answers_recursive($item);
			}
			return $clean;
		}

		if (is_string($value)) {
			return sanitize_text_field($value);
		}

		if (is_numeric($value)) {
			return $value + 0;
		}

		return '';
	}

	private function normalize_step_name($step) {
		$map = array(
			'step1' => 'platform',
			'step2' => 'features',
			'step3' => 'timeline',
			'step4' => 'roles',
			'step5' => 'budget',
			'platform' => 'platform',
			'features' => 'features',
			'backend' => 'backend',
			'design' => 'design',
			'timeline' => 'timeline',
			'budget' => 'budget',
			'roles' => 'roles',
		);

		return $map[$step] ?? 'platform';
	}

	private function filter_meaningful_answers($answers) {
		$meaningful = array();
		$project_type = sanitize_text_field($answers['projectType'] ?? '');
		$complexity = sanitize_text_field($answers['complexity'] ?? '');
		$timeline = sanitize_text_field($answers['timeline'] ?? '');
		$addons = isset($answers['addons']) && is_array($answers['addons']) ? array_map('sanitize_text_field', $answers['addons']) : array();

		if (!empty($project_type)) {
			$meaningful['project_type'] = $project_type;
		}
		if (!empty($complexity)) {
			$meaningful['complexity'] = $complexity;
		}
		if (!empty($timeline)) {
			$meaningful['timeline'] = $timeline;
		}
		if (!empty($addons)) {
			$meaningful['addons'] = array_values($addons);
		}

		return $meaningful;
	}

	private function build_context_flags($answers) {
		$addons = isset($answers['addons']) && is_array($answers['addons']) ? $answers['addons'] : array();
		$feature_count = count($addons);
		$complexity = sanitize_text_field($answers['complexity'] ?? '');
		$timeline = sanitize_text_field($answers['timeline'] ?? '');
		$roles = isset($answers['roles']) && is_array($answers['roles']) ? $answers['roles'] : array();
		$roles_count = count($roles);
		$high_complexity = in_array($complexity, array('premium', 'enterprise'), true);

		$has_ai = false;
		foreach ($addons as $addon) {
			if (strpos(strtolower((string) $addon), 'ai') !== false) {
				$has_ai = true;
				break;
			}
		}

		return array(
			'has_ai' => $has_ai,
			'feature_count' => $feature_count,
			'is_high_complexity' => $high_complexity,
			'high_complexity' => $high_complexity,
			'is_low_budget' => false,
			'is_urgent' => $timeline === 'urgent',
			'roles_count' => $roles_count,
			'multiple_roles' => $roles_count > 1,
		);
	}

	private function build_microcopy_prompt($step, $answers, $context_flags, $calculator_type) {
		return "You are a senior product consultant at a high-end software development company.\n\nYour job is to guide potential clients through estimating their app or website project.\n\nYou must generate three outputs:\n\n1. helper_text (max 18 words)\n- Short, guiding sentence\n- Helps user understand what to choose\n\n2. explanation (max 50 words)\n- Clear explanation of the concept\n- No jargon\n- Simple but authoritative\n\n3. recommendation (max 2 lines)\n- Insight based on user selections\n- Suggest smarter approach (MVP, phased build, cost control, etc.)\n\nTone rules:\n- Confident but not pushy\n- Professional and human\n- No buzzwords\n- No fluff\n- No emojis\n- No exclamation marks\n- No marketing hype\n- Sound like an experienced consultant, not a salesperson\n\nImportant:\n- Be consistent across steps\n- Do not contradict previous guidance\n- Do not mention pricing unless necessary\n- Do not over-explain\n\nDecision rules:\n- If feature_count > 6: suggest MVP or phased approach.\n- If has_ai is true: mention planning, testing, and integration complexity.\n- If is_urgent is true: mention increased cost or parallel development.\n- If is_low_budget and high_complexity: suggest starting with core features only.\n- If multiple_roles: mention increased backend and logic complexity.\n\nThese rules must influence recommendation output.\nDo not output the rules themselves.\n\nContext:\n\nCalculator Type: " . $calculator_type . "\nCurrent Step: " . $step . "\nUser Selections (filtered): " . wp_json_encode($answers) . "\nContext Flags: " . wp_json_encode($context_flags) . "\n\nOutput strictly in JSON format:\n\n{\n  \"helper_text\": \"\",\n  \"explanation\": \"\",\n  \"recommendation\": \"\"\n}";
	}

	private function log_debug($message) {
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('[Pixact Calculator] ' . sanitize_text_field($message));
		}
	}

	private function is_ai_killed() {
		return (bool) get_transient('pixact_ai_kill_switch');
	}

	private function register_ai_failure() {
		$state = get_transient('pixact_ai_fail_state');
		if (!is_array($state)) {
			$state = array('count' => 0);
		}
		$state['count'] = (int) $state['count'] + 1;
		set_transient('pixact_ai_fail_state', $state, self::AI_FAILURE_WINDOW_SECONDS);

		if ($state['count'] >= self::AI_FAILURE_THRESHOLD) {
			set_transient('pixact_ai_kill_switch', 1, self::AI_KILL_SWITCH_SECONDS);
			$this->log_debug('AI kill switch activated after repeated failures.');
		}
	}

	private function clear_ai_failure_state() {
		delete_transient('pixact_ai_fail_state');
		delete_transient('pixact_ai_kill_switch');
	}

	private function parse_microcopy_content($content) {
		$content = trim((string) $content);
		$content = preg_replace('/^```(?:json)?\s*/', '', $content);
		$content = preg_replace('/\s*```$/', '', (string) $content);
		$decoded = json_decode($content, true);

		if (!is_array($decoded)) {
			return array();
		}

		return array(
			'helper_text' => $this->clamp_text(sanitize_text_field($decoded['helper_text'] ?? ''), 180),
			'explanation' => $this->clamp_text(sanitize_text_field($decoded['explanation'] ?? ''), 420),
			'recommendation' => $this->clamp_text(sanitize_text_field($decoded['recommendation'] ?? ''), 260),
		);
	}

	private function clamp_text($text, $max_chars) {
		$text = (string) $text;
		if (function_exists('mb_strlen') && function_exists('mb_substr')) {
			return mb_strlen($text) > $max_chars ? mb_substr($text, 0, $max_chars) : $text;
		}

		return strlen($text) > $max_chars ? substr($text, 0, $max_chars) : $text;
	}

	private function get_default_microcopy_for_step($step) {
		$settings = $this->get_settings();
		$ui = $settings['ui'] ?? array();
		$step_key_map = array(
			'step1' => array('helper' => 'step1Microcopy', 'explanation' => 'step1Explanation'),
			'step2' => array('helper' => 'step2Microcopy', 'explanation' => 'step2Explanation'),
			'step3' => array('helper' => 'step3Microcopy', 'explanation' => 'step3Explanation'),
			'step4' => array('helper' => 'step4Microcopy', 'explanation' => 'step4Explanation'),
			'step5' => array('helper' => 'step5Subtitle', 'explanation' => 'step5Explanation'),
			'platform' => array('helper' => 'step1Microcopy', 'explanation' => 'step1Explanation'),
			'features' => array('helper' => 'step2Microcopy', 'explanation' => 'step2Explanation'),
			'timeline' => array('helper' => 'step3Microcopy', 'explanation' => 'step3Explanation'),
			'roles' => array('helper' => 'step4Microcopy', 'explanation' => 'step4Explanation'),
			'budget' => array('helper' => 'step5Subtitle', 'explanation' => 'step5Explanation'),
			'backend' => array('helper' => 'step2Microcopy', 'explanation' => 'step2Explanation'),
			'design' => array('helper' => 'step4Microcopy', 'explanation' => 'step4Explanation'),
		);
		$keys = $step_key_map[$step] ?? $step_key_map['step1'];

		return array(
			'helper_text' => sanitize_text_field($ui[$keys['helper']] ?? 'We tailor each estimate around your selected priorities.'),
			'explanation' => sanitize_text_field($ui[$keys['explanation']] ?? 'This section helps you understand scope and delivery trade-offs based on your selections.'),
			'recommendation' => sanitize_text_field($ui['resultRecommendation'] ?? 'Start with a focused MVP to validate the core product quickly.'),
		);
	}

	public function set_lead_columns($columns) {
		$custom_columns = array(
			'cb'            => $columns['cb'] ?? '',
			'title'         => 'Lead',
			'lead_name'     => 'Name',
			'lead_email'    => 'Email',
			'lead_phone'    => 'Phone',
			'project_type'  => 'Project',
			'estimate_range'=> 'Estimate',
			'timeline'      => 'Timeline',
			'date'          => $columns['date'] ?? 'Date',
		);

		return $custom_columns;
	}

	public function render_lead_columns($column, $post_id) {
		$name = get_post_meta($post_id, 'name', true);
		$email = get_post_meta($post_id, 'email', true);
		$phone = get_post_meta($post_id, 'phone', true);
		$project_type = get_post_meta($post_id, 'project_type', true);
		$estimate_range = get_post_meta($post_id, 'estimate_range', true);
		$timeline = get_post_meta($post_id, 'timeline', true);

		switch ($column) {
			case 'lead_name':
				echo esc_html($name ? $name : '—');
				break;
			case 'lead_email':
				if (!empty($email)) {
					echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
				} else {
					echo '—';
				}
				break;
			case 'lead_phone':
				echo esc_html($phone ? $phone : '—');
				break;
			case 'project_type':
				echo esc_html($project_type ? ucfirst($project_type) : '—');
				break;
			case 'estimate_range':
				echo esc_html($estimate_range ? $estimate_range : '—');
				break;
			case 'timeline':
				echo esc_html($timeline ? ucfirst($timeline) : '—');
				break;
		}
	}

	public function register_lead_meta_box() {
		add_meta_box(
			'pixact_lead_details',
			'Lead Details',
			array($this, 'render_lead_meta_box'),
			self::CPT,
			'normal',
			'high'
		);
	}

	public function render_lead_meta_box($post) {
		$name = get_post_meta($post->ID, 'name', true);
		$email = get_post_meta($post->ID, 'email', true);
		$phone = get_post_meta($post->ID, 'phone', true);
		$project_type = get_post_meta($post->ID, 'project_type', true);
		$estimate_range = get_post_meta($post->ID, 'estimate_range', true);
		$timeline = get_post_meta($post->ID, 'timeline', true);
		$answers_json = get_post_meta($post->ID, 'answers_json', true);
		$decoded_answers = json_decode($answers_json, true);
		$pretty_json = wp_json_encode($decoded_answers ? $decoded_answers : array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		?>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">Name</th>
					<td><?php echo esc_html($name ? $name : '—'); ?></td>
				</tr>
				<tr>
					<th scope="row">Email</th>
					<td>
						<?php if (!empty($email)) : ?>
							<a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">Phone</th>
					<td><?php echo esc_html($phone ? $phone : '—'); ?></td>
				</tr>
				<tr>
					<th scope="row">Project Type</th>
					<td><?php echo esc_html($project_type ? ucfirst($project_type) : '—'); ?></td>
				</tr>
				<tr>
					<th scope="row">Estimate Range</th>
					<td><?php echo esc_html($estimate_range ? $estimate_range : '—'); ?></td>
				</tr>
				<tr>
					<th scope="row">Timeline</th>
					<td><?php echo esc_html($timeline ? ucfirst($timeline) : '—'); ?></td>
				</tr>
			</tbody>
		</table>
		<h3>Full Answers JSON</h3>
		<textarea readonly style="width:100%;min-height:320px;font-family:monospace;"><?php echo esc_textarea($pretty_json ? $pretty_json : '{}'); ?></textarea>
		<?php
	}
}

new Pixact_Cost_Calculator_Plugin();
