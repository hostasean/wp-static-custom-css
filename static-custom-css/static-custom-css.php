<?php
/*
Plugin Name: Static Custom CSS
Description: Add custom CSS to a static file that can be cached & minified to enable faster page load speeds.
Version: 1.1
Author: Joe Ogden
Author URI: https://www.joeogden.com
*/

// Function to enqueue stylesheets
function static_custom_css_enqueue_style() {	
	wp_enqueue_style('static-custom-css-style', plugin_dir_url(__FILE__)."style.css", false); 
}

/*
// Enqueue any scripts, not currently used
function static_custom_css_enqueue_script() {
	wp_enqueue_script( 'my-js', 'filename.js', false );
}
*/

// Enqueue the new static stylesheet
add_action('wp_enqueue_scripts', 'static_custom_css_enqueue_style');


// Function to create the admin page (under the Appearance menu)
function static_custom_css_page() {
add_theme_page('Static Custom CSS', 'Static Custom CSS', 'upload_files', 'static_custom_css', 'static_custom_css_screen');
}
// Create the admin page
add_action('admin_menu', 'static_custom_css_page');

// Function to display the admin page and do stuff with the POST
function static_custom_css_screen() {

$form_url = "themes.php?page=static_custom_css";
$output = $error = '';

/**
 * write submitted text into file (if any)
 * or read the text from file - if there is no submission
 **/
if(isset($_POST['csstext'])){ //new submission
    
    if(false === ($output = static_custom_css_text_write($form_url))){
        return; // Displaying credentials form - no need for further processing
    
    } elseif(is_wp_error($output)){
        $error = $output->get_error_message();
        $output = '';
    }
    
} else { // Read from file
    
    if(false === ($output = static_custom_css_text_read($form_url))){
        return; // Displaying credentials form - no need for further processing
    
    } elseif(is_wp_error($output)) {
        $error = $output->get_error_message();
        $output = '';
    }
}

$output = esc_textarea($output); // Escaping for printing

?>
<div class="wrap">
<h2>Static Custom CSS</h2>

<?php if(!empty($error)): ?>
    <div class="error below-h2"><?php echo $error;?></div>
<?php endif; ?>

<form method="post" action="" style="margin-top: 2em;">

<?php wp_nonce_field('static_custom_css_screen'); ?>

<fieldset class="form-table">
    <label for="csstext">Enter your custom CSS:</label><br>
    <textarea id="csstext" name="csstext" rows="16" class="large-text"><?php echo $output;?></textarea>
</fieldset>
    
   
<?php submit_button('Submit', 'primary', 'csstext_submit', true);?>

</form>
</div>
<?php
}


/**
 * Initialise Filesystem object
 *
 * @param str $form_url - URL of the page to display request form
 * @param str $method - connection method
 * @param str $context - destination folder
 * @param array $fields - fileds of $_POST array that should be preserved between screens
 * @return bool/str - false on failure, stored text on success
 **/
function static_custom_css_filesystem_init($form_url, $method, $context, $fields = null) {
    global $wp_filesystem;
    
    
    /* first attempt to get credentials */
    if (false === ($creds = request_filesystem_credentials($form_url, $method, false, $context, $fields))) {
        
        /**
         * if we comes here - we don't have credentials
         * so the request for them is displaying
         * no need for further processing
         **/
        return false;
    }
    
    /* now we got some credentials - try to use them*/        
    if (!WP_Filesystem($creds)) {
        
        /* incorrect connection data - ask for credentials again, now with error message */
        request_filesystem_credentials($form_url, $method, true, $context);
        return false;
    }
    
    return true; //filesystem object successfully initiated
}


/**
 * Perform writing into file
 *
 * @param str $form_url - URL of the page to display request form
 * @return bool/str - false on failure, stored text on success
 **/
function static_custom_css_text_write($form_url){
    global $wp_filesystem;
    
    check_admin_referer('static_custom_css_screen');
    
    // Sanitise the input but preserve newlines
    $new_line_substitute = '--NEWLINE--';
		$escaped_new_lines = str_replace("\n", $new_line_substitute, $_POST['csstext']);
		$sanitised = sanitize_text_field($escaped_new_lines);
		$csstext = str_replace($new_line_substitute, "\n", $sanitised);
		$csstext = stripslashes($csstext);
		
    $form_fields = array('csstext'); // Fields that should be preserved across screens
    $method = ''; // Leave this empty to perform test for 'direct' writing
    $context = plugin_dir_path(__FILE__); // Target folder
            
    $form_url = wp_nonce_url($form_url, 'static_custom_css_screen'); // Page url with nonce value
    
    if(!static_custom_css_filesystem_init($form_url, $method, $context, $form_fields))
        return false; // Stop further processing when request form is displaying
    
    
    /*
     * now $wp_filesystem could be used
     * get correct target file first
     **/
    $target_dir = $wp_filesystem->find_folder($context);
    $target_file = trailingslashit($target_dir).'style.css';
       
    
    /* write into file */
    if(!$wp_filesystem->put_contents($target_file, $csstext, FS_CHMOD_FILE)) 
        return new WP_Error('writing_error', 'Error when writing file'); // Return error object
      
    
    return $csstext;
}


/**
 * Read text from file
 *
 * @param str $form_url - URL of the page where request form will be displayed
 * @return bool/str - false on failure, stored text on success
 **/
function static_custom_css_text_read($form_url){
    global $wp_filesystem;

    $csstext = '';
    
    $form_url = wp_nonce_url($form_url, 'static_custom_css_screen');
    $method = ''; // Leave this empty to perform test for 'direct' writing
    $context = plugin_dir_path(__FILE__); // Target folder
    
    if(!static_custom_css_filesystem_init($form_url, $method, $context))
        return false; // Stop further processign when request formis displaying
    
    
    /*
     * now $wp_filesystem could be used
     * get correct target file first
     **/
    $target_dir = $wp_filesystem->find_folder($context);
    $target_file = trailingslashit($target_dir).'style.css';
    
    
    /* read the file */
    if($wp_filesystem->exists($target_file)){ // Check for existence
        
        $csstext = $wp_filesystem->get_contents($target_file);
        if(!$csstext)
            return new WP_Error('reading_error', 'Error when reading file'); // Return error object           
        
    }   
    
    return $csstext;    
}

?>