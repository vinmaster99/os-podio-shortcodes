<?php

// Utility function to add parameters to urls
function addParamToURL($url, $key, $value) {
	$query = parse_url($url, PHP_URL_QUERY);
	if ($query)
		return $url."&$key=$value";
	else
		return $url."?$key=$value";
}

// show/hide errors
ini_set('display_errors', '1');

// Need this for default wordpress functions
require_once("../../../wp-load.php");

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
			if ($key == 'download_link') {
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

	// Download file
	if ($download_link != 'none') {
		// Get to the upload folder
		chdir('../../../../');
		header('Content-disposition: attachment; filename='.$download_link);
	    header('Content-type: application/pdf');
	    // readfile($_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/'.$download_link);
	    readfile('http://company.onescreen.com/files/'.$download_link);
	}

	// This is a whitepaper form so need to fetch reference id
	if ($discovery_source_item != 'none') {
		Podio::authenticate('app', array('app_id'=>OS_PODIO_SOURCE_APP,'app_token'=>OS_PODIO_SOURCE_APP_TOKEN));

		$discovery_source = PodioItem::get_by_app_item_id($discovery_source_app,$discovery_source_item);
		$test_values = array();
		$attributes = $discovery_source->__attributes;
		$discovery_source_item_id = $attributes['item_id'];
	}

	// Set up contact fields
	$contact_args['phone'] = array($contact_args['phone']);
	$contact_args['title'] = array($contact_args['title']);
	$contact_args['mail'] = array($contact_args['mail']);
	$contact_args['external_id'] = 'contact';

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
		}
 	}
	catch(PodioError $e){
		// printr($e);
		printr("Error in form");
	}
	printr("Redirecting back")
;	echo '<a href="'.addParamToURL($_SERVER['HTTP_REFERER'], 'success', 'true').'">Or click here to go back now';
}

?>

<script type="text/JavaScript">
redirectTime = "0";
redirectURL = "<?php echo addParamToURL($_SERVER['HTTP_REFERER'], 'success', 'true'); ?>";
function timedRedirect() {
	setTimeout("location.href = redirectURL;",redirectTime);
}
timedRedirect();
</script>