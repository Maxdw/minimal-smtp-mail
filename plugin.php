<?php
/**
 * Plugin Name: Minimal SMTP mail
 * Description: The minimum code required to allow you to send emails over SMTP. When enabled and configured the plugin will configure WordPress's built-in method of emailing to send emails over SMTP rather than using PHP's mail() function. Can be configured under 'Settings'.
 * Version: 1.0.0
 * Requires at least: 4.2
 * Author: Dutchwise
 * Author URI: http://www.dutchwise.nl/
 * Text Domain: minsmtp
 * Domain Path: /locale/
 * Network: true
 * License: MIT license (http://www.opensource.org/licenses/mit-license.php)
 */

include 'html.php';

class MinimalSmtpMail {
	
	/**
	 * Stores the configured PHPMailer object.
	 *
	 * @var PHPMailer
	 */
	protected $_phpmailer;
	
	/**
	 * Sanitizes email settings before saving.
	 *
	 * [smtp] => 1
     * [host] => smtp.example.com
     * [auth] => 1
     * [port] => 25
     * [username] => d
     * [password] => w
     * [security] => ssl
     * [from] => max@dutchwise.nl
     * [from_name] => Max
	 *
	 * @param array $input
	 * @return array
	 */
	public function sanitizeEmailSettings(array $input) {
		foreach($input as $key => &$value) {
			$value = stripslashes(strip_tags($value));
		}
		
		$fields = array('smtp', 'auth', 'port');
		
		foreach($fields as $field) {
			switch($field) {
				case 'smtp':
				case 'auth':
					$input[$field] = (int)!!$input[$field];	
					break;	
				case 'port':
					$input[$field] = (int)$input[$field];
					break;
			}
		}
		
		return apply_filters('minsmtp_sanitize_email_options', $input);
	}
	
	/**
	 * Renders the email admin settings page.
	 *
	 * @return void
	 */
	public function renderAdminSettingsPage() {
		$html = new HtmlHelper(false);
		
		echo $html->open('div', array('class' => 'wrap'));
		
		// start form
		echo $html->open('form', array(
			'action' => 'options.php',
			'method' => 'POST',
			'accept-charset' => get_bloginfo('charset'),
			'novalidate'
		));
		
		// form title
		echo $html->element('h2', __('Email', 'minsmtp'));
		
		echo $html->single('br');
		
		// render the test email button
		if(isset($_GET['send_test'])) {
			echo $html->element('a', array(
				'class' => 'button',
				'href' => '?page=email'),
			__('Disable sending test email to sender upon saving', 'minsmtp'));
		}
		else {
			echo $html->element('a', array(
				'class' => 'button',
				'href' => '?page=email&send_test'),
			__('Toggle and send immediate test email to sender', 'minsmtp'));
		}
		
		// prepare form for settings (nonce, referer fields)
		settings_fields('email');
		
		// renders all settings sections of the specified page
		do_settings_sections('email');
		
		// renders the submit button
		submit_button();
		
		echo $html->close();
	}
	
	/**
	 * Renders the email admin settings section.
	 *
	 * @param array $args 'id', 'title', 'callback'
	 * @return void
	 */
	public function renderAdminEmailSettingsSection($args) {
		// do nothing
	}
	
	/**
	 * Renders the email admin settings fields.
	 *
	 * @param array $args Unknown
	 * @return void
	 */
	public function renderAdminEmailSettingField($args) {
		$options = get_option('email_settings');
		$html = new HtmlHelper();
		$atts = array();
		
		// if option does not exist, add to database
		if($options == '') {
			add_option('email_settings', array());
		}
		
		// make sure the required label_for and field arguments are present to render correctly
		if(!isset($args['label_for'], $args['field'])) {
			throw new InvalidArgumentException('add_settings_field incorrectly configured');
		}
		
		// define attributes each field should have
		$atts['id'] = $args['label_for'];
		$atts['name'] = "email_settings[{$args['field']}]";
		
		// render html based on which field needs to be rendered
		switch($args['field']) {
			case 'smtp':
			case 'auth';
				$atts['type'] = 'checkbox';
				$atts['value'] = '1';
				
				$html->single('input', array(
					'id' => $atts['id'] . '_hidden',
					'type' => 'hidden',
					'value' => 0
				) + $atts);
				
				if(isset($options[$args['field']]) && $options[$args['field']]) {
					$atts['checked'] = 'checked';
				}
				
				$html->single('input', $atts);				
				break;
			case 'host':
				$atts['type'] = 'text';
				$atts['placeholder'] = 'smtp.example.com';
				
				if(isset($options[$args['field']])) {
					$atts['value'] = $options[$args['field']];
				}
				
				$html->single('input', $atts);				
				break;
			case 'port':
				$atts['type'] = 'number';
				$atts['placeholder'] = __('25, 465 or 587', 'minsmtp');
				
				if(isset($options[$args['field']])) {
					$atts['value'] = $options[$args['field']];
				}
				
				$html->single('input', $atts);	
				break;
			case 'username':
			case 'from_name':
				$atts['type'] = 'text';
				
				if(isset($options[$args['field']])) {
					$atts['value'] = $options[$args['field']];
				}
				
				$html->single('input', $atts);	
				break;
			case 'password':
				$atts['type'] = 'password';
				
				if(isset($options[$args['field']])) {
					$atts['value'] = $options[$args['field']];
				}
				
				$html->single('input', $atts);	
				break;
			case 'security':				
				$html->open('select', $atts);
				
				$selection = array('' => 'Disabled', 'ssl' => 'SSL', 'tls' => 'TLS');
				
				foreach($selection as $value => $label) {
					$is_selected = array();
					
					if(isset($options[$args['field']]) && $options[$args['field']] == $value) {
						$is_selected = array('selected');
					}
					
					$html->element('option', array('value' => $value) + $is_selected, $label);
				}
				
				break;
			case 'from':
				$atts['type'] = 'email';
				
				if(isset($options[$args['field']])) {
					$atts['value'] = $options[$args['field']];
				}
				
				$html->single('input', $atts);	
				break;
		}
		
		$html->close();
		
		echo $html;
	}
	
	/**
	 * Configures the PHPMailer object that WordPress
	 * to send emails but without SMTP by default.
	 *
	 * @param PHPMailer $phpmailer
	 * @return void
	 */
	public function configurePhpMailer(PHPMailer $phpmailer) {
		// load the settings array
		$options = get_option('email_settings');
		
		if($options['smtp']) {
			// Define that we are sending with SMTP
			$phpmailer->isSMTP();
			
			// The hostname of the mail server
			$phpmailer->Host = $options['host'];
			
			// SMTP port number - likely to be 25, 465 or 587
			$phpmailer->Port = (int)$options['port'];
			$smtp = $phpmailer->getSMTPInstance();
			$smtp->SMTP_PORT = (int)$options['port'];
			
			// Encryption system to use - ssl or tls
			$phpmailer->SMTPSecure = $options['security'];
			
			// Use SMTP authentication (true|false)
			$phpmailer->SMTPAuth = (boolean)$options['auth'];	
			
			if((boolean)$options['auth']) {
				// Username to use for SMTP authentication
				$phpmailer->Username = $options['username'];
			
				// Password to use for SMTP authentication
				$phpmailer->Password = $options['password'];
			}
		}
		
		// Sender email address
		$phpmailer->From = $options['from'];
		
		if($options['from_name']) {
			// Sender name
			$phpmailer->FromName = $options['from_name'];
		}
		
		$this->_phpmailer = $phpmailer;
	}
	
	/**
	 * Runs when the WordPress admin area is initialised.
	 *
	 * @return void
	 */
	public function onAdminInit() {
		register_setting('email', 'email_settings', array($this, 'sanitizeEmailSettings'));
		
		add_settings_section(
			'email_section',				// ID used to identify this section and with which to register options
			__( 'SMTP', 'minsmtp' ),		// Title to be displayed on the administration page
			array($this, 'renderAdminEmailSettingsSection'),
			'email'						// Page on which to add this section of options
		);
		
		// field names and labels
		$fields = array(
			'smtp' => __('Enable SMTP', 'minsmtp'),
			'host' => __('SMTP Hostname', 'minsmtp'),
			'port' => __('SMTP Port number', 'minsmtp'),
			'auth' => __('Use SMTP Authentication', 'minsmtp'),
			'username' => __('SMTP Auth Username', 'minsmtp'),
			'password' => __('SMTP Auth Password', 'minsmtp'),
			'security' => __('Encryption (SSL or TLS)', 'minsmtp'),
			'from' => __('Sender email address', 'minsmtp'),
			'from_name' => __('Sender name', 'minsmtp')
		);
		
		// register and render the fields using add_settings_field and the $fields array
		foreach($fields as $field => $label) {
			add_settings_field(
				"email_settings[{$field}]",	// ID used to identify the field throughout the theme
				$label,						// The label to the left of the option interface element
				array($this, 'renderAdminEmailSettingField'),
				'email',						// The page on which this option will be displayed
				'email_section',				// The name of the section to which this field belongs
				array(						// The array of arguments to pass to the callback.
					'field' => $field,
					'label_for' => $field
				)
			);
		}
		
		// send test email
		if(isset($_GET['send_test'])) {
			$this->_sendTestEmail();
		}
		
		// display a notice of open ssl is not enabled
		$this->_checkIfOpenSslIsLoaded();
	}
	
	/**
	 * Checks if OPEN_SSL is enabled and displays
	 * a notice if it isn't.
	 *
	 * @return boolean
	 */
	protected function _checkIfOpenSslIsLoaded() {
		if(!$result = extension_loaded('openssl')) {
			add_action('admin_notices', function() {
				$class = "error";
				$message = __('Warning: OPEN_SSL is not loaded', 'minsmtp');
				echo "<div class=\"$class\"><p>$message</p></div>"; 
			});
		}
		
		return $result;
	}
	
	/**
	 * Includes the PHPMailer class.
	 *
	 * @return void
	 */
	protected function _requirePHPMailer() {
		global $phpmailer;
		
		if(!($phpmailer instanceof PHPMailer)) {
			require_once ABSPATH . WPINC . '/class-phpmailer.php';
			require_once ABSPATH . WPINC . '/class-smtp.php';
			$phpmailer = new PHPMailer( true );
		}
	}
	
	/**
	 * Sends a test email to the sender 'from' address.
	 * Displays notices depending on success/error.
	 * 
	 * @return boolean
	 */
	protected function _sendTestEmail() {
		// default result
		$is_sent = false;
		
		// load the settings array
		$options = get_option('email_settings');
		
		// include PHPMailer for validation
		if(!class_exists('PHPMailer')) {
			$this->_requirePHPMailer();
		}
		
		// verify 'from' email address
		if(isset($options['from']) && PHPMailer::validateAddress($options['from'])) {
			// send test message
			$is_sent = wp_mail($options['from'], 'test', 'test message', array(
				'Content-Type: text/html; charset=UTF-8'
			));
			
			if(!$is_sent) {
				if(!isset($this->_phpmailer)) {
					throw new RuntimeException('wp_mail did not instantiate PHPMailer.');
				}
				
				$phpmailer = $this->_phpmailer;
				
				// displays error notice concerning PHPMailer
				add_action('admin_notices', function() use($phpmailer) {
					$class = "error";
					$message = $phpmailer->ErrorInfo;
					echo "<div class=\"$class\"><p>$message</p></div>"; 
				});
			}
			// displays success message
			else add_action('admin_notices', function() {
				$class = "notice updated success";
				$message = __('Message successfully sent', 'minsmtp');
				echo "<div class=\"$class\"><p>$message</p></div>"; 
			});
		}
		// displays warning message
		else add_action('admin_notices', function() {
			$class = "error";
			$message = __("Unable to send test email: The 'From' emailaddress is not correctly set.", 'minsmtp');
			echo "<div class=\"$class\"><p>$message</p></div>"; 
		});
		
		return $is_sent;
	}
	
	/**
	 * Runs when the WordPress admin menus are initialised.
	 *
	 * @return void
	 */
	public function onAdminMenu() {
		// adds the email menu item to WordPress's main Settings menu
		add_options_page(__('Email Settings', 'minsmtp'), __('Email', 'minsmtp'), 'manage_options', 'email', array($this, 'renderAdminSettingsPage'));
	}
	
	/**
	 * Class constructor
	 */
	public function __construct() {
		add_action('phpmailer_init', array($this, 'configurePhpMailer'));
		add_action('admin_menu', array($this, 'onAdminMenu'));
		add_action('admin_init', array($this, 'onAdminInit'));
	}
	
}

new MinimalSmtpMail;