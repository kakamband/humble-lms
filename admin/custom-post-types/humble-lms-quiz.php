<?php

$labels = array(
  'name'                  => _x( 'Quizzes', 'Post Type General Name', 'humble-lms' ),
  'singular_name'         => _x( 'Quiz', 'Post Type Singular Name', 'humble-lms' ),
  'menu_name'             => __( 'Quizzes', 'humble-lms' ),
  'name_admin_bar'        => __( 'Quiz', 'humble-lms' ),
  'archives'              => __( 'Quiz Archives', 'humble-lms' ),
  'attributes'            => __( 'Quiz Attributes', 'humble-lms' ),
  'parent_item_colon'     => __( 'Parent Quiz:', 'humble-lms' ),
  'all_items'             => __( 'All Quizzes', 'humble-lms' ),
  'add_new_item'          => __( 'Add New Quiz', 'humble-lms' ),
  'add_new'               => __( 'Add New', 'humble-lms' ),
  'new_item'              => __( 'New Quiz', 'humble-lms' ),
  'edit_item'             => __( 'Edit Quiz', 'humble-lms' ),
  'update_item'           => __( 'Update Quiz', 'humble-lms' ),
  'view_item'             => __( 'View Quiz', 'humble-lms' ),
  'view_items'            => __( 'View Quizzes', 'humble-lms' ),
  'search_items'          => __( 'Search Quiz', 'humble-lms' ),
  'not_found'             => __( 'Not found', 'humble-lms' ),
  'not_found_in_trash'    => __( 'Not found in Trash', 'humble-lms' ),
  'featured_image'        => __( 'Featured Image', 'humble-lms' ),
  'set_featured_image'    => __( 'Set featured image', 'humble-lms' ),
  'remove_featured_image' => __( 'Remove featured image', 'humble-lms' ),
  'use_featured_image'    => __( 'Use as featured image', 'humble-lms' ),
  'insert_into_item'      => __( 'Insert into quiz', 'humble-lms' ),
  'uploaded_to_this_item' => __( 'Uploaded to this quiz', 'humble-lms' ),
  'items_list'            => __( 'Quizzes list', 'humble-lms' ),
  'items_list_navigation' => __( 'Quizzes list navigation', 'humble-lms' ),
  'filter_items_list'     => __( 'Filter quizzes list', 'humble-lms' ),
);

$rewrite = array(
  'slug'                  => __('quiz', 'humble-lms'),
  'with_front'            => true,
);

$args = array(
  'label'                 => __( 'Quiz', 'humble-lms' ),
  'description'           => __( 'Quiz', 'humble-lms' ),
  'labels'                => $labels,
  'supports'              => array( 'title', 'revisions'),
  'show_in_rest'          => true,
  'taxonomies'            => array( 'category', 'post_tag' ),
  'hierarchical'          => false,
  'public'                => true,
  'show_ui'               => true,
  'show_in_menu'          => true,
  'menu_position'         => 5,
  'menu_icon'             => 'dashicons-welcome-learn-more',
  'show_in_admin_bar'     => false,
  'show_in_nav_menus'     => false,
  'can_export'            => true,
  'has_archive'           => false,
  'exclude_from_search'   => true,
  'publicly_queryable'    => true,
  'rewrite'               => $rewrite,
  'capability_type'       => 'page',
);

register_post_type( 'humble_lms_quiz', $args );

// Quiz meta boxes

function humble_lms_quiz_add_meta_boxes()
{
  add_meta_box( 'humble_lms_quiz_questions_mb', __('Questions in this quiz', 'humble-lms'), 'humble_lms_quiz_questions_mb', 'humble_lms_quiz', 'normal', 'default' );
  add_meta_box( 'humble_lms_quiz_passing_percent_mb', __('Percent of correct answers needed to pass the quiz (%)', 'humble-lms'), 'humble_lms_quiz_passing_percent_mb', 'humble_lms_quiz', 'normal', 'default' );
  add_meta_box( 'humble_lms_quiz_passing_required_mb', __('Passing required', 'humble-lms'), 'humble_lms_quiz_passing_required_mb', 'humble_lms_quiz', 'normal', 'default' );
  add_meta_box( 'humble_lms_quiz_include_result_mb', __('Include result in course total', 'humble-lms'), 'humble_lms_quiz_include_result_mb', 'humble_lms_quiz', 'normal', 'default' );
  add_meta_box( 'humble_lms_quiz_max_attempts_mb', __('Max. number of attempts', 'humble-lms'), 'humble_lms_quiz_max_attempts_mb', 'humble_lms_quiz', 'normal', 'default' );
  add_meta_box( 'humble_lms_quiz_shuffle_mb', __('Randomization', 'humble-lms'), 'humble_lms_quiz_shuffle_mb', 'humble_lms_quiz', 'normal', 'default' );
}

add_action( 'add_meta_boxes', 'humble_lms_quiz_add_meta_boxes' );

// Meta box questions
function humble_lms_quiz_questions_mb() {
  global $post;

  wp_nonce_field('humble_lms_meta_nonce', 'humble_lms_meta_nonce');

  $translator = new Humble_LMS_Translator;
  $quiz_questions = Humble_LMS_Content_Manager::get_quiz_questions( $post->ID );

  $args = array(
    'post_type' => 'humble_lms_question',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => 'title',
    'order' => 'ASC',
    'exclude' => $quiz_questions,
    'lang' => $translator->current_language(),
  );

  $questions = get_posts( $args );

  $selected_questions = [];
  foreach( $quiz_questions as $id ) {
    if( get_post_status( $id ) ) {
      if( $translator->get_post_language( $id ) !== $translator->current_language() ) {
        continue;
      }

      $question = get_post( $id );
      array_push( $selected_questions, $question );
    }
  }

  // Add quiz lightbox
  echo '<div class="humble-lms-add-content-lightbox-wrapper">';
    echo '<div class="humble-lms-add-content-lightbox" data-post_type="humble_lms_question" data-lang="' . $translator->current_language() . '">';
      echo '<div class="humble-lms-add-content-lightbox-title">' . __('Add question', 'humble-lms') . '</div>';
      echo '<input type="text" class="widefat humble-lms-add-content-name" name="humble-lms-add-content-name" value="" placeholder="' . __('Question title', 'humble-lms') . '&hellip;">';
      echo '<p class="humble-lms-add-content-error" data-message="' . __('Please add a question title.', 'humble-lms') . '"></p>';
      echo '<a class="button button-primary humble-lms-add-content-submit">' . __('Create and add', 'humble-lms') . '</a> <a class="button humble-lms-add-content-cancel">' . __('Close') . '</a>';
      echo '<p class="humble-lms-add-content-success"><a target="_blank">' . __('Content added – click to edit.', 'humble-lms') . '</a></p>';
    echo '</div>';
  echo '</div>';

  if( $questions || $selected_questions ):

    echo '<div id="humble-lms-admin-quiz-questions">';
      echo '<select class="humble-lms-searchable" data-content="quiz_questions"  multiple="multiple">';
        foreach( $selected_questions as $question ) {
          echo '<option data-id="' . $question->ID . '" value="' . $question->ID . '" ';
            if( is_array( $quiz_questions ) && in_array( $question->ID, $quiz_questions ) ) { echo 'selected'; }
          echo '>' . $question->post_title . ' (ID ' . $question->ID . ')</option>';
        }
        foreach( $questions as $question ) {
          echo '<option data-id="' . $question->ID . '" value="' . $question->ID . '" ';
            if( is_array( $quiz_questions ) && in_array( $question->ID, $quiz_questions ) ) { echo 'selected'; }
          echo '>' . $question->post_title . ' (ID ' . $question->ID . ')</option>';
        }
      echo '</select>';
      echo '<input class="humble-lms-multiselect-value" id="humble_lms_quiz_questions" name="humble_lms_quiz_questions" type="hidden" value="' . implode(',', $quiz_questions) . '">';
    echo '</div>';
  
  else:

    echo '<p>' . __('No quiz questions found. Please add one or more question first.', 'humble-lms') . '</p>';

  endif;

  echo '<p><a class="humble-lms-open-admin-lightbox button button-primary">' . __('Add question', 'humble-lms') . '</a></p>';
}

// Meta box passing percent
function humble_lms_quiz_passing_percent_mb()
{
  global $post;

  $passing_percent = absint( get_post_meta( $post->ID, 'humble_lms_quiz_passing_percent', true ) );

  echo '<input class="widefat" name="humble_lms_quiz_passing_percent" id="humble_lms_quiz_passing_percent" type="number" value="' . $passing_percent . '" min="0" max="100">';
}

// Meta box include in total

function humble_lms_quiz_include_result_mb() {
  global $post;

  $include = get_post_meta($post->ID, 'humble_lms_quiz_include_result', true);
  $checked = $include ? 'checked' : '';

  echo '<p><input type="checkbox" name="humble_lms_quiz_include_result" id="humble_lms_quiz_include_result" value="1" ' . $checked . '>' . __('Include quiz result in course results.', 'humble-lms') . '</p>';
}

// Meta box passing required

function humble_lms_quiz_passing_required_mb() {
  global $post;

  $required = get_post_meta($post->ID, 'humble_lms_quiz_passing_required', true);
  $checked = $required ? 'checked' : '';

  echo '<p><input type="checkbox" name="humble_lms_quiz_passing_required" id="humble_lms_quiz_passing_required" value="1" ' . $checked . '>' . __('Students have to pass this quiz in order to complete the lesson.', 'humble-lms') . '</p>';
}

// Meta box maxiumum number of attempts

function humble_lms_quiz_max_attempts_mb() {
  global $post;

  $num_attempts = get_post_meta($post->ID, 'humble_lms_quiz_max_attempts', true);

  if( ! isset( $num_attempts ) ) {
    $num_attempts = 0;
  }

  echo '<p><input type="number" min="0" max="9999" name="humble_lms_quiz_max_attempts" id="humble_lms_quiz_max_attempts" value="' . absint( $num_attempts ) . '"></p>';
  echo '<p class="description">' . __('A value of "0" means that the number of attempts per user is not limited.', 'humble-lms') . '</p>';
}

// Meta box randomization

function humble_lms_quiz_shuffle_mb() {
  global $post;

  $shuffle = get_post_meta( $post->ID, 'humble_lms_shuffle', true );
  $checked = $shuffle === '1' ? 'checked="checked"' : '';

  echo '<input type="checkbox" name="humble_lms_shuffle" id="humble_lms_shuffle" value="1" ' . $checked . '> ' . __('Shuffle all answers in this quiz.', 'humble-lms');
}

// Save metabox data

function humble_lms_save_quiz_meta_boxes( $post_id, $post )
{
  $nonce = ! empty( $_POST['humble_lms_meta_nonce'] ) ? $_POST['humble_lms_meta_nonce'] : '';

  if( ! wp_verify_nonce( $nonce, 'humble_lms_meta_nonce' ) ) {
    return $post_id;
  }
  
  if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
    return $post_id;
  }

  if( ! is_admin() ) {
    return false;
  }
  
  if ( ! current_user_can( 'edit_post', $post_id ) ) {
    return $post_id;
  }
  
  if ( ( ! $post_id ) || get_post_type( $post_id ) !== 'humble_lms_quiz' ) {
    return false;
  }

  // Let's save some data!
  $quiz_meta['humble_lms_quiz_questions'] = isset( $_POST['humble_lms_quiz_questions'] ) ? explode(',', $_POST['humble_lms_quiz_questions']) : array();
  $quiz_meta['humble_lms_quiz_questions'] = array_map( 'esc_attr', $quiz_meta['humble_lms_quiz_questions'] );
  $quiz_meta['humble_lms_quiz_passing_percent'] = absint( $_POST['humble_lms_quiz_passing_percent'] );
  $quiz_meta['humble_lms_quiz_include_result'] = absint( $_POST['humble_lms_quiz_include_result'] );
  
  if( $quiz_meta['humble_lms_quiz_passing_percent'] > 100 )
    $quiz_meta['humble_lms_quiz_passing_percent'] = 100;

  $quiz_meta['humble_lms_quiz_passing_required'] = isset( $_POST['humble_lms_quiz_passing_required'] ) ? 1 : 0;
  $quiz_meta['humble_lms_quiz_max_attempts'] = isset( $_POST['humble_lms_quiz_max_attempts'] ) ? absint( $_POST['humble_lms_quiz_max_attempts'] ) : 0;
  $quiz_meta['humble_lms_shuffle'] = isset( $_POST['humble_lms_shuffle'] ) ? 1 : 0;
  
  if( ! empty( $quiz_meta ) && sizeOf( $quiz_meta ) > 0 )
  {
    foreach ($quiz_meta as $key => $value)
    {
      if( $post->post_type == 'revision' ) return; // Don't store custom data twice

      if( get_post_meta( $post->ID, $key, FALSE ) ) {
        update_post_meta( $post->ID, $key, $value );
      } else {
        add_post_meta( $post->ID, $key, $value );
      }

      if( ! $value ) delete_post_meta( $post->ID, $key ); // Delete if blank
    }
  }
}

add_action('save_post', 'humble_lms_save_quiz_meta_boxes', 1, 2);
