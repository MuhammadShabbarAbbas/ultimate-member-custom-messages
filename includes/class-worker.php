<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('Ultimate_Member_Custom_Messages_Worker')):
    class Ultimate_Member_Custom_Messages_Worker
    {
        public function __construct()
        {
            add_action('um_submit_form_errors_hook_', [$this, 'um_submit_form_errors_hook_'], 999);
        }

        function um_submit_form_errors_hook_($args)
        {
            $form_id = $args['form_id'];
            $mode = $args['mode'];
            $fields = unserialize($args['custom_fields']);

            $um_profile_photo = um_profile('profile_photo');
            if (get_post_meta($form_id, '_um_profile_photo_required', true) && (empty($args['profile_photo']) && empty($um_profile_photo))) {
                UM()->form()->add_error('profile_photo', __('Profile Photo is required.', 'umcm'));
            }

            if (!empty($fields)) {

                $can_edit = false;
                $current_user_roles = [];
                if (is_user_logged_in()) {

                    $can_edit = UM()->roles()->um_current_user_can('edit', $args['user_id']);

                    um_fetch_user(get_current_user_id());
                    $current_user_roles = um_user('roles');
                    um_reset_user();
                }

                foreach ($fields as $key => $array) {

                    if ($mode == 'profile') {
                        $restricted_fields = UM()->fields()->get_restricted_fields_for_edit();
                        if (is_array($restricted_fields) && in_array($key, $restricted_fields)) {
                            continue;
                        }
                    }

                    $can_view = true;
                    if (isset($array['public']) && $mode != 'register') {

                        switch ($array['public']) {
                            case '1': // Everyone
                                break;
                            case '2': // Members
                                if (!is_user_logged_in()) {
                                    $can_view = false;
                                }
                                break;
                            case '-1': // Only visible to profile owner and admins
                                if (!is_user_logged_in()) {
                                    $can_view = false;
                                } elseif ($args['user_id'] != get_current_user_id() && !$can_edit) {
                                    $can_view = false;
                                }
                                break;
                            case '-2': // Only specific member roles
                                if (!is_user_logged_in()) {
                                    $can_view = false;
                                } elseif (!empty($array['roles']) && count(array_intersect($current_user_roles, $array['roles'])) <= 0) {
                                    $can_view = false;
                                }
                                break;
                            case '-3': // Only visible to profile owner and specific roles
                                if (!is_user_logged_in()) {
                                    $can_view = false;
                                } elseif ($args['user_id'] != get_current_user_id() && !empty($array['roles']) && count(array_intersect($current_user_roles, $array['roles'])) <= 0) {
                                    $can_view = false;
                                }
                                break;
                            default:
                                $can_view = apply_filters('um_can_view_field_custom', $can_view, $array);
                                break;
                        }

                    }

                    $can_view = apply_filters('um_can_view_field', $can_view, $array);

                    if (!$can_view) {
                        continue;
                    }


                    /**
                     * UM hook
                     *
                     * @type filter
                     * @title um_get_custom_field_array
                     * @description Extend custom field data on submit form error
                     * @input_vars
                     * [{"var":"$array","type":"array","desc":"Field data"},
                     * {"var":"$fields","type":"array","desc":"All fields"}]
                     * @change_log
                     * ["Since: 2.0"]
                     * @usage
                     * <?php add_filter( 'um_get_custom_field_array', 'function_name', 10, 2 ); ?>
                     * @example
                     * <?php
                     * add_filter( 'um_get_custom_field_array', 'my_get_custom_field_array', 10, 2 );
                     * function my_get_custom_field_array( $array, $fields ) {
                     *     // your code here
                     *     return $array;
                     * }
                     * ?>
                     */
                    $array = apply_filters('um_get_custom_field_array', $array, $fields);

                    if (!empty($array['conditions'])) {
                        try {
                            foreach ($array['conditions'] as $condition) {
                                $continue = um_check_conditions_on_submit($condition, $fields, $args, true);
                                if ($continue === true) {
                                    continue 2;
                                }
                            }
                        } catch (Exception $e) {
                            UM()->form()->add_error($key, sprintf(__('%s - wrong conditions.', 'umcm'), $array['title']));
                            $notice = '<div class="um-field-error">' . sprintf(__('%s - wrong conditions.', 'umcm'), $array['title']) . '</div><!-- ' . $e->getMessage() . ' -->';
                            add_action('um_after_profile_fields', function () use ($notice) {
                                echo $notice;
                            }, 900);
                        }
                    }

                    if (isset($array['type']) && $array['type'] == 'checkbox' && isset($array['required']) && $array['required'] == 1 && !isset($args[$key])) {
                        UM()->form()->add_error($key, sprintf(__('%s is required.', 'umcm'), $array['title']));
                    }

                    if (isset($array['type']) && $array['type'] == 'radio' && isset($array['required']) && $array['required'] == 1 && !isset($args[$key]) && !in_array($key, array('role_radio', 'role_select'))) {
                        UM()->form()->add_error($key, sprintf(__('%s is required.', 'umcm'), $array['title']));
                    }

                    if (isset($array['type']) && $array['type'] == 'multiselect' && isset($array['required']) && $array['required'] == 1 && !isset($args[$key]) && !in_array($key, array('role_radio', 'role_select'))) {
                        UM()->form()->add_error($key, sprintf(__('%s is required.', 'umcm'), $array['title']));
                    }

                    /* WordPress uses the default user role if the role wasn't chosen in the registration form. That is why we should use submitted data to validate fields Roles (Radio) and Roles (Dropdown). */
                    if (in_array($key, array('role_radio', 'role_select')) && isset($array['required']) && $array['required'] == 1 && empty(UM()->form()->post_form['submitted']['role'])) {
                        UM()->form()->add_error('role', __('Please specify account type.', 'umcm'));
                        UM()->form()->post_form[$key] = '';
                    }

                    /**
                     * UM hook
                     *
                     * @type action
                     * @title um_add_error_on_form_submit_validation
                     * @description Submit form validation
                     * @input_vars
                     * [{"var":"$field","type":"array","desc":"Field Data"},
                     * {"var":"$key","type":"string","desc":"Field Key"},
                     * {"var":"$args","type":"array","desc":"Form Arguments"}]
                     * @change_log
                     * ["Since: 2.0"]
                     * @usage add_action( 'um_add_error_on_form_submit_validation', 'function_name', 10, 3 );
                     * @example
                     * <?php
                     * add_action( 'um_add_error_on_form_submit_validation', 'my_add_error_on_form_submit_validation', 10, 3 );
                     * function my_add_error_on_form_submit_validation( $field, $key, $args ) {
                     *     // your code here
                     * }
                     * ?>
                     */
                    do_action('um_add_error_on_form_submit_validation', $array, $key, $args);

                    if (!empty($array['required'])) {
                        if (!isset($args[$key]) || $args[$key] == '' || $args[$key] == 'empty_file') {
                            if (empty($array['label'])) {
                                UM()->form()->add_error($key, __('This field is required', 'umcm'));
                            } else {
                                UM()->form()->add_error($key, sprintf(__('%s is required', 'umcm'), $array['label']));
                            }
                        }
                    }

                    if (isset($args[$key])) {

                        if (isset($array['max_words']) && $array['max_words'] > 0) {
                            if (str_word_count($args[$key], 0, "éèàôù") > $array['max_words']) {
                                UM()->form()->add_error($key, sprintf(__('You are only allowed to enter a maximum of %s words', 'umcm'), $array['max_words']));
                            }
                        }

                        if (isset($array['min_chars']) && $array['min_chars'] > 0) {
                            if ($args[$key] && mb_strlen($args[$key]) < $array['min_chars']) {
                                if (empty($array['label'])) {
                                    UM()->form()->add_error($key, sprintf(__('This field must contain at least %s characters', 'umcm'), $array['min_chars']));
                                } else {
                                    UM()->form()->add_error($key, sprintf(__('Your %s must contain at least %s characters', 'umcm'), $array['label'], $array['min_chars']));
                                }
                            }
                        }

                        if (isset($array['max_chars']) && $array['max_chars'] > 0) {
                            if ($args[$key] && mb_strlen($args[$key]) > $array['max_chars']) {
                                if (empty($array['label'])) {
                                    UM()->form()->add_error($key, sprintf(__('This field must contain less than %s characters', 'umcm'), $array['max_chars']));
                                } else {
                                    UM()->form()->add_error($key, sprintf(__('Your %s must contain less than %s characters', 'umcm'), $array['label'], $array['max_chars']));
                                }
                            }
                        }

                        if (isset($array['type']) && $array['type'] == 'textarea' && UM()->profile()->get_show_bio_key($args) !== $key) {
                            if (!isset($array['html']) || $array['html'] == 0) {
                                if (wp_strip_all_tags($args[$key]) != trim($args[$key])) {
                                    UM()->form()->add_error($key, __('You can not use HTML tags here', 'umcm'));
                                }
                            }
                        }

                        if (isset($array['force_good_pass']) && $array['force_good_pass'] == 1) {
                            if (!UM()->validation()->strong_pass($args[$key])) {
                                UM()->form()->add_error($key, __('Your password must contain at least one lowercase letter, one capital letter and one number', 'umcm'));
                            }
                        }

                        if (isset($array['force_confirm_pass']) && $array['force_confirm_pass'] == 1) {
                            if ($args['confirm_' . $key] == '' && !UM()->form()->has_error($key)) {
                                UM()->form()->add_error('confirm_' . $key, __('Please confirm your password', 'umcm'));
                            }
                            if ($args['confirm_' . $key] != $args[$key] && !UM()->form()->has_error($key)) {
                                UM()->form()->add_error('confirm_' . $key, __('Your passwords do not match', 'umcm'));
                            }
                        }

                        if (isset($array['min_selections']) && $array['min_selections'] > 0) {
                            if ((!isset($args[$key])) || (isset($args[$key]) && is_array($args[$key]) && count($args[$key]) < $array['min_selections'])) {
                                UM()->form()->add_error($key, sprintf(__('Please select at least %s choices', 'umcm'), $array['min_selections']));
                            }
                        }

                        if (isset($array['max_selections']) && $array['max_selections'] > 0) {
                            if (isset($args[$key]) && is_array($args[$key]) && count($args[$key]) > $array['max_selections']) {
                                UM()->form()->add_error($key, sprintf(__('You can only select up to %s choices', 'umcm'), $array['max_selections']));
                            }
                        }

                        if (isset($array['min']) && is_numeric($args[$key])) {
                            if (isset($args[$key]) && $args[$key] < $array['min']) {
                                UM()->form()->add_error($key, sprintf(__('Minimum number limit is %s', 'umcm'), $array['min']));
                            }
                        }

                        if (isset($array['max']) && is_numeric($args[$key])) {
                            if (isset($args[$key]) && $args[$key] > $array['max']) {
                                UM()->form()->add_error($key, sprintf(__('Maximum number limit is %s', 'umcm'), $array['max']));
                            }
                        }

                        if (!empty($array['validate'])) {

                            switch ($array['validate']) {

                                case 'custom':
                                    $custom = $array['custom_validate'];
                                    /**
                                     * UM hook
                                     *
                                     * @type action
                                     * @title um_custom_field_validation_{$custom}
                                     * @description Submit form validation for custom field
                                     * @input_vars
                                     * [{"var":"$key","type":"string","desc":"Field Key"},
                                     * {"var":"$field","type":"array","desc":"Field Data"},
                                     * {"var":"$args","type":"array","desc":"Form Arguments"}]
                                     * @change_log
                                     * ["Since: 2.0"]
                                     * @usage add_action( 'um_custom_field_validation_{$custom}', 'function_name', 10, 3 );
                                     * @example
                                     * <?php
                                     * add_action( 'um_custom_field_validation_{$custom}', 'my_custom_field_validation', 10, 3 );
                                     * function my_custom_field_validation( $key, $field, $args ) {
                                     *     // your code here
                                     * }
                                     * ?>
                                     */
                                    do_action("um_custom_field_validation_{$custom}", $key, $array, $args);
                                    break;

                                case 'numeric':
                                    if ($args[$key] && !is_numeric($args[$key])) {
                                        UM()->form()->add_error($key, __('Please enter numbers only in this field', 'umcm'));
                                    }
                                    break;

                                case 'phone_number':
                                    if (!UM()->validation()->is_phone_number($args[$key])) {
                                        UM()->form()->add_error($key, __('Please enter a valid phone number', 'umcm'));
                                    }
                                    break;

                                case 'youtube_url':
                                    if (!UM()->validation()->is_url($args[$key], 'youtube.com')) {
                                        UM()->form()->add_error($key, sprintf(__('Please enter a valid %s username or profile URL', 'umcm'), $array['label']));
                                    }
                                    break;

                                case 'telegram_url':
                                    if (!UM()->validation()->is_url($args[$key], 't.me')) {
                                        UM()->form()->add_error($key, sprintf(__('Please enter a valid %s username or profile URL', 'umcm'), $array['label']));
                                    }
                                    break;

                                case 'soundcloud_url':
                                    if (!UM()->validation()->is_url($args[$key], 'soundcloud.com')) {
                                        UM()->form()->add_error($key, sprintf(__('Please enter a valid %s username or profile URL', 'umcm'), $array['label']));
                                    }
                                    break;

                                case 'facebook_url':
                                    if (!UM()->validation()->is_url($args[$key], 'facebook.com')) {
                                        UM()->form()->add_error($key, sprintf(__('Please enter a valid %s username or profile URL', 'umcm'), $array['label']));
                                    }
                                    break;

                                case 'twitter_url':
                                    if (!UM()->validation()->is_url($args[$key], 'twitter.com')) {
                                        UM()->form()->add_error($key, sprintf(__('Please enter a valid %s username or profile URL', 'umcm'), $array['label']));
                                    }
                                    break;

                                case 'instagram_url':

                                    if (!UM()->validation()->is_url($args[$key], 'instagram.com')) {
                                        UM()->form()->add_error($key, sprintf(__('Please enter a valid %s profile URL', 'umcm'), $array['label']));
                                    }
                                    break;

                                case 'google_url':
                                    if (!UM()->validation()->is_url($args[$key], 'plus.google.com')) {
                                        UM()->form()->add_error($key, sprintf(__('Please enter a valid %s username or profile URL', 'umcm'), $array['label']));
                                    }
                                    break;

                                case 'linkedin_url':
                                    if (!UM()->validation()->is_url($args[$key], 'linkedin.com')) {
                                        UM()->form()->add_error($key, sprintf(__('Please enter a valid %s username or profile URL', 'umcm'), $array['label']));
                                    }
                                    break;

                                case 'vk_url':
                                    if (!UM()->validation()->is_url($args[$key], 'vk.com')) {
                                        UM()->form()->add_error($key, sprintf(__('Please enter a valid %s username or profile URL', 'umcm'), $array['label']));
                                    }
                                    break;

                                case 'discord':
                                    if (!UM()->validation()->is_discord_id($args[$key])) {
                                        UM()->form()->add_error($key, __('Please enter a valid Discord ID', 'umcm'));
                                    }
                                    break;

                                case 'url':
                                    if (!UM()->validation()->is_url($args[$key])) {
                                        UM()->form()->add_error($key, __('Please enter a valid URL', 'umcm'));
                                    }
                                    break;

                                case 'unique_username':

                                    if ($args[$key] == '') {
                                        UM()->form()->add_error($key, __('You must provide a username', 'umcm'));
                                    } elseif ($mode == 'register' && username_exists(sanitize_user($args[$key]))) {
                                        UM()->form()->add_error($key, __('Username already exists', 'umcm'));
                                    } elseif (is_email($args[$key])) {
                                        UM()->form()->add_error($key, __('Username cannot be an email', 'umcm'));
                                    } elseif (!UM()->validation()->safe_username($args[$key])) {
                                        UM()->form()->add_error($key, __('Your username contains invalid characters', 'umcm'));
                                    }

                                    break;

                                case 'unique_username_or_email':

                                    if ($args[$key] == '') {
                                        UM()->form()->add_error($key, __('You must provide a username or email', 'umcm'));
                                    } elseif ($mode == 'register' && username_exists(sanitize_user($args[$key]))) {
                                        UM()->form()->add_error($key, __('The username you entered is incorrect', 'umcm'));
                                    } elseif ($mode == 'register' && email_exists($args[$key])) {
                                        UM()->form()->add_error($key, __('This email already exists', 'umcm'));
                                    } elseif (!UM()->validation()->safe_username($args[$key])) {
                                        UM()->form()->add_error($key, __('Your username contains invalid characters', 'umcm'));
                                    }

                                    break;

                                case 'unique_email':

                                    $args[$key] = trim($args[$key]);

                                    if (in_array($key, array('user_email'))) {

                                        if (!isset($args['user_id'])) {
                                            $args['user_id'] = um_get_requested_user();
                                        }

                                        $email_exists = email_exists($args[$key]);

                                        if ($args[$key] == '' && in_array($key, array('user_email'))) {
                                            UM()->form()->add_error($key, __('You must provide your email', 'umcm'));
                                        } elseif (in_array($mode, array('register')) && $email_exists) {
                                            UM()->form()->add_error($key, __('This email already exists', 'umcm'));
                                        } elseif (in_array($mode, array('profile')) && $email_exists && $email_exists != $args['user_id']) {
                                            UM()->form()->add_error($key, __('This email already exists', 'umcm'));
                                        } elseif (!is_email($args[$key])) {
                                            UM()->form()->add_error($key, __('The email you entered is incorrect', 'umcm'));
                                        } elseif (!UM()->validation()->safe_username($args[$key])) {
                                            UM()->form()->add_error($key, __('Your email contains invalid characters', 'umcm'));
                                        }

                                    } else {

                                        if ($args[$key] != '' && !is_email($args[$key])) {
                                            UM()->form()->add_error($key, __('The email you entered is incorrect', 'umcm'));
                                        } elseif ($args[$key] != '' && email_exists($args[$key])) {
                                            UM()->form()->add_error($key, __('This email already exists', 'umcm'));
                                        } elseif ($args[$key] != '') {

                                            $users = get_users('meta_value=' . $args[$key]);

                                            foreach ($users as $user) {
                                                if ($user->ID != $args['user_id']) {
                                                    UM()->form()->add_error($key, __('The email you entered is incorrect', 'umcm'));
                                                }
                                            }

                                        }

                                    }

                                    break;

                                case 'is_email':

                                    $args[$key] = trim($args[$key]);

                                    if ($args[$key] != '' && !is_email($args[$key])) {
                                        UM()->form()->add_error($key, __('This is not a valid email', 'umcm'));
                                    }

                                    break;

                                case 'unique_value':

                                    if ($args[$key] != '') {

                                        $args_unique_meta = array(
                                            'meta_key' => $key,
                                            'meta_value' => $args[$key],
                                            'compare' => '=',
                                            'exclude' => array($args['user_id']),
                                        );

                                        $meta_key_exists = get_users($args_unique_meta);

                                        if ($meta_key_exists) {
                                            UM()->form()->add_error($key, __('You must provide a unique value', 'umcm'));
                                        }
                                    }
                                    break;

                                case 'alphabetic':

                                    if ($args[$key] != '') {

                                        if (!preg_match('/^\p{L}+$/u', str_replace(' ', '', $args[$key]))) {
                                            UM()->form()->add_error($key, __('You must provide alphabetic letters', 'umcm'));
                                        }

                                    }

                                    break;

                                case 'lowercase':

                                    if ($args[$key] != '') {

                                        if (!ctype_lower(str_replace(' ', '', $args[$key]))) {
                                            UM()->form()->add_error($key, __('You must provide lowercase letters.', 'umcm'));
                                        }
                                    }

                                    break;

                            }

                        }

                    }

                    if (isset($args['description'])) {
                        $max_chars = UM()->options()->get('profile_bio_maxchars');
                        $profile_show_bio = UM()->options()->get('profile_show_bio');

                        if ($profile_show_bio) {
                            if (mb_strlen(str_replace(array("\r\n", "\n", "\r\t", "\t"), ' ', $args['description'])) > $max_chars && $max_chars) {
                                UM()->form()->add_error('description', sprintf(__('Your user description must contain less than %s characters', 'umcm'), $max_chars));
                            }
                        }

                    }

                } // end if ( isset in args array )
            }
        }


    }

    return new Ultimate_Member_Custom_Messages_Worker();
endif;