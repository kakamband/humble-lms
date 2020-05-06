<?php

if ( ! defined( 'ABSPATH' ) )
  exit;

get_header();

echo '<h1 class="humble-lms-track-single-title">' . get_the_title() . '</h1>';

echo do_shortcode('[humble_lms_paypal_buttons_single_item]');

echo do_shortcode('[humble_lms_course_archive track_id="' . get_the_ID() . '"]');

get_footer();
