<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Generate fixtures based on user input and save them as SportsPress events
 */
function mkfg_generate_fixtures() {
    // Get league and season inputs
    $league = isset($_POST['mkfg_league']) && $_POST['mkfg_league'] > 0 ? intval($_POST['mkfg_league']) : 0;
    $season = isset($_POST['mkfg_season']) && $_POST['mkfg_season'] > 0 ? intval($_POST['mkfg_season']) : 0;
    $method = isset($_POST['mkfg_method']) && in_array($_POST['mkfg_method'], ['one_way', 'round_robin']) ? $_POST['mkfg_method'] : 'one_way';
    $start_date = isset($_POST['mkfg_start_date']) && !empty($_POST['mkfg_start_date']) ? sanitize_text_field($_POST['mkfg_start_date']) : false;
    $start_time = isset($_POST['mkfg_start_time']) && !empty($_POST['mkfg_start_time']) ? sanitize_text_field($_POST['mkfg_start_time']) : '00:00:00';

    // Determine teams to use
    if ($league || $season) {
        $args = [
            'post_type' => 'sp_team',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'tax_query' => [],
        ];

        if ($league) {
            $args['tax_query'][] = [
                'taxonomy' => 'sp_league',
                'field' => 'term_id',
                'terms' => $league,
            ];
        }
        if ($season) {
            $args['tax_query'][] = [
                'taxonomy' => 'sp_season',
                'field' => 'term_id',
                'terms' => $season,
            ];
        }
        if (count($args['tax_query']) > 1) {
            $args['tax_query']['relation'] = 'AND';
        }

        $team_posts = get_posts($args);
        $selected_teams = array_map(function($post) { return intval($post->ID); }, $team_posts);
    } else {
        $selected_teams = isset($_POST['mkfg_teams']) && is_array($_POST['mkfg_teams']) ? array_map('intval', $_POST['mkfg_teams']) : [];
    }

    // Validate teams
    if (count($selected_teams) < 2) {
        echo '<div class="notice notice-error"><p>Not enough teams to generate fixtures. Please select at least two teams or ensure the league/season has teams assigned.</p></div>';
        return;
    }

    // Ensure no invalid team IDs
    $selected_teams = array_filter($selected_teams, function($id) {
        return $id > 0; // Remove any IDs less than 1
    });
    if (count($selected_teams) < 2) {
        echo '<div class="notice notice-error"><p>Invalid team IDs detected. Please ensure valid teams are selected.</p></div>';
        return;
    }

    // Generate fixture list based on method
    $fixtures = ($method === 'round_robin') ? mkfg_round_robin($selected_teams) : mkfg_one_way($selected_teams);

    if (empty($fixtures)) {
        echo '<div class="notice notice-error"><p>No fixtures could be generated. Please try again.</p></div>';
        return;
    }

    // Validate and set default league if not specified
    if (!$league) {
        $leagues = get_terms(['taxonomy' => 'sp_league', 'hide_empty' => false]);
        if (empty($leagues) || is_wp_error($leagues)) {
            echo '<div class="notice notice-error"><p>No leagues found. Please create at least one league in SportsPress before generating fixtures.</p></div>';
            return;
        }
        $league = $leagues[0]->term_id;
    }

    // Validate and set default season if not specified
    if (!$season) {
        $seasons = get_terms(['taxonomy' => 'sp_season', 'hide_empty' => false]);
        if (empty($seasons) || is_wp_error($seasons)) {
            echo '<div class="notice notice-error"><p>No seasons found. Please create at least one season in SportsPress before generating fixtures.</p></div>';
            return;
        }
        $season = $seasons[0]->term_id;
    }

    // Save fixtures as SportsPress events
    $fixture_count = 0;
    foreach ($fixtures as $fixture) {
        $base_date = $start_date ? $start_date : current_time('Y-m-d');
        $full_datetime = $start_date ? "$base_date $start_time" : current_time('Y-m-d H:i:s');
        $fixture_date = date('Y-m-d H:i:s', strtotime("$full_datetime +{$fixture['index']} days"));

        // Get short names for teams, fall back to full names if not set
        $home_short_name = get_post_meta($fixture['home'], 'sp_short_name', true) ?: get_the_title($fixture['home']);
        $away_short_name = get_post_meta($fixture['away'], 'sp_short_name', true) ?: get_the_title($fixture['away']);

        $event_id = wp_insert_post([
            'post_title' => esc_html($home_short_name) . ' vs ' . esc_html($away_short_name),
            'post_type' => 'sp_event',
            'post_status' => 'publish',
            'post_date' => $fixture_date,
        ]);

        if (is_wp_error($event_id)) {
            echo '<div class="notice notice-error"><p>Error creating fixture: ' . esc_html($event_id->get_error_message()) . '</p></div>';
            continue;
        }

        // Assign teams to the event with SportsPress-compatible format
        $home_id = intval($fixture['home']);
        $away_id = intval($fixture['away']);
        if ($home_id > 0 && $away_id > 0) {
            // Delete existing sp_team meta to avoid conflicts
            delete_post_meta($event_id, 'sp_team');
            
            // Add teams as individual meta entries (mimics checklist behavior)
            add_post_meta($event_id, 'sp_team', $home_id, false);
            add_post_meta($event_id, 'sp_team', $away_id, false);

            // Set home and away team meta
            update_post_meta($event_id, 'sp_home_team', $home_id);
            update_post_meta($event_id, 'sp_away_team', $away_id);
            update_post_meta($event_id, 'sp_main_result', '');
        } else {
            echo '<div class="notice notice-error"><p>Invalid team IDs for fixture: ' . esc_html($home_short_name) . ' vs ' . esc_html($away_short_name) . '. Skipping.</p></div>';
            continue;
        }

        // Assign league and season
        if ($league) {
            wp_set_post_terms($event_id, [$league], 'sp_league', false);
        }
        if ($season) {
            wp_set_post_terms($event_id, [$season], 'sp_season', false);
        }

        // Set a default venue
        $venues = get_terms(['taxonomy' => 'sp_venue', 'hide_empty' => false]);
        if (!empty($venues) && !is_wp_error($venues)) {
            wp_set_post_terms($event_id, [$venues[0]->term_id], 'sp_venue', false);
        }

        $fixture_count++;
    }

    echo '<div class="notice notice-success"><p>Successfully generated ' . esc_html($fixture_count) . ' fixtures! Check the <a href="' . admin_url('edit.php?post_type=sp_event') . '">Matches</a> section.</p></div>';
}

/**
 * Generate one-way fixtures (each team plays each other once)
 * @param array $teams Array of team IDs
 * @return array Array of fixture arrays
 */
function mkfg_one_way($teams) {
    $fixtures = [];
    $team_count = count($teams);

    for ($i = 0; $i < $team_count - 1; $i++) {
        for ($j = $i + 1; $j < $team_count; $j++) {
            $fixtures[] = [
                'home' => $teams[$i],
                'away' => $teams[$j],
                'index' => count($fixtures),
            ];
        }
    }
    return $fixtures;
}

/**
 * Generate round-robin fixtures (each team plays each other twice, home and away)
 * @param array $teams Array of team IDs
 * @return array Array of fixture arrays
 */
function mkfg_round_robin($teams) {
    $fixtures = [];
    $team_count = count($teams);

    for ($i = 0; $i < $team_count; $i++) {
        for ($j = $i + 1; $j < $team_count; $j++) {
            $fixtures[] = [
                'home' => $teams[$i],
                'away' => $teams[$j],
                'index' => count($fixtures),
            ];
            $fixtures[] = [
                'home' => $teams[$j],
                'away' => $teams[$i],
                'index' => count($fixtures),
            ];
        }
    }
    return $fixtures;
}