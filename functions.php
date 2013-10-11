<?php 

function register_styles_scripts(){
	wp_register_style('os-podio-shortcodes', plugins_url('css/style.css', __FILE__));
}
add_action( 'wp_enqueue_scripts', 'register_styles_scripts' );

// main test/printing function
if (!function_exists('printr')){
	function printr($array){
	  echo '<pre>';
	  print_r($array);
	  echo '</pre>';
	}
}

// returns html error message
if (!function_exists('os_message')){
	function os_message($message){
		return '<span style="color:blue;position:absolute;top:15px;background:#f5f5f5;padding:10px;margin-bottom:15px;box-shadow:2px 3px 3px #888;">ERROR: ' . $message . '</span>';
	}
}

if (!function_exists('stripallslashes')){
	function stripallslashes($query) {
	    $data = explode("\\",$query);
	    $cleaned = implode("",$data);
	    return $cleaned;
	}
}

// Utility function to add parameters to urls
function addParamToURL($url, $key, $value) {
	$query = parse_url($url, PHP_URL_QUERY);
	if ($query)
		return $url."&$key=$value";
	else
		return $url."?$key=$value";
}

?>