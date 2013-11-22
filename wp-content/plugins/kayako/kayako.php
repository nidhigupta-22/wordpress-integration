<?php
/*
 * Plugin Name:	Kayako for Wordpress
 * Plugin URI:	http://www.kayako.com/wordpress
 * Description:	Customer support from the convenience of own blog
 * Version: 1.0
 * Author : Saloni Dhall <saloni.dhall@kayako.com>
 * Author URI :	http://www.kayako.com/people/saloni.dhall
 * License: http://www.kayako.com/license
 */

require_once( plugin_dir_path(__FILE__) . 'KayakoApi.php' );
define(WP_DEBUG, true);
session_start();

/*
 * Kayako plugin class
 *
 * This is the main plugin class, handles all the plugin options, WordPress
 * hooks and filters, as well as options validation. Kayako Live chat tag generator,
 * Ticket Submission Form for frontend users, Comments converted to kayako tickets.
 *
 */

final class Kayako {

    private $settings = array();
    private $setting_liveChat = array();
    private $ticketInfoContainer = array();
    private $store;



    /*
     *  Class Constructor
     */
    public function __construct()  {
        //Calling Hooks ...........
	add_action('admin_menu', array(&$this, 'kayako_admin_menu'));
	add_action('admin_init', array(&$this, 'kayako_admin_init'));
	add_action('wp_head',array(&$this, 'enqueue_scripts'));
	add_shortcode('kayako_helpdesk', array(&$this, '_display_contact_ticket_form'));





        //Custom actions calls.....
	add_action('wp_ajax_ticketPostDiv', array(&$this, 'ticketPostDiv'));
	add_action('wp_ajax_post_reply_action', array(&$this, 'post_reply_action'));
	add_action('wp_ajax_update_ticket_properties', array(&$this, 'update_ticket_properties'));


	if ( $settings = get_option('kayako-settings') )
	{
            $this->settings = get_option('kayako-settings');

	    if ( empty($this->settings['kayako_url']) || empty($this->settings['kayako_key']) || empty($this->settings['kayako_secret']) )
	    {
		add_action('admin_notices', array(&$this,'my_admin_notice'));
	    }

            $this->kayakoAPI = new KayakoApi($settings['kayako_url'], $settings['kayako_key'], $settings['kayako_secret']);
	}

	if ( isset($_REQUEST['tid']) && isset($_REQUEST['tURL']) )
	{
		add_action('admin_notices', array(&$this,'comment_section_notices'));
	}


        $this->AccessFormProcess();

        $this->setting_liveChat = get_option('kayako-livechat_tag-settings');


        $this->setup();


        add_action('wp_dashboard_setup', array($this, '_kayako_dashboard_widget_setup'));
     }


     /*
      * Display the kayako validation
      */
     public function my_admin_notice()  {

	echo '<div class="updated"><p>It is required to fill the API key, Secret key, Kayako URL ! There is an issue in establishing connection with kayako helpdesk, Please provide the full details !</p></div>';

    }


	/*
	 * Display the Comment Section notices
	 */
    public function comment_section_notices() {

	echo  '<div class="updated"><p>Your comment has been converted to ticket successfully ! To View your ticket Click here  <a href="'.urldecode($_REQUEST['tURL']).'" target = "_blank">#'.$_REQUEST['tid'].'</a></p></div>';
    }


    /*
     *  Setup function loads the default settings, stores the current user details
     */
    public function setup() {
        // Load up the settings, set the Kayako URL and initialize the API object.
        $this->_load_settings();

		global $current_user;
		wp_get_current_user();
		if ($current_user->ID) {
            $this->user = $current_user;
		}
    }


    /*
     *  Load Settings It returns the default settings
     *
     */
    private function _load_settings() {

	$this->settings = get_option('kayako-settings', false);

	$this->livechat_tag_settings = get_option('kayako-livechat_tag-settings', false);

        //Save Default setting
	$this->default = array(
			'form_title' => __( 'Kayako Ticket Form Submission', 'kayako' ),
			'fullname' => __( 'Name', 'kayako') ,
			'email' => __( 'Email', 'kayako' ),
			'tickettype' => __( 'Select Ticket Type', 'kayako' ),
			'ticketpriority' => __( 'Select Ticket Priority', 'kayako' ),
			'department' => __( 'Select Department', 'kayako' ),
			'subject' => __( 'Subject', 'kayako' ),
			'contents' => __( 'Description', 'kayako' ),

		);
    }


    /*
     * Admin Initialization
     * Registration of different sections has been done here.
     * All the options are stored in the $this->settings
     * array which is kept under the 'kayako-settings' key inside
     * the WordPress database.
     *
     */
    public function kayako_admin_init() {

	// Scripts and style sheet
	add_action('admin_print_styles', array($this, 'kayako_admin_print_styles'));


        //Check for the zcomments
	add_filter('comment_row_actions', array(&$this, '_add_comment_row_actions'), 10, 2);
	add_filter('manage_edit-comments_columns', array( &$this, '_add_comments_columns_filter' ), 10, 1 );
	add_action('manage_comments_custom_column', array( &$this, '_add_comments_columns_action' ), 10, 1 );


        //Gathering API information form setup
	register_setting('kayako-settings', 'kayako-settings', '');
	add_settings_section('api_setting_form', __('Access Details', 'kayako'), array(&$this, '_settings_section_api_setting_form'), 'kayako-settings');
	add_settings_field('kayako_url', __('Your Kayako API URL', 'kayako'), array(&$this, '_settings_field_kayako_url'), 'kayako-settings', 'api_setting_form');
	add_settings_field('kayako_key', __('API Key', 'kayako'), array(&$this, '_settings_field_api_key'), 'kayako-settings', 'api_setting_form');
	add_settings_field('kayako_secret', __('API Secret', 'kayako'), array(&$this, '_settings_api_secret'), 'kayako-settings', 'api_setting_form');
	add_settings_section('api_setting_form', __('Access Details', 'kayako'), array(&$this, '_settings_section_api_setting_form'), 'kayako-settings');



	//Create a Kayako Ticket
	add_settings_section('kayako_ticket_form', __('Kayako Ticket Submission Form Display Settings', 'kayako'), array(&$this, '_kayako_ticket_form'), 'kayako-settings');
	add_settings_field('ky_formtitle', __( 'Form Title', 'kayako' ), array( &$this, '_settings_field_form_title' ), 'kayako-settings', 'kayako_ticket_form' );
	add_settings_field('ky_fullname', __( 'Fullname Label', 'kayako' ), array( &$this, '_settings_field_fullname' ), 'kayako-settings', 'kayako_ticket_form');
	add_settings_field('ky_email', __( 'Email Label', 'kayako' ), array( &$this, '_settings_field_email' ), 'kayako-settings', 'kayako_ticket_form' );
    add_settings_field('ky_tickettype', __( 'Ticket Type Label', 'kayako' ), array( &$this, '_settings_field_tickettype' ), 'kayako-settings', 'kayako_ticket_form');
    add_settings_field('ky_ticketpriority', __( 'Ticket priority Label', 'kayako' ), array( &$this, '_settings_field_ticketpriority' ), 'kayako-settings', 'kayako_ticket_form' );
  	add_settings_field('ky_department', __( 'Department Label', 'kayako' ), array( &$this, '_settings_field_department' ), 'kayako-settings', 'kayako_ticket_form' );
	add_settings_field('ky_subject', __( 'Subject Label', 'kayako' ), array( &$this, '_settings_field_subject' ), 'kayako-settings', 'kayako_ticket_form' );
	add_settings_field('ky_contents', __( 'Content Label', 'kayako' ), array( &$this, '_settings_field_contents' ), 'kayako-settings', 'kayako_ticket_form' );


	//Kayako Live chat Settings
	register_setting('kayako-livechat_tag-settings', 'kayako-livechat_tag-settings', '');
	add_settings_section('kayako_livechat_tag_form', __('Live Chat tag generator', 'kayako'), array(&$this, 'livechat_tag_settings_description'), 'kayako-livechat_tag-settings');
	add_settings_field('kayako_tag', __('Paste the tag generated by Kayako fusion here : ', 'kayako'), array(&$this, '_livechat_tag_textarea'), 'kayako-livechat_tag-settings', 'kayako_livechat_tag_form');
	add_settings_section('kayako_livechat_note', __('Note :', 'kayako'), array(&$this, '_kayako_livechat_note'), 'kayako-livechat_tag-settings');


        //Processes the forms
	$this->_process_formData();

    }


    /*
     * Kayako admin menu
     */
    public function kayako_admin_menu() {

		add_menu_page('Kayako for Wordpress', 'Kayako', 'manage_options', 'kayako-support', array(&$this, '_admin_menu_contents'), plugins_url('kayako/images/small_esupport.gif'), 99);
        add_submenu_page('kayako-support', 'Kayako Settings', __('Kayako Settings', 'support'), 'manage_options', 'kayako-support', array(&$this, '_admin_menu_contents'));
        add_submenu_page('kayako-support', 'Kayako Livechat Tag', __('LiveChat Tag', 'support'), 'manage_options', 'kayako-livechat_tag-settings', array(&$this, '_livechat_form_contents'));

    }


    /*
     * Kayako Admin Print Styles
     */
    public function kayako_admin_print_styles() {
        wp_enqueue_style('kayako-admin',  plugins_url('/css/admin.css', __FILE__));
		wp_enqueue_style('my-script',  plugins_url('/css/general.css', __FILE__));
        wp_enqueue_script('kayako-admin', plugins_url('/js/kayako.js', __FILE__), array('jquery'));
		wp_localize_script('kayako-admin', 'kayako', array('plugin_url' => admin_url()."edit-comments.php") );
		wp_enqueue_script('my-script', plugins_url('/js/popup.js', __FILE__), array('jquery'));

    }


    /*
     * It displays admin menu contents
     * It comes up with the entire form for saving settings
     */
    public function _admin_menu_contents() {

	if ( isset($_REQUEST['action']) && $_REQUEST['action'] == 'convert_comments' || $_REQUEST['c'] )
	{
	    $this->display_comment_conversion_form();
	}
	else
	{
	    ?>
	    <div class="wrap">
		<div id="kayako-icon32" class="icon32"><br></div>
		<h2><?php _e('Kayako for WordPress Settings', 'kayako'); ?></h2>
		<form method="post" action="options.php">
		    <input type="hidden" name ="kayako-settings[saved]" />
		    <?php wp_nonce_field('update-options'); ?>
		    <?php settings_fields('kayako-settings'); ?>
		    <?php do_settings_sections('kayako-settings'); ?>
		    <p class="submit" align="left">
			<input name="Submit" type="submit" class="button-primary" value="<?php esc_attr_e('Save Details', 'kayako'); ?>" />
		    </p>
		</form>
	    </div>
	    <?php
	}
    }


    /*
     * Setting of api_setting_section_api_form
     * It outputs the description of heading which displays while saving main kayako API settings
     *
     */
    public function _settings_section_api_setting_form() {
        _e('Please enter your Kayako API Key, Secret Key, Kayako URL for further connection !', 'kayako');
    }


    /*
     *  It returns a textbox which outputs the Kayako URL input box
     */
    public function _settings_field_kayako_url() {

        if ( !empty($this->settings) && array_key_exists('kayako_url', $this->settings) ) {
		$kayako_url = htmlspecialchars($this->settings['kayako_url']);
        }
        ?>
        <input type="text" name="kayako-settings[kayako_url]" id="kayako-settings[kayako_url]"  size="60" value="<?php echo $kayako_url; ?>" /> <?php
    }


    /*
     *  It returns a textbox which outputs the Kayako URL input box
     */
    public function _settings_field_api_key() {
	if ( !empty($this->settings) && array_key_exists('kayako_key', $this->settings) ) {
		$kayako_key = htmlspecialchars($this->settings['kayako_key']);
        }
        ?>
        <input type="text" name="kayako-settings[kayako_key]" id="kayako-settings[kayako_key]"  size="60" value="<?php echo $kayako_key; ?>"/> <?php
    }


    /*
     * It returns a textbox which outputs the Kayako URL input box
     */
    public function _settings_api_secret() {
        if ( !empty($this->settings) && array_key_exists('kayako_secret', $this->settings)  ) {
		$kayako_secret = htmlspecialchars($this->settings['kayako_secret']);
        }
        ?> <input type="text" name="kayako-settings[kayako_secret]" id="kayako-settings[kayako_secret]"  size="60" value="<?php echo $kayako_secret; ?>"/> <?php
    }


    /*
     *  It returns a textbox which outputs the Kayako Form Title input box
     */
    public function _settings_field_form_title() {
        if ( !empty($this->settings) && array_key_exists('form_title', $this->settings) && !empty($this->settings['form_title']) &&  $this->settings['form_title']<> '0' )
	{
	    $form_value = $this->settings['form_title'];
	}
        else
	{
	    $form_value = $this->default['form_title'];
        }
	?>
            <input type="text" size="40" name="kayako-settings[form_title]" value="<?php echo $form_value; ?>" placeholder="Kayako Ticket Form Submission" />
	<?php
     }


    /*
     * It returns a textbox which displays the Fullname input box
     */
    public function _settings_field_fullname() {
        if ( !empty($this->settings) && array_key_exists('fullname', $this->settings) && !empty($this->settings['fullname']) &&  $this->settings['fullname']<> '0')
	{
	    $fullname = $this->settings['fullname'];
	}
        else
	{
	    $fullname = $this->default['fullname'];
	}
	?>
            <input type="text" size="40" name="kayako-settings[fullname]" value="<?php echo $fullname; ?>" placeholder="Please enter your fullname" />
	<?php
    }


     /*
      * It returns a textbox which displays the Email Address input box
      */
     public function _settings_field_email() {
        if ( !empty($this->settings) && array_key_exists('email', $this->settings) && !empty($this->settings['email']) &&  $this->settings['email']<> '0')
	{
	    $email = $this->settings['email'];
	}
	else
	{
	    $email = $this->default['email'];
	}
         ?>
            <input type="text" size="40" name="kayako-settings[email]" value="<?php echo $email; ?>" placeholder="Your Email Address" />
	<?php
    }


     /* Labeling of Ticket Type
      * It returns a textbox which asks for the input for the label ticket type
      */
     public function _settings_field_tickettype() {
         if ( !empty($this->settings) && array_key_exists('tickettype', $this->settings) && !empty($this->settings['tickettype']) &&  $this->settings['tickettype']<> '0')
	{
	    $tickettype = $this->settings['tickettype']; }
	else
	{
	    $tickettype = $this->default['tickettype'];
	}
         ?>
            <input type="text" size="40" name="kayako-settings[tickettype]" value="<?php echo $tickettype; ?>" placeholder="What type of Query ?" />
	<?php
     }


     /* Labeling of Ticket priority
      * It returns a textbox which asks for the input for the label ticketpriority
      */
     public function _settings_field_ticketpriority() {
        if ( !empty($this->settings) && array_key_exists('ticketpriority', $this->settings) && !empty($this->settings['ticketpriority']) &&  $this->settings['ticketpriority']<> '0')
	{
	    $ticketpriority = $this->settings['ticketpriority']; }
	else
	{
	    $ticketpriority = $this->default['ticketpriority'];
	}
	?>
            <input type="text" size="40" name="kayako-settings[ticketpriority]" value="<?php echo $ticketpriority; ?>" placeholder="What's the priority" />
	<?php
     }


     /* Labeling of department
      * It returns a textbox which asks for the input for the label department
      */
    public function _settings_field_department() {
        if ( !empty($this->settings) && array_key_exists('department', $this->settings) && !empty($this->settings['department']) &&  $this->settings['department']<> '0')
	{
	    $department = $this->settings['department'];
	}
        else
	{
	    $department = $this->default['department'];
	}
        ?>
            <input type="text" size="40" name="kayako-settings[department]" value="<?php echo $department; ?>" placeholder="Select Department" />
	<?php
     }


     /* Labeling for Ticket Subject
      * It returns a textbox which asks for the input for the label subject
      */
    public function _settings_field_subject() {
        if ( !empty($this->settings) && array_key_exists('subject', $this->settings) && !empty($this->settings['subject']) &&  $this->settings['subject']<> '0') {
	    $subject = $this->settings['subject'];

	}
        else
	{
	    $subject = $this->default['subject'];
        }
        ?>
            <input type="text" size="40" name="kayako-settings[subject]" value="<?php echo $subject; ?>" placeholder="Enter Subject" />
	<?php
    }


     /* Labeling for contents
      * It returns a textbox which asks for the input for the label ticket contents
      */
     public function _settings_field_contents() {
        if ( !empty($this->settings) && array_key_exists('contents', $this->settings) && !empty($this->settings['contents']) &&  $this->settings['contents']<> '0')
	{
	    $contents = $this->settings['contents']; }
	else
	{
	    $contents = $this->default['contents'];
	}
         ?>
            <input type="text" size="40" name="kayako-settings[contents]" value="<?php echo $contents; ?>" placeholder="Please enter your query" />
	<?php
    }


     /* Kayako Dashboard Widget
      * It displays a ticket view list on the frontend of kayako widget setup
      */
     public function _kayako_dashboard_widget_setup() {
	if ($this->settings)
	{
	    wp_add_dashboard_widget('kayako-dashboard-viewticket-widget', __('Kayako Helpdesk Tickets', 'kayako'), array(&$this, '_admin_view_tickets_bar'));
	}

    }


    /* Livechat Form Contents
     * A form for submission of LiveChat Tag Generator
     */
    public function _livechat_form_contents() {
        ?>
        <div class="wrap">
            <?php echo "<h2>" . __('Kayako fusion live chat setup', 'setup') . "</h2>"; ?>
            <form method="post" action="options.php">
                <?php wp_nonce_field('update-options'); ?>
                <?php settings_fields('kayako-livechat_tag-settings'); ?>
                <?php do_settings_sections('kayako-livechat_tag-settings'); ?>
                <p class="submit">
                    <input name="Submit" type="submit" class="button-primary" value="<?php esc_attr_e('Save Tag', 'kayako'); ?>" />
                </p>
            </form>
        </div>
        <?php
    }


    /* Live Chat Textarea box
     * It displays a textarea box in form
     */
    public function _livechat_tag_textarea() {
        if ( $this->livechat_tag_settings )
	{
            if ( array_key_exists('kayako_tag', $this->livechat_tag_settings) ) {
                $kayako_tag = htmlspecialchars($this->livechat_tag_settings['kayako_tag']);
            }
        }
        ?>
        <textarea rows="10" cols="100" name="kayako-livechat_tag-settings[kayako_tag]" id="kayako-livechat_tag-settings[kayako_tag]"><?php echo $kayako_tag; ?></textarea>
        <?php
    }


    /*
     * It displays the Live Chat tag description Heading
     */
    public function livechat_tag_settings_description() {
        _e('The Kayako Livechat places a convenient Chat Icon on your pages that allow your visitors to initiate a chat !', 'kayako');
    }


    /*
     *  It usually submits the request for comments to be converted to kayako tickets
     */
    public function _process_formData() {

        //Convert comment to ticket conversion
        if (isset($_POST['process_convert_comment_form']) && $_POST['process_convert_comment_form'] == 'kayako_comment_ticket_convert')
	{

            $rand_check_varStore = $_POST['rand_check'];

	    if ( isset($_REQUEST['c']) )
             {
                $comment_id = base64_decode($_REQUEST['c']);
                $comment = get_comment($comment_id);
             }

             if ( $_POST['departmentID'] && $_POST['ticketpriorityID'] && $_POST['tickettypeID'] )
             {
                $departmentID = $_REQUEST['departmentID'];
                $ticketpriorityID = $_REQUEST['ticketpriorityID'];
                $tickettypeID = $_REQUEST['tickettypeID'];
             }

            if ( empty($departmentID) || empty($ticketpriorityID) || empty($tickettypeID) || empty($_REQUEST['c']) )
            {
                 wp_die( __('Sorry ! the fields mentioned over there(Department, Ticket priority, Ticket Type) are mandatory ! ') );

	    }

             // Convert a comment into a ticket....................
             $_emailAddress = $comment->comment_author_email;
             $_commentContent = strip_tags($comment->comment_content);
             $_fullName = $comment->comment_author;

             $post = get_post($comment->comment_post_ID);
             $_subject = $post->post_title;

            //Create a ticket in kayako

	    if ( $rand_check_varStore == $_SESSION['var'] )
	    {
		$location = admin_url()."edit-comments.php";
		wp_redirect($location);
	    }

	    $_successful_ticket_creation = $this->kayakoAPI->CreateTicketRESTAPI($_fullName, $_emailAddress, $_commentContent, $_subject, $departmentID, 1, $ticketpriorityID, $tickettypeID);


            $commentStoreData = array();
            $commentStoreData['displayid'] = $_successful_ticket_creation['result']['ticket']['0']['displayid'];
            $commentStoreData['kayako_url'] = $this->settings['kayako_url'];
            $commentStoreData['kayako_key'] = $this->settings['kayako_key'];

	    if ( stristr($_successful_ticket_creation['errorReceived'],  "SMTP"))
            {
                $_SESSION['var'] = $rand_check_varStore;
		wp_die( __('The SMTP Details are not configured properly ! The issue occured, please check your helpdesk settings. However your ticket has created successfuly ! Not able to print ticket details !') );

            }
	    elseif ($_successful_ticket_creation['errorReceived'] || $_successful_ticket_creation['errorMessage'])
	    {

		$_SESSION['var'] = $rand_check_varStore;
		wp_die( __('There is some issue occured ! Not able to create ticket ! Please try again !') );

	    }
             else
            {
		$ticketID = $_successful_ticket_creation['result']['ticket']['0']['displayid'];
                $get_ticket_URL = $this->kayakoAPI->_ticket_url($ticketID);
                update_comment_meta($comment->comment_ID, 'kayako-ticket', $commentStoreData);
		$_SESSION['var'] = $rand_check_varStore;
		$location = admin_url()."edit-comments.php?tid=".$ticketID."&tURL=".urlencode($get_ticket_URL);
		wp_redirect($location);

                //$this->_set_kayako_notice('comment_to_ticket', __('Your comment has been converted to ticket successfully ! To View your ticket Click here  <a href="'.$get_ticket_URL.'" target = "_blank">#'.$ticketID.'</a>'), 'alert');

	    }
        }

    }


    /* It returns the search results for kayako tickets for kayako API
     *
     */
    public function _return_current_user_details() {
	 //get Current user email address

       if ( $this->user )
       {
	    $user_emailAddress = $this->user->user_email;
       }

       //Search for the above user in helpdesk

       $_getUserSearchResults = $this->kayakoAPI->_getUserSearchEmailAddress($user_emailAddress);

       return $_getUserSearchResults;
    }


    /*
     *  It returns a Current Logged in user 'User ID'
     */
     public function get_current_UserID() {
	$userDetails = $this->_return_current_user_details();

	return $userDetails['result']['user']['0']['id'];

    }


    /* Kayako ticket view list dashboard widget
     * It displays all the tickets of the user whosoever is currently logged in the system
     */
    public function _admin_view_tickets_bar() {

      $_getUserSearchResults =  $this->_return_current_user_details();

       if ( is_array($_getUserSearchResults) && isset($_getUserSearchResults['result']['user']) )
       {
           $_userExistsHastrue = true;

           //Perform a ticket search based on email address
           $result = $this->kayakoAPI->_getTicketList($this->user->user_email);

       }

       $departmentID = (isset($_GET['department_type']) && !empty($_GET['department_type'])) ? base64_decode($_GET['department_type']) : $result['result']['ticket']['0']['departmentid'];

       ?>
        <div>
            <form name="testform" id="test">
            <table>
		 <tr>
                    <td>Choose Department :
                    </td>
                    <td> <select name="department_type" onchange="this.form.submit()">
                        <option value ="<?php echo base64_encode("no-dept"); ?>">- Select Department -</option>
                     <?php foreach($this->getDepartments() as $key => $val)
			    {
				if ( $val['module'] == 'tickets' && $val['type'] == 'public'  )
					      { ?>
						<option value="<?php echo base64_encode($val['id']); ?>" <?php selected( $val['id'] == $departmentID ); ?>> - <?php echo $val['title']; ?> - </option>
					<?php }
			    }  ?>
                     </select>
                    </td>
                </tr>
            </table>
        </form>
        </div>
	<div>
        <table id="tickets" border="0" width ="100%" cellpadding ="2" cellspacing ="4" >
        <tr>
            <th class="white">Ticket ID</th>
            <th class="white">Last Activity</th>
            <th class="white">Last Replier</th>
            <th class="white">Replies</th>
            <th class="white">Department</th>
            <th class="white">Priority</th>
            <th class="white">Ticket Status</th>
        </tr>
        <?php
       $getAllDepartmentIDS = array();
       if (!empty($result['result']['ticket']))
           {
	    foreach ($result['result'] as $key => $getvalue)
	    {

              foreach ($getvalue as $key2 => $ticketvals)
              {

		  $getAllDepartmentIDS[] = $ticketvals['departmentid'];
                  if( $ticketvals['departmentid'] == $departmentID && $ticketvals['departmentid'] != '0' )
                   {

                    $_statusProperties = $this->getStatusTitle($ticketvals['statusid']);
                    $_priorityProperties = $this->getPriorityTitle($ticketvals['priorityid']);

                    //Get URL
                    if ( array_key_exists("posts", $ticketvals) )
                    {
                        $_getTicketID = $ticketvals['posts']['0']['post']['0']['ticketid'];
                    }

                     if ( substr($this->settings['kayako_url'], -1, 1) == '?')
                          $baseUrl = substr($this->settings['kayako_url'], 0, -14);
                          $baseUrl.= "index.php?/Default/Tickets/Ticket/View/".$_getTicketID;

                 ?>
                <tr>
                    <td class="white"><a class ="post_reply" href="javascript:void();" kayako_post_reply="<?php echo $_getTicketID; ?>"><?php echo $ticketvals['displayid']; ?></a></td>
                    <td class="white"><?php echo date('M d, Y h:i A',$ticketvals['lastactivity']); ?></td>
                    <td class="white"><?php echo $ticketvals['lastreplier']; ?></td>
                    <td class="white"><?php echo $ticketvals['replies']; ?></td>
                    <td class="white"><?php echo $this->getDepartmentTitle($ticketvals['departmentid']); ?></td>
                    <td style="background-color: <?php echo $_priorityProperties['bgcolorcode']; ?>"> <span style="color:<?php echo $_priorityProperties['frcolorcode']; ?>"><?php echo $_priorityProperties['title'] ?></span></td>
                    <td style="background-color: <?php echo $_statusProperties['statusbgcolor']; ?>"> <span style="color:<?php echo $_statusProperties['statuscolor']; ?>"><?php echo $_statusProperties['title'] ?></span></td>
                </tr>


        <?php     }
              }
           }
        }
        if ( !in_array($departmentID, $getAllDepartmentIDS) )
             {?>
                <tr>
                    <td style ="background-color:#ad3632; color:white; font-weight:bold;" colspan ="7" align ="center">Sorry!, No Tickets found for the selected department </td>
                </tr>
       <?php } ?>

    </table>
        </div>
		<div id="popupContact">
		    <a id="popupContactClose" class="pointer">x</a>
		    <div id="placeTicketInfoContainer">

		    </div>
		</div>
	<div id="backgroundPopup"></div>
       <?php
    }


    /* Kayako getStatusTitle
     * Function which returns the Ticketstatus properties
     */
    public function getStatusTitle($status_id) {
        $statuses = $this->getTicketStatuses();
        $ticket_statusTitle = array();

        if ( !empty($status_id) )
        {
            foreach ( $statuses as $key => $val )
            {
               if ( $status_id == $val['id'] )
               {
                   $ticket_statusTitle['title'] = $val['title'];
                   $ticket_statusTitle['statusbgcolor'] = $val['statusbgcolor'];
                   $ticket_statusTitle['statuscolor'] = $val['statuscolor'];
               }
            }
        }
        return $ticket_statusTitle;
    }


    /* Kayako getPriorityTitle
     * Function which returns the Ticketpriority properties
     */
    public function getPriorityTitle($priority_id) {
        $priorities = $this->getTicketPriority();
        $priority_title = array();

        if ( !empty($priority_id) )
        {
            foreach ( $priorities as $key => $val )
            {
               if ( $priority_id == $val['id'] )
               {
                   $priority_title['title'] = $val['title'];
                   $priority_title['frcolorcode'] = $val['frcolorcode'];
                   $priority_title['bgcolorcode'] = $val['bgcolorcode'];
               }
            }
        }
        return $priority_title;
    }


    /* Kayako getDepartmentTitle
     * Function which returns the department properties
     */
    public function getDepartmentTitle($departmentID) {
        $departments = $this->getDepartments();

        if (!empty($departments) )
        {
            foreach ( $departments as $key => $val )
            {
               if ( $departmentID == $val['id'] )
               {
                   $department_title = $val['title'];
               }
            }
        }
        return $department_title;
    }


    /*
     * Function which sets the kayako_notice which displays warnings or errors
     */
    private function _set_kayako_notice($context, $text, $type = 'note') {
        if ( isset($this->notices[$context . '_' . $type]) )
            $this->notices[$context . '_' . $type][] = $text;
        else
            $this->notices[$context . '_' . $type] = array($text);
    }


    /* Function which
     * places the notices on wherever you required just pass the content type
     */
    private function _print_notices($context) {
        echo '<div>';
        foreach (array('note', 'confirm', 'alert') as $type) {
            if (isset($this->notices[$context . '_' . $type])) {
                $notices = $this->notices[$context . '_' . $type];
                foreach ($notices as $notice)
                    ?>
                <div id="message" class="updated">
                    <p><?php echo $notice; ?></p>
                </div>
                <?php
            }
        }
        echo '</div>';
    }


    /* Function which adds the a link over comment section
     * Add the Row action to over comments
     */
    public function _add_comment_row_actions($actions, $comment) {

        $_returnDepartments = $this->getDepartments();
        $_returnTicketTypes = $this->getTicketTypes();
	$_returnTicketPriority = $this->getTicketPriority();
        $commentMetaData = get_comment_meta($comment->comment_ID, 'kayako-ticket', true);


        $_checkCondition = (isset($commentMetaData) && isset($commentMetaData['kayako_key']) == $this->settings['kayako_key']) ? 'no': 'yes';
        //echo "cond=".$cond;

        if ($comment->comment_type != 'pingback' && !$commentMetaData || ( isset($_checkCondition) && $_checkCondition == 'yes'))
	{
            if ( $comment->comment_ID <> '1')
            {
                $actions['kayako'] = '<a  href="admin.php?page=kayako-support&action=convert_comments&c='.base64_encode($comment->comment_ID).'">Convert Comment to Kayako Ticket</a>';
            }

        }

	return $actions;
    }


    /*
     * Adds an extra column to the comments table with the "kayako" key,"Kayako #Ticket-ID" as the caption.
     */
    public function _add_comments_columns_filter( $columns ) {

		$columns['kayako'] = 'Kayako #Ticket-ID';

		return $columns;
    }


    /*  Column Action on ticket creation
     *
     */
    public function _add_comments_columns_action( $column ) {
		global $comment;
		if ( $column == 'kayako' ) {
			$_mainData = get_comment_meta( $comment->comment_ID, 'kayako-ticket', true );

			//Get ticket ID from ticket_display_ID
			if ( $_mainData )
			{
			   $_displayID = $_mainData['displayid'];
                           $_check_kayako_key = trim($_mainData['kayako_key']);
                        }

			//get ticket url
			$get_ticket_URL = $this->kayakoAPI->_ticket_url($_displayID);

                        // Make sure it's valid before printing.

                        if ( $comment->comment_ID <> '1') {
                            if ( $comment->comment_type != 'pingback' && isset($_displayID) && ($_check_kayako_key == $this->settings['kayako_key']))
                            {
                                echo '<a target="_blank" class="kayako_display_ticket_id" href="'. $get_ticket_URL . '">#' . $_displayID . '</a><div class="log"></div>';
                            }
                            else if( isset($_mainData['errorReceived']) )
                            {
                                echo '<a target="_blank" class="kayako_display_ticket_id">#No data display</a><div class="log"></div>';
                            }
                        }

		}

    }


    /*
     *  It returns the ticketPostDiv which displays through ajax call
     */
    public function ticketPostDiv()
    {
         if ( isset($_REQUEST['ticketID']) && is_numeric($_REQUEST['ticketID']) ) {
            $ticketID = $_REQUEST['ticketID'];
            $result = $this->getTicketInfoPost($ticketID);
            $statusTitle = $this->getStatusTitle($result['result']['ticket']['0']['statusid']);
            $priorityTitle = $this->getPriorityTitle($result['result']['ticket']['0']['priorityid']);

	    $creationTime = date('M d, Y h:i A', $result['result']['ticket']['0']['creationtime']);
	    $lastActivity  = date('M d, Y h:i A', $result['result']['ticket']['0']['lastactivity']);
	    $ownerStaffname = $result['result']['ticket']['0']['ownerstaffname'];
	    $var_ownerName = isset($ownerStaffname) && !empty($ownerStaffname) ? $ownerStaffname : "Unassigned";

	    $_returnTicketPriority = $this->getTicketPriority();
	    $_returnTicketStatuses = $this->getTicketStatuses();

	    $_GetTicketListContainer = $this->kayakoAPI->getTicketProperties('/Tickets/TicketPost/ListAll/'.$ticketID.'/');


	   }
	$html = '<form class ="submit_ticket_properties">
                    <table border="0" width = "100%" cellpadding = "3" cellspacing ="3">
                    <span id="put_display_message" align="center"></span>
                    <tr>
			<td class="ky_view_ticket">View Ticket : </td>
			<td class ="ky_ticket_display">' .$result['result']['ticket']['0']['displayid'] . '</td>
		    </tr>
		    <tr>
		     <td class="ky_view_ticket">Created On : </td>
		     <td class ="ky_red_text">' . $creationTime . '</td>
		     <td class="ky_view_ticket">LastActivity : </td>
		     <td class ="ky_red_text">' . $lastActivity . '</td>
	            </tr>
                    <tr>
                        <td colspan="4" class="normal_text">Subject : '.$result['result']['ticket']['0']['subject'] . '<input type="hidden" id="place_ticket_ID" name="post_ticketID"/></td>
                    </tr>
		    <tr style="background-color:#FFFFFF; border="display:none;">
                        <td align="left" colspan="4">
			    <input type="submit" value="Update" class="kayako-submit2 button-primary"/>
			    <div class="kayako_loader_ticketSubmit" style="display: none"></div>
			    <input type="button" value="Reply" id="Reply" class="button-primary"/>


			</td>
                    </tr>
		    <tr>
		    <td colspan="4">
			    <table border="0" width ="100%" cellpadding="2" cellspacing="3" style="background-color:#8bb467;">
			    <tr>
				<td class="ky_green_color">Department</td>
				<td class="ky_green_color">Owner</td>
				<td class="ky_green_color">Status</td>
				<td class="ky_green_color">Priority</td>
			    </tr>
			    <tr>
				<td class="ky_green_color">'. $this->getDepartmentTitle($result['result']['ticket']['0']['departmentid']) . '</td>
				<td class="ky_green_color">'. $var_ownerName . '</td>
				<td class="ky_green_color"><select name="ticketstatusID" id="ticketstatusID"  class="normal_text">';
				    if (!$_returnTicketStatuses['errorMessage']) {
					foreach( $_returnTicketStatuses as $key => $val)
						{
						    if($val['type'] == 'public')
						    {
							$html.='<option value="'.$val['id'].'" '; if($result['result']['ticket']['0']['statusid'] == $val['id']) { $html.='selected=selected'; } $html.='>'.$val['title'].'</option>';
						    }
						}

				    $html.=' </select>'; } else { echo "<div class = 'ky_small_text'>No data found</div>"; } $html.='</td>
				 <td class="ky_green_color"><select name="ticketPriorityID" id="ticketPriorityID"  class="normal_text">';
					if (!$_returnTicketPriority['errorMessage']) {
					foreach( $_returnTicketPriority as $key => $val)
						{
						    if($val['type'] == 'public')
						    {
							$html.='<option value="'.$val['id'].'" '; if($result['result']['ticket']['0']['priorityid'] == $val['id']) { $html.='selected=selected'; } $html.='>'.$val['title'].'</option>';
						    }
						}

				    $html.=' </select> '; } else { echo "<div class = 'ky_small_text'>No data found</div>"; } $html.='</td>

			    </tr>
			    </table>
		    </td>
		    </tr>
		    </table>
		    </form>
		    <div id="click" style="display:none;">
			<form class="form_reply_content">
			<table border="0" width ="100%" cellpading="0" cellspacing="0">
			<tr>
			    <td valign="top" class="normal_text">Post Reply</td>
			    <td><textarea name="replyTicketContent" cols="55" rows="8" class="large-text"></textarea></div></td>
			</tr>
			<tr>
			    <td align="left"><input type="submit" value="Post" class="kayako-submit button-primary"/><div class="kayako-loader" style="display: none"></div></td>
			</tr>
			</table>
			</form>
		    </div>
		    <div>
		    <table border="0" cellpadding="0" cellspacing="0" width="100%">';
			foreach($_GetTicketListContainer['result']['post'] as $key=> $value)
			{
			    $html.='<tr>
				    <td class="ky_display_text" colspan="2">Posted On : <a id="displayTicketData" kayako-display-id = ' .$_GetTicketListContainer['result']['post'][$key]['ticketpostid'] . ' href="javascript:void" >'. date('M d, Y h:i A',$_GetTicketListContainer['result']['post'][$key]['dateline']).' </a> BY '. $_GetTicketListContainer['result']['post'][$key]['fullname'].'</span> </td>
				    </tr>
				    <tr>
				    <td colspan="2" id="kayako-display-id'. $_GetTicketListContainer['result']['post'][$key]['ticketpostid'] . '" style="display:none;" class="post_reply_divdisplay">' .$_GetTicketListContainer['result']['post'][$key]['contents'] . '</td>
				    </tr>';

			}

		    $html.='</table>
			</div>';

	$response = array('status' => 200, 'divcontainer' => $html);

        // Return the response JSON
        echo json_encode($response);
        die();

    }


    /*
     *  It executes the Ajax request for the ticket post
     */
    public function post_reply_action()
    {

	if ( isset($_REQUEST['ticketid']) && is_numeric($_REQUEST['ticketid']) && isset($_REQUEST['post_contents']) && !empty($_REQUEST['post_contents']))
	{
	    $ticketID = $_REQUEST['ticketid'];
	    $post_contents = $_REQUEST['post_contents'];
	}
	$current_userID = $this->get_current_UserID();
	$createReply = $this->kayakoAPI->CreateTicketPost($ticketID, $post_contents, $current_userID);

	if ( $createReply['errorReceived'] || $createReply['errorMessage'] )
	{
		$response = array('status' => 401, 'statusMessage' => "Bad Request");
	}
	else
	{
		$response = array('status' => 200, 'statusMessage' => $createReply);
	}
	// Return the response JSON
        echo json_encode($response);
        die();
    }


    /*
     *  It executes the Ajax request to update the ticket properties
     */
    public function update_ticket_properties()
    {

	if ( isset($_REQUEST['ticketid']) && is_numeric($_REQUEST['ticketstatusid']) && isset($_REQUEST['ticketPriorityid']) && is_numeric($_REQUEST['ticketPriorityid']) )
	{
	    $ticketID = $_REQUEST['ticketid'];
	    $ticketstatusID = $_REQUEST['ticketstatusid'];
	    $ticketPriorityID = $_REQUEST['ticketPriorityid'];
	}

	$sendParameters = array();

	$sendParameters["statusid"] = $ticketstatusID;
	$sendParameters["priorityid"] = $ticketPriorityID;
	$sendParameters["ticketid"] = $ticketID;


	//Update the ticket status too............
	$updateTicket = $this->kayakoAPI->UpdateTicket($sendParameters);

	if ( $updateTicket['errorReceived'] || $updateTicket['errorMessage'] )
	{
	    $response = array('status' => 401, 'statusMessage' => "Bad Request");
	}
	else
	{
	    $response = array('status' => 200, 'statusMessage' => "success");
	}
	// Return the response JSON
        echo json_encode($response);
        die();
     }


    /*
     * Get ticket Info Post Container
     */
    public function getTicketInfoPost($_ticketID)
    {
        $_GetTicketContainer = $this->kayakoAPI->getTicketProperties('/Tickets/Ticket/'.$_ticketID.'/');

	return $_GetTicketContainer;

    }


    /*
     *  It returns all the departments of kayako helpdesk
     */
    public function getDepartments()
    {
	$_returnDepartments = $this->kayakoAPI->getTicketProperties('/Base/Department/');
	if ( isset($_returnDepartments['errorMessage']) )
	{
	     $this->storeErrorMessage = $_returnDepartments['errorMessage'];
	}
	else
	{
	    $_returnDepartments = $_returnDepartments['result']['department'];
	}

	return $_returnDepartments;

    }


    /*
     * It returns all the ticket statuses
     */
    public function getTicketStatuses()
    {
	$_getStatuses = $this->kayakoAPI->getTicketProperties('/Tickets/TicketStatus/');

        if ( isset($_getStatuses['errorMessage']) )
	{
	    $this->storeErrorMessage = $_getStatuses['errorMessage'];
	}
	else
	{
	    $_getStatuses = $_getStatuses['result']['ticketstatus'];
	}

	return $_getStatuses;
    }


    /*
     * It returns the list of ticket priorities available in kayako helpdesk
     */
    public function getTicketPriority()
    {
	$_getPriorities = $this->kayakoAPI->getTicketProperties('/Tickets/TicketPriority/');

	if ( isset($_getPriorities['errorMessage']) ) {

	    $this->storeErrorMessage = $_getPriorities['errorMessage'];
	}
	else
	{
	    $_getPriorities = $_getPriorities['result']['ticketpriority'];
	}

	return $_getPriorities;
    }


    /*
     * It returns the list of ticket types
     */
    public function getTicketTypes()
    {
	$_getTicketTypes = $this->kayakoAPI->getTicketProperties('/Tickets/TicketType');

	if ( isset($_getTicketTypes['errorMessage']) ) {
	     $this->storeErrorMessage = $_getTicketTypes['errorMessage'];
	}
	else
	{
	    $_getTicketTypes = $_getTicketTypes['result']['tickettype'];
	}


	return $_getTicketTypes;
    }


    /*
     *  Kayako Live chat tag code gathering
     */
    public function kayako_livechat_tag_code()
    {
	echo stripslashes($this->setting_liveChat['kayako_tag']);
    }


    /*
     * Kayako Live chat Note setting description
     */
    public function _kayako_livechat_note()
    {
	 _e('After setting your options, include the following template snippet anywhere in your theme to display the LiveChat Tag Icon: <p><code><</code><code>?php if(function_exists(\'the_kayako_liveChatTag\')) the_kayako_liveChatTag(); ?</code><code>></code></p>', 'kayako');
    }


    /*
     *  Kayako Ticket Form Display description
     */
    public function _kayako_ticket_form()
    {
	_e('The Kayako Ticket form is a way for users to submit their requests. This settings displays the form wherever you want in your wordpress site !</br>
	    Add <span class="tagcolor">[kayako_helpdesk]</span> short code in your post to display the kayako ticket submission form ');

    }


    /*
     * Kayako Ticket Form Display Section
     */
    public function _display_contact_ticket_form()
     {
	 $_returnDepartments = $this->getDepartments();
	 $_returnTicketTypes = $this->getTicketTypes();
	 $_returnTicketPriority = $this->getTicketPriority();

	 if ( is_wp_error($this->getErrorMsg) )
	 {
	     if ( is_wp_error($this->getErrorMsg) )
		echo  $this->getErrorMsg->get_error_message();

	     if ( !empty($this->ticketInfoContainer) && isset($this->ticketInfoContainer['result']['ticket']['0']['displayid']) )
	     {
	    ?>
	     <div id="ky_ticket">
		<h2>General Information</h2>
		<div class="basic_class">Ticket ID : #<?php echo $this->ticketInfoContainer['result']['ticket']['0']['displayid']; ?></div>
		<div class="basic_class">Fullname : <?php echo $this->ticketInfoContainer['result']['ticket']['0']['fullname']; ?></div>
		<div class="basic_class">Email : <?php echo $this->ticketInfoContainer['result']['ticket']['0']['email']; ?></div>
		<div>&nbsp;</div>
		<h2>Subject : <?php echo $this->ticketInfoContainer['result']['ticket']['0']['subject']; ?></h2>
		<div class="basic_class"><?php echo $this->ticketInfoContainer['result']['ticket']['0']['posts']['0']['post']['0']['contents']; ?></div>
	    </div>
	    <?php
	     }

	 }
	 else
	 { ?>
	 <div id="ky_ticket">
             <form method="post">
                <input type="hidden" name="ky_ticket_submission" value="kayako_login_details"/>
		<input type="hidden" name="rand_check_frontend" id ="rand_check_frontend" value="<?php echo mt_rand(); ?>"/>
                <?php if ($this->storeErrorMessage) $this->errorMessagePrint($_returnDepartments['errorMessage']); ?>
                <h1><?php echo $this->settings['form_title']; ?></h1>
                <div class ="label-class"><?php echo $this->settings['department']; ?></div>
                <p><?php if (!$_returnDepartments['errorMessage']) { ?>
			    <select name="ky_department"  id="ky_department" class="default_select_box">
                    <?php foreach( $_returnDepartments as $key => $val)
					{ if ( $val['module'] == 'tickets' && $val['type'] == 'public' )
					    { ?>
						<option value="<?php echo $val['id']; ?>"> <?php echo $val['title']; ?></option>
					<?php }
					}   ?>
			    </select> <?php } else { echo "<div class='ky_small_text'>No data found</div>"; } ?>
                </p>

                <div class="label-class"><?php echo $this->settings['tickettype']; ?></div>
                <p><?php if (!$_returnTicketTypes['errorMessage']) { ?>   <select name="ky_tickettype" class="default_select_box">
			<?php foreach( $_returnTicketTypes as $key => $val)
					    {
                                                if($val['type'] == 'public') {
                                                ?>
						    <option value="<?php echo $val['id']; ?>"> <?php echo $val['title']; ?></option>
					    <?php
                                                }
					    }  ?>

		    </select> <?php } else { echo "<div class = 'ky_small_text'>No data found</div>"; } ?></p>

                    <div class="label-class"><?php echo $this->settings['ticketpriority']; ?></div>
                <p><?php if (!$_returnTicketPriority['errorMessage']) { ?><select name="ky_ticketpriority" id="ky_ticketpriority" class="default_select_box" >
                    <?php foreach( $_returnTicketPriority as $key => $val)
					{
                                            if($val['type'] == 'public') {
                                            ?>
						<option value="<?php echo $val['id']; ?>"> <?php echo $val['title']; ?></option>
					<?php
                                            }
					}  ?>

                  </select><?php } else { echo "<div class = 'ky_small_text'>No data found</div>"; } ?></p>


                <p class="kayako_fullname"><label for="kayako_fullname"><?php echo $this->settings['fullname']; ?></label> <span class="required">*</span>
                <input type="text" name="ky_fullname" id="ky_fullname" value=""  size="22" <?php echo "aria-required='true'"; ?> /></p>
                <p class="kayako_email"><label for="kayako_email"><?php echo $this->settings['email']; ?></label><span class="required">*</span>
                <input type="text" name="ky_email" id="ky_email" value=""  size="22" <?php echo "aria-required='true'"; ?> /></p>

                <p class="kayako_subject"><label for="kayako_subject"><?php echo $this->settings['subject']; ?></label> <span class="required">*</span>
                <input type="text" name="ky_subject" id="ky_subject" value=""  size="22" <?php echo "aria-required='true'"; ?> /></p>
                <p class="kayako_contents"><label for="kayako_contents"><?php echo $this->settings['contents']; ?></label><span class="required" style="left:95%;">*</span>
                <textarea rows="7" cols="500" name="ky_contents" id="ky_contents"></textarea></p>

                <input name="submit_ticket" type="submit" id="submit"  value="<?php esc_attr_e('Submit Ticket'); ?>" class="form-submit" />

        </form>
        </div>

	 <?php }
	 ?>

	<?php
    }


    /* Main ticket submission function for frontend users
     * It creates a ticket on successfull submission
     * else throws related errors.
     */
    public function AccessFormProcess()
     {

	if ( $_POST['submit_ticket'] && $_POST['rand_check_frontend'] )
         {
	    $ky_fullname = ( isset($_POST['ky_fullname']) )  ? trim(strip_tags($_POST['ky_fullname'])) : null;
            $ky_subject = ( isset($_POST['ky_subject']) )   ? trim($_POST['ky_subject']) : null;
            $ky_email = ( isset($_POST['ky_email']) )     ? trim($_POST['ky_email']) : null;
            $ky_ticketpriority = ( isset($_POST['ky_ticketpriority']) ) ? trim($_POST['ky_ticketpriority']) : null;
            $ky_tickettype = ( isset($_POST['ky_tickettype']) )  ? trim(strip_tags($_POST['ky_tickettype'])) : null;
            $ky_department = ( isset($_POST['ky_department']) )   ? trim($_POST['ky_department']) : null;
            $ky_contents = ( isset($_POST['ky_contents']) )     ? trim($_POST['ky_contents']) : null;
	    $rand_check_frontend = ( isset($_POST['rand_check_frontend']) )   ? trim($_POST['rand_check_frontend']) : null;

	    if ( '' == $ky_fullname || '' == $ky_subject || '' == $ky_email || '' == $ky_ticketpriority || '' == $ky_tickettype || '' == $ky_department )
                _default_wp_die_handler( __('<strong>ERROR</strong>: please fill all the required fields which are mandatory ! Unable to Proceed further !') );
            elseif ( !is_email($ky_email) )
                wp_die( __('<strong>ERROR</strong>: please enter a valid email address.') );
            elseif ( '' == $ky_contents )
                wp_die( __('<strong>ERROR</strong>: please type a message information') );


	    if ( $_SESSION['storeVar'] == $rand_check_frontend)
	    {
		return false;
	    }

	    $create_ticket = $this->kayakoAPI->CreateTicketRESTAPI($ky_fullname, $ky_email, $ky_contents, $ky_subject, $ky_department, 1, $ky_ticketpriority, $ky_tickettype);

	    $ticketID = $create_ticket['result']['ticket']['0']['displayid'];
	    $get_ticket_URL = $this->kayakoAPI->_ticket_url($ticketID);


	    if ( $create_ticket['errorReceived'] )
	    {
		    if ( stristr($create_ticket['errorReceived'], "SMTP") )
		    {
			 $this->getErrorMsg = new WP_Error('kayako_ticket_creation', __("<div class='frontend_errormessage'><strong>Error : </strong>There is an issue with SMTP configuration. Please check your helpdesk details ! </div>"));
		    }
		    else
		    {
			 $this->getErrorMsg = new WP_Error('kayako_ticket_creation', __("<div class='frontend_errormessage'><strong>Error : </strong>There is some technical issue occured, not able to create ticket ! </div>"));
		    }

		    $_SESSION['storeVar'] = $rand_check_frontend;
	    }
	    else
	    {
		    $this->getErrorMsg = new WP_Error('kayako_ticket_creation', __("<div class='frontend_successmessage'>Your Ticket has been created successfully ! Your generated ticket ID is <a href= '". $get_ticket_URL."' target = '_blank'>#" . $ticketID . " </a></div>"));
		    $this->ticketInfoContainer = $create_ticket;
		    $_SESSION['storeVar'] = $rand_check_frontend;
	    }



          }
    }


    /*
     *  It prints the errorMessage like admin notices if connectivity is not proper
     */
    public function errorMessagePrint($_code)
    {
	echo  "<div class='frontend_errormessage'> <strong>ERROR : </strong>". $_code . " ! There must be some issue with the helpdesk connectivity ! Not able to fetch data !</div>";
    }


    /*
     * It includes all the scripts or classes
     */
    public function enqueue_scripts()
     {
        wp_enqueue_style('my-script',  plugins_url('/css/admin.css', __FILE__));
	wp_enqueue_script('my-script', plugins_url('/js/kayako.js', __FILE__), array('jquery'));
	wp_localize_script('my-script', 'myAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' )));
     }


     /*
      *  It displays the comment conversion form
      */
     public function display_comment_conversion_form()
     {
	    $_returnDepartments = $this->getDepartments();
	    $_returnTicketTypes = $this->getTicketTypes();
	    $_returnTicketPriority = $this->getTicketPriority();

	    if ( array_key_exists('department', $this->settings) && !empty($this->settings['department']) &&  $this->settings['department']<> '0' )
	    {
		    $department = $this->settings['department'];
	    }
	    else
	    {
		    $department = $this->default['department'];
	    }


	    if ( array_key_exists('ticketpriority', $this->settings) && !empty($this->settings['ticketpriority']) &&  $this->settings['ticketpriority'] <> '0' )
	    {
		    $ticketpriority = $this->settings['ticketpriority'];

	    }
	    else
	    {
		    $ticketpriority = $this->default['ticketpriority'];
	    }

	    if ( array_key_exists('tickettype', $this->settings) && !empty($this->settings['tickettype']) &&  $this->settings['tickettype'] <> '0' )
	    {
		    $tickettype = $this->settings['tickettype'];

	    }
	    else
	    {
		    $tickettype = $this->default['tickettype'];
	    }


	    if ( !empty($this->settings) && array_key_exists('form_title', $this->settings) && !empty($this->settings['form_title']) &&  $this->settings['form_title'] <> '0' )
	    {
		    $form_value = $this->settings['form_title'];
	    }
	    else
	    {
		    $form_value = $this->default['form_title'];
	    }

            //Comment Data which is selected
            if ( isset($_REQUEST['c']) )
	    {
		$commentData = get_comment(base64_decode($_REQUEST['c']));
	    }
        ?>
	<div class="wrap">
        <div id="icon-edit-comments" class="icon32"><br></div>
        <h2><?php if ( $commentData ) { _e('Convert this comment into kayako ticket', 'kayako'); } else { _e('Convert Comment to kayako ticket', 'kayako'); } ?></h2>
        <div id="post-body" class="metabox-holder columns-2">
        <div id="post-body-content">
        <div id="namediv" class="stuffbox">
	    <h3><label for="name"><?php _e( 'Comment Information' ) ?></label></h3>
		<div class="inside">
		<form method="post" name="comment_conversion_ticket">
		<input type="hidden" name="rand_check" id="rand_check" value="<?php echo mt_rand();?>">
		<div><?php $this->_print_notices('comment_to_ticket'); ?></div>
		<input type="hidden" name="process_convert_comment_form" value="kayako_comment_ticket_convert"/>
		<table class="form-table editcomment">
                    <tbody>
                        <?php if ( $commentData )
				{ ?>
                        <tr valign="top">
                           <td class="first">Comment Data : </td>
                           <td><span>Posted By : </span>
                                <span><?php echo $commentData->comment_author; ?> ( <?php echo strip_tags($commentData->comment_author_email); ?> )</span>
                                <div>Submitted On : <?php echo $commentData->comment_date; ?></div>
                                <div style="font-style:italic"> <?php echo ($commentData->comment_content); ?></div>
                          </td>
                        </tr>
                        <?php	} ?>
                     <tr><td colspan="2"><em>Please select further required information or basic ticket properties mandatory for ticket creation !</em></td></div> </tr>
                            <td class="first"><?php echo $department; ?></td>
                            <td> <?php if (!$_returnDepartments['errorMessage']) { ?>
                                                <select name="departmentID" style="width:150px;">
                                        <?php foreach($this->getDepartments() as $key => $val)
                                                            { if ( $val['module'] == 'tickets' && $val['type'] == 'public' )
                                                                { ?>
                                                                    <option value="<?php echo $val['id']; ?>"> <?php echo $val['title']; ?></option>
                                                            <?php }
                                                            }  ?>
                                                </select><?php } else { echo "<div class = 'ky_small_text'>No data found</div>"; } ?></td>
                    </tr>
                    <tr valign="top">
                            <td class="first">
                            <?php
                                    echo $ticketpriority;
                    ?></td>
                            <td> <?php if (!$_returnTicketTypes['errorMessage']) { ?>
                                                <select name ="ticketpriorityID" style="width:150px;">
                                                    <?php foreach($this->getTicketPriority() as $key => $val)
                                                            {
                                                                if($val['type'] == 'public') {
                                                            ?>
                                                                    <option value="<?php echo $val['id']; ?>"> <?php echo $val['title']; ?></option>
                                                            <?php
                                                                }
                                                            }  ?>

                                                </select><?php } else { echo "<div class = 'ky_small_text'>No data found</div>"; } ?></td>
                    </tr>
                    <tr valign="top">
                            <td class="first">
                            <?php echo $tickettype; ?></td>
                            <td> <?php if (!$_returnTicketPriority['errorMessage']) { ?>
                                                <select name ="tickettypeID" style="width:150px;">
                                                    <?php foreach($this->getTicketTypes() as $key => $val)
                                                            {
                                                                if($val['type'] == 'public') {
                                                                ?>
                                                                    <option value="<?php echo $val['id']; ?>"> <?php echo $val['title']; ?></option>
                                                            <?php
                                                                }
                                                            }  ?>

                                                </select><?php } else { echo "<div class = 'ky_small_text'>No data found</div>"; } ?></td>
                    </tr>

                    <tr valign="top">
                                            <td class="first">
                                                <input type="submit" value="Convert to ticket" class="button-primary"/>
                                            </td>
                                            <td>
                                            <a href="#comments-form" class="cancel button-secondary alignleft" onclick="window.location='edit-comments.php';"><?php _e('Cancel'); ?></a>
                                            </td>
                                        </tr>
                    </tbody>
                    </table></form></br></br>

</div>
</div></div></div></div>
	<?php

     }

}

// Register the Kayako class initialization during WordPress' init action. Globally available through $kayako.

add_action('init', create_function('', 'global $kayako; $kayako = new Kayako();'));

/*
 * LiveChat Tag template tag
 * @global $kayako_support
 *
 */
function the_kayako_liveChatTag() {
	global $kayako;

	if ( $kayako )
	{
	    $kayako->kayako_livechat_tag_code();
	}

} ?>