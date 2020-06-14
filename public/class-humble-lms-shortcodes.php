<?php
/**
 * This class provides the frontend plugin shortcodes.
 *
 * @package    Humble_LMS
 * @subpackage Humble_LMS/public
 * @author     Sebastian Honert <hello@sebastianhonert.com>
 */
if( ! class_exists( 'Humble_LMS_Public_Shortcodes' ) ) {

  class Humble_LMS_Public_Shortcodes {

    /**
     * Initialize the class and set its properties.
     *
     * @since    0.0.1
     */
    public function __construct() {

      $this->user = new Humble_LMS_Public_User;
      $this->access_handler = new Humble_LMS_Public_Access_Handler;
      $this->options_manager = new Humble_LMS_Admin_Options_Manager;
      $this->content_manager = new Humble_LMS_Content_Manager;
      $this->quiz = new Humble_LMS_Quiz;
      $this->translator = new Humble_LMS_Translator;

    }

    /**
     * Shortcode: track archive
     *
     * @since    0.0.1
     */
    public function track_archive( $atts = null ) {
      $html = '';
      $options = $this->options_manager->options;
      $tiles_per_page = isset( $options['tiles_per_page'] ) ? (int)$options['tiles_per_page'] : 10;
      $tile_width = isset( $options['tile_width_track'] ) ? $options['tile_width_track'] : 'half';

      extract( shortcode_atts( array (
        'ids' => '',
        'tile_width' => $tile_width,
        'style' => '',
        'class' => '',
      ), $atts ) );

      $selected_category = isset( $_GET['category'] ) ? (int)$_GET['category'] : 0;

      $args = array(
        'post_type' => 'humble_lms_track',
        'post_status' => 'publish',
        'posts_per_page' => $tiles_per_page,
        'meta_key' => 'humble_lms_track_position',
        'orderby' => 'meta_value_num',
        'order' => 'ASC',
        'paged' => get_query_var('paged') ? get_query_var('paged') : 1,
        'cat' => $selected_category,
      );

      if( ! empty( $ids ) ) {
        $args['post__in'] = explode(',', str_replace(' ','', $ids));
      }

      $tracks = new WP_Query( $args );

      if( $tracks->have_posts() ) {
        if( isset( $this->options_manager->options['sort_tracks_by_category'] ) && $this->options_manager->options['sort_tracks_by_category'] === 1 ) {
          $categories = $this->content_manager->get_categories('humble_lms_track', true);
          if( ! empty( $categories ) ) {
            $html .= '<form action="' . Humble_LMS_Public::get_nopaging_url() . '" method="get" id="humble_lms_archive_select_category"><select name="category">';
            $html .= '<option value="0">' . __('Sort by category', 'humble-lms') . '</option>';
            foreach( $categories as $category ) {
              $selected = $selected_category === $category->term_id ? 'selected' : '';
              $html .= '<option value="' . $category->term_id . '" ' . $selected . '>' . $category->name . '</option>';
            }
            $html .= '</select></form>';
          }
        }          

        $html .= '<div class="humble-lms-flex-columns ' . $class . '" style="' . $style . '">';
        while ( $tracks->have_posts() ) {
          $tracks->the_post();
          $html .= do_shortcode('[humble_lms_track_tile tile_width="' . $tile_width . '" track_id="' . get_the_ID() . '"]');
        }
        $html .= '</div>';
        $html .= $this->humble_lms_paginate_links( $tracks );
      } else {
        $html .= '<p>' . __('No tracks found.', 'humble-lms') . '</p>';
      }

      wp_reset_postdata();

      return $html;
    }

    /**
     * Shortcode: track tile
     *
     * @since    0.0.1
     */
    public function track_tile( $atts = null ) {
      extract( shortcode_atts( array (
        'track_id' => '',
        'tile_width' => 'half',
        'class' => '',
        'style' => '',
      ), $atts ) );

      if( ! $track_id || get_post_type( $track_id ) !== 'humble_lms_track' )
        return;

      $track = get_post( $track_id );
      $completed = $this->user->completed_track( $track_id ) ? 'humble-lms-track-completed' : '';
      $featured_img_url = get_the_post_thumbnail_url( $track_id, 'humble-lms-course-tile' );
      $providers = get_the_terms( $track_id, 'humble_lms_tax_provider' );
      $level = strip_tags( get_the_term_list( $track_id, 'humble_lms_tax_course_level', '', ', ' ) );
      $level_str = $level ? $level : __('Not specified', 'humble-lms');
      $duration = get_post_meta( $track_id, 'humble_lms_track_duration', true );
      $duration_str = $duration ? $duration : __('Not specified', 'humble-lms');
      $progress = $this->user->track_progress( $track_id, get_current_user_id() );
      $color = get_post_meta( $track_id, 'humble_lms_track_color', true );
      $overlay_color = $color !== '' ? 'background-color:' . $color : '';
      $is_for_sale = get_post_meta( $track_id, 'humble_lms_is_for_sale', true );
      $price = ! $this->user->purchased( $track_id ) ? $this->options_manager->get_currency() . ' ' . $this->content_manager->get_price( $track_id ) : null;

      $html = '<div class="humble-lms-course-tile-wrapper humble-lms-flex-column--' . $tile_width . ' ' . $completed . ' ' . $class . '" style="' . $style .'"">';
        $html .= '<a style="background-image: url(' . $featured_img_url . ')" href="' . esc_url( get_permalink( $track_id ) ) . '" class="humble-lms-course-tile">';
          $html .= '<div class="humble-lms-course-tile-layer" style="' . $overlay_color . '"></div>';
          $html .= '<div class="humble-lms-16-9">';
            $html .= '<div class="humble-lms-course-title">' . $track->post_title . '</div>';
          $html .= '</div>';
        $html .= '</a>';
        $html .= '<div class="humble-lms-course-tile-meta">';
          $html .= $is_for_sale && $price ? '<span class="humble-lms-price"><strong>' . __('Price', 'humble-lms') . ':</strong> <span>' . $price . '*</span></span>' : '';
          if( $providers ) {
            $providers_str = get_the_term_list( $track_id, 'humble_lms_tax_provider', '', ', ' );
            $providers_str = strip_tags( $providers_str );
            $html .= '<span class="humble-lms-difficulty"><strong>' . __('Provider', 'humble-lms') . ':</strong> ' . $providers_str . '</span>';
          }
          $html .= $level ? '<span class="humble-lms-difficulty"><strong>' . __('Level', 'humble-lms') . ':</strong> ' . $level_str . '</span>' : '';
          $html .= $duration ? '<span class="humble-lms-duration"><strong>' . __('Duration', 'humble-lms') . ':</strong> ' . $duration_str  . '</span>' : '';
          $html .= '<span class="humble-lms-progress"><strong>' . __('Progress', 'humble-lms') . ':</strong> ' . $progress  . '%</span>';
          $html .= do_shortcode('[humble_lms_progress_bar progress="' . $progress . '"]');
        $html .= '</div>';
      $html .= '</div>';

      return $html;
    }

    /**
     * Shortcode: course archive
     *
     * @since    0.0.1
     */
    public function course_archive( $atts = null ) {
      global $post;

      $html = '';
      $options = $this->options_manager->options;
      $tiles_per_page = isset( $options['tiles_per_page'] ) ? (int)$options['tiles_per_page'] : 10;
      $tile_width = isset( $options['tile_width_course'] ) ? $options['tile_width_course'] : 'half';

      extract( shortcode_atts( array (
        'ids' => '',
        'track_id' => '',
        'tile_width' => $tile_width,
        'style' => '',
        'class' => '',
      ), $atts ) );

      $is_track = ! empty( $track_id ) && ( is_single() && $post->post_type === 'humble_lms_track' );
      $courses = $this->content_manager->get_track_courses( $track_id );
      $selected_category = isset( $_GET['category'] ) ? (int)$_GET['category'] : 0;

      $args = array(
        'post_type' => 'humble_lms_course',
        'post_status' => 'publish',
        'posts_per_page' => $is_track ? -1 : $tiles_per_page,
        'paged' => get_query_var('paged') ? get_query_var('paged') : 1,
        'post__in' => $courses,
        'orderby' => 'post__in',
        'order' => 'ASC',
        'cat' => $selected_category,
      );
  
      if( ! empty( $ids ) ) {
        $args['post__in'] = explode(',', str_replace(' ','', $ids));
      }

      if( $is_track) {
        $track_courses = $this->content_manager->get_track_courses( $track_id );

        if( ! empty( $track_courses ) ) {
          $args['post__in'] = $track_courses;
        } else {
          return '<p>' . __('This track does not include any courses.', 'humble-lms') . '</p>';
        }
      }

      $courses = new WP_Query( $args );

      if ( $courses->have_posts() ) {
        if( isset( $this->options_manager->options['sort_courses_by_category'] ) && $this->options_manager->options['sort_courses_by_category'] === 1 ) {
          $categories = $this->content_manager->get_categories('humble_lms_course', true);
          if( ! empty( $categories ) ) {
            $selected_category = isset( $_GET['category'] ) ? (int)$_GET['category'] : 0;
            $html .= '<form action="' . Humble_LMS_Public::get_nopaging_url() . '" method="get" id="humble_lms_archive_select_category"><select name="category">';
            $html .= '<option value="0">' . __('Sort by category', 'humble-lms') . '</option>';
            foreach( $categories as $category ) {
              $selected = $selected_category === $category->term_id ? 'selected' : '';
              $html .= '<option value="' . $category->term_id . '" ' . $selected . '>' . $category->name . '</option>';
            }
            $html .= '</select></form>';
          }
        }

        $html .= '<div class="humble-lms-flex-columns ' . $class . '" style="' . $style . '">';
        while ( $courses->have_posts() ) {
          $courses->the_post();
          $html .= do_shortcode('[humble_lms_course_tile tile_width="' . $tile_width . '" course_id="' . $post->ID . '"]');
        }
        $html .= '</div>';
        $html .= $this->humble_lms_paginate_links( $courses );
      } else {
        $html .= '<p>' . __('No courses found.', 'humble-lms') . '</p>';
      }

      wp_reset_postdata();

      return $html;
    }

    /**
     * Shortcode: course tile
     *
     * @since    0.0.1
     */
    public function course_tile( $atts = null ) {
      extract( shortcode_atts( array (
        'course_id' => '',
        'tile_width' => 'half',
        'class' => '',
        'style' => '',
      ), $atts ) );

      if( ! $course_id || get_post_type( $course_id ) !== 'humble_lms_course' )
        return;

      $course = get_post( $course_id );

      $completed = $this->user->completed_course( $course_id ) ? 'humble-lms-course-completed' : '';
      $featured_img_url = get_the_post_thumbnail_url( $course_id, 'humble-lms-course-tile');
      $providers = get_the_terms( $course_id, 'humble_lms_tax_provider' );
      $level = strip_tags( get_the_term_list( $course_id, 'humble_lms_tax_course_level', '', ', ') );
      $level_str = $level ? $level : __('Not specified', 'humble-lms');
      $duration = get_post_meta( $course_id, 'humble_lms_course_duration', true );
      $duration_str = $duration ? $duration : __('Not specified', 'humble-lms');
      $progress = $this->user->course_progress( $course_id, get_current_user_id() );
      $color = get_post_meta( $course_id, 'humble_lms_course_color', true );
      $overlay_color = $color !== '' ? 'background-color:' . $color : '';
      $is_for_sale = get_post_meta( $course_id, 'humble_lms_is_for_sale', true );
      $price = ! $this->user->purchased( $course_id ) ? $this->options_manager->get_currency() . ' ' . $this->content_manager->get_price( $course_id ) : null;

      $html = '<div class="humble-lms-course-tile-wrapper humble-lms-flex-column--' . $tile_width . ' ' . $completed . ' ' . $class . '" style="' . $style .'">';
        $html .= '<a style="background-image: url(' . $featured_img_url . ')" href="' . esc_url( get_permalink( $course_id ) ) . '" class="humble-lms-course-tile">';
          $html .= '<div class="humble-lms-course-tile-layer" style="' . $overlay_color . '"></div>';
          $html .= '<div class="humble-lms-16-9">';
            $html .= '<div class="humble-lms-course-title">' . $course->post_title . '</div>';
          $html .= '</div>';
        $html .= '</a>';
        $html .= '<div class="humble-lms-course-tile-meta">';
          $html .= $is_for_sale && $price ? '<span class="humble-lms-price"><strong>' . __('Price', 'humble-lms') . ':</strong> <span>' . $price . '*</span></span>' : '';
          if( $providers ) {
            $providers_str = get_the_term_list( $course_id, 'humble_lms_tax_provider', '', ', ' );
            $providers_str = strip_tags( $providers_str );
            $html .= '<span class="humble-lms-difficulty"><strong>' . __('Provider', 'humble-lms') . ':</strong> ' . $providers_str . '</span>';
          }
          $html .= $level ? '<span class="humble-lms-difficulty"><strong>' . __('Level', 'humble-lms') . ':</strong> ' . $level_str . '</span>' : '';
          $html .= $duration ? '<span class="humble-lms-duration"><strong>' . __('Duration', 'humble-lms') . ':</strong> ' . $duration_str  . '</span>' : '';
          $html .= '<span class="humble-lms-progress"><strong>' . __('Progress', 'humble-lms') . ':</strong> ' . $progress  . '%</span>';
          $html .= do_shortcode('[humble_lms_progress_bar progress="' . $progress . '"]');
        $html .= '</div>';
      $html .= '</div>';

      return $html;
    }

    /**
     * Course progress in percent.
     * 
     * @return float
     * @since   0.0.1
     */
    function progress_bar( $atts = null ) {
      extract( shortcode_atts( array (
        'progress' => 0
      ), $atts ) );

      $html = '<div class="humble-lms-progress-bar">';
      $html .= '<div class="humble-lms-progress-bar-inner" style="width:' . $progress . '%"></div>';
      $html .= '</div>';

      return $html;
    }

    /**
     * Shortcode: syllabus
     *
     * @since    0.0.1
     */
    public function syllabus( $atts = null ) {
      global $post;
      $html = '';

      extract( shortcode_atts( array (
        'course_id' => $post->ID,
        'context' => 'course',
        'style' => '',
        'class' => '',
      ), $atts ) );

      if( is_single() && get_post_type() === 'humble_lms_lesson' )
        $context = 'lesson';

      if( $context === 'lesson' ) {
        $syllabus_class = 'humble-lms-syllabus--lesson';
        $lesson_id = $post->ID;
        $course_id = isset( $_POST['course_id'] ) ? (int)$_POST['course_id'] : null;
        
        // Try to get course_id by checking if this lesson is
        // attached to only one course.
        if( ! $course_id ) {
          $course_ids = $this->content_manager->find_courses_by('lesson', $lesson_id );
          if( is_array( $course_ids ) && sizeOf( $course_ids ) === 1 ) {
              $course_id = $course_ids[0];
          }
        }
      } else {
        $syllabus_class = '';
        $lesson_id = null;
      }

      $lessons = $this->content_manager->get_course_lessons( $course_id );
      $sections = $this->content_manager->get_course_sections( $course_id );

      if( is_single() && get_post_type() === 'humble_lms_course' && empty( $sections ) ) {
        return '<p>' . __('There are no lessons attached to this course', 'humble-lms') . '</p>';
      }

      // Course Syllabus
      $html .= '<nav class="humble-lms-syllabus ' . $class . ' ' . $syllabus_class . '" style="' . $style . '">';
        $html .= $lesson_id ? '' : '<h2>' . __('Syllabus', 'humble-lms') . '</h2>';

        if( ! $course_id ) {
          $html .= '<p>' . __('Looking for the course syllabus? It seems that you have accessed this lesson directly so it is not related to a specific course. Please open the course and start your learning activities from there.', 'humble-lms') . '</p>';
        } else {
          $html .= '<ul class="humble-lms-syllabus-lessons">';

          $lesson_index = 0;
  
          foreach( $sections as $key => $section ) {

            $set_title = true;
            $section_title = ! empty( $section['title'] ) ? $section['title'] : '';

            if( empty( $section['lessons'] ) ) {
              continue;
            }

            $section_lessons = $section['lessons'];
            if( ! is_array( $section_lessons ) || empty( $section_lessons ) ) {
              continue;
            }

            foreach( $section_lessons as $key => $id ) {
              $lesson_index++;
              $lesson = get_post( $id );
              $description = $context === 'course' ? get_post_meta( $lesson->ID, 'humble_lms_lesson_description', true ) : '';
              $class_lesson_current = $lesson->ID === $lesson_id ? 'humble-lms-syllabus-lesson--current' : '';
              $class_lesson_completed = $this->user->completed_lesson( get_current_user_id(), $lesson->ID ) ? 'humble-lms-syllabus-lesson--completed' : '';
              $locked = $this->access_handler->can_access_lesson( $lesson->ID, $course_id ) === 'allowed' ? '' : '<i class="ti-lock"></i>';
              $html .= $section_title && $set_title ? '<li class="humble-lms-syllabus-section-title">' . $section_title . '</li>' : '';

              if( $set_title ) {
                $set_title = false;
              }

              $html .= '<li class="humble-lms-syllabus-lesson humble-lms-open-lesson ' . $class_lesson_current . ' ' . $class_lesson_completed . '" data-lesson-id="' . $lesson->ID  . '" data-course-id="' . $course_id . '">';
              $html .= '<span class="humble-lms-syllabus-title">' . $locked . $lesson_index . '. ' . $lesson->post_title . '</span>';
              $html .= $description? '<span class="humble-lms-syllabus-description">' . $description . '</span>' : '';
              $html .= '</li>';
            }
          }
          
          $html .= '</ul>';
        }

      $html .= '</nav>';
      $html .= '<a class="humble-lms-toggle-syllabus">' . __('+/- Toggle syllabus', 'humble-lms') . '</a>';

      // Meta information
      if( $lesson_id ) {
        $duration = get_post_meta( $course_id, 'humble_lms_course_duration', true );
        $duration = $duration ? '<span class="humble-lms-duration"><strong>' . __('Duration', 'humble-lms') . ':</strong> ' . $duration . '</span>' : '';

        $html .= '<p class="humble-lms-course-meta humble-lms-course-meta--lesson">';
          $html .= ! $course_id ? '<strong>' . __('Course', 'humble-lms') . ':</strong> ' . __('not selected', 'humble-lms') . '<br>' : '<strong>' . __('Course', 'humble-lms') . ':</strong> <a class="humble-lms-syllabus-course-title" href="' . esc_url( get_permalink( $course_id ) ) . '">' . get_the_title( $course_id ) . '</a><br>';
          $html .= $duration;
        $html .= '</p>';
      }

      // View course/lesson
      if( $context === 'course' ) {
        if( isset( $lessons[0] ) && ! empty( $lessons[0] ) ) {
          $html .= '<span class="humble-lms-btn humble-lms-btn--success humble-lms-btn--start-course humble-lms-open-lesson" data-lesson-id="' . $lessons[0] . '" data-course-id="' . $course_id . '">' . __('Start the course now', 'humble-lms') . '</span>';
        }
      }

      return $html;
    }

    /**
     * Display track/course/lesson instructor(s).
     * Lesson > Course > Track
     * 
     * @return string
     * @since   0.0.1
     */
    function instructors( $atts = null ) {
      global $post;

      extract( shortcode_atts( array (
        'widget' => '',
        'style' => '',
        'class' => '',
      ), $atts ) );
      
      $html = '';
      $widget = filter_var( $widget, FILTER_VALIDATE_BOOLEAN );
      $allowed_templates = array(
        'humble_lms_lesson',
        'humble_lms_course',
        'humble_lms_track',
      );

      // Inside a lesson?
      if( $post->post_type === 'humble_lms_lesson' ) {
        $instructors = $this->content_manager->get_instructors( $post->ID );
      }

      // Inside a course?
      if( empty( $instructors ) ) {
        if( $post->post_type === 'humble_lms_course' ) {
          $post_id = $post->ID;
        } else {
          $post_id = isset( $_POST['course_id'] ) ? (int)$_POST['course_id'] : null;
        }
        
        $instructors = $this->content_manager->get_instructors( $post_id );
      }

      $html .= '<div class="humble-lms-instructors ' . $class . '" style="' . $style . '">';

      if( isset( $instructors[0] ) && ! empty( $instructors ) ) {
        foreach( $instructors as $user_id ) {
          if( get_userdata( $user_id ) ) {
            $user = get_user_by( 'id', $user_id );
            $html .= '<a href="mailto:' . $user->user_email . '">' . $user->display_name . '</a>';
          }
        }
      } else {
        $html .= $widget ? '<p>' . __('No instructors available.', 'humble-lms'). '</p>' : '';
      }  

      $html .= '</div>';

      return $html;
    }

    /**
     * Display track/course providers.
     * 
     * @return string
     * @since   0.0.1
     */
    function providers( $atts = null ) {
      global $post;

      extract( shortcode_atts( array (
        'title' => '',
        'hide_title' => '',
        'style' => '',
        'class' => '',
      ), $atts ) );

      $html = '';

      $title = $title ? esc_attr( $title ) : __('Providers', 'humble-lms');
      $providers = get_the_terms( $post->ID, 'humble_lms_tax_provider' );

      $html .= '<div class="humble-lms-providers" class="' . $class . '" style="' . $style . '">';
        if( ! filter_var( $hide_title, FILTER_VALIDATE_BOOLEAN ) ) {
          $html .= $title ? '<h2 class="humble-lms-providers-title">' . $title . '</h2>' : '';
        }

        if( $providers ) {
          foreach( $providers as $provider ) {
            $html .= '<div class="humble-lms-provider">
              <p><span class="humble-lms-provider-name">' . $provider->name . '</span></p>';

              if( $provider->description ) {
                $html .= '<p><span class="humble-lms-provider-description">' . $provider->description . '</span></p>';
              }
              
            $html .= '</div>';
          }
        } else {
          $html .= '<p class="last">' . __('Providers not specified.', 'humble-lms') . '</p>';
        }

      $html .= '</div>';

      return $html;
    }

    /**
     * Display course start/end date.
     * 
     * @return string
     * @since   0.0.1
     */
    function timeframe( $atts = null ) {
      global $post;

      extract( shortcode_atts( array (
        'course_id' => '',
        'title' => '',
        'hide_title' => '',
        'style' => '',
        'class' => '',
      ), $atts ) );

      $title = $title ? esc_attr( $title ) : __('Timeframe', 'humble-lms');

      if( ! $course_id ) {
        $course_id = $post->ID;
      }

      $course = get_post_type( $course_id );

      if( ! $course ) {
        return __('Timeframe information not available. Please provide a valid course ID.', 'humble-lms');
      }

      $timestamps = Humble_LMS_Content_Manager::get_timestamps( $course_id );
      $has_start_date = ! empty( $timestamps['date_from'] );
      $has_end_date = ! empty( $timestamps['date_to'] );
      $has_start_and_end_date = $has_start_date && $has_end_date;

      $html = '<div class="humble-lms-timeframe" class="' . $class . '" style="' . $style . '">';
        if( ! filter_var( $hide_title, FILTER_VALIDATE_BOOLEAN ) ) {
          $html .= $title ? '<h2 class="humble-lms-timeframe-title">' . $title . '</h2>' : '';
        }
        
        $html .= '<p class="humble-lms-timeframe-content">';

          if( ! $has_start_date && ! $has_end_date ) {
            $html .= __('This course will be opened indefinitely.', 'humble-lms');
          } else if( $has_start_and_end_date ) {
            $html .= sprintf( __('This course starts on %s and ends on %s.', 'humble-lms'), $timestamps['date_from'], $timestamps['date_to'] );
          } else if( $has_start_date && ! $has_end_date ) {
            $html .= sprintf( __('This course starts on %s.', 'humble-lms'), $timestamps['date_from'] );
          } else if( ! $has_start_date && $has_end_date ) {
            $html .= sprintf( __('This course ends on %s.', 'humble-lms'), $timestamps['date_to'] );
          }

          if( ! empty( $timestamps['info'] ) )
            $html .= ' ' . $timestamps['info'];
  
        $html .= '</p>';
      $html .= '</div>';

      return $html;
    }

    /**
     * Shortcode: mark lesson complete button
     *
     * @since    0.0.1
     */
    public function mark_complete_button( $atts = null ) {
      global $post;
      
      $course_id = $this->content_manager->get_course_id( $post->ID );

      if( ! $course_id )
        return;

      $course = get_post( $course_id );

      if( ! $course )
        return;

      extract( shortcode_atts( array (
        'style' => '',
        'class' => '',
      ), $atts ) );

      $lessons = $this->content_manager->get_course_lessons( $course_id );
      $key = array_search( $post->ID, $lessons );
      $is_first = $key === array_key_first( $lessons );
      $is_last = $key === array_key_last( $lessons );

      if( ! $is_last ) {
        $next_lesson = get_post( $lessons[$key+1] );
      }

      if( ! $is_first ) {
        $prev_lesson = get_post( $lessons[$key-1] );
      }
      
      $html = '';
      $quizzes = Humble_LMS_Content_Manager::get_lesson_quizzes( $post->ID );
      $lesson_has_quiz = isset( $quizzes ) && ! empty( $quizzes );
      $lesson_completed = $this->user->completed_lesson( get_current_user_id(), $post->ID );
      $quiz_class = $lesson_has_quiz ? 'humble-lms-has-quiz' : '';
      $quiz_ids_string = implode(',', $quizzes);
      $passing_required = false;
      $user_completed_quizzes = true;

      foreach( $quizzes as $id ) {
        if( $this->quiz->get_passing_required( $id ) ) {
          $passing_required = true;
        }
        if( ! $this->user->completed_quiz( $id ) ) {
          $user_completed_quizzes = false;
        }
      }
  
      // Evaluate quiz button
      $button_label = ! $user_completed_quizzes ? __('Check your answers', 'humble-lms') : __('Quiz passed. Try again?', 'humble-lms');
      $button_class = $user_completed_quizzes ? 'humble-lms-btn--success' : '';
      if( $lesson_has_quiz ) {
        $html .= '<form method="post" id="humble-lms-evaluate-quiz" class="' . $quiz_class . '">';
          $html .= '<input type="hidden" name="course-id" value="' . $course_id . '">';
          $html .= '<input type="hidden" name="lesson-id" value="' . $post->ID . '">';
          $html .= '<input type="hidden" name="quiz-ids" value="' . $quiz_ids_string . '">';
          $html .= '<input type="hidden" name="lesson-completed" value="' . $lesson_completed . '">';
          $html .= '<input type="hidden" name="try-again" value="' . ( $user_completed_quizzes ? 1 : 0 ) . '">';
          $html .= '<input type="submit" class="humble-lms-btn ' . $button_class . '" value="' . $button_label . '">';
        $html .= '</form>';
      }

      // Mark complete button
      $hidden_style = ! $user_completed_quizzes && ( $passing_required && $lesson_has_quiz && ! $lesson_completed ) ? 'display:none' : '';
      $html .= '<form method="post" id="humble-lms-mark-complete" class="' . $quiz_class . '" style="' . $hidden_style . '">';
        $html .= '<input type="hidden" name="course-id" id="course-id" value="' . $course_id . '">';
        $html .= '<input type="hidden" name="lesson-id" id="lesson-id" value="' . $post->ID . '">';
        $html .= '<input type="hidden" name="quiz-ids" id="quiz-ids" value="' . $quiz_ids_string . '">';
        $html .= '<input type="hidden" name="lesson-completed" id="lesson-completed" value="' . $lesson_completed . '">';

        $button_labels = $this->options_manager->get_button_labels();
        if( $lesson_completed ) {
          $html .= '<input type="submit" class="humble-lms-btn humble-lms-btn--success" value="' . $button_labels[1] . '">';
        } else {
          $html .= '<input type="submit" class="humble-lms-btn humble-lms-btn--error" value="' . $button_labels[0] . '">';
        }
      $html .= '</form>';

      $html .= '<div class="humble-lms-next-prev-lesson">';

      if( $is_first ) {
        $html .= '<a class="humble-lms-prev-lesson-link" href="' . esc_url( get_permalink( $course_id ) ) . '">' . __('Back to course overview', 'humble-lms') . '</a>';
      } else {
        $html .= '<a class="humble-lms-prev-lesson-link humble-lms-open-lesson" data-course-id="' . $course_id . '" data-lesson-id="' . $prev_lesson->ID . '">' . __('Previous lesson', 'humble-lms') . '</a>';
      }

      if( $is_last ) {
        $html .= '<a class="humble-lms-next-lesson-link humble-lms-open-lesson" data-course-id="' . $course_id . '" data-lesson-id="' . $lessons[0] . '">' . __('Back to first lesson', 'humble-lms') . '</a>';
      } else {
        $html .= '<a class="humble-lms-next-lesson-link humble-lms-open-lesson" data-course-id="' . $course_id . '" data-lesson-id="' . $next_lesson->ID . '">' . __('Next lesson', 'humble-lms') . '</a>';
      }
      
      $html .= '</div>';

      return $html;
    }

    /**
     * Pagination
     * 
     * @since   0.0.1
     */
    public function humble_lms_paginate_links( $query ) {
      global $wp_query; if( ! $query ) $query = $wp_query;
  
      $big = 999999999;
      $html = paginate_links( array(
          'base' => str_replace( $big, '%#%', get_pagenum_link( $big ) ),
          'format' => '?paged=%#%',
          'current' => max( 1, get_query_var('paged') ),
          'total' => $query->max_num_pages,
          'mid_size' => 5,
          'prev_text' => '&laquo;',
          'next_text' => '&raquo;'
      ) );

      return $html;
    }

    /**
     * Display user progress (tracks and courses).
     * 
     * @return string
     * @since   0.0.1
     */
    function user_progress() {
      if( ! is_user_logged_in() )
        return $this->display_login_text();

      $html = '';
      $tracks_completed = get_user_meta( get_current_user_id(), 'humble_lms_tracks_completed', false );
      $courses_completed = get_user_meta( get_current_user_id(), 'humble_lms_courses_completed', false );

      $args = array(
        'post_type' => 'humble_lms_track',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_key' => 'humble_lms_track_position',
        'orderby' => 'meta_value_num',
        'order' => 'ASC',
        'lang' => $this->translator->current_language(),
      );

      $tracks = get_posts( $args );

      if( ! $tracks ) {
        $html .= '<p>' . __('No tracks available.', 'humble-lms') . '</p>';
      }

      foreach( $tracks as $key => $track ) {
        $counter = 0;
        $class_completed = $this->user->completed_track( $track->ID ) ? 'humble-lms-track-progress-track--completed' : '';
        $html .= '<p class="humble-lms-progress-track-title ' . $class_completed . '"><a href="' . esc_url( get_permalink( $track->ID ) ) . '">' . get_the_title( $track->ID ) . '</a></p>';

        $track_courses = $this->content_manager->get_track_courses( $track->ID );

        if( empty( $track_courses) )
          continue;

        $html .= '<div class="humble-lms-track-progress">';

        foreach( $track_courses as $key => $course ) {
          if( get_post_status( $course ) !== 'publish' )
            continue;

          $counter++;
          $class_completed = $this->user->completed_course( $course ) ? 'humble-lms-track-progress-course--completed' : '';
          $html .= '<a href="' . esc_url( get_permalink( $course ) ) . '" class="humble-lms-track-progress-course ' . $class_completed . '" title="' . get_the_title( $course ) . '">';
          $html .= $counter;
          $html .= '</a>';
          $html .= $key !== array_key_last( $track_courses ) ? '<i class="ti-angle-right humble-lms-track-progress-course-separator"></i>' : '';
        }

        $html .= '</div>';
      }

      return $html;
    }

    /**
     * Display user awards.
     * 
     * @return string
     * @since   0.0.1
     */
    function user_awards() {
      if( ! is_user_logged_in() )
        return $this->display_login_text();

      $awards = get_user_meta( get_current_user_id(), 'humble_lms_awards', false );

      if( isset( $awards[0] ) && ! empty( $awards[0] ) ) {
        foreach( $awards[0] as $key => $id ) {
          if( get_post_status( $id ) !== 'publish' ) {
            unset( $awards[0][$key] );
          }
        }
      }

      $html = '';

      if( ! isset( $awards[0] ) || ! $awards[0] || empty( $awards[0] ) ) {
        $html .= '<p>' . __('You have not received any awards yet.', 'humble-lms') . '</p>';
      } else {
        $html .= '<div class="humble-lms-awards-list">';
        foreach( $awards[0] as $award ) {
          $html .= '<div class="humble-lms-awards-list-item">';
          $html .= '<img src="' . get_the_post_thumbnail_url( $award ) . '" title="' . get_the_title( $award ) . '" alt="' . get_the_title( $award ) . '" />';
          $html .= '</div>';
        }
        $html .= '</div>';
      }

      return $html;
      
    }

    /**
     * Display user certificates.
     * 
     * @return string
     * @since   0.0.1
     */
    function user_certificates() {
      if( ! is_user_logged_in() )
        return $this->display_login_text();

      $certificates = get_user_meta( get_current_user_id(), 'humble_lms_certificates', false );

      if( isset( $certificates[0] ) && ! empty( $certificates[0] ) ) {
        foreach( $certificates[0] as $key => $id ) {
          if( get_post_status( $id ) !== 'publish' ) {
            unset( $certificates[0][$key] );
          }
        }
      }

      $html = '';

      if( ! isset( $certificates[0] ) || ! $certificates[0] || empty( $certificates[0] ) ) {
        $html .= '<p>' . __('You have not been issued any certificates yet.', 'humble-lms') . '</p>';
      } else {
        $html .= '<div class="humble-lms-certificates-list">';
        foreach( $certificates[0] as $certificate ) {
          $image = has_post_thumbnail( $certificate ) ? get_the_post_thumbnail_url( $certificate ) : plugins_url( 'humble-lms/public/assets/img/certificate.png' );
          $html .= '<div class="humble-lms-certificates-list-item">';
          $html .= '<a href="' . esc_url( get_permalink( $certificate ) ) . '"><img src="' . $image . '" alt="' . get_the_title( $certificate ) . '" title="' . get_the_title( $certificate ) . '" /></a>';
          $html .= '</div>';
        }
        $html .= '</div>';
      }

      return $html;
      
    }

    /**
     * Default login link text.
     * 
     * @return string
     * @since   0.0.1
     */
    public function display_login_text() {
      return sprintf( __('Please %s first.', 'humble-lms'), '<a href="' . $this->options_manager->login_url . '">log in</a>');
    }

    /**
     * Custom login form.
     * 
     * @return false
     * @since   0.0.1
     */
    public function humble_lms_custom_login_form() {
      ob_start();
  
      if( isset( $_GET['login'] ) && $_GET['login'] === 'failed' ) {
        echo '<div class="humble-lms-message humble-lms-message--error">';
          echo '<strong>' . __('Login failed.', 'humble-lms') . '</strong> ' . __('Username and password do not match.', 'humble-lms');
        echo '</div>';
      } else if( isset( $_GET['login'] ) && $_GET['login'] === 'empty' ) {
        // echo '<div class="humble-lms-message humble-lms-message--error">';
        //   echo  '<strong>' . __('Login failed.', 'humble-lms') . '</strong> ' . __('Please enter your username and password.', 'humble-lms');
        // echo '</div>';
      } else if( isset( $_GET['login'] ) && $_GET['login'] === 'false' ) {
        echo '<div class="humble-lms-message humble-lms-message--success">';
          echo  __('You have successfully been logged out.', 'humble-lms');
        echo '</div>';
      } else if( isset( $_GET['login'] ) && $_GET['login'] === 'invalidkey' ) {
        echo '<div class="humble-lms-message humble-lms-message--error">';
          echo sprintf( __('Invalid key. Please try to %s again.', 'humble-lms'), '<a href="' . home_url( 'lost-password' ) . '">' . __('reset your password', 'humble-lms') . '</a>' );
        echo '</div>';
      } else if( isset( $_GET['login'] ) && $_GET['login'] === 'expiredkey' ) {
        echo '<div class="humble-lms-message humble-lms-message--error">';
          echo sprintf( __('Expired key. Please try to %s again.', 'humble-lms'), '<a href="' . home_url( 'lost-password' ) . '">' . __('reset your password', 'humble-lms') . '</a>' );
        echo '</div>';
      } else if( isset( $_GET['password'] ) && $_GET['password'] === 'changed' ) {
        echo '<div class="humble-lms-message humble-lms-message--success">';
          _e('Password changed successfully. Please sign in below.', 'humble-lms');
        echo '</div>';
      }

      if( isset( $_GET['checkemail'] ) && $_GET['checkemail'] === 'confirm' ) {
        echo '<div class="humble-lms-message humble-lms-message--success">' . __('Please check your email inbox for a link to reset your password.', 'humble-lms') . '</div>';
      }

      if ( ! is_user_logged_in() ) {
        $args = array(
            'redirect' => admin_url(), 
            'form_id' => 'humble-lms-custom-login-form',
            'label_username' => __( 'Username', 'humble-lms' ),
            'label_password' => __( 'Password', 'humble-lms' ),
            'label_remember' => __( 'Remember me', 'humble-lms' ),
            'label_log_in' => __( 'Login', 'humble-lms' ),
            'remember' => true
        );
        wp_login_form( $args );
        echo '<p><a href="' . site_url('/wp-login.php?action=lostpassword') . '">' . __('Lost your password?', 'humble-lms') . '</a> | <a href="' . site_url('/wp-login.php?action=register') . '">' . __('Register', 'humble-lms') . '</a></p>';
      } else {
          echo '<p>' . __('You are already signed in.', 'humble-lms') . '</p>';
      }

      return ob_get_clean();
    }

    /**
     * Custom registration form.
     * 
     * @return false
     * @since   0.0.1
     */
    public function humble_lms_custom_registration_form() {
      if( is_user_logged_in() ) {
        return '<p>' . __('You are already signed in.', 'humble-lms') . '</p>';
      }

      if( ! get_option( 'users_can_register' ) ) {
        return '<div class="humble-lms-message humble-lms-message--error">' . __('This site is currently not open for registration.', 'humble-lms') . '</div>';
      }

      ob_start();
      
      if( $codes = Humble_LMS_Admin::humble_lms_errors()->get_error_codes() ) {
        echo '<div class="humble-lms-message humble-lms-message--error">';
          foreach( $codes as $code ) {
            $message = Humble_LMS_Admin::humble_lms_errors()->get_error_message( $code );
            echo '<strong>' . __('Error') . ':</strong> ' . $message . '<br>';
          }
        echo '</div>';
      }

      $registration_has_country = isset( $this->options_manager->options['registration_has_country'] ) && $this->options_manager->options['registration_has_country'] === 1;
      $countries = isset( $this->options_manager->options['registration_countries'] ) ? maybe_unserialize( $this->options_manager->options['registration_countries'] ) : $this->options_manager->countries;

      $post_user_login = isset( $_POST['humble-lms-user-login'] ) ? sanitize_text_field( $_POST['humble-lms-user-login'] ) : '';
      $post_user_first = isset( $_POST['humble-lms-user-first'] ) ? sanitize_text_field( $_POST['humble-lms-user-first'] ) : '';
      $post_user_last = isset( $_POST['humble-lms-user-last'] ) ? sanitize_text_field( $_POST['humble-lms-user-last'] ) : '';
      $post_user_country = $registration_has_country && isset( $_POST['humble-lms-user-country'] ) ? sanitize_text_field( $_POST['humble-lms-user-country'] ) : '';
      $post_user_email = isset( $_POST['humble-lms-user-email'] ) ? sanitize_email( $_POST['humble-lms-user-email'] ) : '';
      $post_user_email_confirm = isset( $_POST['humble-lms-user-email-confirm'] ) ? sanitize_email( $_POST['humble-lms-user-email-confirm'] ) : '';
      $post_email_agreement_checked = isset( $_POST['humble-lms-email-agreement'] ) ? 'checked="checked"' : '';

      ?>
      
      <form id="humble-lms-registration-form" class="humble-lms-form" action="" method="post">
        <fieldset>
          <p>
            <label for="humble-lms-user-login" class="humble-lms-required"><?php _e('Username', 'humble-lms'); ?></label>
            <input name="humble-lms-user-login" id="humble-lms-user-login" class="humble-lms-required" type="text" value="<?php echo $post_user_login; ?>" />
            <input class="humble-lms-honeypot" type="text" name="humble-lms-honeypot" value="" />
          </p>
          <p>
            <label for="humble-lms-user-first" class="humble-lms-required"><?php _e('First Name', 'humble-lms'); ?><br><small><?php _e('Required for certification.', 'humble-lms'); ?></small></label>
            <input name="humble-lms-user-first" id="humble-lms-user-first" type="text" value="<?php echo $post_user_first; ?>" />
          </p>
          <p>
            <label for="humble-lms-user-last" class="humble-lms-required"><?php _e('Last Name', 'humble-lms'); ?><br><small><?php _e('Required for certification.', 'humble-lms'); ?></small></label>
            <input name="humble-lms-user-last" id="humble-lms-user-last" type="text" value="<?php echo $post_user_last; ?>" />
          </p>
          <?php if( $registration_has_country ): ?>
            <p>
              <label for="humble-lms-user-country" class="humble-lms-required"><?php _e('Country', 'humble-lms'); ?></label>
              <select name="humble-lms-user-country" id="humble-lms-user-country">
                <option value=""><?php _e('Please select your country', 'humble-lms'); ?></option>

                <?php 
                foreach( $countries as $key => $country ) {
                  $selected = $country === $post_user_country ? 'selected' : '';
                  echo '<option value="' . $country . '" ' . $selected . '>' . $country . '</option>';
                }
                ?>

              </select>
            </p>
          <?php endif; ?>
          <p>
            <label for="humble-lms-user-email" class="humble-lms-required"><?php _e('Email address', 'humble-lms'); ?></label>
            <input name="humble-lms-user-email" id="humble-lms-user-email" class="humble-lms-required" type="email" value="<?php echo $post_user_email; ?>" />
          </p>
          <p>
            <label for="humble-lms-user-email-confirm" class="humble-lms-required"><?php _e('Confirm email address', 'humble-lms'); ?></label>
            <input name="humble-lms-user-email-confirm" id="humble-lms-user-email-confirm" class="humble-lms-required" type="email" value="<?php echo $post_user_email_confirm; ?>" />
          </p>
          <p>
            <label for="password" class="humble-lms-required">
              <?php _e('Password'); ?><br>
              <small><?php _e('Min. 12 characters, at least 1 letter and 1 number', 'humble-lms'); ?></small>
            </label>
            <input name="humble-lms-user-pass" id="password" class="humble-lms-required" type="password" value="" />
          </p>
          <p>
            <label for="password-again" class="humble-lms-required"><?php _e('Password again', 'humble-lms'); ?></label>
            <input name="humble-lms-user-pass-confirm" id="password-again" class="humble-lms-required" type="password" value="" />
          </p>
          <p>
            <?php $class = isset( $this->options_manager->options['email_agreement'] ) && $this->options_manager->options['email_agreement'] === 1 ? 'humble-lms-required' : ''; ?>
            <label for="email-agreement" class="<?php echo $class; ?>"><?php _e('Email agreement', 'humble-lms'); ?> </label>
            <input name="humble-lms-email-agreement" id="email-agreement" class="<?php echo $class; ?>" type="checkbox" value="1" <?php echo $post_email_agreement_checked; ?> /> <?php _e('Yes, I wish to receive emails from this website which are essential for participating in the online courses.', 'humble-lms'); ?>
          </p>
          <?php
            if( $this->options_manager->has_recaptcha() ) {
              $website_key = $this->options_manager->options['recaptcha_website_key'];

              if( $website_key ) {
                echo '<div class="humble-lms-recaptcha g-recaptcha" data-sitekey="' . $website_key . '"></div>';
              }
            }
          ?>
          <p>
            <input type="hidden" name="humble-lms-register-nonce" value="<?php echo wp_create_nonce('humble-lms-register-nonce'); ?>" />
            <input type="submit" class="humble-lms-btn" value="<?php _e('Register Your Account', 'humble-lms'); ?>"/>
          </p>
        </fieldset>

        <input type="hidden" name="humble-lms-form" value="humble-lms-registration" />
      </form><?php 
      
      echo '<p><a href="' . site_url('/wp-login.php?action=lostpassword') . '">' . __('Lost your password?', 'humble-lms') . '</a> | <a href="' . site_url('/wp-login.php') . '">' . __('Login', 'humble-lms') . '</a></p>';
      
      return ob_get_clean();
    }

    /**
     * Custom lost password form.
     * 
     * @return false
     * @since   0.0.1
     */
    public function humble_lms_custom_lost_password_form() {
      if( is_user_logged_in() ) {
        return '<p>' . __('You are already signed in.', 'humble-lms') . '</p>';
      }

      ob_start();

      if( isset( $_GET['lost_password_sent'] ) ) {
        echo '<p class="humble-lms-message humble-lms-message--success">' . __( 'Check your email for a link to reset your password.', 'humble-lms' ) . '</div>';
      } elseif( isset( $_GET['errors'] ) ) {
        switch( sanitize_text_field( $_GET['errors'] ) ) {
          case 'empty_username':
            $errors[] = __('Please enter a valid email address.', 'humble-lms');
            break;
          case 'invalid_email':
          case 'invalidcombo':
            $errors[] = __('There are no users registered with this email address.', 'humble-lms');
            break;
        }
      } 
  
      if( ! empty( $errors ) ) {
        echo '<div class="humble-lms-message humble-lms-message--error">';
          foreach( $errors as $error ) {
            echo '<strong>' . __('Error') . ':</strong> ' . $error . '<br>';
          }
        echo '</div>';
      }
      
      ?>

      <div id="lostpasswordform" class="humble-lms-lost-password">
        <p><?php _e('Please enter your email address and we will send you a link you can use to pick a new password.', 'humble-lms'); ?></p>
    
        <form id="humble-lms-lost-password-form" action="<?php echo wp_lostpassword_url(); ?>" method="post">
          <p>
            <label for="user_login"><?php _e( 'Your email address', 'humble-lms' ); ?>
            <input type="text" name="user_login" id="user_login">
          </p>
    
          <p class="humble-lms-lost-password-submit">
            <input type="submit" name="submit" class="humble-lms-btn" value="<?php _e( 'Reset Password', 'humble-lms' ); ?>" />
          </p>
        </form>
      </div><?php

      echo '<p><a href="' . site_url('/wp-login.php') . '">' . __('Login', 'humble-lms') . '</a> | <a href="' . site_url('/wp-login.php?action=register') . '">' . __('Register', 'humble-lms') . '</a></p>';
    
      return ob_get_clean();
    }

    /**
     * Custom reset password form.
     * 
     * @return false
     * @since   0.0.1
     */
    public function humble_lms_custom_reset_password_form() {
      if( is_user_logged_in() ) {
        return '<p>' . __('You are already signed in.', 'humble-lms') . '</p>';
      }
      
      ob_start();

      if( isset( $_REQUEST['error'] ) && $_REQUEST['error'] === 'password_reset_mismatch' ) {
        echo '<div class="humble-lms-message humble-lms-message--error"><strong>' . __('Error', 'humble-lms') . ':</strong> ' . __('The passwords you entered do not match.', 'humble-lms') . '</div>';
      }

      if( isset( $_REQUEST['error'] ) && $_REQUEST['error'] === 'password_reset_empty' ) {
        echo '<div class="humble-lms-message humble-lms-message--error"><strong>' . __('Error', 'humble-lms') . ':</strong> ' . __('Your new password should be at least 12 characters long and contain min. 1 letter and 1 number.', 'humble-lms') . '</div>';
      }
      
      ?>

      <form name="resetpassform" id="resetpassform" action="<?php echo site_url( 'wp-login.php?action=resetpass' ); ?>" method="post" autocomplete="off">
        <input type="hidden" id="user_login" name="rp_login" value="<?php echo esc_attr( $_GET['login'] ); ?>" autocomplete="off" />
        <input type="hidden" name="rp_key" value="<?php echo esc_attr( $_GET['key'] ); ?>" />
         
        <?php

        if( $codes = Humble_LMS_Admin::humble_lms_errors()->get_error_codes() ) {
          echo '<div class="humble-lms-message humble-lms-message--error">';
            foreach( $codes as $code ) {
              $message = Humble_LMS_Admin::humble_lms_errors()->get_error_message( $code );
              echo '<strong>' . __('Error') . ':</strong> ' . $message . '<br>';
            }
          echo '</div>';
        }

        ?>
 
        <p>
          <label for="pass1"><?php _e( 'New password', 'humble-lms' ) ?></label>
          <input type="password" name="pass1" id="pass1" class="input" size="20" value="" autocomplete="off" />
        </p>
        <p>
          <label for="pass2"><?php _e( 'Repeat new password', 'humble-lms' ) ?></label>
          <input type="password" name="pass2" id="pass2" class="input" size="20" value="" autocomplete="off" />
        </p>
         
        <p class="description humble-lms-message"><?php echo wp_get_password_hint(); ?></p>
         
        <p class="resetpass-submit">
            <input type="submit" name="submit" id="resetpass-button" class="humble-lms-btn" value="<?php _e( 'Reset Password', 'humble-lms' ); ?>" />
        </p>
      </form><?php

      return ob_get_clean();
    }

    /**
     * Custom user profile.
     * 
     * @return false
     * @since   0.0.1
     */
    public function humble_lms_custom_user_profile() {
      if( ! is_user_logged_in() ) {
        return sprintf( __('Please %s first.', 'humble-lms'), '<a href="' . $this->options_manager->login_url . '">log in</a>');
      }

      $user_id = get_current_user_ID();
      $userdata = get_userdata( $user_id );
      
      ob_start();

      if( isset( $_GET['progress'] ) && esc_attr( $_GET['progress'] ) === 'reset' ) {
        echo '<div class="humble-lms-message humble-lms-message--success">' . __('You progress was reset successfully.', 'humble-lms') . '</div>';
      }
      
      if( $codes = Humble_LMS_Admin::humble_lms_errors()->get_error_codes() ) {
        echo '<div class="humble-lms-message humble-lms-message--error">';
          foreach( $codes as $code ) {
            $message = Humble_LMS_Admin::humble_lms_errors()->get_error_message( $code );
            echo '<strong>' . __('Error') . ':</strong> ' . $message . '<br>';
          }
        echo '</div>';
      } else {
        if( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
          echo '<div class="humble-lms-message humble-lms-message--success">' . __('Profile update successful.', 'humble-lms') . '</div>';
        }
      }

      $registration_has_country = isset( $this->options_manager->options['registration_has_country'] ) && $this->options_manager->options['registration_has_country'] === 1;
      $countries = isset( $this->options_manager->options['registration_countries'] ) ? maybe_unserialize( $this->options_manager->options['registration_countries'] ) : $this->options_manager->countries;
      $user_login = $userdata->user_login;
      $user_first = $userdata->first_name;
      $user_last = $userdata->last_name;
      $user_country = get_user_meta( $user_id, 'humble_lms_country', true );
      $user_email = $userdata->user_email;
      $useremail_confirm = isset( $_POST['humble-lms-user-email'] ) ? sanitize_email( $_POST['humble-lms-user-email'] ) : '';
      $user_membership = get_user_meta( $user_id, 'humble_lms_membership', true );
      $options = get_option('humble_lms_options');
      $checkout_post_id = isset( $options['custom_pages']['checkout'] ) ? (int)$options['custom_pages']['checkout'] : null;

      ?>
      
      <form id="humble-lms-user-profile-form" class="humble-lms-form" action="" method="post">
        <fieldset>

          <?php
          
            if( Humble_LMS_Admin_Options_Manager::has_paypal() ) {
              echo '<label for="humble-lms-user-membership">' . __('Membership', 'humble-lms') . '</label>';
              echo '<p><strong>' . ucfirst( $user_membership ) . '</strong></p>';
              echo $this->content_manager->user_can_upgrade_membership() ? '<p><a class="humble-lms-btn humble-lms-btn--success" href="' . esc_url( get_permalink( $checkout_post_id ) ) . '">' . __('Upgrade membership', 'humble-lms') . '</a></p>' : '';
            } else {
              echo '<label for="humble-lms-user-membership">' . __('Membership', 'humble-lms') . ' <small>(' . __('Account status', 'humble-lms' ) . ')</small></label>';
              echo '<p><strong>' . ucfirst( $user_membership ) . '</strong></p>';
            }

          ?>

          <label for="humble-lms-user-login"><?php _e('Username', 'humble-lms'); ?> <small>(<?php echo __('Can\'t be changed', 'humble-lms' ); ?>)</small></label>
          <p><strong><?php echo $user_login; ?></strong></p>
          <input type="hidden" name="humble-lms-user-login" id="humble-lms-user-login" class="humble-lms-required" type="text" value="<?php echo $user_login; ?>" />
          
          <label for="humble-lms-user-first"><?php _e('First Name', 'humble-lms'); ?> <small>(<?php echo __('Can\'t be changed', 'humble-lms' ); ?>)</small></label>
          <p><strong><?php echo $user_first; ?></strong></p>
          <input type="hidden" name="humble-lms-user-first" id="humble-lms-user-first" type="text" value="<?php echo $user_first; ?>" />
          
          <label for="humble-lms-user-last"><?php _e('Last Name', 'humble-lms'); ?> <small>(<?php echo __('Can\'t be changed', 'humble-lms' ); ?>)</small></label>
          <p><strong><?php echo $user_last; ?></strong></p>
          <input type="hidden" name="humble-lms-user-last" id="humble-lms-user-last" type="text" value="<?php echo $user_last; ?>" />
          <?php if( $registration_has_country ): ?>
            <p>
              <label for="humble-lms-user-country" class="humble-lms-required"><?php _e('Country', 'humble-lms'); ?></label>
              <select name="humble-lms-user-country" id="humble-lms-user-country">
                <option value=""><?php _e('Please select your country', 'humble-lms'); ?></option>

                <?php 
                foreach( $countries as $key => $country ) {
                  $selected = $country === $user_country ? 'selected' : '';
                  echo '<option value="' . $country . '" ' . $selected . '>' . $country . '</option>';
                }
                ?>

              </select>
            </p>
          <?php endif; ?>
          <p>
            <label for="humble-lms-user-email" class="humble-lms-required"><?php _e('Email address', 'humble-lms'); ?></label>
            <input name="humble-lms-user-email" id="humble-lms-user-email" class="humble-lms-required" type="email" value="<?php echo $user_email; ?>" />
          </p>
          <p>
            <label for="humble-lms-user-email-confirm" class="humble-lms-required"><?php _e('Confirm email address', 'humble-lms'); ?></label>
            <input name="humble-lms-user-email-confirm" id="humble-lms-user-email-confirm" class="humble-lms-required" type="email" value="" />
          </p>
          <p>
            <label for="password" class="humble-lms-required">
              <?php _e('Password'); ?><br>
              <small><?php _e('Min. 12 characters, at least 1 letter and 1 number', 'humble-lms'); ?></small>
            </label>
            <input name="humble-lms-user-pass" id="password" class="humble-lms-required" type="password" value="" />
          </p>
          <p>
            <label for="password-again" class="humble-lms-required"><?php _e('Password again', 'humble-lms'); ?></label>
            <input name="humble-lms-user-pass-confirm" id="password-again" class="humble-lms-required" type="password" value="" />
          </p>
          <p>
            <input type="hidden" name="humble-lms-update-user-nonce" value="<?php echo wp_create_nonce('humble-lms-update-user-nonce'); ?>" />
            <input type="submit" class="humble-lms-btn" value="<?php _e('Save changes', 'humble-lms'); ?>"/>
          </p>
        </fieldset>

        <input type="hidden" name="humble-lms-form" value="humble-lms-update-user" />
      </form>
      
      <p><a id="humble-lms-reset-user-progress" data-user-id="<?php echo get_current_user_ID(); ?>" class="humble-lms-btn humble-lms-btn--error"><small><?php _e('Reset my learning progress', 'humble-lms'); ?></small></a></p>
      
      <?php 
       
      return ob_get_clean();
    }

    /**
     * List of purchased items for a single user.
     * 
     * @return string
     * @since   0.0.1
     */
    public function humble_lms_user_purchases( $user_id = null ) {
      $purchases = $this->user->purchases( $user_id );

      // TODO

      if( ! isset( $purchases ) || empty( $purchases ) ) {
        return '<p>' . __('You have not purchased any courses yet.', 'humble-lms') . '</p>';
      }

      $purchased_tracks = array();
      $purchased_courses = array();

      foreach( $purchases as $item ) {
        $post_type = get_post_type( $item );
        if( $post_type === 'humble_lms_track' ) {
          array_push( $purchased_tracks, $item );
        } else if( $post_type === 'humble_lms_course' ) {
          array_push( $purchased_courses, $item );
        }
      }

      $html = '<h4>' . __('Tracks', 'humble-lms') . '</h4>';
      if( empty( $purchased_tracks ) ) {
        $html .= '<p>' . __('You have not purchased any tracks yet.', 'humble-lms') . '</p>';
      } else { 
        $html .= '<ul class="humble-lms-user-purchases">';
          foreach( $purchased_tracks as $track ) {
            $track = get_post( $track );
            $html .= '<li><a href="' . esc_url( get_permalink( $track->ID ) ) . '">' . $track->post_title . '</a></li>';
          }
        $html .= '</ul>';
      }

      $html .= '<h4>' . __('Courses', 'humble-lms') . '</h4>';
      if( empty( $purchased_courses ) ) {
        $html .= '<p>' . __('You have not purchased any courses yet.', 'humble-lms') . '</p>';
      } else { 
        $html .= '<ul class="humble-lms-user-purchases">';
          foreach( $purchased_courses as $course ) {
            $course = get_post( $course );
            $html .= '<li><a href="' . esc_url( get_permalink( $course->ID ) ) . '">' . $course->post_title . '</a></li>';
          }
        $html .= '</ul>';
      }

      return $html;
    }

    /**
     * List of transactions for a single user.
     * 
     * @return string
     * @since   0.0.1
     */
    public function humble_lms_user_transactions() {
      if( ! is_user_logged_in() )
        return;

      $user_id = get_current_user_id();

      $html = '';

      $args = array(
        'post_type' => 'humble_lms_txn',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => array(
          array(
            'key' => 'humble_lms_txn_user_id',
            'value' => $user_id,
            'compare' => '=',
          ),
        ),
        'order' => 'DESC',
      );

      $transactions = get_posts( $args );

      if( ! $transactions ) {
        return '<p>' . __('No transactions found.', 'humble-lms') . '</p>';
      }

      foreach( $transactions as $txn ) {
        $user_id_txn = get_post_meta( $txn->ID, 'humble_lms_txn_user_id', true );
        $order_details = get_post_meta( $txn->ID, 'humble_lms_order_details', false );
        $order_details = isset( $order_details[0] ) ? $order_details[0] : $order_details;

        $order_id = isset( $order_details['order_id'] ) ? $order_details['order_id'] : '';
        $email_address = isset( $order_details['email_address'] ) ? $order_details['email_address'] : '';
        $payer_id = isset( $order_details['payer_id'] ) ? $order_details['payer_id'] : '';
        $status = isset( $order_details['status'] ) ? $order_details['status'] : '';
        $payment_service_provider = isset( $order_details['payment_service_provider'] ) ? $order_details['payment_service_provider'] : '';
        $create_time = isset( $order_details['create_time'] ) ? $order_details['create_time'] : '';
        $update_time = isset( $order_details['update_time'] ) ? $order_details['update_time'] : '';
        $given_name = isset( $order_details['given_name'] ) ? $order_details['given_name'] : '';
        $surname = isset( $order_details['surname'] ) ? $order_details['surname'] : '';
        $reference_id = isset( $order_details['reference_id'] ) ? $order_details['reference_id'] : '';
        $currency_code = isset( $order_details['currency_code'] ) ? $order_details['currency_code'] : '';
        $amount = isset( $order_details['value'] ) ? $order_details['value'] : '';
        $content_link = '';
        $post_type = get_post_type( $reference_id );

        $created = new DateTime($create_time);
        $created = $created->format('Y-m-d H:i:s');

        $updated = new DateTime($update_time);
        $updated = $updated->format('Y-m-d H:i:s');

        $html .= '<div class="humble-lms-user-transaction">';
          $html .= '<div class="humble-lms-user-transaction__title">';
            $html .= '<div class="humble-lms-user-transaction__title_name">';
              
              if( $post_type === 'humble_lms_track' ) {
                $content_type = __('Track', 'humble-lms');
              } elseif( $post_type === 'humble_lms_course' ) {
                $content_type = __('Course', 'humble-lms');
              } else {
                $content_type = __('Unknown', 'humble-lms');
              }

              if( get_post( $reference_id ) ) {
                $content = get_post( $reference_id );
                $content_link = '<a href="' . esc_url( get_permalink( $reference_id ) ) . '">' . esc_url( get_permalink( $reference_id ) ) . '</a>';
                $html .= $content->post_title . ' <span>(' . $content_type . ')</span>';
              } else {
                echo __('Unknown content', 'humbl-lms');
                $content_link = __('not available', 'humble-lms');
              }
            $html .= '</div>';
            $html .= '<span class="humble-lms-user-transaction__amount">' . $currency_code . ' ' . $amount . '</span> | ' . $created . ' | ID ' . $txn->ID;
          $html .= '</div>';
          $html .= '<div class="humble-lms-user-transaction__content">';
            $html .= '<p><strong>' . __('Transaction ID', 'humble-lms') . ':</strong> ' . $txn->ID . '</p>';
            $html .= '<p><strong>' . __('Reference ID', 'humble-lms') . ':</strong> ' . $reference_id . '</p>';
            $html .= '<p><strong>' . __('URL', 'humble-lms') . ':</strong> ' . $content_link . '</p>';
            $html .= '<p><strong>' . __('User ID', 'humble-lms') . ':</strong> ' . $user_id_txn . '</p>';
            $html .= '<p><strong>' . __('Amount', 'humble-lms') . ':</strong> ' . $amount . '</p>';
            $html .= '<p><strong>' . __('Currency code', 'humble-lms') . ':</strong> ' . $currency_code . '</p>';
            $html .= '<p><strong>' . __('Order ID', 'humble-lms') . ':</strong> ' . $order_id . '</p>';
            $html .= '<p><strong>' . __('Email adress', 'humble-lms') . ':</strong> ' . $email_address . '</p>';
            $html .= '<p><strong>' . __('Payer ID', 'humble-lms') . ':</strong> ' . $payer_id . '</p>';
            $html .= '<p><strong>' . __('Status', 'humble-lms') . ':</strong> ' . $status . '</p>';
            $html .= '<p><strong>' . __('Payer ID', 'humble-lms') . ':</strong> ' . $payer_id . '</p>';
            $html .= '<p><strong>' . __('Payment service provider', 'humble-lms') . ':</strong> ' . $payment_service_provider . '</p>';
            $html .= '<p><strong>' . __('Create time', 'humble-lms') . ':</strong> ' . $created . '</p>';
            $html .= '<p><strong>' . __('Update time', 'humble-lms') . ':</strong> ' . $updated . '</p>';
            $html .= '<p><strong>' . __('Given name', 'humble-lms') . ':</strong> ' . $given_name . '</p>';
            $html .= '<p><strong>' . __('Surname', 'humble-lms') . ':</strong> ' . $surname . '</p>';
          $html .= '</div>';
        $html .= '</div>';
      }

      return $html;
    }

    /**
     * Quizzes.
     * 
     * @return false
     * @since   0.0.1
     */
    public function humble_lms_quiz( $atts = null ) {
      extract( shortcode_atts( array (
        'ids' => '',
        'style' => '',
        'class' => '',
      ), $atts ) );

      if( ! $ids ) {
        $ids = [];
        return '<p>' . __('Please enter at least one valid quiz ID.', 'humble-lms') . '</p>';
      }

      $quizzes = $this->quiz->get( $ids );
      
      $html = '';
      $html .= '<div class="humble-lms-quiz-message"><div><div class="humble-lms-quiz-message-inner"><div>
        <div class="humble-lms-quiz-message-close" aria-label="Close quiz overlay">
          <i class="ti-close"></i>
        </div>
        <div class="humble-lms-message-quiz humble-lms-message-quiz--completed">
          <h3 class="humble-lms-quiz-message-title">' . __('Well done!', 'humble-lms') . '</h3>
          <p>' . __('You passed this quiz with a score of', 'humble-lms') . '</p><p><span class="humble-lms-quiz-score"></span></p>
        </div>
        <div class="humble-lms-message-quiz humble-lms-message-quiz--failed">
          <h3 class="humble-lms-quiz-message-title">' . __('Bummer', 'humble-lms') . '</h3>
          <p>' . __('You failed this quiz with a score of', 'humble-lms') . '</p>
          <p><span class="humble-lms-quiz-score"></span></p>
        </div>
        <div class="humble-lms-quiz-message-image humble-lms-bounce-in"></div>
      </div></div></div></div>';
      $html .= '<div class="humble-lms-quiz ' . $class . '" style="' . $style . '">';

      if ( $quizzes ) {
        
        foreach( $quizzes as $quiz ) {
          $questions = $this->quiz->questions( $quiz->ID );
          $passing_grade = $this->quiz->get_passing_grade( $quiz->ID );
          $passing_required = $this->quiz->get_passing_required( $quiz->ID ) ? '1' : '0';

          $html .= '<div class="humble-lms-quiz-single" data-passing-grade="' . $passing_grade . '" data-passing-required="' . $passing_required . '">';
  
          foreach( $questions as $question ) {
            $question_type = $this->quiz->question_type( $question->ID );
  
            $html .= '<div class="humble-lms-quiz-question ' . $question_type . '" data-id="' . $question->ID . '">';
              $title = get_post_meta( $question->ID, 'humble_lms_question', true );
              $html .= '<h3 class="humble-lms-quiz-question-title">' . htmlspecialchars( $title ) . '</h3>';
              
              switch( $question_type ) {
                case 'single_choice':
                  $answers = $this->quiz->answers( $question->ID );
                  $html .= $this->quiz->single_choice( $quiz->ID, $answers );
                  break;

                case 'multiple_choice':
                  $answers = $this->quiz->answers( $question->ID );
                  $html .= $this->quiz->multiple_choice( $quiz->ID, $answers );
                  break;

                default:
                  break;
              }
            $html .= '</div>';
          }

          $html .= '</div>';

        }
      } else {
        $html .= '<p>' . __('No quizzes found.', 'humble-lms') . '</p>';
      }

      $html .= '</div>';

      return $html;
    }

    /**
     * PayPal Buttons.
     * 
     * @return false
     * @since   0.0.1
     */
    public function humble_lms_paypal_buttons( $atts = null ) {
      if( ! Humble_LMS_Admin_Options_Manager::has_paypal() ) {
        return;
      }

      if( ! is_user_logged_in() ) {
        $options = get_option('humble_lms_options');
        $login_url = esc_url( get_permalink( $options['custom_pages']['login'] ) );
        $registration_url = esc_url( get_permalink( $options['custom_pages']['registration'] ) );

        return '<div class="humble-lms-message humble-lms-message--success">
          <div class="humble-lms-message-title">' . __('Membership', 'humble-lms') . '</div>
          <div class="humble-lms-message-content">' . sprintf( __('If you would like to purchase a premium memberhsip please %s and %s.', 'humble-lms'), '<a href="' . $registration_url . '">' . __('register an account', 'humble-lms') . '</a>', '<a href="' . $login_url . '">' . __('log in', 'humble-lms') . '</a>' ) .'</div>
        </div>';
      }

      $html = '';
      $options = $this->options_manager;
      $currency = $options->get_currency();

      // Memberships table
      $membership_posts = Humble_LMS_Admin::get_memberships(false);
      $user_membership = get_user_meta( get_current_user_id(), 'humble_lms_membership', true );
      $user_membership_price = Humble_LMS_Admin::get_membership_price_by_slug( $user_membership );
  
      $html .= '<p>' . __('Please select the membership type you would like to purchase. You can upgrade your membership status anytime you want. Your current membership status:', 'humble-lms') . ' <strong>' . ucfirst( $user_membership ) . '</strong></p>';
      $html .= '<div class="humble-lms-checkout-memberships">';

      
      foreach( $membership_posts as $post ) {
        $price = Humble_LMS_Admin::get_membership_price_by_slug( $post->post_name );
        $description = get_post_meta( $post->ID, 'humble_lms_mbship_description', true );
        $class = $price <= $user_membership_price || $user_membership === $post->post_name ? 'disabled' : '';
        
        $price_difference = floatval($price) - floatval($user_membership_price);
        if( $price_difference < 0 ) $price_difference = 0.00;
        $price_difference = number_format( $price_difference, 2 );
        
        $html .= '<div class="humble-lms-checkout-membership ' . $class . '">';
          $html .= '<div class="humble-lms-checkout-membership-input">';
            $html .= '<input type="radio" name="humble_lms_membership" value="' . $post->post_name . '" data-price="' . number_format( $price_difference, 2 ) . '">' . $post->post_title;
            $html .= '<span class="humble-lms-checkout-membership-price">' .  $currency . '&nbsp;' . number_format( $price_difference, 2 ) . '</span>';
          $html .= '</div>';

          if( $description ) {
            $html .= '<div class="humble-lms-checkout-membership-description">';
              $html .= $description;
            $html .= '</div>';
          }
        $html .= '</div>';
      }

      $html .= '</div>';
      $html .= '<div class="humble-lms-btn humble-lms-btn--disabled humble-lms-btn--success humble-lms-btn--purchase humble-lms-btn--purchase-membership">' . __('Buy now', 'humble-lms') . '</a></div>';

      // Paypal container

      $memberships = Humble_LMS_Admin::get_memberships();

      if( ! $memberships ) {
        return __('No memberships found.', 'humble-lms');
      }

      if( ! is_user_logged_in() ) {
        return $this->display_login_text();
      }

      if( ! Humble_LMS_Admin_Options_Manager::has_paypal() ) {
        if( current_user_can('manage_options') ) {
          return '<p>' . __('Please provide your PayPal credentials first.', 'humble-lms') . '</p>';
        } else {
          return '';
        }
      }
      
      $html .= '<div class="humble-lms-lightbox-wrapper">';
        $html .= '<div class="humble-lms-lightbox">';
          $html .= '<div class="humble-lms-lightbox-title">' . __('Purchase membership', 'humble-lms') . '</div>';
          $html .= '<p></p>';
          $html .= '<div id="humble-lms-paypal-buttons" data-membership="" data-price="" data-context="membership"></div>';
        $html .= '</div>';
      $html .= '</div>';
      
      // else {
      //   $html .= '<p>' . __('Woohoo, your account has been upgraded! 😊', 'humble-lms') . '</p><p><a class="humble-lms-btn" href="' . esc_url( site_url() ) . '">' . __('Back to home page', 'humble-lms') . '</a></p>';
      // }

      return $html;
    }

    /**
     * PayPal buy course/track
     * 
     * @return false
     * @since   0.0.1
     */
    public function humble_lms_paypal_buttons_single_item( $atts = null ) {
      global $post;

      if( ! Humble_LMS_Admin_Options_Manager::has_paypal() ) {
        return;
      }

      if( ! is_user_logged_in() ) {
        return $this->purchase_message();
      }

      extract( shortcode_atts( array (
        'post_id' => '',
      ), $atts ) );

      if( ! $post_id ) {
        $post_id = $post->ID;
      }

      $is_for_sale = get_post_meta( $post_id, 'humble_lms_is_for_sale', true );

      if( (int)$is_for_sale !== 1 )
        return '';
      
      $price = $this->content_manager->get_price( $post_id );

      $html = '';
      $currency = $this->options_manager->get_currency();

      if( ! $this->user->purchased( $post_id ) ) {
        $html .= $this->purchase_message();
        $html .= '<div class="humble-lms-lightbox-wrapper">';
          $html .= '<div class="humble-lms-lightbox">';
            $html .= '<div class="humble-lms-lightbox-title">' . __('Purchase now', 'humble-lms') . '</div>';
            $html .= '<p>' . get_the_title( $post_id ) . ', <strong>' . $currency . ' ' . $price . '*</strong></p>';
            $html .= '<div id="humble-lms-paypal-buttons-single-item" data-post-id="' . $post_id . '" data-price="' . $price . '" data-context="single"></div>';
          $html .= '</div>';
        $html .= '</div>';
      }

      return $html;
    }

    /**
     * Purchase message
     * 
     * @since   0.0.1
     */
    public function purchase_message() {
      global $post;

      if( ! get_post( $post->ID ) ) {
        return;
      }

      $post_type = get_post_type( $post->ID );
      $allowed_post_types = array(
        'humble_lms_track',
        'humble_lms_course',
      );

      if( ! in_array( $post_type, $allowed_post_types ) ) {
        return;
      }

      if( ! is_user_logged_in() ) {
        $options = get_option('humble_lms_options');
        $login_url = esc_url( get_permalink( $options['custom_pages']['login'] ) );
        $registration_url = esc_url( get_permalink( $options['custom_pages']['registration'] ) );

        switch( $post_type ) {
          case 'humble_lms_course':
            return '<div class="humble-lms-message humble-lms-message--success">
              <div class="humble-lms-message-title">' . __('Purchase this course', 'humble-lms') . '</div>
              <div class="humble-lms-message-content">' . sprintf( __('If you would like to purchase this course please %s and %s.', 'humble-lms'), '<a href="' . $registration_url . '">' . __('register an account', 'humble-lms') . '</a>', '<a href="' . $login_url . '">' . __('log in', 'humble-lms') . '</a>' ) .'</div>
            </div>';
            break;
          case 'humble_lms_track':
            return '<div class="humble-lms-message humble-lms-message--success">
              <div class="humble-lms-message-title">' . __('Purchase this track', 'humble-lms') . '</div>
              <div class="humble-lms-message-content">' . sprintf( __('If you would like to purchase all courses in this track please %s and %s.', 'humble-lms'), '<a href="' . $registration_url . '">' . __('register an account', 'humble-lms') . '</a>', '<a href="' . $login_url  . '">' . __('log in', 'humble-lms') . '</a>' ) .'</div>
            </div>';
            break;
        }
      }


      switch( get_post_type( $post->ID ) ) {
        case 'humble_lms_course':
          $html = '<div class="humble-lms-message humble-lms-message--success">';
            $html .= '<div class="humble-lms-message-title">' . __('Purchase this course', 'humble-lms') . '</div>';
            $html .= '<div class="humble-lms-message-content">';
              $html .= '<p>' . __('Please click the button below if your would like to purchase this course.', 'humble-lms') . '</p>';
              $html .= '<div class="humble-lms-btn humble-lms-btn--success humble-lms-btn--purchase humble-lms-toggle-lightbox">' . __('Buy now for', 'humble-lms') . ' ' . $this->options_manager->get_currency() . ' ' . Humble_LMS_Content_Manager::get_price( $post->ID ) . '*</a></div>';
            $html .= '</div>';
          $html .= '</div>';
          break;
        case 'humble_lms_track':
          $html = '<div class="humble-lms-message humble-lms-message--success">';
            $html .= '<div class="humble-lms-message-title">' . __('Purchase this track', 'humble-lms') . '</div>';
            $html .= '<div class="humble-lms-message-content">';
              $html .= '<p>' . __('Please click the button below if your would like to purchase this track and all its containing courses.', 'humble-lms') . '</p>';
              $html .= '<div class="humble-lms-btn humble-lms-btn--success humble-lms-btn--purchase humble-lms-toggle-lightbox">' . __('Buy now for', 'humble-lms') . ' ' . $this->options_manager->get_currency() . ' ' . Humble_LMS_Content_Manager::get_price( $post->ID ) . '*</a></div>';
            $html .= '</div>';
          $html .= '</div>';
          break;
      }

      return $html;
    }
    
  }
  
}
