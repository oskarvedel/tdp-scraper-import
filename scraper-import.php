<?php


function import_nl_scraper_data($file)
{
    //get the ids of the unit types and unit links
    $nl_unit_types_ids = get_nl_unit_types();
    $nl_unit_links_ids = get_nl_unit_links();
    //get the ids and urls of the nl locations
    $nl_locations_urls = get_all_nl_locations_ids_and_nldk_urls();

    // Get the user ID for "nettolager"
    $user = get_user_by('login', 'nettolager');
    $user_id = $user ? $user->ID : 0; // If the user doesn't exist, use 0
    if ($user_id == 0) {
        trigger_error('User "nettolager" not found', E_USER_WARNING);
        return;
    }

    //delete existing unit types and unit links
    foreach ($nl_unit_types_ids as $unit_type_id) {
        wp_delete_post($unit_type_id, true);
    }
    foreach ($nl_unit_links_ids as $unit_link_id) {
        wp_delete_post($unit_link_id, true);
    }

    //open the file and serialize the json data
    $json = file_get_contents($file);
    $data = json_decode($json, true);

    //serialize the data
    $sanitized_data = sanitize_data($data);

    //get the unique units
    $unique_units = get_unique_units($sanitized_data);

    //create the unit types
    $unit_types = create_unit_types($unique_units, $user_id);

    //create the unit links
    $unit_links = create_unit_links($sanitized_data, $nl_locations_urls, $unit_types, $user_id);
}

function create_unit_links($sanitized_data, $nl_locations_urls, $unit_types, $user_id)
{
    //loop through the data and create the unit types and unit links
    foreach ($sanitized_data as $item) {
        //get the gd_place id
        $gd_place_id = array_search($item['url'], $nl_locations_urls);
        if (!$gd_place_id) {
            trigger_error('gd_place not found for nettolager url: ' . $item['url'], E_USER_WARNING);
            continue;
        }
        $gd_place_title = get_the_title($gd_place_id);

        //create the unit links
        foreach ($item['singleLocationsUnitData'] as $unitData) {
            $unit_type_id = array_search(get_unit_type_name($unitData), $unit_types);
            $unit_link_id = wp_insert_post(array(
                'post_title' => $gd_place_title  . ' link: ' . $unitData['m2'] . ' m2 - ' . $unitData['m3'] . ' m3',
                'post_type' => 'unit_link',
                'post_status' => 'publish',
                'post_author' => $user_id
            ));

            //set the price
            update_post_meta($unit_link_id, 'price', $unitData['price']);

            $available = $unitData['available'];

            //set avaliability
            update_post_meta($unit_link_id, 'available', $unitData['available']);

            //add the unit type and gd_place to the unit link
            update_post_meta($unit_link_id, 'rel_type', $unit_type_id);
            update_post_meta($unit_link_id, 'rel_lokation', $gd_place_id);
        }
    }
}

function create_unit_types($unique_units, $user_id)
{
    $new_unit_types = array(); // Array to store the new unit types

    foreach ($unique_units as $unit) {
        //get the unit type name
        $unit_type_name = get_unit_type_name($unit);
        $unit_type_id = wp_insert_post(array(
            'post_title' => $unit_type_name,
            'post_type' => 'unit_type',
            'post_status' => 'publish',
            'post_author' => $user_id
        ));

        //set the m2 and m3 sizes
        update_post_meta($unit_type_id, 'm2', $unit['m2']);
        update_post_meta($unit_type_id, 'm3', $unit['m3']);

        //set the unit_type
        update_post_meta($unit_type_id, 'unit_type', 'indoor');

        $new_unit_types[$unit_type_id] = $unit_type_name; // Set the name as the value of the returned array
    }

    return $new_unit_types; // Return the array of new unit types with names as values
}

function get_unit_type_name($unit)
{
    return 'nettolager type: ' . $unit['m2'] . ' m2 - ' . $unit['m3'] . ' m3';
}

function get_unique_units($data)
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


function sanitize_data($data)
{
    return array_map(function ($item) {
        $sanitizedData = array_map(function ($unitData) {
            return array(
                'm2' => str_replace(" m2", "", $unitData['m2']),
                'm3' => str_replace(" m3", "", $unitData['m3']),
                'available' => intval(preg_replace("/[^0-9]/", "", $unitData['available'])),
                'price' => floatval(preg_replace("/[^0-9\.]/", "", $unitData['price']))
            );
        }, $item['singleLocationsUnitData']);

        return array(
            'url' => $item['url'],
            'singleLocationsUnitData' => $sanitizedData
        );
    }, $data);
}

function get_all_nl_locations_ids_and_nldk_urls()
{
    $args = array(
        'post_type' => 'gd_place',
        'author_name' => 'nettolager',
        'posts_per_page' => -1,
        'fields' => 'ids'
    );

    $ids = get_posts($args);

    //the the nldk url for each gd_place
    $posts = array();
    foreach ($ids as $id) {
        $nldk_url = get_post_meta($id, 'nldk_url', true);
        $posts[$id] = $nldk_url;
    }

    return $posts;
}

function get_nl_unit_types()
{
    $args = array(
        'post_type' => 'unit_type',
        'author_name' => 'nettolager',
        'posts_per_page' => -1,
        'fields' => 'ids'
    );

    $unit_types_ids = get_posts($args);

    return $unit_types_ids;
}

function get_nl_unit_links()
{
    $args = array(
        'post_type' => 'unit_link',
        'author_name' => 'nettolager',
        'posts_per_page' => -1,
        'fields' => 'ids'
    );

    $unit_links_ids = get_posts($args);

    return $unit_links_ids;
}
