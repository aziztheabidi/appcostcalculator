<?php
if (!defined('ABSPATH')) {
	exit;
}

class Pixact_Cost_Calculator_REST_API {
	private Pixact_Cost_Calculator_Email $email_service;
	private $pricing_rules_provider;
	private $config_provider;
	private $ai_settings_provider;
	private $integrations_settings_provider;

	public function __construct(
		Pixact_Cost_Calculator_Email $email_service,
		callable $pricing_rules_provider,
		callable $config_provider,
		callable $ai_settings_provider,
		callable $integrations_settings_provider
	) {
		$this->email_service = $email_service;
		$this->pricing_rules_provider = $pricing_rules_provider;
		$this->config_provider = $config_provider;
		$this->ai_settings_provider = $ai_settings_provider;
		$this->integrations_settings_provider = $integrations_settings_provider;
	}

	public function register_routes(): void {
		register_rest_route(
			'pixact/v1',
			'/lead',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array($this, 'handle_lead_submission'),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'pixact/v1',
			'/pricing',
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array($this, 'get_pricing_rules'),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'pixact/v1',
			'/config',
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array($this, 'get_calculator_config'),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'pixact/v1',
			'/microcopy',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array($this, 'generate_microcopy'),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function get_pricing_rules(): WP_REST_Response {
		$provider = $this->pricing_rules_provider;
		$pricing_rules = is_callable($provider) ? call_user_func($provider) : array();
		return new WP_REST_Response($pricing_rules, 200);
	}

	public function get_calculator_config(): WP_REST_Response {
		$provider = $this->config_provider;
		$config = is_callable($provider) ? call_user_func($provider) : array();
		return new WP_REST_Response($config, 200);
	}

	public function generate_microcopy(WP_REST_Request $request): WP_REST_Response {
		$nonce = sanitize_text_field((string) $request->get_header('X-WP-Nonce'));
		if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
			return new WP_REST_Response(array('message' => 'Invalid nonce.'), 403);
		}

		$params = $request->get_json_params();
		$params = is_array($params) ? $params : array();
		$step = sanitize_key((string) ($params['step'] ?? 'platform'));
		$calculator_type = sanitize_text_field((string) ($params['calculator_type'] ?? 'app-web-cost-calculator'));
		$answers = $this->sanitize_answers_recursive($params['answers'] ?? array());
		$fallback = $this->build_fallback_microcopy($step);

		$ai_provider = $this->ai_settings_provider;
		$ai_settings = is_callable($ai_provider) ? call_user_func($ai_provider) : array();
		$enabled = !empty($ai_settings['enabled']);
		$api_key = sanitize_text_field((string) ($ai_settings['api_key'] ?? ''));
		$model = sanitize_text_field((string) ($ai_settings['model'] ?? 'gpt-4o-mini'));
		$temperature = isset($ai_settings['temperature']) ? (float) $ai_settings['temperature'] : 0.4;
		$max_tokens = isset($ai_settings['max_tokens']) ? (int) $ai_settings['max_tokens'] : 300;
		if ($temperature < 0) $temperature = 0;
		if ($temperature > 1) $temperature = 1;
		if ($max_tokens < 64) $max_tokens = 64;
		if ($max_tokens > 2000) $max_tokens = 2000;

		if (!$enabled || $api_key === '') {
			return new WP_REST_Response($fallback, 200);
		}

		$default_system = 'You are a senior product consultant. Return only strict JSON with keys: helper_text, explanation, recommendation. Keep tone concise, practical, and non-salesy.';
		$custom_system = isset($ai_settings['system_prompt']) ? sanitize_textarea_field((string) $ai_settings['system_prompt']) : '';
		$custom_system = trim($custom_system);
		$system_prompt = $custom_system !== '' ? $custom_system : $default_system;
		$user_prompt = sprintf(
			'Step: %s. Calculator: %s. Answers: %s',
			$step,
			$calculator_type,
			wp_json_encode($answers)
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body' => wp_json_encode(
					array(
						'model' => $model,
						'temperature' => $temperature,
						'max_tokens' => $max_tokens,
						'messages' => array(
							array('role' => 'system', 'content' => $system_prompt),
							array('role' => 'user', 'content' => $user_prompt),
						),
						'response_format' => array('type' => 'json_object'),
					)
				),
			)
		);

		if (is_wp_error($response)) {
			return new WP_REST_Response($fallback, 200);
		}
		$body = wp_remote_retrieve_body($response);
		$status = (int) wp_remote_retrieve_response_code($response);
		if ($status < 200 || $status >= 300 || !$body) {
			return new WP_REST_Response($fallback, 200);
		}

		$decoded = json_decode($body, true);
		$content = $decoded['choices'][0]['message']['content'] ?? '';
		$content_decoded = json_decode((string) $content, true);
		if (!is_array($content_decoded)) {
			return new WP_REST_Response($fallback, 200);
		}

		$result = array(
			'helper_text' => sanitize_text_field((string) ($content_decoded['helper_text'] ?? $fallback['helper_text'])),
			'explanation' => sanitize_text_field((string) ($content_decoded['explanation'] ?? $fallback['explanation'])),
			'recommendation' => sanitize_text_field((string) ($content_decoded['recommendation'] ?? $fallback['recommendation'])),
		);

		return new WP_REST_Response($result, 200);
	}

	public function handle_lead_submission(WP_REST_Request $request): WP_REST_Response {
		$nonce = sanitize_text_field((string) $request->get_header('X-WP-Nonce'));
		if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
			return new WP_REST_Response(array('message' => 'Invalid nonce.'), 403);
		}

		$params = $request->get_json_params();
		$params = is_array($params) ? $params : array();

		$honeypot = sanitize_text_field((string) ($params['answers']['lead']['honeypot'] ?? ''));
		if ($honeypot !== '') {
			return new WP_REST_Response(array('message' => 'Spam detected.'), 422);
		}

		$started_at = isset($params['started_at']) ? (int) $params['started_at'] : 0;
		$age_seconds = $started_at > 0 ? (time() - (int) floor($started_at / 1000)) : 0;
		if ($age_seconds > 0 && $age_seconds < 3) {
			return new WP_REST_Response(array('message' => 'Submission too fast.'), 422);
		}

		$name = sanitize_text_field((string) ($params['name'] ?? ''));
		$email = sanitize_email((string) ($params['email'] ?? ''));
		$phone = sanitize_text_field((string) ($params['phone'] ?? ''));
		$timeline = sanitize_text_field((string) ($params['timeline'] ?? ''));
		$complexity = sanitize_text_field((string) ($params['complexity'] ?? ''));
		$estimate_min = isset($params['estimate_min']) ? (int) $params['estimate_min'] : 0;
		$estimate_max = isset($params['estimate_max']) ? (int) $params['estimate_max'] : 0;
		$answers = $this->sanitize_answers_recursive($params['answers'] ?? array());
		$project_type = sanitize_key((string) ($params['project_type'] ?? ($answers['projecttype'] ?? $answers['project_type'] ?? '')));

		if ($name === '' || $email === '' || !is_email($email)) {
			return new WP_REST_Response(array('message' => 'Name and valid email are required.'), 422);
		}

		$post_id = wp_insert_post(
			array(
				'post_type' => Pixact_Cost_Calculator_Post_Type::POST_TYPE,
				'post_status' => 'publish',
				'post_title' => $name . ' - ' . current_time('mysql'),
			),
			true
		);

		if (is_wp_error($post_id)) {
			return new WP_REST_Response(array('message' => 'Unable to save lead.'), 500);
		}

		update_post_meta($post_id, 'name', $name);
		update_post_meta($post_id, 'email', $email);
		update_post_meta($post_id, 'phone', $phone);
		update_post_meta($post_id, 'project_type', $project_type);
		update_post_meta($post_id, 'timeline', $timeline);
		update_post_meta($post_id, 'complexity', $complexity);
		update_post_meta($post_id, 'estimate_min', $estimate_min);
		update_post_meta($post_id, 'estimate_max', $estimate_max);
		update_post_meta($post_id, 'answers', wp_json_encode($answers));
		$lead_summary = function_exists('pixact_format_lead_summary')
			? pixact_format_lead_summary(
				array(
					'project_type' => $project_type,
					'estimate_min' => $estimate_min,
					'estimate_max' => $estimate_max,
					'timeline' => $timeline,
					'complexity' => $complexity,
					'answers' => $answers,
				)
			)
			: '';
		update_post_meta($post_id, 'lead_summary', $lead_summary);
		update_post_meta(
			$post_id,
			'raw_data',
			wp_json_encode(
				array(
					'name' => $name,
					'email' => $email,
					'phone' => $phone,
					'project_type' => $project_type,
					'timeline' => $timeline,
					'complexity' => $complexity,
					'estimate_min' => $estimate_min,
					'estimate_max' => $estimate_max,
					'answers' => $answers,
				)
			)
		);

		$this->email_service->send_new_lead_notification(
			array(
				'name' => $name,
				'email' => $email,
				'phone' => $phone,
				'project_type' => $project_type,
				'timeline' => $timeline,
				'complexity' => $complexity,
				'estimate_min' => $estimate_min,
				'estimate_max' => $estimate_max,
				'lead_summary' => $lead_summary,
				'answers' => $answers,
			)
		);
		try {
			if (function_exists('pixact_send_admin_notification')) {
				pixact_send_admin_notification(
					array(
						'name' => $name,
						'email' => $email,
						'phone' => $phone,
						'project_type' => $project_type,
						'timeline' => $timeline,
						'complexity' => $complexity,
						'estimate_min' => $estimate_min,
						'estimate_max' => $estimate_max,
						'lead_summary' => $lead_summary,
						'answers' => $answers,
					)
				);
			}
		} catch (\Throwable $e) {
			error_log('Pixact Calculator: admin notification exception - ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		$this->maybe_send_lead_webhook(
			array(
				'event' => 'pixact.lead.submitted',
				'lead_id' => (int) $post_id,
				'name' => $name,
				'email' => $email,
				'phone' => $phone,
				'project_type' => $project_type,
				'timeline' => $timeline,
				'complexity' => $complexity,
				'estimate_min' => $estimate_min,
				'estimate_max' => $estimate_max,
				'lead_summary' => $lead_summary,
				'answers' => $answers,
				'submitted_at' => current_time('mysql'),
			)
		);

		return new WP_REST_Response(array('success' => true), 200);
	}

	private function sanitize_answers_recursive($value) {
		if (is_array($value)) {
			$output = array();
			foreach ($value as $key => $item) {
				$output[sanitize_key((string) $key)] = $this->sanitize_answers_recursive($item);
			}
			return $output;
		}
		if (is_bool($value) || is_int($value) || is_float($value)) {
			return $value;
		}
		return sanitize_text_field((string) $value);
	}

	private function build_fallback_microcopy($step): array {
		$fallback_map = array(
			'platform' => 'Choose the platform based on your launch channel and audience behavior.',
			'features' => 'Start with essential capabilities, then expand after initial validation.',
			'timeline' => 'Faster timelines require parallel delivery and tighter scope control.',
			'roles' => 'Select only high-impact add-ons to keep phase one efficient.',
			'budget' => 'Share your details to receive a tailored estimate and delivery plan.',
		);
		$helper = $fallback_map[$step] ?? $fallback_map['platform'];
		return array(
			'helper_text' => $helper,
			'explanation' => 'This guidance helps you balance scope, speed, and implementation complexity.',
			'recommendation' => 'Begin with a focused MVP and expand through phased releases.',
		);
	}

	private function maybe_send_lead_webhook(array $payload): void {
		$provider = $this->integrations_settings_provider;
		$settings = is_callable($provider) ? call_user_func($provider) : array();
		if (!is_array($settings) || empty($settings['webhook_enabled'])) {
			return;
		}
		$url = isset($settings['webhook_url']) ? esc_url_raw((string) $settings['webhook_url']) : '';
		if ($url === '' || !wp_http_validate_url($url)) {
			return;
		}

		wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'blocking' => false,
				'headers' => array(
					'Content-Type' => 'application/json; charset=UTF-8',
					'User-Agent' => 'PixactCostCalculator/1.0.0',
				),
				'body' => wp_json_encode($payload),
			)
		);
	}
}
