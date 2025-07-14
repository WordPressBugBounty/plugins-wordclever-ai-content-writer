<?php
defined('ABSPATH') || exit;

class WordClever_API_Handler {
    public static function generate_content($content_type, $keyword, $tone, $license_key, $resp_count = 1) {
        $username = get_option('wordclever_current_user');

       if (!$username) {
           return new WP_Error('username_error', 'No username found.');
       }
       
        $endpoint = WORDCLEVER_ENDPOINT . 'openai_get_results';
    
        $request_data = [
            'username'     => $username,
            'content_type' => $content_type,
            'keyword'      => $keyword,
            'tone'         => $tone,
            'resp_count'   => $resp_count,
            'license_key'  => $license_key,
        ];
    
        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($request_data),
        ]);
    
        if (is_wp_error($response)) {
            return new WP_Error('server_error', 'Failed to connect to the server endpoint.');
        }
    
        $response_body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($response_body, true);
    
        if (!empty($decoded_response['results'])) {
            // Update user data and plan details if available
            if (!empty($decoded_response['user_data'])) {
                update_option('wordclever_used_request', $decoded_response['user_data']['used_request']);
                
                // Update plan details if available
                if (!empty($decoded_response['user_data']['plan_details'])) {
                    update_option('wordclever_current_plan', [
                        'plan_name' => $decoded_response['user_data']['plan_details']['plan_name'],
                        'plan_amount' => $decoded_response['user_data']['plan_details']['plan_amount'],
                        'plan_fields' => $decoded_response['user_data']['plan_details']['plan_fields'],
                        'plan_sub_head' => $decoded_response['user_data']['plan_details']['plan_sub_head'],
                        'plan_type' => $decoded_response['user_data']['plan_details']['plan_type'],
                        'req_count' => $decoded_response['user_data']['plan_details']['req_count']
                    ]);
                }
            }
            return $decoded_response['results']; // returns 'results' as an array
        }
    
        return new WP_Error('generation_error', $decoded_response['message']);
    }

    //for login / signup
    public static function auth_login_signup($username, $password, $email_id = '', $action = 'login') {
        $site_url = get_site_url();
        $endpoint = WORDCLEVER_ENDPOINT . 'auth_login_signup';
    
        // Prepare the API request data
        $request_data = [
            'username' => $username,
            'password' => $password,
            'site_url'  => esc_url_raw($site_url),
            'email_id' => $email_id,
            'action' => $action,
        ];
    
        $response = wp_remote_post($endpoint, [
            'method'    => 'POST',
            'body'      => wp_json_encode($request_data),
            'timeout'   => 15,
            'headers'   => [
                'Content-Type' => 'application/json',
            ],
        ]);
    
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'data' => [
                    'message'   => __('Failed to connect to the API.', 'wordclever-ai-content-writer'),
                    'code'      => $response_data['code']
                ]
            ];
        }
    
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
    
        // Ensure valid JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'data' => [
                    'message' => __('Invalid JSON response from the server.', 'wordclever-ai-content-writer'),
                    'code'      => $response_data['code']
                ]
            ];
        }
    
        // Handle success case explicitly
        if (isset($response_data['code']) && $response_data['code'] === 200 && $action === 'signup') {
            update_option('wordclever_current_user', $username);
            return [
                'success' => true,
                'data' => [
                    'message' => 'Registration successful! Please log in.',
                    'code'      => $response_data['code']
                ]
            ];
        }
        if (!empty($response_data['message']) && $response_data['message'] === 'User logged in.') {

            update_option('wordclever_current_user', $username);
            $user_data = $response_data['data'];
            update_option('wordclever_request_count', $user_data['request_count']);
            update_option('wordclever_used_request', $user_data['used_request']);
            
            if (!empty($user_data['plan_details'])) {
                update_option('wordclever_current_plan', [
                    'plan_name' => $user_data['plan_details']['plan_name'],
                    'plan_amount' => $user_data['plan_details']['plan_amount'],
                    'plan_fields' => $user_data['plan_details']['plan_fields'],
                    'plan_sub_head' => $user_data['plan_details']['plan_sub_head'],
                    'plan_type' => $user_data['plan_details']['plan_type'],
                    'req_count' => $user_data['plan_details']['req_count']
                ]);
            }
            
            return [
                'success' => true,
                'data' => [
                    'message' => 'User logged in.',
                    'code' => $response_data['code'],
                    'plan_details' => $user_data['plan_details'] ?? null
                ]
            ];
        }
        return [
            'success' => false,
            'data' => [
                'message' => $response_data['message'] ?? 'Unknown error occurred.',
                'code'      => $response_data['code']
            ]
        ];
    }


    //for user data 
    public static function get_user_data_by_username($username) {
        
        $endpoint = WORDCLEVER_ENDPOINT . 'get_data_by_username';
        
        $request_data = [
            'username' => $username,
        ];
        
        $response = wp_remote_post($endpoint, [
            'method'    => 'POST',
            'body'      => wp_json_encode($request_data),
            'timeout'   => 15,
            'headers'   => [
                'Content-Type' => 'application/json',
            ],
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', __('Invalid JSON response from the server.', 'wordclever-ai-content-writer'));
        }

        // Update user data in WordPress options
        if (!empty($response_data['user_data'])) {
            update_option('wordclever_request_count', $response_data['user_data']['request_count']);
            update_option('wordclever_used_request', $response_data['user_data']['used_request']);
            
            // Update plan details if available
            if (!empty($response_data['user_data']['plan_details'])) {
                update_option('wordclever_current_plan', [
                    'plan_name' => $response_data['user_data']['plan_details']['plan_name'],
                    'plan_amount' => $response_data['user_data']['plan_details']['plan_amount'],
                    'plan_fields' => $response_data['user_data']['plan_details']['plan_fields'],
                    'plan_sub_head' => $response_data['user_data']['plan_details']['plan_sub_head'],
                    'plan_type' => $response_data['user_data']['plan_details']['plan_type'],
                    'req_count' => $response_data['user_data']['plan_details']['req_count']
                ]);
            }
        }
        
        return $response_data;
    }
    
    public static function reset_password_by_email($email_id, $password, $verification_status) {

        $site_url = get_site_url();
        $endpoint = WORDCLEVER_ENDPOINT . 'reset_password_by_email';

        $request_data = [
            'email_id' => $email_id,
            'newpassword' => $password,
            'verification_status' => $verification_status
        ];

        $response = wp_remote_post($endpoint, [
            'method'    => 'POST',
            'body'      => wp_json_encode($request_data),
            'timeout'   => 15,
            'headers'   => [
                'Content-Type' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', __('Invalid JSON response from the server.', 'wordclever-ai-content-writer'));
        }
        
        return $response_data;
    }

    public static function send_support_mail($args) {
        $site_url = get_site_url();
        $endpoint = WORDCLEVER_ENDPOINT . 'send_support_mail';

        $request_data = [
            'site_url' => $site_url,
            'first_name' => $args['first_name'],
            'last_name' => $args['last_name'],
            'email' => $args['email'],
            'subject' => $args['subject'],
            'message' => $args['message'],
        ];

        $response = wp_remote_post($endpoint, [
            'method'    => 'POST',
            'body'      => wp_json_encode($request_data),
            'timeout'   => 15,
            'headers'   => [
                'Content-Type' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', __('Invalid JSON response from the server.', 'wordclever-ai-content-writer'));
        }
        
        return $response_data;

    }

    public static function verify_license_key($license_key) {
        $endpoint = WORDCLEVER_ENDPOINT . 'openai_check_license';
        
        $request_data = [
            'license_key' => $license_key,
            'domain' => get_site_url()
        ];
        
        $response = wp_remote_post($endpoint, [
            'method'    => 'POST',
            'body'      => wp_json_encode($request_data),
            'timeout'   => 15,
            'headers'   => [
                'Content-Type' => 'application/json',
            ],
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message()
            ];
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'message' => __('Invalid JSON response from the server.', 'wordclever-ai-content-writer')
            ];
        }

        // If license is valid, update the license status
        if (!empty($response_data['code']) && $response_data['code'] === 200) {
            update_option('wordclever_license_key', $license_key);
            update_option('wordclever_license_status', 'active');
            
            // Update user data if available
            if (!empty($response_data['user_data'])) {
                update_option('wordclever_request_count', $response_data['user_data']['request_count']);
                update_option('wordclever_used_request', $response_data['user_data']['used_request']);
                
                if (!empty($response_data['user_data']['plan_details'])) {
                    $plan_details = $response_data['user_data']['plan_details'];
                    update_option('wordclever_current_plan', [
                        'plan_name' => $plan_details['plan_name'],
                        'plan_amount' => $plan_details['plan_amount'],
                        'plan_fields' => $plan_details['plan_fields'],
                        'plan_sub_head' => $plan_details['plan_sub_head'],
                        'plan_type' => $plan_details['plan_type'],
                        'req_count' => $plan_details['req_count']
                    ]);
                }
            }
            
            return [
                'success' => true,
                'message' => __('License activated successfully!', 'wordclever-ai-content-writer'),
                'user_data' => $response_data['user_data'] ?? null
            ];
        } else {
            // Clear license data if verification fails
            update_option('wordclever_license_key', '');
            update_option('wordclever_license_status', 'inactive');
            update_option('wordclever_current_plan', null);
            
            return [
                'success' => false,
                'message' => $response_data['message'] ?? __('Invalid license key.', 'wordclever-ai-content-writer')
            ];
        }
    }
}
