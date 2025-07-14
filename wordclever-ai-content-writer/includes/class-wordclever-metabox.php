<?php
defined('ABSPATH') || exit;

class WordClever_MetaBox {
    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_metabox']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_ajax_wordclever_generate_content', [__CLASS__, 'generate_content']);
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        add_action('admin_menu', function() {
            remove_submenu_page('wordclever_dashboard', 'wordclever_dashboard');
        });
        add_action('wp_ajax_wordclever_get_used_request', [__CLASS__, 'wordclever_get_used_request']);
        add_action('wp_ajax_wordclever_verify_license', [__CLASS__, 'handle_license_verification']);
        add_action('admin_init', [__CLASS__, 'check_license_and_requests'], 1);
        add_action('admin_init', [__CLASS__, 'update_used_requests_on_load'], 1);

        //for login and registration
        add_action('wp_ajax_wordclever_auth', [__CLASS__, 'handle_auth']);
        add_action('wp_ajax_wordclever_support_tab_form', [__CLASS__, 'wordclever_support_tab_form']);
        add_action('wp_ajax_wordclever_reset_auth_pass', [__CLASS__, 'handle_reset_auth_pass']);
    }

    public static function add_metabox() {
        add_meta_box(
            'wordclever_metabox',
            __('WordClever AI Content Writer', 'wordclever-ai-content-writer'),
            [__CLASS__, 'render_metabox'],
            ['product'],
            'side'
        );
    }

    //for used request

    public static function wordclever_get_used_request() {
        check_ajax_referer('wordclever_nonce', 'security');
        
        $current_user = get_option('wordclever_current_user');
        if (empty($current_user)) {
            wp_send_json_error(['message' => __('User is not logged in.', 'wordclever-ai-content-writer')]);
        }
        
        $response = WordClever_API_Handler::get_user_data_by_username($current_user);
        
        if (is_wp_error($response) || empty($response['user_data'])) {
            wp_send_json_error(['message' => __('Failed to fetch user data.', 'wordclever-ai-content-writer')]);
        }
        
        $used_request = $response['user_data']['used_request'] ?? 0;
        update_option('wordclever_used_request', $used_request);
        
        // Update plan details if available
        if (!empty($response['user_data']['plan_details'])) {
            update_option('wordclever_current_plan', [
                'plan_name' => $response['user_data']['plan_details']['plan_name'],
                'plan_amount' => $response['user_data']['plan_details']['plan_amount'],
                'plan_fields' => $response['user_data']['plan_details']['plan_fields'],
                'plan_sub_head' => $response['user_data']['plan_details']['plan_sub_head'],
                'plan_type' => $response['user_data']['plan_details']['plan_type'],
                'req_count' => $response['user_data']['plan_details']['req_count']
            ]);
        }
        
        wp_send_json_success(['used_request' => $used_request]);
    }
    
    //end

    public static function render_metabox() {
        wp_nonce_field('wordclever_nonce', 'wordclever_nonce_field');

        $current_user = get_option('wordclever_current_user');
        $request_count = get_option('wordclever_request_count');
        $used_request = get_option('wordclever_used_request');
        ?>

          <!-- Username Display -->
          <?php if (!empty($current_user)): ?>
             <div id="wordclever-username" style="margin-bottom: 10px; font-size: 14px; color: #555;">
                 <?php esc_html_e('Logged in as:', 'wordclever-ai-content-writer'); ?> <strong><?php echo esc_html($current_user); ?></strong>
             </div>
         <?php endif; ?>
        
         <!-- Request Information Display -->
         <?php if (!empty($current_user)): ?>
         <div id="wordclever-request-info" style="margin-bottom: 10px; font-size: 14px; color: #555;">
             <p><?php esc_html_e('Free Request Count:', 'wordclever-ai-content-writer'); ?> <strong><?php echo esc_html($request_count); ?></strong></p>
             <p><?php esc_html_e('Used Requests:', 'wordclever-ai-content-writer'); ?> <strong><?php echo esc_html($used_request); ?></strong></p>
             <?php if ($used_request >= $request_count && $request_count > 0): ?>
                 <div class="wordclever-upgrade-plan-btn-wrap mt-3 text-center">
                     <a href="<?php echo esc_attr(WORDCLEVER_MAIN_URL . '/products/wordclever-pro'); ?>" target="_blank" class="plan-card-upgrade-btn"><?php echo esc_html('Upgrade'); ?></a>
                 </div>
             <?php endif; ?>
         </div>
         <?php endif; ?>


        <!-- Metabox Content -->
         <div id="wordclever-ai-box">
              <label for="wordclever-content-type"><?php esc_html_e('Content Type:', 'wordclever-ai-content-writer'); ?></label>
              <select id="wordclever-content-type">
                  <option value="product_description"><?php esc_html_e('Meta title and Description', 'wordclever-ai-content-writer'); ?></option>
              </select>

              <input type="hidden" id="wordclever-generate-license-key" value="<?php echo esc_attr(get_option('wordclever_license_key')); ?>">
          
              <label for="wordclever-keyword"><?php esc_html_e('Keyword:', 'wordclever-ai-content-writer'); ?></label>
              <input type="text" id="wordclever-keyword" placeholder="<?php esc_html_e('Enter a keyword (Max 100 characters)', 'wordclever-ai-content-writer'); ?>" maxlength="100">
          
              <label for="wordclever-tone"><?php esc_html_e('Tone:', 'wordclever-ai-content-writer'); ?></label>
              <select id="wordclever-tone">
                  <option value="formal"><?php esc_html_e('Formal', 'wordclever-ai-content-writer'); ?></option>
                  <option value="informal"><?php esc_html_e('Informal', 'wordclever-ai-content-writer'); ?></option>
                  <option value="professional"><?php esc_html_e('Professional', 'wordclever-ai-content-writer'); ?></option>
                  <option value="creative"><?php esc_html_e('Creative', 'wordclever-ai-content-writer'); ?></option>
              </select>
          
              <label for="wordclever-num-results"><?php esc_html_e('Number of Results:', 'wordclever-ai-content-writer'); ?></label>
              <select id="wordclever-num-results">
                  <?php for ($i = 1; $i <= 3; $i++): ?>
                      <option value="<?php echo esc_attr($i); ?>"><?php echo esc_html($i); ?></option>
                  <?php endfor; ?>
              </select>
          
              <button type="button" id="wordclever-generate-content" <?php echo empty($current_user) ? 'disabled' : ''; ?>>
                  <?php esc_html_e('Generate Content', 'wordclever-ai-content-writer'); ?>
              </button><br><br>
              <strong>Results:</strong>
              <div id="wordclever-results"></div>
          
 
 
             <?php if (empty($current_user)): ?>
                 <div class="wordclever-login-button-parent-box">
                     <button type="button" id="wordclever-login-popup-trigger"><?php esc_html_e('Login / Register', 'wordclever-ai-content-writer'); ?></button>
                 </div>
             <?php endif; ?>
             

            <!-- Login/Register Popup -->
            <div id="wordclever-popup" class="wordclever-popup hidden">
                <div class="wordclever-popup-content">
                    <!-- Login Form -->
                    <div id="wordclever-login-form">
                        <h2><?php esc_html_e('Login', 'wordclever-ai-content-writer'); ?></h2>
                        <input type="text" id="wordclever-login-username" placeholder="<?php esc_html_e('Username', 'wordclever-ai-content-writer'); ?>" required>
                        <div style="position: relative;">
                            <input type="password" id="wordclever-login-password" placeholder="<?php esc_html_e('Password', 'wordclever-ai-content-writer'); ?>" required minlength="6">
                            <span id="toggle-login-password" class="dashicons dashicons-visibility" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;"></span>
                        </div>
                        <!-- Professional Text Below Password Field -->
                        <p style="font-size: 13px; color: #666; margin-top: 5px;">
                            <?php esc_html_e('Forgot your password? Contact admin for assistance.', 'wordclever-ai-content-writer'); ?> 
                            <a href="mailto:support@wpradiant.net" style="color: #0073aa; text-decoration: underline;">
                                <?php esc_html_e('support@wpradiant.net', 'wordclever-ai-content-writer'); ?>
                            </a>
                        </p>
                        <button id="wordclever-login-submit"><?php esc_html_e('Login', 'wordclever-ai-content-writer'); ?></button>
                        <p><button type="button" id="wordclever-toggle-register"><?php esc_html_e('Register here', 'wordclever-ai-content-writer'); ?></button></p>
                    </div>

                    <!-- Register Form -->
                    <div id="wordclever-register-form" class="hidden">
                        <h2><?php esc_html_e('Register', 'wordclever-ai-content-writer'); ?></h2>
                        <input type="text" id="wordclever-register-username" placeholder="<?php esc_html_e('Username', 'wordclever-ai-content-writer'); ?>" required>
                        <input type="email" id="wordclever-register-email" placeholder="<?php esc_html_e('Email', 'wordclever-ai-content-writer'); ?>" required>
                        <div style="position: relative;">
                            <input type="password" id="wordclever-register-password" placeholder="<?php esc_html_e('Password', 'wordclever-ai-content-writer'); ?>" required minlength="6">
                            <span id="toggle-register-password" class="dashicons dashicons-visibility" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;"></span>
                        </div>
                        <button id="wordclever-register-submit"><?php esc_html_e('Register', 'wordclever-ai-content-writer'); ?></button>
                        <p><button type="button" id="wordclever-toggle-login"><?php esc_html_e('Back to Login', 'wordclever-ai-content-writer'); ?></button></p>
                    </div>

                    <button id="wordclever-popup-close" aria-label="<?php esc_html_e('Close', 'wordclever-ai-content-writer'); ?>" style="background: none; border: none; cursor: pointer;">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
            </div>
        </div>

        <?php if (empty($current_user)): ?>
            <p style="color: red;"><?php esc_html_e('You must be logged in to generate content.', 'wordclever-ai-content-writer'); ?></p>
        <?php endif;
    }
    
    public static function enqueue_assets($hook) {

        if ($hook == 'toplevel_page_wordclever-templates') {
            wp_enqueue_style('wordclever-template-style', WORDCLEVER_URL . 'assets/css/template-style.css', array(), WORDCLEVER_VERSION, 'all');
            wp_enqueue_style('wordclever-bootstrap', WORDCLEVER_URL . 'assets/css/bootstrap.min.css', array(), WORDCLEVER_VERSION, 'all');
            wp_enqueue_style('wordclever-fontawesome', WORDCLEVER_URL . 'assets/css/fontawesome-all.min.css', array(), WORDCLEVER_VERSION, 'all');
            wp_enqueue_script('wordclever-bootstrap-js', WORDCLEVER_URL . 'assets/js/bootstrap.min.js', array('jquery'), WORDCLEVER_VERSION, true);
            wp_enqueue_script('wordclever-pagination-js', WORDCLEVER_URL . 'assets/js/templates-pagination.js', array('jquery'), WORDCLEVER_VERSION, true);

            wp_localize_script('wordclever-pagination-js', 'wordclever_pagination_object', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('wordclever_create_pagination_nonce_action'),
                'dashboard_nonce' => wp_create_nonce('wordclever_nonce'),
                'reset_pass_nonce' => wp_create_nonce('wordclever_reset_pass_nonce'),
                'support_tab_nonce' => wp_create_nonce('wordclever_support_tab_nonce'),
                'site_url' => get_site_url()
            ));
        }

        wp_enqueue_style('wordclever-admin-style', WORDCLEVER_URL . 'assets/css/admin-style.css', array(), WORDCLEVER_VERSION, 'all');
        $font_url = WORDCLEVER_URL . 'assets/webfonts/segoe-ui-this/segoeuithis.ttf';
        $custom_css = "
            @font-face {
                font-family: 'Segoe UI';
                src: url('{$font_url}') format('truetype');
                font-weight: normal;
                font-style: normal;
            }
        ";
        
        wp_add_inline_style('wordclever-admin-style', $custom_css);

        wp_enqueue_script('wordclever-admin-script', WORDCLEVER_URL . 'assets/js/admin-script.js', ['jquery'], WORDCLEVER_VERSION, true);
        wp_enqueue_style('dashicons');
        //sweetalert
        wp_enqueue_style('wordclever-sweetalert2-css', WORDCLEVER_URL . 'assets/libs/sweetalert2/sweetalert2.min.css', array(), WORDCLEVER_VERSION);
        wp_enqueue_script('wordclever-sweetalert2-js', WORDCLEVER_URL . 'assets/libs/sweetalert2/sweetalert2.min.js', array('jquery'), WORDCLEVER_VERSION, true); 

        wp_localize_script('wordclever-admin-script', 'wordclever_data', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wordclever_nonce'),
            'site_url' => get_site_url(), 
        ]);
        
    }


    public static function generate_content() {
        check_ajax_referer('wordclever_nonce', 'security');

        $current_user = get_option('wordclever_current_user');
        if (empty($current_user)) {
            wp_send_json_error(['message' => __('User is not logged in. Please log in to generate content.', 'wordclever-ai-content-writer')]);
        }
    
        $request_count = get_option('wordclever_request_count' . $current_user_id, 0);
        $used_request = get_option('wordclever_used_request' . $current_user_id, 0);
    
        // Only check request limit if request_count is not 0 (unlimited)
        if ($request_count > 0 && $used_request >= $request_count) {
            wp_send_json_error(['message' => __('Request limit reached. Upgrade your plan.', 'wordclever-ai-content-writer')]);
        }
    
        $content_type = sanitize_text_field(wp_unslash($_POST['content_type'] ?? ''));
        $keyword = sanitize_text_field(wp_unslash($_POST['keyword'] ?? ''));
        $tone = sanitize_text_field(wp_unslash($_POST['tone'] ?? ''));
        $resp_count = intval($_POST['resp_count'] ?? 1);
        $license_key = sanitize_text_field(wp_unslash($_POST['license_key'] ?? ''));
    
        if (empty($content_type) || empty($keyword) || empty($tone) || $resp_count < 1) {
            wp_send_json_error(['message' => __('All fields are required.', 'wordclever-ai-content-writer')]);
        }
    
        $response = WordClever_API_Handler::generate_content($content_type, $keyword, $tone, $license_key, $resp_count);
    
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }
    
        if (empty($response)) {
            wp_send_json_error(['message' => __('No content generated. Please try again.', 'wordclever-ai-content-writer')]);
        }
    
        wp_send_json_success(['results' => $response]);
    }
    
    public static function handle_reset_auth_pass() {
        check_ajax_referer('wordclever_reset_pass_nonce', 'security');

        $email_id = sanitize_text_field(wp_unslash($_POST['email_id'] ?? ''));
        $newpassword = sanitize_text_field(wp_unslash($_POST['newpassword'] ?? ''));
        $verification_status = sanitize_text_field(wp_unslash($_POST['verification_status'] ?? ''));

        $response = WordClever_API_Handler::reset_password_by_email($email_id, $newpassword, $verification_status);

        if (!isset($response['code'])) {
            wp_send_json_error(['message' => 'Unexpected error occurred. Please try again.']);
        }
        
        if ($response['code'] == 100) {
            wp_send_json_error(['message' => $response['message']]);
        } else {
            wp_send_json_success(['message' => 'Password has been reset successfully. Try logging in again.']);
        }
    }
    
    //for login and register 
    public static function handle_auth() {
        check_ajax_referer('wordclever_nonce', 'security');
    
        $username = sanitize_text_field(wp_unslash($_POST['username'] ?? ''));
        $password = sanitize_text_field(wp_unslash($_POST['password'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $auth_action = sanitize_text_field(wp_unslash($_POST['auth_action'] ?? ''));

    
        if (empty($username) || empty($password)) {
            wp_send_json_error(['message' => __('Username and password are required.', 'wordclever-ai-content-writer')]);
        }
    
        $response = WordClever_API_Handler::auth_login_signup($username, $password, $email, $auth_action);
    
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        if (!empty($response['success']) && $response['success']) {
            wp_send_json_success($response);
        } else {
            wp_send_json_error($response['data']);
        }
    
    }

    //end

    public static function wordclever_support_tab_form() {
        check_ajax_referer('wordclever_support_tab_nonce', 'security');

        if (isset($_POST['form_data'])) {
            parse_str($_POST['form_data'], $form_data);

            $first_name = sanitize_text_field(wp_unslash($form_data['support-first-name'] ?? ''));
            $last_name = sanitize_text_field(wp_unslash($form_data['support-last-name'] ?? ''));
            $email = sanitize_email(wp_unslash($form_data['support-email'] ?? ''));
            $subject = sanitize_text_field(wp_unslash($form_data['support-subject'] ?? ''));
            $message = sanitize_textarea_field(wp_unslash($form_data['support-description'] ?? ''));

            if (empty($first_name) || empty($last_name) || empty($email) || empty($subject) || empty($message)) {
                wp_send_json_error(['message' => 'All fields are required.']);
            }
    
            if (!is_email($email)) {
                wp_send_json_error(['message' => 'Invalid email address.']);
            }

            $args = array(
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'subject' => $subject,
                'message' => $message
            );

            $response = WordClever_API_Handler::send_support_mail($args);

            if (is_wp_error($response)) {
                wp_send_json_error(['message' => $response->get_error_message()]);
            }

            if ( !empty($response['code']) && $response['code'] == 200 ) {
                wp_send_json_success($response);
            } else {
                wp_send_json_error($response);
            }
            
        } else {
            wp_send_json_error(['message' => 'All fields are required.']);
        }
    }

    public static function add_admin_menu() {
        add_menu_page(
            __('WordClever', 'wordclever-ai-content-writer'),
            __('WordClever', 'wordclever-ai-content-writer'),
            'manage_options',
            'wordclever-templates',
            [__CLASS__, 'render_templates_page'],
            'dashicons-lightbulb'
        );

        add_submenu_page(
            'wordclever-templates',
            'Templates',
            'Templates',
            'manage_options',
            'wordclever-templates',
            [__CLASS__, 'render_templates_page'],
        );

        add_submenu_page(
            'wordclever-templates',
            'Help',
            'Help',
            'manage_options',
            'wordclever-help',
            [__CLASS__, 'render_help_page'],
        );
    }

    public static function render_help_page() {
        ?>
        <div id="wordclever-help" class="wrap">
            <h1><?php esc_html_e('WordClever Help', 'wordclever-ai-content-writer'); ?></h1>
            <p><?php esc_html_e('Welcome to WordClever AI Content Writer!', 'wordclever-ai-content-writer'); ?></p>
            <p><?php esc_html_e('Here’s how to use the plugin:', 'wordclever-ai-content-writer'); ?></p>
            <ol>
                <li><?php esc_html_e('Go to woocommerce product edit screen.', 'wordclever-ai-content-writer'); ?></li>
                <li><?php esc_html_e('Find the "WordClever AI Content Writer" box on the right sidebar.', 'wordclever-ai-content-writer'); ?></li>
                <li><?php esc_html_e('Fill in the required fields (Content Type, Keyword, and Tone).', 'wordclever-ai-content-writer'); ?></li>
                <li><?php esc_html_e('Click "Generate Content" to create AI-powered content!', 'wordclever-ai-content-writer'); ?></li>
            </ol>
            <p><?php esc_html_e('As a free user, you have 10 free content generation requests. After that, you will need to upgrade to a paid plan for more requests.', 'wordclever-ai-content-writer'); ?></p>
            <p><?php esc_html_e('For more details, check our documentation or contact support.', 'wordclever-ai-content-writer'); ?></p>
        </div>
        <?php
    }

    public static function render_templates_page() {
        ?>
        <div id="wordclever-templates" class="wrap">
            <div class="row d-flex wordclever-templates-tabs-box">
                <div class="col-xl-1 col-lg-1 col-md-1 wordclever-templates-collections-logo text-center">
                    <img src="<?php echo esc_url(WORDCLEVER_URL . 'assets/images/logo.png'); ?>" alt="banner-image">
                </div>
                <div class="col-xxl-9 col-xl-8 col-lg-8 col-md-8 wordclever-templates-collections-tabs">
                    <ul class="nav nav-tabs" id="myTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard" type="button" role="tab" aria-controls="dashboard" aria-selected="true"><?php echo esc_html('Dashboard');?></button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="license-tab" data-bs-toggle="tab" data-bs-target="#license" type="button" role="tab" aria-controls="license" aria-selected="false"><?php echo esc_html('License');?></button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="templates-tab" data-bs-toggle="tab" data-bs-target="#templates" type="button" role="tab" aria-controls="templates" aria-selected="false"><?php echo esc_html('Templates');?></button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="support-tab" data-bs-toggle="tab" data-bs-target="#support" type="button" role="tab" aria-controls="support" aria-selected="false"><?php echo esc_html('Support');?></button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="premium-tab" data-bs-toggle="tab" data-bs-target="#premium" type="button" role="tab" aria-controls="premium" aria-selected="false"><?php echo esc_html('Upgrade to Premium');?></button>
                        </li>
                    </ul>
                </div>
                <div class="col-xxl-2 col-xl-3 col-lg-3 col-md-3 wordclever-templates-collections-search align-self-center">
                    <div class="search-box d-flex gap-2">
                        <span class="search-outer-box position-relative"><input type="text" name="wordclever-templates-search" autocomplete="off" placeholder="Plumber |">
                            <span class="dashicons dashicons-search"></span>
                        </span>
                    </div>
                </div>
            </div>
            <div class="wordclever-template-content-box d-flex tab-content" style="justify-content:start;" id="nav-tabContent">
                <div class="tab-pane fade active show row  pt-xxl-5 pt-5 mt-5 mt-xxl-5" id="dashboard" role="tabpanel" aria-labelledby="dashboard-tab">
                    <div class="col-xxl-3 col-xl-6 col-lg-6 col-md-6">
                        <div class="wordclever-login-registration-wrap">
                            <div class="wordclever-user-profile"><i class="fa-solid fa-user"></i></div>
                            <div>
                                <ul class="nav nav-tabs" id="login-registration" role="tablist">
                                    <?php
                                        $current_user = get_option('wordclever_current_user');
                                        $request_count = get_option('wordclever_request_count');
                                        $used_request = get_option('wordclever_used_request');

                                        $request_count = ($request_count !== false) ? intval($request_count) : 0;
                                        $used_request = ($used_request !== false) ? intval($used_request) : 0;

                                        $total = sprintf('%02d', $request_count);
                                        $used = sprintf('%02d', $used_request);
                                        $remaining = sprintf('%02d', max(0, $request_count - $used_request));
                                    ?>
                                    <?php if (!isset($current_user) || empty($current_user)) { ?>
                                        <li class="nav-item reset-pass-nav d-none" role="presentation">
                                            <button class="nav-link" id="reset-pass-tab" data-bs-toggle="tab" data-bs-target="#reset-pass" type="button" role="tab" aria-controls="reset-pass" aria-selected="true">Reset Password</button>
                                        </li>
                                        <li class="nav-item login-user-nav" role="presentation">
                                            <button class="nav-link active" id="login-user-tab" data-bs-toggle="tab" data-bs-target="#login-user" type="button" role="tab" aria-controls="login-user" aria-selected="false">Login</button>
                                        </li>
                                        <li class="nav-item signup-user-nav" role="presentation">
                                            <button class="nav-link" id="signup-user-tab" data-bs-toggle="tab" data-bs-target="#signup-user" type="button" role="tab" aria-controls="signup-user" aria-selected="false">Sign Up</button>
                                        </li>
                                    <?php } else { ?>
                                        <li class="nav-item user-details-nav" role="presentation">
                                            <button class="nav-link active" id="user-details-tab" data-bs-toggle="tab" data-bs-target="#user-details" type="button" role="tab" aria-controls="user-details" aria-selected="false">Profile</button>
                                        </li>
                                    <?php } ?>
                                </ul>
                                <div class="tab-content" id="loginRegistrationContent">
                                    <?php if (!isset($current_user) || empty($current_user)) { ?>
                                        <div class="tab-pane fade d-none" id="reset-pass" role="tabpanel" aria-labelledby="reset-pass-tab">
                                            <form id="wordclever-reset-form" method="post" action="<?php echo esc_url(admin_url( 'admin-ajax.php' )); ?>">
                                                <div class="mb-3 reset-pass-group">
                                                    <label for="reset-pass-email"><?php esc_html_e('Email', 'wordclever-ai-content-writer'); ?></label>    
                                                    <input id="reset-pass-email" type="email" class="form-control mb-3" placeholder="example@gmail.com" required>
                                                </div>
                                                <div class="new-pass-group d-none">
                                                    <div class="mb-3">
                                                        <label for="reset-new-pass"><?php esc_html_e('New Password', 'wordclever-ai-content-writer'); ?></label>    
                                                        <input id="reset-new-pass" type="password" class="form-control mb-3" placeholder="********">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="reset-confirm-pass"><?php esc_html_e('Confirm Password', 'wordclever-ai-content-writer'); ?></label>    
                                                        <input id="reset-confirm-pass" type="password" class="form-control mb-3" placeholder="********">
                                                    </div>
                                                </div>
                                                <button type="submit" class="wordclever-dash-btn reset-submit-btn"><?php esc_html_e('Submit', 'wordclever-ai-content-writer'); ?></button>
                                            </form>
                                        </div>
                                        <div class="tab-pane fade show active" id="login-user" role="tabpanel" aria-labelledby="login-user-tab">
                                            <form id="wordclever-login-form" method="post" action="<?php echo esc_url(admin_url( 'admin-ajax.php' )); ?>">
                                                <?php wp_nonce_field('wordclever_nonce', 'wordclever_nonce_field'); ?>
                                                <div class="mb-3">
                                                    <label for="login-username"><?php esc_html_e('Username', 'wordclever-ai-content-writer'); ?></label>    
                                                    <input id="login-username" type="text" class="form-control mb-3" placeholder="username">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="login-pass"><?php esc_html_e('Password', 'wordclever-ai-content-writer'); ?></label>    
                                                    <input id="login-pass" type="password" class="form-control mb-3" placeholder="********">
                                                </div>
                                                <button type="submit" class="wordclever-login-submit"><?php esc_html_e('Login', 'wordclever-ai-content-writer'); ?></button>
                                                <a href="#" class="wordclever-dash-btn forgot-pass"><?php esc_html_e('Forgot your password?', 'wordclever-ai-content-writer'); ?></a>
                                            </form>
                                        </div>
                                        <div class="tab-pane fade" id="signup-user" role="tabpanel" aria-labelledby="signup-user-tab">
                                            <form id="wordclever-registration-form" method="post" action="<?php echo esc_url(admin_url( 'admin-ajax.php' )); ?>">
                                                <div class="mb-3">
                                                    <label for="signup-username"><?php esc_html_e('Username', 'wordclever-ai-content-writer'); ?></label>    
                                                    <input id="signup-username" type="text" class="form-control mb-3" placeholder="Example" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="signup-email"><?php esc_html_e('Email', 'wordclever-ai-content-writer'); ?></label>    
                                                    <input id="signup-email" type="email" class="form-control mb-3" placeholder="example@gmail.com" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="signup-pass"><?php esc_html_e('Password', 'wordclever-ai-content-writer'); ?></label>    
                                                    <input id="signup-pass" type="password" class="form-control mb-3" placeholder="********" required>
                                                </div>
                                                <button type="submit" class="wordclever-dash-btn"><?php esc_html_e('Register', 'wordclever-ai-content-writer'); ?></button>
                                                <button class="wordclever-dash-btn back-to-login"><?php esc_html_e('Back to Login', 'wordclever-ai-content-writer'); ?></button>
                                            </form>
                                        </div>
                                    <?php } else { ?>
                                        <div class="tab-pane fade show active" id="user-details" role="tabpanel" aria-labelledby="user-details-tab">
                                            <div class="user-details-wrap">
                                                <h2 class="wordclever-user-name"><?php echo esc_html($current_user); ?></h2>
                                                <div class="wordclever-user-card-wrap">
                                                    <div class="wordclever-user-card">
                                                        <div class="wordclever-user-inner-card">
                                                            <h2><?php echo esc_html('Total'); ?></h2>
                                                            <p><?php echo esc_html($total); ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="wordclever-user-card">
                                                        <div class="wordclever-user-inner-card">
                                                            <h2><?php echo esc_html('Used'); ?></h2>
                                                            <p><?php echo esc_html($used); ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="wordclever-user-card">
                                                        <div class="wordclever-user-inner-card">
                                                            <h2><?php echo esc_html('Available'); ?></h2>
                                                            <p><?php echo esc_html($remaining); ?></p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php if ($used >= $total && $total > 0): ?>
                                                    <div class="wordclever-upgrade-plan-btn-wrap mt-3 text-center">
                                                        <a href="<?php echo esc_attr(WORDCLEVER_MAIN_URL . '/products/wordclever-pro'); ?>" target="_blank" class="plan-card-upgrade-btn"><?php echo esc_html('Upgrade'); ?></a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                            <p class=""><?php esc_html_e('Contact admin for assistance - support@wpradiant.net', 'wordclever-ai-content-writer'); ?></p>
                        </div>
                    </div>
                    <div class="col-xxl-6 col-xl-12 col-lg-12 order-xxl-2 order-xl-3 order-lg-3 order-md-3 mt-xxl-0 mt-5">
                        <div class="wordclever-steps-wrap">
                            <h2><?php echo esc_html('Welcome to WordClever AI Content Writer!'); ?></h2>
                            <p><?php echo esc_html('Here’s how to use the plugin:'); ?></p>
                            <div class="wordclever-steps">
                                <div class="wordclever-step"><?php echo esc_html('Go to WooCommerce product or page edit screen.'); ?></div>
                                <div class="wordclever-step"><?php echo esc_html('Find the "WordClever AI Content Writer" box on the right sidebar.'); ?></div>
                                <div class="wordclever-step"><?php echo esc_html('Fill in the required fields (Content Type, Keyword, and Tone).'); ?></div>
                                <div class="wordclever-step"><?php echo esc_html('Click "Generate Content" to create AI-powered content!'); ?></div>
                                <div class="wordclever-step"><?php echo esc_html('Copy & Paste the generated result in the field given by your SEO plugin.'); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-3 col-xl-6 col-lg-6 col-md-6 order-xxl-3 order-xl-2 order-lg-2 order-md-2">
                        <?php require WORDCLEVER_PATH . 'includes/bundle-banner-template.php'; ?>
                    </div>
                </div>
                <div class="tab-pane fade row pt-xxl-5 pt-5 mt-5 mt-xxl-5" id="license" role="tabpanel" aria-labelledby="license-tab">
                    <div class="col-xxl-9 col-xl-8 col-lg-7 col-md-7">
                        <div class="wordclever-license-activation-wrap">
                            <h2><i class="fa-solid fa-key pe-4"></i> <?php echo esc_html('Licence Management'); ?></h2>
                            <div class="wordclever-license-activation-status">
                                <span><?php echo esc_html('Activation Status'); ?></span>
                                <span class="wordclever-license-status <?php echo get_option('wordclever_license_status') === 'active' ? 'wordclever-license-active' : 'wordclever-license-no-activation-status'; ?> ps-5">
                                    <?php 
                                    $license_status = get_option('wordclever_license_status');
                                    echo $license_status === 'active' ? esc_html('License Activated') : esc_html('No Active License');
                                    ?>
                                </span>
                            </div>
                            <p><?php echo esc_html('To activate this product, please enter the license key provided by WP Radiant below and press Activate.'); ?></p>
                            <form id="wordclever-license-form" method="post">
                                <input type="text" name="wordclever-license-activation-text" id="wordclever-license-key" placeholder="Enter License Key" value="<?php echo esc_attr(get_option('wordclever_license_key')); ?>" required <?php echo get_option('wordclever_license_status') === 'active' ? 'disabled' : ''; ?>>
                                <div class="licence-check-btn d-flex pt-4">
                                    <button type="submit" class="btn" id="wordclever-activate-license" <?php echo get_option('wordclever_license_status') === 'active' ? 'disabled' : ''; ?>><?php echo get_option('wordclever_license_status') === 'active' ? esc_html('Activated') : esc_html('Activate'); ?></button>
                                    <p class="info align-self-center ps-xl-5 ps-3 pe-lg-3"><i class="fa-solid fa-circle-info"></i> <?php echo esc_html('For more details, check our documentation or contact support.'); ?></p>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="col-xxl-3 col-xl-4 col-lg-5 col-md-5">
                        <?php require WORDCLEVER_PATH . 'includes/bundle-banner-template.php'; ?>
                    </div>
                </div>
                <div class="tab-pane fade pt-xxl-5 pt-5 mt-5 mt-xxl-5" id="templates" role="tabpanel" aria-labelledby="templates-tab">
                    <div class="d-flex">
                        <div class="wordclever-template-sidebar-main-box col-xxl-3 col-xl-4 col-lg-4 col-md-6 pe-4">
                            <div class="wordclever-template-banner-image position-relative mb-2">
                                <img src="<?php echo esc_url(WORDCLEVER_URL . 'assets/images/bundle-banner.png'); ?>" alt="banner-image">
                                <div class="wordclever-template-banner-content-box">
                                    <div class="wordclever-template-banner-heading"><?php echo esc_attr('WordPress Theme Bundle'); ?></div>
                                    <p class="wordclever-template-banner-para"><?php echo esc_attr('Get Access to 55+ Gutenberg WordPress Themes for almost all business Niche'); ?></p>
                                    <a class="wordclever-bundle-buy-now wordclever-bundle-btn mt-2" href="<?php echo esc_url( WORDCLEVER_MAIN_URL . '/products/wordpress-theme-bundle' ); ?>" target="_blank"><?php echo esc_html('Buy Bundle at $69'); ?></a>
                                </div>
                            </div>
                            <div class="wordclever-filter-categories-wrapper position-relative">
                                <div class="wordclever-filter-category-select pb-3">
                                    <span class="wordclever-filter-category-select-content"><?php echo esc_attr('Themes Categories'); ?></span>
                                </div>
                                <ul class="wordclever-templates-collections-group">
                                    <?php
                                        $collections_arr = wordclever_get_collections();
                                        foreach ( $collections_arr as $collection ) {

                                            if ($collection->handle != 'free-products' && $collection->handle != 'frontpage' && $collection->handle != 'gutenberg-wordpress-themes') { ?>
                                                <li class="wordclever-collection-name pb-3" data-value="<?php echo esc_attr($collection->handle); ?>"><?php echo esc_html($collection->title); ?><span class="wordclever-collection-count align-self-center"><?php echo esc_html($collection->productsCount); ?></span></li>
                                            <?php }
                                        }
                                    ?>
                                </ul>
                            </div>
                        </div>
                        <div class="col-xxl-9 col-xl-8 col-lg-8 col-md-6 wordclever-templates-grid-outer-box">
                            <div class="wordclever-templates-grid wordclever-main-grid">
                                <?php $get_filtered_products = wordclever_get_filtered_products();
                                    if (isset($get_filtered_products['products']) && !empty($get_filtered_products['products'])) {
                                        foreach ( $get_filtered_products['products'] as $product ) {
                                            $product_obj = $product->node;

                                            if (isset($product_obj->inCollection) && !$product_obj->inCollection) {
                                                continue;
                                            }
                                            
                                            $demo_url = '';$documentation_url = '';
                                            if (isset($product_obj->metafields->edges)) {
                                                foreach ($product_obj->metafields->edges as $metafield_edge) {
                                                    $metafield = $metafield_edge->node;
                                                    if ($metafield->key === 'custom.live_preview') {
                                                        $demo_url = $metafield->value;
                                                    } elseif ($metafield->key === 'custom.button_documentation') {
                                                        $documentation_url = $metafield->value;
                                                    }
                                                }
                                            }

                                            $product_url = isset($product->node->onlineStoreUrl) ? $product->node->onlineStoreUrl : '';
                                            $image_src = isset($product->node->images->edges[0]->node->src) ? $product->node->images->edges[0]->node->src : '';
                                            $product_price = isset($product->node->variants->edges[0]->node->price) ? $product->node->variants->edges[0]->node->price : ''; ?>

                                            <div class="wordclever-grid-item">
                                                <div class="wordclever-image-wrap">
                                                    <div class="wordclever-image-box">
                                                        <img src="<?php echo esc_url($image_src); ?>" alt="<?php echo esc_attr($product_obj->title); ?>" loading="lazy">
                                                    </div>
                                                    <div class="wordclever-image-overlay">
                                                        <?php if( $demo_url != '' ) { ?>
                                                            <a class="wordclever-demo-url wordclever-btn" href="<?php echo esc_url($demo_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html('Live Preview'); ?></a>
                                                        <?php } else { ?>
                                                            <a class="wordclever-demo-url wordclever-btn" href="<?php echo esc_url( WORDCLEVER_MAIN_URL . '/collections/gutenberg-wordpress-themes' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html('Live Preview'); ?></a>
                                                        <?php } ?>
                                                        <footer>
                                                            <h3><?php echo esc_html($product_obj->title); ?></h3>
                                                        </footer>
                                                        <div class="d-flex" style="justify-content: space-between;">
                                                            <div class="wordclever-price-box">Price:
                                                                <div class="wordclever-price"><?php echo esc_html('$' . $product_price); ?></div>
                                                            </div>
                                                            <a class="wordclever-buy-now wordclever-btn" href="<?php echo esc_attr($product_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html('Buy It Now'); ?></a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php }
                                    }
                                ?>
                            </div>
                            <div class="text-center mt-4">
                                <?php if (isset($get_filtered_products['pagination']->hasNextPage) && $get_filtered_products['pagination']->hasNextPage) { ?>
                                    <a href="#" class="wordclever-load-more" data-pagination="<?php echo esc_attr(isset($get_filtered_products['pagination']->endCursor) ? $get_filtered_products['pagination']->endCursor : '') ?>">Load More</a>
                                    <input type="hidden" name="wordclever-end-cursor" value="<?php echo esc_attr(isset($get_filtered_products['pagination']->endCursor) ? $get_filtered_products['pagination']->endCursor : '') ?>">
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade pt-xxl-5 pt-5 " id="support" role="tabpanel" aria-labelledby="support-tab">
                    <div class="wordclever-support-wrap">
                        <h2><?php echo esc_html('Welcome to WordClever Support'); ?></h2>
                        <p class="wordclever-support-wrap-para"><?php echo esc_html("We're here to ensure your experience with WordClever is smooth and hassle-free. Whether you're looking for detailed documentation, community discussions, or direct support, you'll find everything you need right here."); ?></p>
                        <div class="row pt-xxl-5 pt-4">
                            <div class="col-xxl-3 col-xl-4 col-lg-5 col-md-5">
                                <div class="wordclever-support-left-card mb-3">
                                    <h4><?php echo esc_html('Documentation'); ?></h4>
                                    <p><?php echo esc_html("Access our detailed guides and tutorials to help you set up and make the most of WordClever. From installation to advanced features, we've got you covered."); ?></p>
                                    <a href="<?php echo esc_attr(WORDCLEVER_PREVIEW_URL . '/tutorial/wordclever-documentation'); ?>" target="_blank" class="wordclever-support-btn"><?php echo esc_html('View Documentation'); ?></a>
                                </div>
                                <div class="wordclever-support-left-card">
                                    <h4><?php echo esc_html('Community Forum'); ?></h4>
                                    <p><?php echo esc_html('Join the WordClever community to connect with other users and find solutions to common questions.'); ?></p>
                                    <a href="<?php echo esc_attr(WORDCLEVER_MAIN_URL . '/pages/community'); ?>" target="_blank" class="wordclever-support-btn"><?php echo esc_html('Visit Community Forum'); ?></a>
                                </div>
                            </div>
                            <div class="col-xxl-9 col-xl-8 col-lg-7 col-md-7">
                                <div class="wordclever-support-right-card">
                                    <h4><?php echo esc_html('Support Request'); ?></h4>
                                    <p><?php echo esc_html("Can't find what you're looking for? Reach out to our support team directly via email. We're here to assist you with any technical issues or queries."); ?></p>
                                    <form id="wordclever-support-form" method="post" action="<?php echo esc_url(admin_url( 'admin-ajax.php' )); ?>">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <input type="text" class="form-control mb-3" name="support-first-name" placeholder="First Name" required>
                                            </div>
                                            <div class="col-md-6">
                                                <input type="text" class="form-control mb-3" name="support-last-name" placeholder="Last Name" required>
                                            </div>
                                        </div>
                                        <input type="email" class="form-control mb-3" name="support-email" placeholder="Your Contact Email" required>
                                        <input type="text" class="form-control mb-3" name="support-subject" placeholder="Subject" required>
                                        <textarea class="form-control mb-3" rows="4" placeholder="Description" name="support-description" required></textarea>
                                        <button type="submit" class="wordclever-dash-btn wordclever-support-btn"><?php echo esc_html('Submit'); ?></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade pt-xxl-5 pt-5" id="premium" role="tabpanel" aria-labelledby="premium-tab">
                    <div class="wordcleaver-plans-outer-main-box">
                        <h2><?php echo esc_html('Choose the Right Plan for Your Content Needs'); ?></h2>
                        <p><?php echo esc_html("WordClever AI Content Writer offers flexible plans tailored to suit businesses of all sizes. Whether you're just starting or need robust content generation capabilities, we've got you covered."); ?></p>
                        <div class="d-flex plan-card-wrap" style="flex-wrap: wrap;">
                            <?php
                                $premium_plan_arr = wordclever_get_premium_plans();
                                $current_plan = get_option('wordclever_current_plan');
                                $current_plan_name = isset($current_plan['plan_name']) ? $current_plan['plan_name'] : '';

                                foreach ($premium_plan_arr as $plan) {
                                    
                                    $plan_name = isset($plan->plan_name) ? $plan->plan_name : '';
                                    $plan_sub_head = isset($plan->plan_sub_head) ? $plan->plan_sub_head : '';
                                    $plan_fields = isset($plan->plan_fields) && !empty($plan->plan_fields) ? $plan->plan_fields : array();
                                    $is_current_plan = ($plan_name === $current_plan_name);
                                    ?>
                                    <div class="plan-card-card-outer-box pt-4">
                                        <div class="plan-card card <?php echo $is_current_plan ? 'current-plan' : ''; ?>">
                                            <?php if ($is_current_plan): ?>
                                                <div class="current-plan-badge">Current Plan</div>
                                            <?php endif; ?>
                                            <div class="plan-card-title">
                                                <h2><?php echo esc_html($plan_name); ?></h2>
                                                <h3><?php echo esc_html($plan_sub_head); ?></h3>
                                            </div>

                                            <div class="plan-content-card-box">
                                                <ul class="plan-card-fields">
                                                    <?php
                                                        foreach($plan_fields as $field) { ?>
                                                            <li>
                                                                <span class="pe-3">
                                                                    <?php
                                                                    
                                                                        $checked_icon = file_get_contents(WORDCLEVER_URL . 'assets/images/checked-icon.svg');
                                                                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Outputting trusted local SVG file.
                                                                        echo $checked_icon;
                                                                    ?>
                                                                </span>
                                                                <?php echo esc_html($field); ?>
                                                            </li>
                                                        <?php }
                                                    ?>
                                                </ul>
                                                <?php if (!$is_current_plan): ?>
                                                    <a href="<?php echo esc_attr( WORDCLEVER_MAIN_URL . '/products/wordclever-pro'); ?>" target="_blank" class="plan-card-upgrade-btn"><?php echo esc_html('Upgrade'); ?></a>
                                                <?php else: ?>
                                                    <div class="plan-card-current-btn">Current Plan</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php }

    public static function handle_license_verification() {
        check_ajax_referer('wordclever_nonce', 'security');

        $license_key = sanitize_text_field(wp_unslash($_POST['license_key'] ?? ''));
        
        if (empty($license_key)) {
            wp_send_json_error(['message' => __('License key is required.', 'wordclever-ai-content-writer')]);
        }

        // Set the license key before verification
        update_option('wordclever_license_key', $license_key);

        $response = WordClever_API_Handler::verify_license_key($license_key);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        if (!empty($response['success']) && $response['success'] === true) {
            wp_send_json_success([
                'message' => __('License activated successfully!', 'wordclever-ai-content-writer'),
                'plan_details' => $response['user_data']['plan_details'] ?? null
            ]);
        } else {
            wp_send_json_error([
                'message' => $response['message'] ?? __('Invalid license key.', 'wordclever-ai-content-writer')
            ]);
        }
    }

    public static function check_license_and_requests() {
        // Only run on admin pages
        if (!is_admin()) {
            return;
        }

        $license_key = get_option('wordclever_license_key');
        if (empty($license_key)) {
            update_option('wordclever_license_status', 'invalid');
            update_option('wordclever_used_request', 0);
            update_option('wordclever_request_count', 0);
            return;
        }

        // Check license validity
        $response = WordClever_API_Handler::verify_license_key($license_key);
        
        if (is_wp_error($response)) {
            update_option('wordclever_license_status', 'invalid');
            update_option('wordclever_used_request', 0);
            update_option('wordclever_request_count', 0);
            return;
        }

        if (!empty($response['success']) && $response['success'] === true) {
            update_option('wordclever_license_status', 'active');
            
            // Update plan details if available
            if (!empty($response['user_data']['plan_details'])) {
                update_option('wordclever_current_plan', [
                    'plan_name' => $response['user_data']['plan_details']['plan_name'],
                    'plan_amount' => $response['user_data']['plan_details']['plan_amount'],
                    'plan_fields' => $response['user_data']['plan_details']['plan_fields'],
                    'plan_sub_head' => $response['user_data']['plan_details']['plan_sub_head'],
                    'plan_type' => $response['user_data']['plan_details']['plan_type'],
                    'req_count' => $response['user_data']['plan_details']['req_count']
                ]);
            }

            // Update used requests
            if (!empty($response['user_data']['used_request'])) {
                update_option('wordclever_used_request', $response['user_data']['used_request']);
            }
        } else {
            update_option('wordclever_license_status', 'invalid');
            update_option('wordclever_used_request', 0);
            update_option('wordclever_request_count', 0);
        }
    }

    public static function update_used_requests_on_load() {
        // Only run on admin pages
        if (!is_admin()) {
            return;
        }

        $current_user = get_option('wordclever_current_user');
        if (empty($current_user)) {
            return;
        }

        // Make an AJAX call to update used requests
        $response = WordClever_API_Handler::get_user_data_by_username($current_user);
        
        if (is_wp_error($response) || empty($response['user_data'])) {
            return;
        }
        
        $used_request = $response['user_data']['used_request'] ?? 0;
        update_option('wordclever_used_request', $used_request);
        
        // Update plan details if available
        if (!empty($response['user_data']['plan_details'])) {
            update_option('wordclever_current_plan', [
                'plan_name' => $response['user_data']['plan_details']['plan_name'],
                'plan_amount' => $response['user_data']['plan_details']['plan_amount'],
                'plan_fields' => $response['user_data']['plan_details']['plan_fields'],
                'plan_sub_head' => $response['user_data']['plan_details']['plan_sub_head'],
                'plan_type' => $response['user_data']['plan_details']['plan_type'],
                'req_count' => $response['user_data']['plan_details']['req_count']
            ]);
        }
    }
}

WordClever_MetaBox::init();

