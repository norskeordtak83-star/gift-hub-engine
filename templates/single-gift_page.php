<?php

if (! defined('ABSPATH')) {
    exit;
}

get_header();

if (have_posts()) {
    while (have_posts()) {
        the_post();
        global $post;
        if ($post instanceof WP_Post) {
            echo GHE_Template::render_markup($post); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}

get_footer();
