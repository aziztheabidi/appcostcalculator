<?php
if (!defined('ABSPATH')) {
	exit;
}

class Pixact_Cost_Calculator_Post_Type {
	public const POST_TYPE = 'calculator_lead';

	public function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'label' => 'Calculator Leads',
				'labels' => array(
					'name' => 'Calculator Leads',
					'singular_name' => 'Calculator Lead',
				),
				'public' => false,
				'show_ui' => true,
				'show_in_menu' => true,
				'menu_position' => 26,
				'menu_icon' => 'dashicons-email-alt',
				'supports' => array('title'),
				'capability_type' => 'post',
				'has_archive' => false,
			)
		);
	}
}
