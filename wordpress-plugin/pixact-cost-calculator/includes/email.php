<?php
if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('pixact_send_admin_notification')) {
	function pixact_send_admin_notification(array $data): void {
		$configured = (string) get_option('pixact_admin_notification_email', '');
		$fallback = (string) get_option('admin_email');
		$raw_recipients = $configured !== '' ? $configured : $fallback;
		$parts = array_filter(array_map('trim', explode(',', $raw_recipients)));
		$recipients = array();
		foreach ($parts as $part) {
			$email = sanitize_email($part);
			if ($email !== '' && is_email($email)) {
				$recipients[] = $email;
			}
		}
		$recipients = array_values(array_unique($recipients));
		if (empty($recipients)) {
			error_log('Pixact Calculator: admin notification skipped - no valid recipient.'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return;
		}

		$estimate_min = isset($data['estimate_min']) ? (int) $data['estimate_min'] : 0;
		$estimate_max = isset($data['estimate_max']) ? (int) $data['estimate_max'] : 0;
		$lead_summary = sanitize_textarea_field((string) ($data['lead_summary'] ?? ''));
		if ($lead_summary === '' && function_exists('pixact_format_lead_summary')) {
			$lead_summary = pixact_format_lead_summary($data);
		}

		$subject = 'New Lead Received – Cost Calculator Inquiry';
		$message = implode(
			"\n",
			array(
				'New Lead Received',
				'',
				'Contact Details:',
				'Name: ' . sanitize_text_field((string) ($data['name'] ?? '')),
				'Email: ' . sanitize_email((string) ($data['email'] ?? '')),
				'Phone: ' . sanitize_text_field((string) ($data['phone'] ?? '')),
				'',
				'Project Overview:',
				'Project Type: ' . sanitize_text_field((string) ($data['project_type'] ?? '')),
				'Estimated Range: $' . number_format($estimate_min) . ' - $' . number_format($estimate_max),
				'Timeline: ' . sanitize_text_field((string) ($data['timeline'] ?? '')),
				'Complexity: ' . sanitize_text_field((string) ($data['complexity'] ?? '')),
				'',
				'Project Details:',
				'',
				$lead_summary,
			)
		);

		$sent = wp_mail($recipients, $subject, $message, array('Content-Type: text/plain; charset=UTF-8'));
		if (!$sent) {
			error_log('Pixact Calculator: failed to send admin lead notification email.'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}

class Pixact_Cost_Calculator_Email {
	/** @var callable|null */
	private $settings_provider;

	public function __construct(?callable $settings_provider = null) {
		$this->settings_provider = $settings_provider;
	}

	private function get_email_settings(): array {
		if (!is_callable($this->settings_provider)) {
			return array();
		}
		$settings = call_user_func($this->settings_provider);
		return is_array($settings) ? $settings : array();
	}

	private function apply_template_vars(string $template, array $vars): string {
		$replacements = array();
		foreach ($vars as $key => $value) {
			$replacements['{{' . $key . '}}'] = (string) $value;
		}
		return strtr($template, $replacements);
	}

	private function normalize_headers(array $settings): array {
		$headers = array('Content-Type: text/plain; charset=UTF-8');
		$from_name = sanitize_text_field((string) ($settings['from_name'] ?? ''));
		$from_email = sanitize_email((string) ($settings['from_email'] ?? ''));
		if ($from_email !== '' && is_email($from_email)) {
			if ($from_name !== '') {
				$headers[] = sprintf('From: %s <%s>', $from_name, $from_email);
			} else {
				$headers[] = sprintf('From: %s', $from_email);
			}
		}
		return $headers;
	}

	public function send_new_lead_notification(array $lead): void {
		$settings = $this->get_email_settings();

		$estimate_min = isset($lead['estimate_min']) ? (int) $lead['estimate_min'] : 0;
		$estimate_max = isset($lead['estimate_max']) ? (int) $lead['estimate_max'] : 0;
		$range = '$' . number_format($estimate_min) . ' - $' . number_format($estimate_max);
		$answers_json = wp_json_encode($lead['answers'] ?? array(), JSON_PRETTY_PRINT);
		$lead_summary = sanitize_textarea_field((string) ($lead['lead_summary'] ?? ''));
		if ($lead_summary === '' && function_exists('pixact_format_lead_summary')) {
			$lead_summary = pixact_format_lead_summary(
				array(
					'project_type' => sanitize_text_field((string) ($lead['project_type'] ?? '')),
					'estimate_min' => $estimate_min,
					'estimate_max' => $estimate_max,
					'timeline' => sanitize_text_field((string) ($lead['timeline'] ?? '')),
					'complexity' => sanitize_text_field((string) ($lead['complexity'] ?? '')),
					'answers' => is_array($lead['answers'] ?? null) ? $lead['answers'] : array(),
				)
			);
		}

		$vars = array(
			'name' => sanitize_text_field((string) ($lead['name'] ?? '')),
			'email' => sanitize_email((string) ($lead['email'] ?? '')),
			'phone' => sanitize_text_field((string) ($lead['phone'] ?? '')),
			'estimate_min' => '$' . number_format($estimate_min),
			'estimate_max' => '$' . number_format($estimate_max),
			'timeline' => sanitize_text_field((string) ($lead['timeline'] ?? '')),
			'complexity' => sanitize_text_field((string) ($lead['complexity'] ?? '')),
			'lead_summary' => $lead_summary,
			'answers' => $answers_json ? $answers_json : '{}',
			'estimate_range' => $range,
		);

		$headers = $this->normalize_headers($settings);

		$user_email = sanitize_email((string) ($lead['email'] ?? ''));
		$admin_email_override = sanitize_email((string) ($settings['admin_email'] ?? ''));
		$admin_email = $admin_email_override !== '' && is_email($admin_email_override)
			? $admin_email_override
			: (string) get_option('admin_email');

		$admin_enabled = !empty($settings['enable_admin_email']);
		$user_enabled = !empty($settings['enable_user_email']);

		$admin_subject_template = (string) ($settings['admin_email_subject'] ?? 'New Calculator Lead: {{name}}');
		$user_subject_template = (string) ($settings['user_email_subject'] ?? 'Your estimate from Pixact');
		$admin_template = (string) ($settings['admin_email_template'] ?? '');
		$user_template = (string) ($settings['user_email_template'] ?? '');

		if ($admin_enabled && $admin_email !== '' && is_email($admin_email)) {
			$admin_subject = $this->apply_template_vars($admin_subject_template, $vars);
			$admin_message = $this->apply_template_vars($admin_template, $vars);
			wp_mail($admin_email, $admin_subject, $admin_message, $headers);
		}

		if ($user_enabled && $user_email !== '' && is_email($user_email)) {
			$user_subject = $this->apply_template_vars($user_subject_template, $vars);
			$user_message = $this->apply_template_vars($user_template, $vars);
			wp_mail($user_email, $user_subject, $user_message, $headers);
		}
	}
}
