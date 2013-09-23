<?php 
// show/hide errors
ini_set('display_errors', '1');

// Need this for default wordpress functions
// $path = $_SERVER['DOCUMENT_ROOT'];
// $path = $_SERVER['DOCUMENT_ROOT'] . '/wordpress2';
// include_once ( $path . '/wp-blog-header.php' );
// include_once dirname(__FILE__).'/../../../wp-blog-header.php';
require_once("../../../wp-load.php");

if (isset($_POST)){
	// parameter array that we send to podio
	$args = array();
	$app_id;
	$contact_args = array( );

	/*
	NOTE: For future apps, probably going to pass hidden inputs to this page (ex. app_id)
			to determine which items to create for which apps / workspace
			Also: add discovery source as hidden input
	*/
	// get post values and set up args for podio
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
				// continue;
			}

			if ($key == 'contact' && isset($value['organization'])) {
				// Submit organization name for 2 fields
				$args['fields']['14771676'] = $value['organization'];
			}

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

	// $file = PodioFile::get($item_id);
	// $file_size = file_put_contents($item_name, $file->get_raw());
	// echo "Preparing file";
	// if ($file_size != $item_size) {
	// 	echo "Download Ready";
	// 	$file_name = $item_name;
	//     $file_url = 'http://localhost/wordpress2/' . $file_name;
	//     printr($file_url);
	//     header('Content-disposition: attachment; filename='.$file_name);
	//     header('Content-type: application/pdf');
	//     readfile($file_name);
	// }
	// header('Location: ' . $_SERVER['HTTP_REFERER'].'?submit=true');

	// Download file
	if ($download_link != 'none') {
		// $path = getcwd('../');
		chdir('../../../../');
		// $path = getcwd();
		// $path2 = scandir($path);
		// printr('path1'.$path);
		// printr($path2);
		header('Content-disposition: attachment; filename='.$download_link);
	    header('Content-type: application/pdf');
	    // readfile($_SERVER['DOCUMENT_ROOT'].'/wp-content/uploads/'.$download_link);
	    readfile('http://company.onescreen.com/files/'.$download_link);
	}

	if ($discovery_source_item != 'none') {
		Podio::authenticate('app', array('app_id'=>OS_PODIO_SOURCE_APP,'app_token'=>OS_PODIO_SOURCE_APP_TOKEN));

		$discovery_source = PodioItem::get_by_app_item_id($discovery_source_app,$discovery_source_item);
		$test_values = array();
		// printr($discovery_source);
		// printr($args);
		// printr($test_item);
		// printr($discovery_source_app);
		// printr($discovery_source_item);
		// $test = new PodioAppItemField(array('values'=>$test_values));
		$attributes = $discovery_source->__attributes;
		$discovery_source_item_id = $attributes['item_id'];
	}
	// printr($test);
	// printr($args);
	// printr($contact_args);

	// CLEAN UP THIS CODE TOMORROW!
	// note*: for some reason, some of these fields have to be of type array instead of string (don't know why)
	$contact_args['phone'] = array($contact_args['phone']);
	$contact_args['title'] = array($contact_args['title']);
	$contact_args['mail'] = array($contact_args['mail']);
	$contact_args['external_id'] = 'contact';
	// printr($contact_args);

	try{
		Podio::authenticate('app', array('app_id'=>OS_PODIO_LEADS_APP,'app_token'=>OS_PODIO_LEADS_APP_TOKEN));

		// Get business dev workspace id
		// $workspace = PodioSpace::get_for_url( array('url' => 'https://onescreen.podio.com/business-development') );
		// $workspace_att = $workspace->__attributes;
		// $space_id = $workspace_att['space_id'];
		$space_id = BUSINESS_DEV_WORKSPACE_ID;
		$contact_id = PodioContact::create( $space_id, $contact_args );
		// printr($contact_id);
		if (isset($app_id) && isset($contact_id) && isset($discovery_source_item)){
			$args['fields']['contact'] = $contact_id;
			if ($discovery_source_item != 'none')
				$args['fields'][$discovery_source_field_id] = $discovery_source_item_id;
			// printr($args);
			$item_id = PodioItem::create($app_id, $args);
		}
 	}
	catch(PodioError $e){
		// printr($e);
		printr("Error in form");
	}
	printr("Redirecting back")
;	echo '<a href="'.$_SERVER['HTTP_REFERER'].'">Or click here to go back now';
}

?>

<script type="text/JavaScript">
redirectTime = "0";
redirectURL = "<?php echo $_SERVER['HTTP_REFERER']; ?>";
function timedRedirect() {
	setTimeout("location.href = redirectURL;",redirectTime);
}
timedRedirect();
</script>