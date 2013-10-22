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
			'submit_text' => 'Submit',
			'ga_action' => ''), $atts));

	$form_html = '';

	// check shortcode attributes for required values (app name / app id, etc..)
	if (!isset($form_app) || !isset($discovery_source_app) || !isset($discovery_source_item) || $discovery_source_item == '') 
		return $os_error['missing_app'] . $content;

	// apps check here
	if ( $form_app ){
		try{
			$credentials = array();
			$credentials['username'] = PODIOUSERNAME;
			$credentials['password'] = PODIOPASSWORD;
			Podio::authenticate('password', $credentials);

			// get leads app podio webform and generate custom html/css styles
			$podioforms = PodioForm::get_for_app($form_app);
			$fields = $podioforms[0]->fields;

			// form must not be empty (must have at least 1 field)
			if (count($fields) > 0){
				// start form tags
				if (!isset($ga_action) && $ga_action != '')
					$form_html .= '<div class="span12"><form class="os_podioform" method="POST" action="javascript: checkForm()" ><fieldset>';
				else
					$form_html .= '<div class="span12"><form class="os_podioform" id="'.$ga_action.'" method="POST" action="javascript: checkForm()" ><fieldset>';

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
				if ($download_link != 'none')
					$form_html .= '<input type="hidden" name="download_link" value="'.$download_link.'" />';

				$form_html .= '<input class="os-btn btn-large os-green" type="submit" value="'.$submit_text.'" />';
				// close form tags
				$form_html .= '</fieldset></form></div>';
				return $form_html;
			}
		}
		catch(PodioError $e){
			// printr($e);
			return "Error showing the form";
		}
	}
}
add_shortcode('os_podioform', 'onescreen_podioform');

add_action('wp_enqueue_scripts', 'podio_whitepaper_javascript');
function podio_whitepaper_javascript() {
?>
<script type="text/javascript">
var checkForm = function(){
	var ajaxurl = '<?php echo admin_url("admin-ajax.php"); ?>';
	var form = $(".os_podioform :input");
	var fields_array = ["Discovery Source", "contact[mail]", "contact[name]", "contact[organization]", "contact[phone]", "contact[title]", "discovery_source_app", "discovery_source_item", "download_link", "os_podio_app_id"];
	var data = {action: 'podio_whitepaper'};

	$.each(fields_array, function(index, value){
		data[(form[index]).name] = $(form[index]).val();
	});

	// $(".os_podioform :input").each(function(){
	// 	data[this.name] = $(this).val();
	// });
	$.post(ajaxurl, data, function(response) {
		console.log(response);
		if (response == 'Success')
			$(".alert-success").fadeIn();
		else
			$(".alert-error").fadeIn();
	}).fail(function() {
		$(".alert-success").hide();
		$(".alert-error").fadeIn();
	});

	if ($('[name="download_link"]').length) {
		window.open('files/'+$('[name="download_link"]').val(),'_blank');
		if ($('.os_podioform')[0].id.length)
			_gaq.push(['_trackEvent', 'download', $('.os_podioform')[0].id, $('[name="download_link"]').val(),, false]);
		else
			_gaq.push(['_trackEvent', 'download', 'pdf', $('[name="download_link"]').val(),, false]);
	} else {
		_gaq.push(['_trackEvent', 'contact', 'submit', 'form',, false]);
	}
};
</script>
<?php
}

}

add_action('wp_ajax_podio_whitepaper', 'podio_whitepaper_callback');
if(!function_exists('podio_whitepaper_callback')) {
function podio_whitepaper_callback() {
// show/hide errors
ini_set('display_errors', '1');

if (isset($_POST)){
	// parameter array that we send to podio
	$args = array();
	$app_id;
	$contact_args = array( );

	// get post values and set up args to send to podio
	foreach ($_POST as $key => $value){
		if (!empty($value)){
			// if value is app id then store variable and skip
			if ( stripos('os_podio_app_id', $key) !== false ) {
				$app_id = $value;
				continue;
			}

			// for now assuming value is a string
			if (is_string($value)) $value = stripallslashes($value);
			elseif (is_array($value) && stripos('contact', $key) !== false){
				if (empty($contact_args)) {
					$contact_args = $value;
					$args['fields'][$key] = null;
				}
			}

			// The second organization field is not set but still want to set the data
			if ($key == 'contact' && isset($value['organization'])) {
				// Submit organization name for 2 fields
				$args['fields']['14771676'] = $value['organization'];
			}

			// Get values to set up for podio query
			if ($key == 'action' || $key == ' ') {
				// do nothing, this is for ajax param
			} else if ($key == 'download_link') {
				$download_link = $value;
			} else if ($key == 'discovery_source_app') {
				$discovery_source_app = $value;
			} else if ($key == 'discovery_source_item') {
				$discovery_source_item = $value;
			} else if ($key == 'Discovery_Source') {
				$discovery_source_field_id = $value;
			} else {
				// add value to args array
				$args['fields'][$key] = $value;
			}
		}
	}

	// This is a whitepaper form so need to fetch reference id
	if (isset($discovery_source_item) && $discovery_source_item != 'none') {
		Podio::authenticate('app', array('app_id'=>OS_PODIO_SOURCE_APP,'app_token'=>OS_PODIO_SOURCE_APP_TOKEN));

		$discovery_source = PodioItem::get_by_app_item_id($discovery_source_app,$discovery_source_item);
		$test_values = array();
		$attributes = $discovery_source->__attributes;
		$discovery_source_item_id = $attributes['item_id'];
	}

	// Set up contact fields
	if (array_key_exists('phone', $contact_args)) {
		$contact_args['phone'] = array($contact_args['phone']);
		$contact_args['title'] = array($contact_args['title']);
		$contact_args['mail'] = array($contact_args['mail']);
		$contact_args['external_id'] = 'contact';
	}

	try{
		Podio::authenticate('app', array('app_id'=>OS_PODIO_LEADS_APP,'app_token'=>OS_PODIO_LEADS_APP_TOKEN));

		$space_id = BUSINESS_DEV_WORKSPACE_ID;
		// Create the contact and get contact id
		$contact_id = PodioContact::create( $space_id, $contact_args );

		if (isset($app_id) && isset($contact_id) && isset($discovery_source_item)){
			$args['fields']['contact'] = $contact_id;
			if ($discovery_source_item != 'none') // reference for discovery source
				$args['fields'][$discovery_source_field_id] = $discovery_source_item_id;
			// Create item
			$item_id = PodioItem::create($app_id, $args);
			echo "Success";
			die();
		}
 	}
	catch(PodioError $e){
		echo "Error submitting form";
		die();
	}
	echo "Form not submitted";
	die();
}

}

}

// ----------------------------------------------------------------

// Shortcode for product demo registration form
if(!function_exists('product_demo_form')) {
function product_demo_form($atts, $content = null){
	global $os_error;

	extract(shortcode_atts( 
		array('form_app' => OS_PODIO_PRODUCT_DEMO_APP,
			'download_link' => 'none',
			'submit_text' => 'Submit',
			'ga_label' => '',
			'form_id' => '0',
			'discovery_source_app' => OS_PODIO_SOURCE_APP,
			'discovery_source_item' => 'none',
			'workshop_num' => '1'), $atts));

	$form_html = '';
	$category = '';
	$desc_text = '';

	// apps check here
	if ( $form_app ){
		try{
			$credentials = array();
			$credentials['username'] = PODIOUSERNAME;
			$credentials['password'] = PODIOPASSWORD;
			Podio::authenticate('password', $credentials);

			// get leads app podio webform and generate custom html/css styles
			$podioforms = PodioForm::get_for_app($form_app);
			$fields = $podioforms[0]->fields;

			// form must not be empty (must have at least 1 field)
			if (count($fields) > 0){
				// start form tags
				if (!isset($ga_label) || $ga_label == '')
					$form_html .= '<div class="span12"><form class="os_podioform_'.$form_id.'" method="POST" onsubmit="checkFormProduct(this); return false;" ><fieldset>';
				else
					$form_html .= '<div class="span12"><form class="os_podioform_'.$form_id.'" id="'.$ga_label.'" method="POST" onsubmit="checkFormProduct(this); return false;" ><fieldset>';

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
							$desc_text .= '<label style="text-transform:capitalize;">' . $label . '</label>';
							$desc_text .= '<textarea id="44395412" name="'.$field_id.'" style="width:95%;" required></textarea>';
						} else if ( stripos('small', $size) !== false ) {
				    		$form_html .= '<div class="textbox">
					    	<label for="'.$label.'">'.$label.'</label><br />
							<input type="text" id="'.$field_id.'" name="'.$external_id.'" required value="" /><br />
							</div>';
						}
						break;
						// case 'category' :
						// $category .= '<label>'.$label.'</label>';
						// $category .= '<ul style="list-style-type: none;">';
						// foreach ($att['config']['settings']['options'] as $key => $value) {
						// 	$category .= '<li><label><input name="'.$external_id.'" id='.$field_id.' type="checkbox" value='.$value['id'].'>'.$value['text'].'</label></li>';
						// }
						// $category .= '</ul>';
						// break;
						case 'app' :
						if ($label == 'Webinar Source:') {
							$form_html .= '<input type="hidden" name="'.$field_id.'" value="'.$form_app.'" />';
						}
						break;
					}
				}

				$form_html .= $desc_text;
				$form_html .= $category;

				// Setup for callback.php
				$form_html .= '<input type="hidden" name="discovery_source_app" value="'.$discovery_source_app.'" />';
				$form_html .= '<input type="hidden" name="discovery_source_item" value="'.$discovery_source_item.'" />';
				$form_html .= '<input type="hidden" name="workshop_num" value="'.$workshop_num.'" />';
				$form_html .= '<input class="os-btn btn-large os-green" type="submit" value="'.$submit_text.'" />';
				// close form tags
				$form_html .= '</fieldset></form></div>';
				return $form_html;
			}
		}
		catch(PodioError $e){
			// printr($e);
			return "Error showing the form";
		}
	}
}
add_shortcode('product_demo_form', 'product_demo_form');

add_action('wp_enqueue_scripts', 'product_demo_javascript');
function product_demo_javascript() {
?>
<script type="text/javascript">
var checkFormProduct = function(form){
	var ajaxurl = '<?php echo admin_url("admin-ajax.php"); ?>';
	var fields_array = ["Webinar Source:", "first-name", "last-name", "title", "company", "work-email", "work-phone", "44395412", "44578450", "discovery_source_app", "discovery_source_item", "workshop_num"];
	var data = {action: 'product_demo'};

	$.each(fields_array, function(index, value){
		if (form[value] != undefined && form[value].name != undefined)
			data[(form[value]).name] = $(form[value]).val();
	});
	data['44580179'] = 1;
	console.log(data);

	$.post(ajaxurl, data, function(response) {
		console.log('response: '+JSON.stringify(response));
		if (response == 'Success')
			$(".alert-success").fadeIn();
		else
			$(".alert-error").fadeIn();
	}).fail(function() {
		$(".alert-success").hide();
		$(".alert-error").fadeIn();
	});

	if ($('.os_podioform')[0].id.length)
		_gaq.push(['_trackEvent', 'workshops', 'register', form.id,, false]);
	else
		_gaq.push(['_trackEvent', 'workshops', 'register', 'ga_label',, false]);
};
</script>
<?php
}

}

add_action('wp_ajax_product_demo', 'product_demo_callback');
if(!function_exists('product_demo_callback')) {
function product_demo_callback() {
// show/hide errors
ini_set('display_errors', '1');

if (isset($_POST)){
	// parameter array that we send to podio
	$args = array();
	$app_id;
	$contact_args = array( );
	echo json_encode($_POST);
	// get post values and set up args to send to podio
	foreach ($_POST as $key => $value){
		if (!empty($value)){
			// if value is app id then store variable and skip
			if ( stripos('os_podio_app_id', $key) !== false ) {
				$app_id = $value;
				continue;
			}

			// for now assuming value is a string
			if (is_string($value)) $value = stripallslashes($value);
			elseif (is_array($value) && stripos('contact', $key) !== false){
				if (empty($contact_args)) {
					$contact_args = $value;
					$args['fields'][$key] = null;
				}
			}

			// The second organization field is not set but still want to set the data
			if ($key == 'contact' && isset($value['organization'])) {
				// Submit organization name for 2 fields
				$args['fields']['14771676'] = $value['organization'];
			}

			// Get values to set up for podio query
			if ($key == 'action' || $key == ' ') {
				// do nothing, this is for ajax param
			} else if ($key == 'download_link') {
				$download_link = $value;
			} else if ($key == 'discovery_source_app') {
				$discovery_source_app = $value;
			} else if ($key == 'discovery_source_item') {
				$discovery_source_item = $value;
			} else if ($key == 'workshop_num') {
				$workshop_num = $value;
			} else {
				// add value to args array
				$args['fields'][$key] = $value;
			}
		}
	}

	// This is a whitepaper form so need to fetch reference id
	if (isset($discovery_source_item) && $discovery_source_item != 'none') {
		Podio::authenticate('app', array('app_id'=>OS_PODIO_SOURCE_APP,'app_token'=>OS_PODIO_SOURCE_APP_TOKEN));

		$discovery_source = PodioItem::get_by_app_item_id($discovery_source_app,$discovery_source_item);
		$test_values = array();
		$attributes = $discovery_source->__attributes;
		$discovery_source_item_id = $attributes['item_id'];
	}

	// Podio::authenticate('app', array('app_id'=>OS_PODIO_PRODUCT_DEMO_APP,'app_token'=>OS_PODIO_PRODUCT_DEMO_APP_TOKEN));

	// $temp = PodioItem::get_by_app_item_id('5704030','1');
	// $temp2 = ($temp->__attributes['fields'][6]->__attributes['values']);
	// foreach ($temp2 as $key => $value) {
	// 	printr($value);
	// }
	// printr($workshop_num);
	try{
		Podio::authenticate('app', array('app_id'=>OS_PODIO_PRODUCT_DEMO_APP,'app_token'=>OS_PODIO_PRODUCT_DEMO_APP_TOKEN));
		$app_id = OS_PODIO_PRODUCT_DEMO_APP;
		if (isset($app_id) && isset($workshop_num)){
			if ($discovery_source_item != 'none') // reference for discovery source
				$args['fields']['webinar-source3'] = $discovery_source_item_id;
				// $categories = array('value'=>$workshop_num);
				// $args['fields']['44580179'] = $categories;
			// Create item
			$item_id = PodioItem::create($app_id, $args);
			echo "Success";
			die();
		}
 	}
	catch(PodioError $e){
		printr($e);
		echo "Error submitting form";
		die();
	}
	echo "Form not submitted";
	die();
}

}

}

?>