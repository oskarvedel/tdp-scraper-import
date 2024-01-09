<?php

/**
 * Plugin Name: tdp-scraper-import
 * Version: 1.0
 */

require_once dirname(__FILE__) . '/scraper-import.php';

function add_import_nl_data_button($links)
{
    $import_link = '<a href="' . esc_url(admin_url('admin-post.php?action=import_nl_data')) . '">Import NL Data</a>';
    array_unshift($links, $import_link);
    return $links;
}
add_filter('plugin_action_links_tdp-scraper-import/tdp-scraper-import-plugin.php', 'add_import_nl_data_button');

function handle_import_nl_data()
{
    import_nl_scraper_data(plugins_url('/data/storageUnitsData.json', __FILE__));
    wp_redirect(admin_url('plugins.php?s=tdp&plugin_status=all'));
    exit;
}
add_action('admin_post_import_nl_data', 'handle_import_nl_data');


function add_remove_nl_data_button($links)
{
    $remove_link = '<a href="' . esc_url(admin_url('admin-post.php?action=remove_nl_data')) . '">Remove NL Data</a>';
    array_unshift($links, $remove_link);
    return $links;
}
add_filter('plugin_action_links_tdp-scraper-import/tdp-scraper-import-plugin.php', 'add_remove_nl_data_button');

function handle_remove_nl_data()
{
    remove_nl_data();
    wp_redirect(admin_url('plugins.php?s=tdp&plugin_status=all'));
    exit;
}
add_action('admin_post_remove_nl_data', 'handle_remove_nl_data');
