<?php
/**
 * Register all ajax hooks.
 *
 * @author    Rahul Aryan <support@anspress.io>
 * @license   GPL-2.0+
 * @link      https://anspress.io
 * @copyright 2014 Rahul Aryan
 * @package   AnsPress/ajax
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register all ajax callback
 */
class AnsPress_Ajax {
	/**
	 * Initialize the plugin by setting localization and loading public scripts
	 * and styles.
	 */
	public static function init() {
		anspress()->add_action( 'ap_ajax_suggest_similar_questions', __CLASS__, 'suggest_similar_questions' );
		anspress()->add_action( 'ap_ajax_set_featured', __CLASS__, 'set_featured' );
		anspress()->add_action( 'ap_ajax_hover_card', __CLASS__, 'hover_card' );
		anspress()->add_action( 'ap_ajax_close_question', __CLASS__, 'close_question' );
		anspress()->add_action( 'ap_ajax_select_best_answer', __CLASS__, 'select_best_answer' );
		anspress()->add_action( 'ap_ajax_delete_post', __CLASS__, 'delete_post' );
		anspress()->add_action( 'ap_ajax_permanent_delete_post', __CLASS__, 'permanent_delete_post' );
		anspress()->add_action( 'ap_ajax_restore_post', __CLASS__, 'restore_post' );
		anspress()->add_action( 'ap_ajax_load_tinymce_assets', __CLASS__, 'load_tinymce_assets' );
		anspress()->add_action( 'ap_ajax_filter_search', __CLASS__, 'filter_search' );
		anspress()->add_action( 'ap_ajax_convert_to_post', __CLASS__, 'convert_to_post' );
		anspress()->add_action( 'ap_ajax_delete_attachment', __CLASS__, 'delete_attachment' );
		anspress()->add_action( 'ap_ajax_get_all_answers', __CLASS__, 'get_all_answers' );

		anspress()->add_action( 'ap_ajax_load_comments', 'AnsPress_Comment_Hooks', 'load_comments' );
		anspress()->add_action( 'ap_ajax_edit_comment_form', 'AnsPress_Comment_Hooks', 'edit_comment_form' );
		anspress()->add_action( 'ap_ajax_delete_comment', 'AnsPress_Comment_Hooks', 'delete_comment' );
		anspress()->add_action( 'ap_ajax_change_post_status', 'AnsPress_Post_Status', 'change_post_status' );
		anspress()->add_action( 'ap_ajax_vote', 'AnsPress_Vote', 'vote' );

		// Flag ajax callbacks.
		anspress()->add_action( 'ap_ajax_flag_post', 'AnsPress_Flag', 'flag_post' );
		anspress()->add_action( 'ap_ajax_flag_comment', 'AnsPress_Flag', 'flag_comment' );
		anspress()->add_action( 'ap_ajax_submit_comment', 'AnsPress_Comment_Hooks','submit_comment' );
		anspress()->add_action( 'ap_ajax_approve_comment', 'AnsPress_Comment_Hooks','approve_comment' );
		anspress()->add_action( 'ap_ajax_post_actions_dp', 'AnsPress_Theme', 'post_actions_dp' );
		anspress()->add_action( 'ap_ajax_list_filter', 'AnsPress_Theme', 'list_filter' );
	}

	/**
	 * Show similar questions while asking a question.
	 *
	 * @since 2.0.1
	 */
	public static function suggest_similar_questions() {
		// Die if question suggestion is disabled.
		if ( ap_disable_question_suggestion( ) ) {
			wp_die( 'false' );
		}

		$keyword = ap_sanitize_unslash( 'value', 'request' );
		if ( empty( $keyword ) || ( ! ap_verify_default_nonce() && ! current_user_can( 'manage_options' ) ) ) {
				wp_die( 'false' );
		}

		$keyword = ap_sanitize_unslash( 'value', 'request' );
		$is_admin = (bool) ap_isset_post_value( 'is_admin', false );
		$questions = get_posts( array( // @codingStandardsIgnoreLine
			'post_type' => 'question',
			'showposts' => 10,
			's'         => $keyword,
		));

		if ( $questions ) {
				$items = '<div class="ap-similar-questions-head">';
				$items .= '<h3>' . ap_icon( 'check', true ) . sprintf( __( '%d similar questions found', 'anspress-question-answer' ), count( $questions ) ) . '</h3>';
				$items .= '<p>' . __( 'We\'ve found similar questions that have already been asked, click to read them.', 'anspress-question-answer' ) . '</p>';
				$items .= '</div>';

			$items .= '<div class="ap-similar-questions">';

			foreach ( (array) $questions as $p ) {
				$count = ap_get_answers_count( $p->ID );
				$p->post_title = ap_highlight_words( $p->post_title, $keyword );

				if ( $is_admin ) {
					$items .= '<div class="ap-q-suggestion-item clearfix"><a class="select-question-button button button-primary button-small" href="' . add_query_arg( array( 'post_type' => 'answer', 'post_parent' => $p->ID ), admin_url( 'post-new.php' ) ) . '">' . __( 'Select', 'anspress-question-answer' ) . '</a><span class="question-title">' . $p->post_title . '</span><span class="acount">' . sprintf( _n( '%d Answer', '%d Answers', $count, 'anspress-question-answer' ), $count ) . '</span></div>';
				} else {
					$items .= '<a class="ap-sqitem clearfix" target="_blank" href="' . get_permalink( $p->ID ) . '"><span class="acount">' . sprintf( _n( '%d Answer', '%d Answers', $count, 'anspress-question-answer' ), $count ) . '</span><span class="ap-title">' . $p->post_title . '</span></a>';
				}
			}

			$items .= '</div>';
			$result = array( 'status' => true, 'html' => $items );
		} else {
			$result = array( 'status' => false, 'message' => __( 'No related questions found.', 'anspress-question-answer' ) );
		}

		ap_ajax_json( $result );
	}

	/**
	 * Ajax action for selecting a best answer.
	 *
	 * @since 2.0.0
	 */
	public static function select_best_answer() {
	    $answer_id = (int) ap_sanitize_unslash( 'answer_id', 'request' );

	    if ( ! is_user_logged_in() || ! ap_verify_nonce( 'answer-' . $answer_id ) ) {
	        ap_ajax_json( 'something_wrong' );
	    }

	    $post = ap_get_post( $answer_id );

	    // Unselect best answer if already selected.
	    if ( ap_have_answer_selected( $post->post_parent ) ) {
	        ap_unselect_answer( $answer_id );
	        ap_ajax_json( array(
	        	'message' 	=> 'unselected_the_answer',
	        	'action' 	=> 'unselected_answer',
	        	'do' 		=> 'reload',
	        ) );
	    }

	    // Do not allow answer to be selected as best if status is moderate.
	    if ( 'moderate' === $post->post_status ) {
	    	ap_ajax_json( [ 'message_type' => 'warning', 'message' => __( 'Answer with moderate status cannot be selected as best.', 'anspress-question-answer' ) ] );
	    }

			// Add question activity meta.
			ap_update_post_activity_meta( $post->post_parent, 'answer_selected', get_current_user_id() );

			// Add answer activity meta.
			ap_update_post_activity_meta( $post->ID, 'best_answer', get_current_user_id() );

	    /**
	     * Trigger right after selecting an answer.
			 *
	     * @param integer $post_author Post author ID.
	     * @param integer $question_id Question ID.
	     * @param integer $answer_id   Answer ID.
			 * @todo Move this hook from here.
	     */
		do_action( 'ap_select_answer', $post->post_author, $post->post_parent, $post->ID );

		// Update question qameta.
		ap_set_selected_answer( $post->post_parent, $post->ID );

		// Close question if enabled in option.
		if ( ap_opt( 'close_selected' ) ) {
			ap_insert_qameta( $post->post_parent, [ 'closed' => 1 ] );
		}

		$html = ap_select_answer_btn_html( $answer_id );
		ap_ajax_json( array(
			'message' 	 => 'selected_the_answer',
			'action' 	   => 'selected_answer',
			'do' 		     => 'reload',
			'html' 		   => $html,
		) );
	}

	/**
	 * Process ajax trash posts callback.
	 */
	public static function delete_post() {
		$post_id = (int) ap_sanitize_unslash( 'post_id', 'request' );

		if ( ! ap_verify_nonce( 'delete_post_' . $post_id ) || ! ap_user_can_delete_post( $post_id ) ) {
			ap_ajax_json( 'something_wrong' );
		}

		$post = ap_get_post( $post_id );

		// Delete lock feature.
		// Do not allow to delete if defined time elapsed.
		if ( (time() > (get_the_time( 'U', $post->ID ) + (int) ap_opt( 'disable_delete_after' ))) && ! is_super_admin() ) {

			ap_ajax_json( array(
				'message_type' => 'warning',
				'message' => sprintf( __( 'This post was created %s, hence you cannot delete it.','anspress-question-answer' ), ap_human_time( get_the_time( 'U', $post->ID ) ) ),
			) );
		}

		wp_trash_post( $post_id );

		// Die if not question or answer post type.
		if ( ! in_array( $post->post_type, [ 'question', 'answer' ], true ) ) {
			ap_ajax_json( 'something_wrong' );
		}

		// Delete question.
		if ( 'question' === $post->post_type ) {
			do_action( 'ap_wp_trash_question', $post_id );
			ap_ajax_json( array(
				'action' 		  => 'delete_question',
				'do' 			    => array( 'redirect' => ap_base_page_link() ),
				'message' 		=> 'question_moved_to_trash',
			) );
		}

		do_action( 'ap_wp_trash_answer', $post_id );

		$current_ans = ap_count_published_answers( $post->post_parent );
		$count_label = sprintf( _n( '%d Answer', '%d Answers', $current_ans, 'anspress-question-answer' ), $current_ans );
		ap_ajax_json( array(
			'action' 		     => 'delete_answer',
			'div_id' 		     => '#answer_' . $post_id,
			'count' 		     => $current_ans,
			'count_label' 	 => $count_label,
			'remove' 		     => ( ! $current_ans ? true: false ),
			'message' 		   => 'answer_moved_to_trash',
			'view' 			     => array( 'answer_count' => $current_ans, 'answer_count_label' => $count_label ),
		));
	}

	/**
	 * Handle Ajax callback for permanent delete of post.
	 */
	public static function permanent_delete_post() {
		$post_id = (int) ap_sanitize_unslash( 'post_id', 'request' );

		if ( ! ap_verify_nonce( 'delete_post_' . $post_id ) || ! ap_user_can_permanent_delete() ) {
			ap_ajax_json( 'something_wrong' );
		}

		$post = ap_get_post( $post_id );

		// Die if not question or answer post type.
		if ( ! in_array( $post->post_type, [ 'question', 'answer' ], true ) ) {
			ap_ajax_json( 'something_wrong' );
		}

		if ( 'question' === $post->post_type ) {
			/**
			 * Triggered right before deleting question.
			 *
			 * @param  integer $post_id question ID.
			 */
			do_action( 'ap_wp_trash_question', $post_id );
		} else {
			/**
			 * Triggered right before deleting answer.
			 *
			 * @param  integer $post_id answer ID.
			 */
			do_action( 'ap_wp_trash_answer', $post_id );
		}

		wp_delete_post( $post_id, true );

		if ( 'question' === $post->post_type ) {
			ap_ajax_json( array(
				'action' 		=> 'delete_question',
				'do' 			=> array( 'redirect' => ap_base_page_link() ),
				'message' 		=> 'question_deleted_permanently',
			) );
		}

		$current_ans = ap_count_published_answers( $post->post_parent );
		$count_label = sprintf( _n( '%d Answer', '%d Answers', $current_ans, 'anspress-question-answer' ), $current_ans );
		ap_ajax_json(array(
			'action' 		      => 'delete_answer',
			'div_id' 		      => '#answer_' . $post_id,
			'count' 		      => $current_ans,
			'count_label' 	  => $count_label,
			'remove' 		      => ( ! $current_ans ? true: false),
			'message' 		    => 'answer_deleted_permanently',
			'view' 			      => array( 'answer_count' => $current_ans, 'answer_count_label' => $count_label ),
		));
	}

	/**
	 * Handle Ajax callback for restoring post.
	 */
	public static function restore_post() {
		$args = ap_sanitize_unslash( 'args', 'request' );

		if ( ! ap_verify_nonce( 'restore_' . $args[0] ) || ! ap_user_can_restore() ) {
			ap_ajax_json( 'something_wrong' );
		}

		$post = ap_get_post( $args[0] );

		// Die if not question or answer post type.
		if ( ! in_array( $post->post_type, [ 'question', 'answer' ], true ) ) {
			ap_ajax_json( 'something_wrong' );
		}

		// Do the thing.
		wp_untrash_post( $post->ID );

		ap_ajax_json(array(
			'action' 		     => 'restore_post',
			'do' 			       => [ 'removeClass' => [ '.post-' . $post->ID, 'status-trash' ], 'remove_if_exists' => '.post-' . $post->ID . ' .ap-notice' ],
			'message' 		   => __( 'Post restored successfully', 'anspress-question-answer' ),
			'message_type'   => 'success',
		));
	}

	/**
	 * Handle set feature and unfeature ajax callback
	 */
	public static function set_featured() {
		$post_id = (int) ap_sanitize_unslash( 'post_id', 'request' );

		if ( ! is_super_admin() || ! ap_verify_nonce( 'set_featured_' . $post_id ) ) {
			ap_ajax_json( 'no_permission' );
		}

		$post = ap_get_post( $post_id );

		// Do nothing if post type is not question.
		if ( 'question' !== $post->post_type ) {
			ap_ajax_json( __( 'Only question can be set as featured', 'anspress-question-answer' ) );
		}

		// Check if current question ID is in featured question array.
		if ( ap_is_featured_question( $post ) ) {
			ap_unset_featured_question( $post->ID );
			ap_ajax_json( array(
				'action' 		   => 'unset_featured_question',
				'message' 		 => 'unset_featured_question',
				'do' 			     => array( 'updateHtml' => '#set_featured_' . $post->ID ),
				'html' 			   => __( 'Set as featured', 'anspress-question-answer' ),
			));
		}

		ap_set_featured_question( $post->ID );
		ap_ajax_json( array(
			'action' 		   => 'set_featured_question',
			'message' 		 => 'set_featured_question',
			'do' 			     => array( 'updateHtml' => '#set_featured_' . $post->ID ),
			'html' 			   => __( 'Unset as featured', 'anspress-question-answer' ),
		));

		ap_ajax_json( 'something_wrong' );
	}

	/**
	 * Handle Ajax callback for user hover card
	 */
	public static function hover_card() {
		if ( ap_opt( 'disable_hover_card' ) ) {
			ap_ajax_json( 'something_wrong' );
		}

		$id = (int) ap_sanitize_unslash( 'id', 'p' );
		$type = ap_sanitize_unslash( 'type', 'request', 'user' );

		if ( ! ap_verify_default_nonce() ) {
			ap_ajax_json( 'something_wrong' );
		}

		/**
		 * AP Hover card actions.
		 *
		 * @param integer $id ID.
		 */
		do_action( 'ap_hover_card_' . $type, $id );
		wp_die();
	}

	/**
	 * Close question callback.
	 */
	public static function close_question() {
		$args = ap_sanitize_unslash( 'args', 'p' );

		if ( ! ap_verify_nonce( 'close_' . $args[0] ) ) {
			ap_ajax_json( 'something_wrong' );
		}

		// Check if user can close question.
		if ( ! ap_user_can_close_question() ) {
			ap_ajax_json( 'no_permission' );
		}

		$_post = ap_get_post( $args[0] );

		$toggle = ap_toggle_close_question( $args[0] );
		$close_label = $_post->closed ? __( 'Close', 'anspress-question-answer' ) :  __( 'Open', 'anspress-question-answer' );
		$close_title = $_post->closed ? __( 'Close this question for new answer.', 'anspress-question-answer' ) : __( 'Open this question for new answers', 'anspress-question-answer' );

		ob_start();
		ap_post_status_description( $_post->ID );
		$html = ob_get_clean();

		$results = array(
			'message' 		      => 1 === $toggle ? 'Question closed': 'Question is opened',
			'message_type' 		  => 'success',
			'action' 		        => 'question_closed',
			'do' 			    		  => array(
				'remove_if_exists'    => '#ap_post_status_desc_' . $_post->ID,
				'updateText'          => [ '#close-btn-' . $_post->ID, $close_label ],
			),
			'html'              => $html,
		);

		if ( $toggle ) {
			$results['do']['append_before'] = '#ap_post_actions_' . $_post->ID;
			$results['html'] = $html;
		}

		ap_ajax_json( $results );
	}

	/**
	 * Send JSON response and terminate.
	 *
	 * @param array|string $result Ajax response.
	 */
	public static function send( $result ) {
		ap_send_json( ap_ajax_responce( $result ) );
	}

	/**
	 * Output comment form.
	 */
	public static function comment_form() {
		if ( empty( ap_sanitize_unslash( 'comment', 'p' ) ) ) {
			ap_ajax_json( 'comment_content_empty' );
		}

		$comment_post_ID = (int) ap_sanitize_unslash( 'comment_post_ID', 'p' );

		// @codingStandardsIgnoreStart.
		if ( ! isset( $_REQUEST['comment_ID'] ) ) { // @codingStandardsIgnoreLine
			// Do security check.
			if ( ! ap_user_can_comment( $comment_post_ID ) || ! isset( $_POST['__nonce'] ) || ! wp_verify_nonce( $_POST['__nonce'], 'comment_' . (int) $_POST['comment_post_ID'] ) ) {
				ap_ajax_json( 'no_permission' );
			}
		} else {
			if ( ! ap_user_can_edit_comment( (int) $_REQUEST['comment_ID'] ) || ! wp_verify_nonce( $_REQUEST['__nonce'], 'comment_'.(int) $_REQUEST['comment_ID'] ) ) {
				ap_ajax_json( 'no_permission' );
			}
		}

		$post = ap_get_post( $comment_post_ID );

		if ( ! $post || empty( $post->post_status ) ) {
			ap_ajax_json( 'something_wrong' );
		}

		if ( in_array( $post->post_status, array( 'draft', 'pending', 'trash' ) ) ) {
			ap_ajax_json( 'draft_comment_not_allowed' );
		}

		$filter_type = isset( $_POST['comment_ID'] ) ? 'ap_before_updating_comment' : 'ap_before_inserting_comment';
		$filter = apply_filters( $filter_type, false, $_POST['comment'] );

		if ( true === $filter && is_array( $filter ) ) {
			ap_ajax_json( $filter );
		}

		if ( isset( $_POST['comment_ID'] ) ) {
			$comment_id = (int) $_POST['comment_ID'];
			$updated = wp_update_comment( array( 'comment_ID' => $comment_id, 'comment_content' => trim( $_POST['comment'] ) ) );

			if ( $updated ) {
				$comment = get_comment( $comment_id );

				ob_start();
				comment_text( $comment_id );
				$html = ob_get_clean();

				ap_ajax_json( array(
					'action'          => 'edit_comment',
					'comment_ID'      => $comment->comment_ID,
					'comment_post_ID' => $comment->comment_post_ID,
					'comment_content' => $comment->comment_content,
					'html'            => $html,
					'message'         => 'comment_edit_success'
				) );
			}
		} else {
			$user = wp_get_current_user();
			if ( $user->exists() ) {
				$user_ID              = $user->ID;
				$comment_author       = wp_slash( $user->display_name );
				$comment_author_email = wp_slash( $user->user_email );
				$comment_author_url   = wp_slash( $user->user_url );
				$comment_content      = trim( $_POST['comment'] );
				$comment_type         = 'anspress';
			} else {
				ap_ajax_json( 'no_permission' );
			}

			$comment_parent = 0;

			if ( isset( $_POST['comment_ID'] ) ) {
				$comment_parent = absint( $_POST['comment_ID'] );
			}

			$commentdata = compact( 'comment_post_ID', 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_content', 'comment_type', 'comment_parent', 'user_ID' );

			// Automatically approve parent comment.
			if ( ! empty( $_POST['approve_parent'] ) ) { // Input var okay.
				$parent = get_comment( $comment_parent );
				if ( $parent && $parent->comment_approved === '0' && $comment_post_ID === $parent->comment_post_ID ) {
					if ( wp_set_comment_status( $parent->comment_ID, 'approve' ) ) {
						$comment_auto_approved = true; }
				}
			}

			$comment_id = wp_new_comment( $commentdata );

			if ( $comment_id > 0 ) {
				$comment = get_comment( $comment_id );
				do_action( 'ap_after_new_comment', $comment );
				ob_start();
				ap_comment( $comment );
				$html = ob_get_clean();
				$count = get_comment_count( $comment->comment_post_ID );
				ap_ajax_json( array(
					'action'          => 'new_comment',
					'status'          => true,
					'comment_ID'      => $comment->comment_ID,
					'comment_post_ID' => $comment->comment_post_ID,
					'comment_content' => $comment->comment_content,
					'html'            => $html,
					'message'         => 'comment_success',
					'view'            => array(
						'comments_count_' . $comment->comment_post_ID => '(' . $count['approved'].')', 'comment_count_label_' .$comment->comment_post_ID => sprintf( _n( 'One comment', '%d comments', $count['approved'], 'anspress-question-answer' ), $count['approved'] ),
						),
				) );
			}
		}
		// @codingStandardsIgnoreEnd.
	}

	/**
	 * Load tinyMCE assets using ajax.
	 *
	 * @since 3.0.0
	 */
	public static function load_tinymce_assets() {
		$settings = ap_tinymce_editor_settings( 'answer' );

		if ( false !== $settings['tinymce'] ) {
			$settings['tinymce'] = array(
				'content_css'      => ap_get_theme_url( 'css/editor.css' ),
				'wp_autoresize_on' => true,
			);
		}

		echo '<div class="ap-editor">';
	    wp_editor( '', 'description', $settings );
	    echo '</div>';
	    \_WP_Editors::enqueue_scripts();
	    ob_start();
		print_footer_scripts();
		$scripts = ob_get_clean();
		echo str_replace( 'jquery-core,jquery-migrate,', '', $scripts ); // xss okay.
		\_WP_Editors::editor_js();
	    wp_die();
	}

	/**
	 * Handles ajax callback for list filter search.
	 *
	 * @since 3.0.0
	 */
	public static function filter_search() {
		$filter = ap_sanitize_unslash( 'filter', 'request' );
		$search_query = ap_sanitize_unslash( 'val', 'request' );
		do_action( 'ap_list_filter_search_' . $filter, $search_query );
	}

	/**
	 * Ajax callback for converting a question into a post.
	 *
	 * @since 3.0.0
	 */
	public static function convert_to_post() {
		if ( ! ap_verify_default_nonce() || ! is_super_admin( ) ) {
			ap_ajax_json( 'something_wrong' );
		}

		$args = ap_sanitize_unslash( $_POST['args'] ); // @codingStandardsIgnoreLine
		$row = set_post_type( $args[0], 'post' );

		// After success trash all answers.
		if ( $row ) {
			global $wpdb;

			// Get IDs of all answer.
			$answer_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_parent = %d and post_type = 'answer' ", (int) $args[0] ) ); // db call okay, cache ok.

			foreach ( (array) $answer_ids as $id ) {
				wp_delete_post( $id );
			}

			ap_ajax_json( [ 'do' => [ 'redirect' => get_permalink( $args[0] ) ] ] );
		}
	}

	/**
	 * Delete question or answer attachment.
	 */
	public static function delete_attachment() {
		if ( ! ap_verify_default_nonce() || ! ap_user_can_upload_image() ) {
			ap_ajax_json( 'no_permission' );
		}

		$args = ap_sanitize_unslash( 'args', 'request' );

		// If user cannot delete then die.
		if ( ! ap_user_can_delete_attachment( $args[0] ) ) {
			ap_ajax_json( 'no_permission' );
		}

		$row = wp_delete_attachment( $args[0], true );

		if ( false !== $row ) {
			ap_ajax_json( array(
				'action'        => 'delete_attachment',
				'attachment_id' => $args[0],
				'do'            => array( 'remove_if_exists' => '#' . $args[0] ),
				'message'       => __( 'Attachment deleted permanently','anspress-question-answer' ),
				'message_type'  => 'success',
			) );
		}
	}

	public static function get_all_answers() {
		global $answers;

		$question_id = ap_sanitize_unslash( 'question_id', 'p' );
		$answers_arr = [];
		$answers = ap_get_answers( [ 'question_id' => $question_id ] );

		if ( ap_user_can_see_answers() ) :
			while ( ap_have_answers() ) : ap_the_answer();
				global $post, $wp_post_statuses;
				$answers_arr[] = array(
					'ID' => get_the_ID(),
					'content' => get_the_content(),
					'avatar' => ap_get_author_avatar( 30 ),
					'author' => ap_user_display_name( $post->post_author ),
					'activity' => ap_get_recent_post_activity(),
					'edit_link' => esc_url_raw( get_edit_post_link() ),
					'trash_link' => esc_url_raw( get_delete_post_link() ),
					'status' => esc_attr( $wp_post_statuses[ $post->post_status ]->label ),
					'selected' => ap_get_post_field( 'selected' ),
				);
			endwhile;
		endif;

		ap_ajax_json( [ 'data' => $answers_arr ] );
	}
}
