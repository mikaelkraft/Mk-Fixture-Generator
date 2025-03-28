<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Callback function to render the admin page for MK Fixture Generator
 */
function mkfg_admin_page_callback() {
    // Enqueue jQuery UI Datepicker scripts and styles
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');

    ?>
    <div class="wrap">
        <h1>MK Fixture Generator</h1>
        <p>Generate fixtures for SportsPress teams using one-way or round-robin methods.</p>
        
        <form method="post" action="">
            <?php wp_nonce_field('mkfg_generate_fixtures', 'mkfg_nonce'); ?>
            
            <!-- Teams Selection Section -->
            <div class="postbox">
                <button type="button" class="handlediv" aria-expanded="true"><span class="toggle-indicator" aria-hidden="true"></span></button>
                <h2 class="hndle"><span>Select Teams Manually</span></h2>
                <div class="inside">
                    <p>Select teams manually if you want to generate fixtures for a custom set of teams. Leave unselected if using league/season filters.</p>
                    <?php
                    $teams = get_posts([
                        'post_type' => 'sp_team',
                        'posts_per_page' => -1,
                        'post_status' => 'publish',
                        'orderby' => 'title',
                        'order' => 'ASC',
                    ]);
                    
                    if (empty($teams)) {
                        echo '<p>No teams found. Please create teams in SportsPress first.</p>';
                    } else {
                        echo '<div id="mkfg-team-list" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #333; color: #fff">';
                        foreach ($teams as $team) {
                            echo '<label><input type="checkbox" name="mkfg_teams[]" value="' . esc_attr($team->ID) . '"> ' . esc_html($team->post_title) . '</label><br>';
                        }
                        echo '</div>';
                    }
                      ?>
                </div>
            </div>

            <!-- League/Season Filters Section -->
            <div class="postbox">
                <button type="button" class="handlediv" aria-expanded="true"><span class="toggle-indicator" aria-hidden="true"></span></button>
                <h2 class="hndle"><span>Filter by League or Season</span></h2>
                <div class="inside">
                    <p>Select a league or season to generate fixtures for all associated teams. This will override manual team selection if chosen.</p>
                    <p><strong>Note:</strong> Selecting both a league and season will only include teams that are in both. Choose 'All Leagues' or 'All Seasons' to ignore a filter.</p>
                    <div style="margin-bottom: 15px;">
                        <label for="mkfg_league" style="display: block; margin-bottom: 5px;">League:</label>
                        <?php
                        wp_dropdown_categories([
                            'taxonomy' => 'sp_league',
                            'name' => 'mkfg_league',
                            'id' => 'mkfg_league',
                            'show_option_none' => 'All Leagues',
                            'hide_empty' => 0,
                            'class' => 'widefat',
                        ]);
                        ?>
                    </div>
                    <div>
                        <label for="mkfg_season" style="display: block; margin-bottom: 5px;">Season:</label>
                        <?php
                        wp_dropdown_categories([
                            'taxonomy' => 'sp_season',
                            'name' => 'mkfg_season',
                            'id' => 'mkfg_season',
                            'show_option_none' => 'All Seasons',
                            'hide_empty' => 0,
                            'class' => 'widefat',
                        ]);
                        ?>
                    </div>
                </div>
            </div>

            <!-- Fixture Method Section -->
            <div class="postbox">
                <button type="button" class="handlediv" aria-expanded="true"><span class="toggle-indicator" aria-hidden="true"></span></button>
                <h2 class="hndle"><span>Fixture Method</span></h2>
                <div class="inside">
                    <p>Choose how fixtures should be generated:</p>
                    <label style="margin-right: 20px;"><input type="radio" name="mkfg_method" value="one_way" checked> One-Way (each team plays each other once)</label>
                    <label><input type="radio" name="mkfg_method" value="round_robin"> Round-Robin (each team plays each other twice, home and away)</label>
                </div>
            </div>

            <!-- Start Date and Time Section -->
            <div class="postbox">
                <button type="button" class="handlediv" aria-expanded="true"><span class="toggle-indicator" aria-hidden="true"></span></button>
                <h2 class="hndle"><span>Fixture Start Date and Time</span></h2>
                <div class="inside">
                    <p>Choose the start date and time for the fixtures. Leave time blank to default to 00:00:00 (midnight).</p>
                    <div style="margin-bottom: 15px;">
                        <label for="mkfg_start_date" style="display: block; margin-bottom: 5px;">Start Date:</label>
                        <input type="text" name="mkfg_start_date" id="mkfg_start_date" class="regular-text" placeholder="Select a date">
                    </div>
                    <div>
                        <label for="mkfg_start_time" style="display: block; margin-bottom: 5px;">Start Time:</label>
                        <input type="time" name="mkfg_start_time" id="mkfg_start_time" class="regular-text" step="900" placeholder="HH:MM">
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <p style="margin-top: 20px;">
                <input type="submit" name="mkfg_generate" class="button button-primary" value="Generate Fixtures">
            </p>
        </form>
    </div>

    <!-- JavaScript to toggle team selection and initialize date picker -->
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Toggle team selection
            var $teamList = $('#mkfg-team-list input');
            var $league = $('#mkfg_league');
            var $season = $('#mkfg_season');

            function toggleTeamSelection() {
                if ($league.val() > 0 || $season.val() > 0) {
                    $teamList.prop('disabled', true).prop('checked', false);
                    $('#mkfg-team-list').css('opacity', '0.5');
                } else {
                    $teamList.prop('disabled', false);
                    $('#mkfg-team-list').css('opacity', '1');
                }
            }

            $league.add($season).on('change', toggleTeamSelection);
            toggleTeamSelection(); // Initial check

            // Initialize date picker
            $('#mkfg_start_date').datepicker({
                dateFormat: 'yy-mm-dd',
                minDate: 0, // Prevent selecting past dates
            });
        });
    </script>
    <?php

    // Handle form submission
    if (isset($_POST['mkfg_generate']) && check_admin_referer('mkfg_generate_fixtures', 'mkfg_nonce')) {
        mkfg_generate_fixtures();
    }
}