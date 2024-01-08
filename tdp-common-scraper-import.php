<?php

$statistics_data_fields = array(
    'num of units available',
    'num of m2 available',
    'num of m3 available',
    'average price',
    'lowest price',
    'highest price',
    'smallest m2 size',
    'largest m2 size',
    'average m2 price',
    'average m3 price',
    'mini size lowest price',
    'mini size highest price',
    'mini size average price',
    'mini size average m2 price',
    'mini size average m3 price',
    'small size lowest price',
    'small size highest price',
    'small size average price',
    'small size average m2 price',
    'small size average m3 price',
    'medium size lowest price',
    'medium size highest price',
    'medium size average price',
    'medium size average m2 price',
    'medium size average m3 price',
    'large size lowest price',
    'large size highest price',
    'large size average price',
    'large size average m2 price',
    'large size average m3 price',
    'very large size lowest price',
    'very large size highest price',
    'very large size average price',
    'very large size average m2 price',
    'very large size average m3 price'
);

/**
 * Extracts the geolocation slug from the current URL.
 *
 * This function uses the global $wp object to get the current request URL,
 * parses the URL to get the path, and then extracts the last part of the path
 * as the geolocation slug.
 *
 * @return string The geolocation slug extracted from the URL.
 */
function extract_geolocation_slug_via_url_scraper_import()
{

    // Access the global $wp object
    global $wp;

    // Get the current request URL
    $url = home_url($wp->request);

    // Parse the URL to get the path
    $parsedUrl = parse_url($url);

    if (!isset($parsedUrl['path'])) {
        return '';
    }

    // Split the path into parts
    $pathParts = explode('/', trim($parsedUrl['path'], '/'));

    // Get the last part of the path as the slug
    $slug = end($pathParts);

    // Return the slug
    return $slug;
}

/**
 * Extracts the geolocation ID from the current URL.
 *
 * This function first calls the extract_geolocation_slug_via_url function to get
 * the geolocation slug from the URL. It then uses the Pods plugin to get the
 * geolocation object associated with the slug, and extracts the ID of the
 * geolocation object.
 *
 * @return int|null The geolocation ID if found, null otherwise.
 */
function extract_geolocation_id_via_url_scraper_import()
{

    // Get the geolocation slug from the URL
    $slug = extract_geolocation_slug_via_url_unit_list();

    // Use the Pods plugin to get the geolocation object associated with the slug
    $slug_test = pods('geolocations', $slug);

    // Initialize the geolocation ID as null
    $geolocation_id = null;

    // If the geolocation object exists, extract its ID
    if ($slug_test && $slug_test->exists()) {
        $geolocation_id = $slug_test->field('ID');
    }

    // Return the geolocation ID
    return $geolocation_id;
}

function get_statistics_data_for_single_gd_place_scraper_import($gd_place_id)
{
    global $statistics_data_fields;

    $return_array = [];

    foreach ($statistics_data_fields as $field) {
        $value = get_post_meta($gd_place_id, $field, true);
        $return_array[$field] = $value;
    }

    if (count(array_filter($return_array, function($value) { return $value !== ""; })) == 0) {
        return false;
    } else {
        return $return_array;
    }
}
