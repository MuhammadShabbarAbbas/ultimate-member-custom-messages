<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('Ultimate_Member_Submit_Form_Errors_Login')):
    class Ultimate_Member_Submit_Form_Errors_Login
    {
        public function __construct()
        {
            add_action('plugins_loaded', [$this, 'hooks']);

        }

        function hooks()
        {
            remove_action('um_submit_form_errors_hook_login', 'um_submit_form_errors_hook_login', 10);
            add_action('um_submit_form_errors_hook_login', [$this, 'um_submit_form_errors_hook_login'], 2);
        }

        function um_submit_form_errors_hook_login($args)
        {
            $is_email = false;

            $form_id = $args['form_id'];
            $mode = $args['mode'];
            $user_password = $args['user_password'];


            if (isset($args['username']) && $args['username'] == '') {
                UM()->form()->add_error('username', __('Please enter your username or email', 'ultimate-member'));
            }

            if (isset($args['user_login']) && $args['user_login'] == '') {
                UM()->form()->add_error('user_login', __('Please enter your username', 'ultimate-member'));
            }

            if (isset($args['user_email']) && $args['user_email'] == '') {
                UM()->form()->add_error('user_email', __('Please enter your email', 'ultimate-member'));
            }

            if (isset($args['username'])) {
                $authenticate = $args['username'];
                $field = 'username';
                if (is_email($args['username'])) {
                    $is_email = true;
                    $data = get_user_by('email', $args['username']);
                    $user_name = isset($data->user_login) ? $data->user_login : null;
                } else {
                    $user_name = $args['username'];
                }
            } elseif (isset($args['user_email'])) {
                $authenticate = $args['user_email'];
                $field = 'user_email';
                $is_email = true;
                $data = get_user_by('email', $args['user_email']);
                $user_name = isset($data->user_login) ? $data->user_login : null;
            } else {
                $field = 'user_login';
                $user_name = $args['user_login'];
                $authenticate = $args['user_login'];
            }

            if ($args['user_password'] == '') {
                UM()->form()->add_error('user_password', __('Please enter your password', 'ultimate-member'));
            }


            $user = get_user_by('login', $user_name);
            if (!$user) {
                UM()->form()->add_error('username', __('Email address not registered.', 'ultimate-member'));
            } else {
                if (wp_check_password($args['user_password'], $user->data->user_pass, $user->ID)) {
                    UM()->login()->auth_id = username_exists($user_name);
                } else {
                    UM()->form()->add_error('user_password', __('Password is incorrect. Please try again.', 'ultimate-member'));
                }
            }

            // @since 4.18 replacement for 'wp_login_failed' action hook
            // see WP function wp_authenticate()
            $ignore_codes = array('empty_username', 'empty_password');

            $user = apply_filters('authenticate', null, $authenticate, $args['user_password']);
            if (is_wp_error($user) && !in_array($user->get_error_code(), $ignore_codes)) {
                UM()->form()->add_error($user->get_error_code(), __('Incorrect Email or password', 'ultimate-member'));
            }

            $user = apply_filters('wp_authenticate_user', $user, $args['user_password']);
            if (is_wp_error($user) && !in_array($user->get_error_code(), $ignore_codes)) {
                UM()->form()->add_error($user->get_error_code(), __('Password is incorrect. Please try again.', 'ultimate-member'));
            }

            // if there is an error notify wp
            if (UM()->form()->has_error($field) || UM()->form()->has_error($user_password) || UM()->form()->count_errors() > 0) {
                do_action('wp_login_failed', $user_name, UM()->form()->get_wp_error());
            }
        }


    }

    return new Ultimate_Member_Submit_Form_Errors_Login();
endif;