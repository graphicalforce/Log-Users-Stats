<?php
/**
 * Plugin Name: Log Users Stats
 * Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
 * Description: A plugin that displays the 'Total Minutes', 'Number of Logins', and 'Average Time in Minutes Per Login' that each user has logged on the site. Stats can be exported to csv, and can be reset.
 * Version: 1.0
 * Author: Jeff Freeman
 * Author URI: http://graphicalforce.com
 * License: GPL2
 */

function freeman_your_last_login_time($login) {
    global $user_ID;
    $user = get_user_by('login', $login);
    $time_start = time();
    update_user_meta($user->ID, 'start_time', $time_start);
}
add_action('wp_login','freeman_your_last_login_time');

function freeman_get_time_on_logout($user_id) {
    global $user_ID;
    $user = get_user_by('id', $user_ID);
    $time_end = time();
    $time_start = get_user_meta($user->ID, 'start_time', true);
    $total_time = (intval($time_end) - intval($time_start));
    $total_time = round($total_time/60);
    $total_all_time = get_user_meta($user->ID, 'total_time', true);
    $total_time = $total_all_time + $total_time;
    update_user_meta($user->ID, 'total_time', $total_time);


    $logged_in_amount = get_user_meta($user->ID, 'logged_in_amount', true);
    $logged_in_amount = $logged_in_amount + 1;
    update_user_meta($user->ID, 'logged_in_amount', $logged_in_amount);

    $average_time = ($total_time/$logged_in_amount);
    update_user_meta($user->ID, 'average_time', $average_time);
}
add_action('wp_logout', 'freeman_get_time_on_logout');

add_filter('manage_users_columns', 'freeman_add_user_minutes_column');
function freeman_add_user_minutes_column($columns) {
    $columns['total_time'] = 'Total Minutes';
    $columns['logged_in_amount'] = '# of Logins';
    $columns['average_time'] = 'Ave. Min./Login';
    return $columns;
}
 
add_action('manage_users_custom_column',  'freeman_show_user_minutes_column_content', 10, 3);
function freeman_show_user_minutes_column_content($value, $column_name, $user_id) {
    $output = " ";
    $user = get_userdata( $user_id );
    if ( 'total_time' == $column_name )
        $output .= ($user->total_time);
    if ( 'logged_in_amount' == $column_name )
        $output .= ($user->logged_in_amount);
    if ( 'average_time' == $column_name )
        $output .= ($user->average_time);
    return $output;
}

add_action('admin_footer', 'freeman_custom_user_buttons');
function freeman_custom_user_buttons() {
    $screen = get_current_screen();
    if ( $screen->id != "users" )   // Only add to users.php page
        return;
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('<option>').val('del_user_meta').text('Delete User Logs').appendTo("select[name='action']");
            $('<option>').val('export_user_meta').text('Export User Logs').appendTo("select[name='action']");
        });
    </script>
    <?php
} 

add_action('load-users.php', 'freeman_delete_users_info');
function freeman_delete_users_info() {
    if(isset($_GET['action']) && $_GET['action'] === 'del_user_meta') {  // Check if our custom action was selected
        $del_users = $_GET['users'];  // Get array of user id's which were selected for meta deletion
        if ($del_users) {  // If any users were selected
            foreach ($del_users as $del_user) {
            delete_user_meta($del_user, 'logged_in_amount');
            delete_user_meta($del_user, 'total_time');
            delete_user_meta($del_user, 'average_time');
            }
        }
    }
}

add_action('load-users.php', 'freeman_export_users_info');
function freeman_export_users_info() {
    if(isset($_GET['action']) && $_GET['action'] === 'export_user_meta') { 
        $del_users = $_GET['users'];  
        if ($del_users) { 
        $fp = fopen('file.csv', 'w');
        $User_Name_Row = array("USERNAME", "Total Minutes", "# of Logins", "Ave. Min./Login");
        fputcsv($fp, $User_Name_Row);
            foreach ($del_users as $del_user) {
                $user_info = get_userdata($del_user);
                $user_name = ($user_info->user_login);
                $logged_in_amount = get_user_meta($del_user, 'logged_in_amount', true);
                $total_time = get_user_meta($del_user, 'total_time', true);
                $average_time = get_user_meta($del_user, 'average_time', true);

                $list = array (
                        array ($user_name, $total_time, $logged_in_amount, $average_time)
                    );
                
                foreach ($list as $fields) {
                    fputcsv($fp, $fields);
                }
            }
        }

        fclose($fp);

        $file="file.csv"; //file location 
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($file).'"'); 
        header('Content-Length: ' . filesize($file));
        readfile($file);
    }
}