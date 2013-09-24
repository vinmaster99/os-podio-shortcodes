<?php 
/**
PODIO SHORTCODES
*/

$os_error = array();
$os_error['missing_attributes'] = os_message('MISSING SHORTCODE ATTRIBUTES!');
$os_error['missing_app'] = os_message('MISSING APP - ex. [os_podioform app="leads"] or [os_podioform app="12345678"] ');

// Shortcode that creates a "whitepaper" form and sends it to the leads app
if(!function_exists('onescreen_podioform')) {
function onescreen_podioform($atts, $content = null){
	wp_enqueue_style('os-podio-shortcodes');
	global $os_error;

	// return error if there no attributes
	if (empty($atts)) return $os_error['missing_attributes'] . $content;

	extract($atts);
	extract(shortcode_atts( 
		array('form_app' => OS_PODIO_LEADS_APP,
			'discovery_source_app' => OS_PODIO_SOURCE_APP,
			'discovery_source_item' => 'none', //none means this is a contact form instead of whitepapers
			'download_link' => 'none',
			'submit_text' => 'Submit'), $atts));

	$form_html = '';

	// Display a success message if form submission is a success
	if ($_GET['success'] == 'true')
		$form_html .= '<div id="success" style="display:none;" >true</div>';

	// check shortcode attributes for required values (app name / app id, etc..)
	if (!isset($form_app) || !isset($discovery_source_app) || !isset($discovery_source_item) || $discovery_source_item == '') 
		return $os_error['missing_app'] . $content;

	// apps check here 
	if ( $form_app ){
		try{
			$credentials = array();
			$credentials['username'] = USERNAME;
			$credentials['password'] = PASSWORD;
			Podio::authenticate('password', $credentials);

			// get leads app podio webform and generate custom html/css styles
			$podioforms = PodioForm::get_for_app($form_app);
			$fields = $podioforms[0]->fields;

			// form must not be empty (must have at least 1 field)
			if (count($fields) > 0){
				// start form tags
				$form_html .= '<div class="span12"><form class="os_podioform" method="POST" action="'.plugins_url('callback.php', __FILE__).'" ><fieldset>';

				foreach ($fields as $field){
					$field_id = $field['field_id'];
					// checking settings field for 'contact_field_types' array (to generate textbox for app field type 'contact')
					if (!empty($field['settings']['contact_field_types'])) $contact_field_types = $field['settings']['contact_field_types'];

					// get list of podio app object
					$appfield_object = PodioAppField::get($form_app, $field_id);

					$att = $appfield_object->__attributes;	// grab list of form fields
					$external_id = $att['external_id'];
					$type = $att['type'];
					$label = $att['config']['label'];
					if (!empty($att['config']['settings']['required'])) $required = $att['config']['settings']['required']; else $required = '';

					// generate form fields based on appfield type
					switch ($type){
						case 'text' : 
						$size = $att['config']['settings']['size'];
						// Large size text is a textarea
						if ( stripos('large', $size) !== false ){
							$desc_text = '<label style="text-transform:capitalize;">' . 'How Can We Help?' . '</label>';
							$desc_text .= '<textarea name="'.$field_id.'" style="width:95%;" required></textarea>';
						}
						break;
						case 'contact' :
						if (isset($contact_field_types)){
								// print_r($contact_field_types);
							foreach ($contact_field_types as $contact_label){
								// Add e to show email instead of mail
								if ( stripos('mail', $contact_label) !== false )
									$form_html .= '<label style="text-transform:capitalize;">e' . $contact_label . '</label>';
								// Show custom text for organization
								else if ( stripos('organization', $contact_label) !== false )
									$form_html .= '<label style="text-transform:capitalize;">' . 'Company' . '</label>';
								else
									$form_html .= '<label style="text-transform:capitalize;">' . $contact_label . '</label>';

								$form_html .= '<input type="text" name="contact['.$contact_label.']" style="width:95%;" required/>';
							}
						}
						break;
						case 'app' :
						if ($label == 'Discovery Source') {
							$form_html .= '<input type="hidden" name="'.$label.'" value="'.$field_id.'" />';
						}
						break;
					}
				}

				// Only show textarea for contact forms
				if ($discovery_source_item == 'none')
					$form_html .= $desc_text;

				// Setup for callback.php
				$form_html .= '<input type="hidden" name="os_podio_app_id" value="'.$form_app.'" />';
				$form_html .= '<input type="hidden" name="discovery_source_app" value="'.$discovery_source_app.'" />';
				$form_html .= '<input type="hidden" name="discovery_source_item" value="'.$discovery_source_item.'" />';
				$form_html .= '<input type="hidden" name="download_link" value="'.$download_link.'" />';

				$form_html .= '<input class="os-btn btn-large os-green" type="submit" value="'.$submit_text.'" />';
				// close form tags
				$form_html .= '</fieldset></form></div>';
				return $form_html;
			}
		}
		catch(PodioError $e){
			printr($e);
		}
	}
}
add_shortcode('os_podioform', 'onescreen_podioform');
}
?>