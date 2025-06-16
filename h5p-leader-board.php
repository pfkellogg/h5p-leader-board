<?php
/**
 * Plugin Name: H5P Leaderboard
 * Description: Provides a shortcode [pfk_h5p_leaderboard] to display H5P results leaderboards,
 * broken down by H5P ID, H5P Title, User Name, User ID, Date, and Score.
 * Version: 1.0.0
 * Author: Patrick Kellogg
 * License: GPL2
 */

// Prevent direct access to the file.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * pfk_h5p_leaderboard_shortcode
 *
 * This function processes the [pfk_h5p_leaderboard] shortcode.
 * It queries the H5P results, contents, and WordPress users tables
 * to construct a leaderboard display.
 *
 * @param array $atts Shortcode attributes (currently none supported, but good practice).
 * @return string The HTML output for the leaderboard table.
 */
function h5p_leaderboard_shortcode( $atts ) {
    global $wpdb; // Access the WordPress database object.

    // Define table names with the WordPress prefix.
    $h5p_results_table = $wpdb->prefix . 'h5p_results';
    $h5p_contents_table = $wpdb->prefix . 'h5p_contents';
    $wp_users_table = $wpdb->users; // WordPress users table is directly accessible via $wpdb->users

    // Query to retrieve leaderboard data.
    // Joins h5p_results with h5p_contents to get H5P titles,
    // and with wp_users to get user display names.
    // Orders results first by content_id (to group H5P items) and then by score in descending order (highest score first).
    $query = $wpdb->prepare(
        "
        SELECT
            hr.content_id,
            hc.title AS h5p_title,
            wu.display_name AS user_name,
            hr.user_id,
            hr.score,
            hr.max_score,
            hr.finished AS result_date
        FROM
            {$h5p_results_table} hr
        INNER JOIN
            {$h5p_contents_table} hc ON hr.content_id = hc.id
        INNER JOIN
            {$wp_users_table} wu ON hr.user_id = wu.ID
        ORDER BY
            hr.content_id ASC, hr.score DESC
        "
    );

    $results = $wpdb->get_results( $query ); // Execute the query and get results.

    // Start building the HTML output.
    $output = '<div class="h5p-leaderboard-container">';
    $output .= '<style>
        .h5p-leaderboard-container {
            font-family: "Inter", sans-serif; /* Use Inter font */
            margin: 20px 0;
            overflow-x: auto; /* For responsive table on small screens */
            border-radius: 8px; /* Rounded corners for the container */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); /* Soft shadow */
        }
        .h5p-leaderboard-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            background-color: #ffffff; /* White background */
            border-radius: 8px; /* Rounded corners for the table */
            overflow: hidden; /* Ensures child elements respect border-radius */
        }
        .h5p-leaderboard-table th,
        .h5p-leaderboard-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0; /* Light border */
        }
        .h5p-leaderboard-table th {
            background-color: #f2f2f7; /* Light gray header background */
            color: #333333; /* Darker text for headers */
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9em;
        }
        .h5p-leaderboard-table tbody tr:last-child td {
            border-bottom: none; /* No border for the last row */
        }
        .h5p-leaderboard-table tbody tr.odd {
            background-color: #fcfcfc; /* Lighter stripe */
        }
        .h5p-leaderboard-table tbody tr.even {
            background-color: #f8f8fc; /* Slightly darker stripe */
        }
        .h5p-leaderboard-table tbody tr:hover {
            background-color: #eef2ff; /* More noticeable hover effect */
        }
        .h5p-leaderboard-table td {
            color: #555555;
            font-size: 0.95em;
        }
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .h5p-leaderboard-table thead {
                display: none; /* Hide header on small screens */
            }
            .h5p-leaderboard-table,
            .h5p-leaderboard-table tbody,
            .h5p-leaderboard-table tr,
            .h5p-leaderboard-table td {
                display: block; /* Make table elements stack */
                width: 100%;
            }
            .h5p-leaderboard-table tr {
                margin-bottom: 15px;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            }
            .h5p-leaderboard-table td {
                text-align: right;
                padding-left: 50%;
                position: relative;
            }
            .h5p-leaderboard-table td::before {
                content: attr(data-label); /* Use data-label for psuedo-elements */
                position: absolute;
                left: 15px;
                width: calc(50% - 30px);
                padding-right: 10px;
                white-space: nowrap;
                text-align: left;
                font-weight: 600;
                color: #333333;
            }
        }
    </style>';

    if ( $results ) {
        $output .= '<table class="h5p-leaderboard-table">';
        $output .= '<thead>';
        $output .= '<tr>';
        $output .= '<th>H5P ID</th>';
        $output .= '<th>H5P Title</th>';
        $output .= '<th>User Name</th>';
        $output .= '<th>User ID</th>';
        $output .= '<th>Date</th>';
        $output .= '<th>Score</th>';
        $output .= '</tr>';
        $output .= '</thead>';
        $output .= '<tbody>';

        $current_h5p_id = null;
        $row_counter = 0; // Initialize row counter for striping

        foreach ( $results as $row ) {
            // Check if the H5P ID has changed to reset the striping
            if ( $current_h5p_id !== $row->content_id ) {
                $current_h5p_id = $row->content_id;
                $row_counter = 0; // Reset counter for new H5P ID group
            }

            // Determine the row class for striping
            $row_class = ( $row_counter % 2 == 0 ) ? 'even' : 'odd';

            // Sanitize and format data for display.
            $h5p_id      = absint( $row->content_id );
            $h5p_title   = esc_html( $row->h5p_title );
            $user_name   = esc_html( $row->user_name );
            $user_id     = absint( $row->user_id );
            $score       = absint( $row->score );
            $max_score   = absint( $row->max_score );
            // Format date to a more readable format.
            $result_date = ! empty( $row->result_date ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row->result_date ) ) : 'N/A';

            // Calculate percentage if max_score is not zero, otherwise just show score/max_score.
            $display_score = ( $max_score > 0 ) ? sprintf( '%d/%d (%.0f%%)', $score, $max_score, ( $score / $max_score ) * 100 ) : sprintf( '%d/%d', $score, $max_score );


            $output .= '<tr class="' . $row_class . '">'; // Add the striping class here
            $output .= '<td data-label="H5P ID">' . $h5p_id . '</td>';
            $output .= '<td data-label="H5P Title">' . $h5p_title . '</td>';
            $output .= '<td data-label="User Name">' . $user_name . '</td>';
            $output .= '<td data-label="User ID">' . $user_id . '</td>';
            $output .= '<td data-label="Date">' . $result_date . '</td>';
            $output .= '<td data-label="Score">' . $display_score . '</td>';
            $output .= '</tr>';

            $row_counter++; // Increment row counter
        }

        $output .= '</tbody>';
        $output .= '</table>';
    } else {
        $output .= '<p>No H5P results found yet.</p>';
    }

    $output .= '</div>'; // Close h5p-leaderboard-container

    return $output;
}

// Register the shortcode.
add_shortcode( 'pfk_h5p_leaderboard', 'h5p_leaderboard_shortcode' );

