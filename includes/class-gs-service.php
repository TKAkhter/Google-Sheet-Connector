<?php
/**
 * Service class for Google Sheet Connector
 * @since 1.0
 */
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Gs_Connector_Service Class
 *
 * @since 1.0
 */
class Gs_Connector_Service
{

    private $allowed_tags = array( 'text', 'email', 'url', 'tel', 'number', 'range', 'date', 'textarea', 'select', 'checkbox', 'radio', 'acceptance', 'quiz', 'file', 'hidden','mask' );

    // private $special_mail_tags = array( 'date', 'time', 'serial_number', 'remote_ip', 'user_agent', 'url', 'post_id', 'post_name', 'post_title', 'post_url', 'post_author', 'post_author_email', 'site_title', 'site_description', 'site_url', 'site_admin_email', 'user_login', 'user_email', 'user_display_name' );

    private $special_mail_tags = array( 'date', 'time', 'url', 'post_url' );
    
    protected $gs_uploads   = array();
   
   /**
    *  Set things up.
    *  @since 1.0
    */
    public function __construct()
    {
        add_action('wp_ajax_verify_gs_integation', array( $this, 'verify_gs_integation' ));
        add_action('wp_ajax_gs_clear_log', array( $this, 'gs_clear_logs' ));
      
        add_action('wp_ajax_deactivate_gs_integation', array( $this, 'deactivate_gs_integation' ));

       // Add new tab to contact form 7 editors panel
        add_filter('wpcf7_editor_panels', array( $this, 'cf7_gs_editor_panels' ));

        add_action('wpcf7_after_save', array( $this, 'save_gs_settings' ));
        add_action('wpcf7_before_send_mail', array( $this, 'save_uploaded_files_local' ));
        add_action('wpcf7_mail_sent', array( $this, 'cf7_save_to_google_sheets' ));
       //add_action( 'admin_notices', array( $this, 'display_upgrade_notice' ) );
      
       //add_action( 'wp_ajax_set_upgrade_notification_interval', array( $this, 'set_upgrade_notification_interval' ) );
       //add_action( 'wp_ajax_close_upgrade_notification_interval', array( $this, 'close_upgrade_notification_interval' ) );
    }

   /**
    * AJAX function - verifies the token
    *
    * @since 1.0
    */
    public function verify_gs_integation()
    {
       // nonce checksave_gs_settings
        check_ajax_referer('gs-ajax-nonce', 'security');

       /* sanitize incoming data */
        $Code = sanitize_text_field($_POST["code"]);

        update_option('gs_access_code', $Code);

        if (get_option('gs_access_code') != '') {
            include_once(GS_CONNECTOR_ROOT . '/lib/google-sheets.php');
            cf7gsc_googlesheet::preauth(get_option('gs_access_code'));
            update_option('gs_verify', 'valid');
            wp_send_json_success();
        } else {
            update_option('gs_verify', 'invalid');
            wp_send_json_error();
        }
    }
   
    /**
    * AJAX function - deactivate activation
    * @since 4.2
    */
    public function deactivate_gs_integation()
    {
       // nonce check
        check_ajax_referer('gs-ajax-nonce', 'security');

        if (get_option('gs_token') !== '') {
            delete_option('gs_token');
            delete_option('gs_access_code');
            delete_option('gs_verify');

            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }
   
   /**
    * AJAX function - clear log file
    * @since 2.1
    */
    public function gs_clear_logs()
    {
       // nonce check
        check_ajax_referer('gs-ajax-nonce', 'security');
      
        $handle = fopen(GS_CONNECTOR_PATH . 'logs/log.txt', 'w');
        fclose($handle);
      
        wp_send_json_success();
    }

   /**
    * Add new tab to contact form 7 editors panel
    * @since 1.0
    */
    public function cf7_gs_editor_panels($panels)
    {
        if (current_user_can('wpcf7_edit_contact_form')) {
            $panels['google_sheets'] = array(
            'title' => __('Google Sheets', 'contact-form-7'),
            'callback' => array( $this, 'cf7_editor_panel_google_sheet' )
            );
        }
        return $panels;
    }

   /**
    * Set Google sheet settings with contact form
    * @since 1.0
    */
    public function save_gs_settings($post)
    {
        $default = array(
         "sheet-name" => "",
          "sheet-id" => "",
         "sheet-tab-name" => "",
          "tab-id" => ""
          
        );
        $sheet_data = isset($_POST['cf7-gs']) ? $_POST['cf7-gs'] : $default;
        update_post_meta($post->id(), 'gs_settings', $sheet_data);

        $form_id = sanitize_text_field($_GET['post']);

        $assoc_arr = [];
        $meta = get_post_meta($form_id, '_form', true);
        $fields = $this->get_contact_form_fields($meta);
        $saved_mail_tags = get_post_meta($form_id, 'gs_map_mail_tags');

        if ($fields) {
            foreach ($fields as $field) {
                $single = $this->get_field_assoc($field);
                if ($single) {
                    $assoc_arr[] = $single;
                }
            }
        }foreach ($assoc_arr as $key => $value) {
          // echo '<pre>'.print_r($key,TRUE).'</pre>';
          foreach ($value as $assoc_arr_key => $assoc_arr_value) {
            // echo '<pre>'.print_r($assoc_arr_key,TRUE).'</pre>';
            // echo '<pre>'.print_r($assoc_arr_value,TRUE).'</pre>';
            $final_header_array[] = $assoc_arr_value;
          }
        }
        // echo '<pre>'.print_r($final_header_array,TRUE).'</pre>';
        // echo '<pre>'.print_r($sheet_data,TRUE).'</pre>';
        $final_header_array = $sheet_data;
        $i = 0;
        foreach ($final_header_array as $final_header_array_key => $final_header_array_value) {
          if($i < 4 || $final_header_array_value == 1) {
              unset($final_header_array[$final_header_array_key]);
          }
          $i++;
        }
        // echo '<pre>'.print_r($sheet_data,TRUE).'</pre>';
        // echo '<pre>'.print_r($final_header_array,TRUE).'</pre>';
        // die();
        include_once(GS_CONNECTOR_ROOT . "/lib/google-sheets.php");
        $doc = new cf7gsc_googlesheet();
        $doc->auth();
        $doc->setSpreadsheetId($sheet_data['sheet-id']);
        $doc->setWorkTabId($sheet_data['tab-id']);
        // $worksheetCell = $service->spreadsheets_values->get($spreadsheetId, $worksheet_id . "!1:1"); 
        if(!empty($sheet_data['sheet-id'])) {
          $doc->add_header($sheet_data['sheetname'], $sheet_data['sheet-tab-name'], $final_header_array, $final_header_array);
        } 
    }
   
   /**
    * Create array of file name for the uploaded files
    * @since 4.5
    */
    public function save_uploaded_files_local()
    {
        $form = WPCF7_Submission::get_instance();
        if ($form) {
              $files       = $form->uploaded_files();
              $uploads_stored  = array();

            foreach ($files as $field_name => $file_path) {
                if (! isset($_FILES[ $field_name ])) {
                          continue;
                }
        
                $file_details = $_FILES[ $field_name ];
                $file_name = $file_details['name'];
                $uploads_stored[ $field_name ] = $file_name;
            }
            $this->gs_uploads = $uploads_stored;
        }
    }

   /**
    * Function - To send contact form data to google spreadsheet
    * @param object $form
    * @since 1.0
    */
    public function cf7_save_to_google_sheets($form)
    {
      
        $submission = WPCF7_Submission::get_instance();
      
       // get form data
        $form_id = $form->id();
        $form_data = get_post_meta($form_id, 'gs_settings');
        $data = array();
    
       // if contact form sheet name and tab name is not empty than send data to spreedsheet
        if ($submission && (! empty($form_data[0]['sheet-name']) ) && (! empty($form_data[0]['sheet-tab-name']) )) {
            $posted_data = $submission->get_posted_data();
     
           // make sure the form ID matches the setting otherwise don't do anything
            try {
                include_once(GS_CONNECTOR_ROOT . "/lib/google-sheets.php");
                $doc = new cf7gsc_googlesheet();
                $doc->auth();
                $doc->setSpreadsheetId($form_data[0]['sheet-id']);
                $doc->setWorkTabId($form_data[0]['tab-id']);

                // Special Mail Tags
                $meta = array();

                $special_mail_tags = array( 'serial_number', 'remote_ip', 'user_agent', 'url', 'date', 'time', 'post_id', 'post_name', 'post_title', 'post_url', 'post_author', 'post_author_email', 'site_title', 'site_description', 'site_url', 'site_admin_email', 'user_login', 'user_email', 'user_url', 'user_first_name', 'user_last_name', 'user_nickname', 'user_display_name' );

                foreach ($special_mail_tags as $smt) {
                    $tagname = sprintf('_%s', $smt);

                    $mail_tag = new WPCF7_MailTag(
                        sprintf('[%s]', $tagname),
                        $tagname,
                        ''
                    );
                
                    $meta[$smt] = apply_filters('wpcf7_special_mail_tags', '', $tagname, false, $mail_tag);
                }
                $meta_data = $form_data[0];

                if (! empty($meta_data)) {
                    $data["date"] = array_key_exists("date", $meta_data) ? $meta["date"] : '';
                    $data["time"] = array_key_exists("time", $meta_data) ? $meta["time"] : '';
                    $data["serial-number"] = array_key_exists("serial_number", $meta_data) ? $meta["serial_number"] : '';
                    $data["remote-ip"] = array_key_exists("remote_ip", $meta_data) ? $meta["remote_ip"] : '';
                    $data["user-agent"] = array_key_exists("user_agent", $meta_data) ? $meta["user_agent"] : '';
                    $data["url"] = array_key_exists("url", $meta_data) ? $meta["url"] : '';
                    $data["post-id"] = array_key_exists("post_id", $meta_data) ? $meta["post_id"] : '';
                    $data["post-name"] = array_key_exists("post_name", $meta_data) ? $meta["post_name"] : '';
                    $data["post-title"] = array_key_exists("post_title", $meta_data) ? $meta["post_title"] : '';
                    $data["post-url"] = array_key_exists("post_url", $meta_data) ? $meta["post_url"] : '';
                    $data["post-author"] = array_key_exists("post_author", $meta_data) ? $meta["post_author"] : '';
                    $data["post-author-email"] = array_key_exists("post_author_email", $meta_data) ? $meta["post_author_email"] : '';
                    $data["site-title"] = array_key_exists("site_title", $meta_data) ? $meta["site_title"] : '';
                    $data["site-description"] = array_key_exists("site_description", $meta_data) ? $meta["site_description"] : '';
                    $data["site-url"] = array_key_exists("site_url", $meta_data) ? $meta["site_url"] : '';
                    $data["site-admin-email"] = array_key_exists("site_admin_email", $meta_data) ? $meta["site_admin_email"] : '';
                    $data["user-login"] = array_key_exists("user_login", $meta_data) ? $meta["user_login"] : '';
                    $data["user-email"] = array_key_exists("user_email", $meta_data) ? $meta["user_email"] : '';
                    $data["user-url"] = array_key_exists("user_url", $meta_data) ? $meta["user_url"] : '';
                    $data["user-first-name"] = array_key_exists("user_first_name", $meta_data) ? $meta["user_first_name"] : '';
                    $data["user-last-name"] = array_key_exists("user_last_name", $meta_data) ? $meta["user_last_name"] : '';
                    $data["user-nickname"] = array_key_exists("user_nickname", $meta_data) ? $meta["user_nickname"] : '';
                    $data["user-display-name"] = array_key_exists("user_display_name", $meta_data) ? $meta["user_display_name"] : '';
                }

                foreach ($posted_data as $key => $value) {
            // exclude the default wpcf7 fields in object
                    if (strpos($key, '_wpcf7') !== false || strpos($key, '_wpnonce') !== false) {
                            // do nothing
                    } else {
                         // Get file name array
                         $uploaded_file = $this->gs_uploads;
                        if (array_key_exists($key, $uploaded_file) || isset($uploaded_file[ $key ])) {
                            $data[ $key ] = $uploaded_file[ $key ];
                            continue;
                        }

                         // handle strings and array elements
                        if (is_array($value)) {
                            $data[ $key ] = implode(', ', $value);
                        } else {
                            $data[ $key ] = stripcslashes($value);
                        }
                    }
                }
                $doc->add_row($data);
            } catch (Exception $e) {
                $data['ERROR_MSG'] = $e->getMessage();
                $data['TRACE_STK'] = $e->getTraceAsString();
                Gs_Connector_Utility::gs_debug_log($data);
            }
        }
    }

   /*
    * Google sheet settings page
    * @since 1.0
    */

    public function cf7_editor_panel_google_sheet($post)
    {
        $form_id = sanitize_text_field($_GET['post']);
        $form_data = get_post_meta($form_id, 'gs_settings');
        // echo '<pre>'.print_r($form_data, true).'</pre>';
        ?>
        <form method="post">
          <div class="gs-fields">
            <div class="fields">
              <h2><span>
                  <?php echo esc_html(__('Google Sheet Settings', 'gsconnector')); ?>
                </span></h2>
              <p>
                <label>
                  <?php echo esc_html(__('Google Sheet Name', 'gsconnector')); ?>
                </label>
                <input type="text" name="cf7-gs[sheet-name]" id="gs-sheet-name"
                  value="<?php echo ( isset($form_data[0]['sheet-name']) ) ? esc_attr($form_data[0]['sheet-name']) : ''; ?>" />
                <a href="" class=" gs-name help-link">
                  <?php echo esc_html(__('Where do i get Sheet Name?', 'gsconnector')); ?><span class='hover-data'>
                    <?php echo esc_html(__('Go to your google account and click on"Google apps" icon and than click "Sheets". Select the name of the appropriate sheet you want to link your contact form or create new sheet.', 'gsconnector')); ?>
                  </span>
                </a>
              </p>
              <p>
                <label>
                  <?php echo esc_html(__('Google Sheet Id', 'gsconnector')); ?>
                </label>
                <input type="text" name="cf7-gs[sheet-id]" id="gs-sheet-id"
                  value="<?php echo ( isset($form_data[0]['sheet-id']) ) ? esc_attr($form_data[0]['sheet-id']) : ''; ?>" />
                <a href="" class=" gs-name help-link">
                  <?php echo esc_html(__('Google Sheet Id?', 'gsconnector')); ?><span class='hover-data'>
                    <?php echo esc_html(__('you can get sheet id from your sheet URL', 'gsconnector')); ?>
                  </span>
                </a>
              </p>
              <p>
                <label>
                  <?php echo esc_html(__('Google Sheet Tab Name', 'gsconnector')); ?>
                </label>
                <input type="text" name="cf7-gs[sheet-tab-name]" id="gs-sheet-tab-name"
                  value="<?php echo ( isset($form_data[0]['sheet-tab-name']) ) ? esc_attr($form_data[0]['sheet-tab-name']) : ''; ?>" />
                <a href="" class=" gs-name help-link">
                  <?php echo esc_html(__('Where do i get Tab Name?', 'gsconnector')); ?><span class='hover-data'>
                    <?php echo esc_html(__('Open your Google Sheet with which you want to link your contact form . You will notice a tab names at bottom of the screen. Copy the tab name where you want to have an entry of contact form.', 'gsconnector')); ?>
                  </span>
                </a>
              </p>
              <p>
                <label>
                  <?php echo esc_html(__('Google Tab Id', 'gsconnector')); ?>
                </label>
                <input type="text" name="cf7-gs[tab-id]" id="gs-tab-id"
                  value="<?php echo ( isset($form_data[0]['tab-id']) ) ? esc_attr($form_data[0]['tab-id']) : ''; ?>" />
                <a href="" class=" gs-name help-link">
                  <?php echo esc_html(__('Google Tab Id?', 'gsconnector')); ?><span class='hover-data'>
                    <?php echo esc_html(__('you can get tab id from your sheet URL', 'gsconnector')); ?>
                  </span>
                </a>
              </p>
            </div>
            <div class="cd-faq-items">
              <ul id="basics" class="cd-faq-group">
                <li class="content-visible">
                  <a class="cd-faq-trigger" href="#0">
                    <?php echo esc_html(__('Field List - ', 'gsconnector')); ?>
                  </a>
                  <div class="cd-faq-content" style="display: block;">
                    <div class="gs-demo-fields gs-second-block">
                      <h2>
                        <!-- <span class="gs-info"> -->
                          <!-- <?php echo esc_html(__('Map mail tags with custom header name and save automatically to google sheet. ', 'gsconnector')); ?> -->
                        <!-- </span> -->
                      </h2>
                      <?php $this->display_form_fields($form_id, $form_data); ?>
                    </div>
                  </div>
                </li>
              </ul>
            </div>
            <div class="cd-faq-items">
              <ul id="basics" class="cd-faq-group">
                <li class="content-visible">
                  <a class="cd-faq-trigger" href="#0">
                    <?php echo esc_html(__('Special Mail Tags - ', 'gsconnector')); ?>
                  </a>
                  <div class="cd-faq-content" style="display: block;">
                    <div class="gs-demo-fields gs-third-block">
                      <?php $this->display_form_special_tags($form_id, $form_data); ?>
                    </div>
                  </div>
                </li>
              </ul>
            </div>
            <!-- <div class="cd-faq-items">
              <ul id="basics" class="cd-faq-group">
                <li class="content-visible">
                  <a class="cd-faq-trigger" href="#0">
                    <?php echo esc_html(__('Custom Mail Tags ', 'gsconnector')); ?>
                  </a>
                  <div class="cd-faq-content" style="display: block;">
                    <div class="gs-demo-fields gs-third-block">
                      <?php $this->display_form_custom_tag($form_id); ?>
                    </div>
                  </div>
                </li>
              </ul>
            </div> -->
          </div>
        </form>
        <?php
        // include(GS_CONNECTOR_PATH . "includes/pages/gs-field-list.php");
        
        // include(GS_CONNECTOR_PATH . "includes/pages/gs-special-mailtags.php");
      
        // include(GS_CONNECTOR_PATH . "includes/pages/gs-custom-mail-tags.php");

        // include(GS_CONNECTOR_PATH . "includes/pages/gs-custom-ordering.php");
    }
   
   /**
    * Function - fetch contact form list that is connected with google sheet
    * @since 2.1
    */
    public function get_forms_connected_to_sheet()
    {
        global $wpdb;
      
        $query = $wpdb->get_results("SELECT ID, post_title, meta_value from ".$wpdb->prefix."posts as p JOIN ".$wpdb->prefix."postmeta as pm on p.ID = pm.post_id where pm.meta_key='gs_settings' AND p.post_type='wpcf7_contact_form' ORDER BY p.ID");
        return $query;
    }
   
   /**
    * Function - display contact form fields to be mapped to google sheet
    * @param int $form_id
    * @since 1.0
    */
    public function display_form_fields($form_id, $form_data)
    {
        ?>

        <?php
       // fetch saved fields
        $saved_mail_tags = get_post_meta($form_id, 'gs_map_mail_tags');
      // echo '<pre>'.print_r($form_data,TRUE).'</pre>';

      // echo '<pre>'.print_r($saved_mail_tags,TRUE).'</pre>';      
       // fetch mail tags
        $assoc_arr = [];
        $meta = get_post_meta($form_id, '_form', true);
        $fields = $this->get_contact_form_fields($meta);

        if ($fields) {
            foreach ($fields as $field) {
                $single = $this->get_field_assoc($field);
                if ($single) {
                    $assoc_arr[] = $single;
                }
            }
        }
        // echo '<pre>'.print_r($assoc_arr,TRUE).'</pre>'; 
        if (! empty($assoc_arr)) {
            ?>
<table class="gs-field-list">
            <?php
            $count = 0;
            foreach ($assoc_arr as $key => $value) {
                foreach ($value as $k => $v) {
                    $saved_val = "";
                    $checked = '';
                    $disabled = 'disabled';
                    if (isset($form_data[0][$v.'-tick'])) {
                           $saved_val = $saved_mail_tags[0][$v];
                           $checked = "checked";
                           $disabled =  '';
                    } 
            
                    $placeholder = preg_replace('/[\\_]|\\s+/', '-', $v);
                    if (isset($form_data[0][$v])) {
                           $saved_val_field = $v;
                    } else {
                        $saved_val_field =  $placeholder;
                    }

                    ?>
  <tr>
    <td><input type="checkbox" name="cf7-gs[<?php echo $v; ?>-tick]" id="gs-<?php echo $v; ?>-tick" value="1" <?php
        echo $checked; ?> ></td>
    <td>
                    <?php echo $v; ?> :
    </td>

    <td class="gs-r-pad">
      <input type="text" id="gs-<?php echo $v; ?>" <?php echo $disabled; ?> name="cf7-gs[<?php echo $v; ?>]"
        value="<?php echo $saved_val_field; ?>"
        placeholder="<?php echo $placeholder; ?>">
    </td>
  </tr>
                    <?php
                    $count++;
                }
            }
            ?>
</table>
            <?php
        } else {
            echo '<p><span class="gs-info">' . __('No mail tags available.', 'gsconnector') . '</span></p>';
        }
    }
   
    public function get_contact_form_fields($meta)
    {
        $regexp = '/\[.*\]/';
        $arr = [];
        if (preg_match_all($regexp, $meta, $arr) == false) {
            return false;
        }
        return $arr[0];
    }
   
    public function get_field_assoc($content)
    {
        $regexp_type = '/(?<=\[)[^\s\*]*/';
        $regexp_name = '/(?<=\s)[^\s\]]*/';
        $arr_type = [];
        $arr_name = [];
        if (preg_match($regexp_type, $content, $arr_type) == false) {
            return false;
        }
        if (!in_array($arr_type[0], $this->allowed_tags)) {
            return false;
        }
        if (preg_match($regexp_name, $content, $arr_name) == false) {
            return false;
        }
        return array($arr_type[0] => $arr_name[0]);
    }
   
   /**
    * Function - display contact form special mail tags to be mapped to google sheet
    * @since 2.6
    */
    public function display_form_special_tags($form_id, $form_data)
    {
      
     
      
        $tags_count = count($this->special_mail_tags);
        $num_of_cols = 1;
        ?>
<h2>
  <!-- <span class="gs-info"> -->
        <!-- <?php echo esc_html(__('Map special mail tags with custom header name and save automatically to google sheet. ', 'gsconnector')); ?> -->
  <!-- </span> -->
</h2>
<table class="gs-field-list special">
        <?php
            
        for ($i = 0; $i <= $tags_count; $i++) {
            if ($i == $tags_count) {
                break;
            }
            $checked = '';
            $disabled = '';
            $tag_name = $this->special_mail_tags[ $i ];

            if (isset($form_data[0][$tag_name.'-tick'])) {
                   $checked = "checked";
            } else {
                $disabled = 'disabled';
            }
                           
            $placeholder = str_replace('_', '-', $tag_name);

            if (isset($form_data[0][$tag_name]) && array_key_exists($tag_name.'-tick', $form_data[0])) {
                    $tag_value = esc_attr($form_data[0][$tag_name]);
            } /*elseif (!array_key_exists($tag_name.'-tick', $form_data[0])) {
                    $tag_value = '';
                    unset($form_data[0][$tag_name]);
            }*/ else {
                    $tag_value = $placeholder;
                    unset($form_data[0][$tag_name]);
}
            // $tag_value = isset($form_data[0][$tag_name]) ? esc_attr($form_data[0][$tag_name]) : '';
            echo '<tr>';
            echo '<td><input type="checkbox" name="cf7-gs['.$tag_name.'-tick]" id="gs-'.$tag_name.'-tick" value="1" '.$checked.'></td>';
            echo '<td class="special-tags">[_' . $tag_name . '] </td>';
            echo '<td class="gs-r-pad"><input type="text" class="name-field" name="cf7-gs['. $tag_name . ']" value="'.$tag_value.'" placeholder="'. $placeholder .'" '.$disabled.'> </td>';
if ($i % $num_of_cols == 1) {
     echo '</tr><tr>';
}
        }
        ?>
</table>
        <?php
    }
   
    function display_form_custom_tag($form_id)
    {
        $custom_mail_tags = array();
        $num_of_cols = 2;
       
        if (has_filter("gscf7_special_mail_tags")) {
           // Filter hook for custom mail tags
            $custom_tags = apply_filters("gscf7_special_mail_tags", $custom_mail_tags, $form_id);
            $custom_tags_count = count($custom_tags);
            $num_of_cols = 2;
           // fetch saved fields
            $saved_cmail_tags = get_post_meta($form_id, 'gs_map_custom_mail_tags');
            ?>
<table class="gs-field-list">
            <?php
               echo '<tr>';
            for ($i = 0; $i <= $custom_tags_count; $i++) {
                if ($i == $custom_tags_count) {
                     break;
                }
                   $tag_name = $custom_tags[ $i ];
                   $modify_tag = ltrim($tag_name, '_');
                   $saved_val = "";
                   $checked = "";
                if (! empty($saved_cmail_tags) && array_key_exists($modify_tag, $saved_cmail_tags[0])) :
                    $saved_val = $saved_cmail_tags[0][$modify_tag];
                    $checked = "checked";
                endif;
                  
                  //hack - todo
                   $placeholder_explode = explode('_', $tag_name, 2);
                   $placeholder = str_replace('_', '-', $placeholder_explode[1]);
               
                   echo '<td><input type="checkbox" name="gs-ct-ck['. $i . ']" value="1" ' . $checked . '></td>';
                   echo '<td>[' . $tag_name . ']</td>';
                   echo '<td class="gs-r-pad"><input type="hidden" name="gs-ct-key['. $i . ']" value="' . $tag_name . '" ><input type="hidden" name="gs-ct-placeholder['. $i . ']" value="' . $placeholder . '" ><input type="text" name="gs-ct-custom-header['. $i . ']" value="' . $saved_val . '" placeholder="'. $placeholder .'"></td>';
                if ($i % $num_of_cols == 1) {
                     echo '</tr><tr>';
                }
            }
            ?>
</table>
            <?php
        } else {
            echo '<p><span class="gs-info">' . __('No custom mail tags available.', 'gsconnector') . '</span></p>';
        }
    }
   
    public function display_upgrade_notice()
    {
        $get_notification_display_interval = get_option('gs_upgrade_notice_interval');
        $close_notification_interval = get_option('gs_close_upgrade_notice');
      
        if ($close_notification_interval === "off") {
            return;
        }
      
        if (! empty($get_notification_display_interval)) {
            $adds_interval_date_object = DateTime::createFromFormat("Y-m-d", $get_notification_display_interval);
            $notice_interval_timestamp = $adds_interval_date_object->getTimestamp();
        }
      
        if (empty($get_notification_display_interval) || current_time('timestamp') > $notice_interval_timestamp) {
            $ajax_nonce   = wp_create_nonce("gs_upgrade_ajax_nonce");
            $upgrade_text = '<div class="gs-adds-notice">';
            $upgrade_text .= '<span><b>CF7 Google Sheet Connector </b> ';
            $upgrade_text .= 'version 4.0 would required you to <a href="'.  admin_url("admin.php?page=wpcf7-google-sheet-config") . '">reauthenticate</a> with your Google Account again due to update of Google API V3 to V4.<br/><br/>';
            $upgrade_text .= 'To avoid any loss of data redo the Google Sheet settings of each Contact Forms again with required sheet and tab details.</span>';
            $upgrade_text .= '<ul class="review-rating-list">';
            $upgrade_text .= '<li><a href="javascript:void(0);" class="cf7gsc_upgrade" title="Done">Yes, I have done.</a></li>';
            $upgrade_text .= '<li><a href="javascript:void(0);" class="cf7gsc_upgrade_later" title="Remind me later">Remind me later.</a></li>';
            $upgrade_text .= '</ul>';
            $upgrade_text .= '<input type="hidden" name="gs_upgrade_ajax_nonce" id="gs_upgrade_ajax_nonce" value="' . $ajax_nonce . '" />';
            $upgrade_text .= '</div>';

            $upgrade_block = Gs_Connector_Utility::instance()->admin_notice(array(
            'type'    => 'upgrade',
            'message' => $upgrade_text
            ));
            echo $upgrade_block;
        }
    }
   
    public function set_upgrade_notification_interval()
    {
       // check nonce
        check_ajax_referer('gs_upgrade_ajax_nonce', 'security');
        $time_interval = date('Y-m-d', strtotime('+10 day'));
        update_option('gs_upgrade_notice_interval', $time_interval);
        wp_send_json_success();
    }
   
    public function close_upgrade_notification_interval()
    {
       // check nonce
        check_ajax_referer('gs_upgrade_ajax_nonce', 'security');
        update_option('gs_close_upgrade_notice', 'off');
        wp_send_json_success();
    }
}

$gs_connector_service = new Gs_Connector_Service();