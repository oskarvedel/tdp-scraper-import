<?php

function remove_boxdepotet_data()
{

    remove_boxdepotet_unit_links();
    remove_boxdepotet_unit_types();
}

function remove_boxdepotet_unit_types()
{
    $boxdepotet_unit_types_ids = get_boxdepotet_unit_types();
    foreach ($boxdepotet_unit_types_ids as $unit_type_id => $unit_type_name) {
        wp_delete_post($unit_type_id, true);
    }
    trigger_error('boxdepotet unit types removed, deleted ' . count($boxdepotet_unit_types_ids) . ' unit types', E_USER_NOTICE);
}

function remove_boxdepotet_unit_links()
{
    $boxdepotet_unit_links_ids = get_boxdepotet_unit_links();
    foreach ($boxdepotet_unit_links_ids as $unit_link_id) {
        wp_delete_post($unit_link_id, true);
    }
    trigger_error('boxdepotet unit links removed, deleted ' . count($boxdepotet_unit_links_ids) . ' unit links', E_USER_NOTICE);
}

function import_boxdepotet_scraper_data()
{
    // xdebug_break();

    //call https://boxdepotet-unit-scraper.onrender.com/scrape/boxdepotet to get the latest data
    $url = 'https://boxdepotet-unit-scraper.onrender.com/scrape/boxdepotet';
    //set the wp_remote_get timout to 5 minutes
    add_filter('http_request_timeout', function () {
        return 300;
    });
    $response = wp_remote_get($url);
    if (is_wp_error($response)) {
        trigger_error('Error getting boxdepotet data: ' . $response->get_error_message(), E_USER_WARNING);
        return;
    }

    $file = plugins_url('/data/boxdepotetUnits.json', __FILE__);
    //remove unit links
    remove_boxdepotet_unit_links();

    //get the ids and urls of the boxdepotet locations
    $boxdepotet_locations_urls = get_all_boxdepotet_locations_ids_and_partner_department_urls();

    // Get the user ID for "boxdepotet"
    $user = get_user_by('login', 'boxdepotet');
    $user_id = $user ? $user->ID : 0; // If the user doesn't exist, use 0
    if ($user_id == 0) {
        trigger_error('User "boxdepotet" not found', E_USER_WARNING);
        return;
    }

    //open the file and serialize the json data
    $json = $response['body'];
    $data = json_decode($json, true);

    unset($json);

    //serialize the data
    $sanitized_data = sanitize_boxdepotet_data($data);
    unset($data);

    //get the unique units
    $unique_units = get_boxdepotet_unique_units($sanitized_data);

    //get any existing unit types
    $existing_unit_types = get_boxdepotet_unit_types();

    //create the unit types
    $new_unit_types = create_boxdepotet_unit_types($unique_units, $user_id, $existing_unit_types);
    trigger_error('Created ' . count($new_unit_types) . ' boxdepotet unit types', E_USER_NOTICE);

    unset($unique_units);

    //set the unit_types to the existing unit types + the new unit types
    $unit_types = $existing_unit_types + $new_unit_types;
    //make sure each unit type in the array has a unique id
    $unit_types = array_unique($unit_types);

    //create the unit links
    create_boxdepotet_unit_links($sanitized_data, $boxdepotet_locations_urls, $unit_types, $user_id);
    trigger_error('Created all boxdepotet unit links', E_USER_NOTICE);
    return;
}

function create_boxdepotet_unit_links($sanitized_data, $boxdepotet_locations_urls, $unit_types, $user_id)
{
    $batch_size = 10; // Adjust this to a suitable size

    // Split the data into batches
    $batches = array_chunk($sanitized_data, $batch_size);

    // Use WordPress's built-in object caching, if available, to store titles
    $cached_titles = [];

    // Loop through the batches
    foreach ($batches as $batch_index => $batch) {
        // Loop through the data in the current batch
        foreach ($batch as $item) {
            // Get the gd_place id
            $gd_place_id = array_search($item['url'], $boxdepotet_locations_urls);
            if (!$gd_place_id) {
                trigger_error('gd_place not found for boxdepotet url: ' . $item['url'], E_USER_WARNING);
                continue;
            }

            // Check if the title is already cached
            if (!isset($cached_titles[$gd_place_id])) {
                $cached_titles[$gd_place_id] = get_the_title($gd_place_id);
            }
            $title = $cached_titles[$gd_place_id];

            // Create the unit links
            foreach ($item['singleLocationsUnitData'] as $unitData) {
                $unit_type_id = array_search(get_boxdepotet_unit_type_name($unitData), $unit_types);
                $unit_link_id = wp_insert_post(array(
                    'post_title' => $title  . ' link: ' . $unitData['m2'] . ' m2 / ' . $unitData['m3'] . ' m3',
                    'post_type' => 'unit_link',
                    'post_status' => 'publish',
                    'post_author' => $user_id
                ));

                // Set the price and availability
                update_post_meta($unit_link_id, 'price', $unitData['price']);
                if ($unitData['available'] == 0) {
                    update_post_meta($unit_link_id, 'available', '1');
                } else {
                    update_post_meta($unit_link_id, 'available', '0');
                    update_post_meta($unit_link_id, 'available_date', $unitData['available']);
                }

                //set the bookUrl
                update_post_meta($unit_link_id, 'booking_link', $unitData['bookUrl']);

                // Add the unit type and gd_place to the unit link
                update_post_meta($unit_link_id, 'rel_type', $unit_type_id);
                update_post_meta($unit_link_id, 'rel_lokation', $gd_place_id);
            }

            // Log how many unit links were created for the gd_place
            trigger_error('Created ' . count($item['singleLocationsUnitData']) . ' boxdepotet unit links for gd_place: ' . $title, E_USER_NOTICE);
        }
        // Free memory after processing each batch
        unset($batch);
    }

    // Free memory by unsetting the cached titles
    unset($cached_titles);
}


function create_boxdepotet_unit_types($unique_units, $user_id, $existing_unit_types)
{
    $new_unit_types = array(); // Array to store the new unit types

    foreach ($unique_units as $unit) {
        // Check if the unit type already exists
        $unitName = get_boxdepotet_unit_type_name($unit);
        $existing_unit_type_id = array_search($unitName, $existing_unit_types);
        if ($existing_unit_type_id) {
            continue; // Skip creating the unit type if it already exists
        }

        // Get the unit type name
        $unit_type_name = get_boxdepotet_unit_type_name($unit);
        $unit_type_id = wp_insert_post(array(
            'post_title' => $unit_type_name,
            'post_type' => 'unit_type',
            'post_status' => 'publish',
            'post_author' => $user_id
        ));

        // Set the m2 and m3 sizes
        update_post_meta($unit_type_id, 'm2', $unit['m2']);
        update_post_meta($unit_type_id, 'm3', $unit['m3']);

        // Set the unit_type
        update_post_meta($unit_type_id, 'unit_type', 'indoor');

        $new_unit_types[$unit_type_id] = $unit_type_name; // Set the name as the value of the returned array

        // trigger_error('Created unit type: ' . $unit_type_name, E_USER_NOTICE);
    }

    return $new_unit_types; // Return the array of new unit types with names as values
}

function get_boxdepotet_unit_type_name($unit)
{
    return 'boxdepotet type: ' . $unit['m2'] . ' m2 / ' . $unit['m3'] . ' m3';
}

function get_boxdepotet_unique_units($data)
{
    $uniqueUnits = array();
    $seen = array();

    foreach ($data as $item) {
        foreach ($item['singleLocationsUnitData'] as $unitData) {
            $key = $unitData['m2'] . '-' . $unitData['m3'];

            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $uniqueUnits[] = $unitData;
            }
        }
    }

    return $uniqueUnits;
}


function sanitize_boxdepotet_data($data)
{
    return array_map(function ($item) {
        $sanitizedData = array_map(function ($unitData) {
            return array(
                'm2' => str_replace(" m2", "", $unitData['squareMeters']),
                'm3' => str_replace(" m3", "", $unitData['cubicMeters']),
                'available' => intval(preg_replace("/[^0-9]/", "", $unitData['availability'])),
                'price' => floatval(preg_replace("/[^0-9\.]/", "", $unitData['price'])),
                'bookUrl' => $unitData['bookUrl']
            );
        }, $item['roomData']);

        return array(
            'url' => $item['url'],
            'singleLocationsUnitData' => $sanitizedData
        );
    }, $data);
}

function get_all_boxdepotet_locations_ids_and_partner_department_urls()
{
    $args = array(
        'post_type' => 'gd_place',
        'author_name' => 'boxdepotet',
        'posts_per_page' => -1,
        'fields' => 'ids'
    );

    $ids = get_posts($args);

    //the the boxdepotetdk url for each gd_place
    $posts = array();
    foreach ($ids as $id) {
        $partner_department_url = get_post_meta($id, 'partner_department_url', true);
        $posts[$id] = $partner_department_url;
    }

    return $posts;
}

function get_boxdepotet_unit_types()
{
    $args = array(
        'post_type' => 'unit_type',
        'author_name' => 'boxdepotet',
        'posts_per_page' => -1,
        'fields' => 'ids'
    );

    $unit_types_ids = get_posts($args);

    //for each unit type, set the key as the unit type name
    $unit_types = array();
    foreach ($unit_types_ids as $id) {
        $unit_types[$id] = get_the_title($id);
    }
    return $unit_types;
}

function get_boxdepotet_unit_links()
{
    $args = array(
        'post_type' => 'unit_link',
        'author_name' => 'boxdepotet',
        'posts_per_page' => -1,
        'fields' => 'ids'
    );

    $unit_links_ids = get_posts($args);

    return $unit_links_ids;
}
