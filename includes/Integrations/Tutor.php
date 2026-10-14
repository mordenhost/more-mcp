<?php
namespace Mordenhost_MCP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tutor {

	const COURSE_CPT     = 'courses';
	const TOPIC_CPT      = 'topics';
	const LESSON_CPT     = 'lesson';
	const QUIZ_CPT       = 'tutor_quiz';
	const ASSIGNMENT_CPT = 'tutor_assignments';

	const ENROL_CPT    = 'tutor_enrolled';
	const ENROL_STATUS = 'completed';
	const CATEGORY_TAX = 'course-category';

	const PRICE_META = '_tutor_course_price';

	const ENROL_FILTERS = array(
		'completed' => array( 'completed' ),
		'pending'   => array( 'pending' ),
		'cancelled' => array( 'cancel', 'canceled' ),
		'any'       => array(),
	);

	public static function is_available() {

		
		if ( defined( 'TUTOR_VERSION' ) || function_exists( 'tutor' ) ) {
			return true;
		}
		if ( function_exists( 'post_type_exists' ) ) {
			return post_type_exists( self::COURSE_CPT ) && post_type_exists( self::TOPIC_CPT );
		}
		return false;
	}

	public static function get_manifest() {
		return array(
			'providers'    => array( 'tutor' ),
			'capabilities' => array( 'lms' ),
			'kind'         => 'plugin',
		);
	}

	public static function get_tools() {
		if ( ! self::is_available() ) {
			return array();
		}
		return array(
			array(
				'name'        => 'tutor_get_status',
				'description' => 'Read Tutor LMS scale: course counts by post status (published, draft, pending, private) and the number of distinct enrolled students. Aggregate counts only, never course records or student data, and cannot modify anything. Read-only diagnostic.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			),
			array(
				'name'        => 'tutor_get_course_structure',
				'description' => 'Read the curriculum outline of one Tutor LMS course (by course ID): its ordered topics, and within each topic the ordered lessons, quizzes, and assignments, each with title, type, and order. Returns course STRUCTURE only — no lesson/quiz content, no questions or answers, and no per-learner progress or grades. Read-only; cannot modify the curriculum.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'course_id' => array(
							'type'        => 'integer',
							'description' => 'The post ID of the Tutor LMS course to outline.',
						),
					),
					'required'   => array( 'course_id' ),
				),
			),
			array(
				'name'        => 'tutor_list_courses',
				'description' => 'List the Tutor LMS course catalogue: each course\'s id, title, slug, status, and price. Read-only enumeration so an agent can discover course IDs to feed tutor_get_course_structure. Catalogue fields only — never enrolment rows, learner/user ids, per-student progress, or lesson content. Cannot modify anything.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit'  => array(
							'type'        => 'integer',
							'description' => 'Maximum courses to return (1-100, default 50).',
						),
						'offset' => array(
							'type'        => 'integer',
							'description' => 'Number of courses to skip, for paging past the limit.',
						),
					),
				),
			),
			array(
				'name'        => 'tutor_list_enrollments',
				'description' => 'List the enrolments of one Tutor LMS course: enrolment id, WordPress user id, status (completed, pending payment, or cancelled) and enrolment date, with per-status counts. Learners are identified by user id only, never by name or email. Read-only.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'course_id' => array(
							'type'        => 'integer',
							'description' => 'The post ID of the Tutor LMS course.',
						),
						'status'    => array(
							'type'        => 'string',
							'enum'        => array_keys( self::ENROL_FILTERS ),
							'description' => 'Which enrolments to list (default completed, the active ones).',
						),
						'limit'     => array(
							'type'        => 'integer',
							'description' => 'Maximum enrolments to return (1-100, default 50).',
						),
						'offset'    => array(
							'type'        => 'integer',
							'description' => 'Number of enrolments to skip, for paging.',
						),
					),
					'required'   => array( 'course_id' ),
				),
			),
			array(
				'name'        => 'tutor_get_student_progress',
				'description' => 'Read one learner\'s progress in one Tutor LMS course, as Tutor computes it: enrolment status, completed and total course items (lessons, quizzes, assignments), percent complete, and whether and when the course was marked complete. Read-only.',
				'inputSchema' => self::learner_schema(),
			),
			array(
				'name'        => 'tutor_enroll_student',
				'description' => 'Enroll a WordPress user in a FREE Tutor LMS course through Tutor\'s own enrolment (the same record a learner gets by clicking Enroll). This fires Tutor\'s enrolment hooks, so add-ons may email the learner. Refuses paid courses (they are enrolled through their order), unpublished courses, and a user with a pending paid enrolment; an already-enrolled user is reported, not enrolled twice. Undo with tutor_unenroll_student.',
				'inputSchema' => self::learner_schema(),
			),
			array(
				'name'        => 'tutor_unenroll_student',
				'description' => 'Cancel a user\'s active enrolment in a Tutor LMS course through Tutor\'s own cancel call (what Tutor does when an order is deleted). The enrolment is marked cancelled and the learner loses access; the record and their progress are kept, and tutor_enroll_student can enroll them again. Fires Tutor\'s cancellation hook. Refuses when the user has no active enrolment.',
				'inputSchema' => self::learner_schema(),
			),
		);
	}

	private static function learner_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'course_id' => array(
					'type'        => 'integer',
					'description' => 'The post ID of the Tutor LMS course.',
				),
				'user_id'   => array(
					'type'        => 'integer',
					'description' => 'The learner\'s WordPress user ID (find it with wp_get_users).',
				),
			),
			'required'   => array( 'course_id', 'user_id' ),
		);
	}

	public static function execute_tool( $name, $args ) {

		
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'You do not have permission to use LMS tools.' );
		}
		if ( ! self::is_available() ) {
			throw new \Exception( 'Tutor LMS is not active.' );
		}

		if ( 'tutor_get_course_structure' === $name ) {
			return self::get_course_structure( $args );
		}
		if ( 'tutor_list_courses' === $name ) {
			return self::list_courses( $args );
		}
		$args = is_array( $args ) ? $args : array();
		switch ( $name ) {
			case 'tutor_list_enrollments':
				return self::list_enrollments( $args );
			case 'tutor_get_student_progress':
				return self::student_progress( $args );
			case 'tutor_enroll_student':
				return self::enroll_student( $args );
			case 'tutor_unenroll_student':
				return self::unenroll_student( $args );
		}
		if ( 'tutor_get_status' !== $name ) {
			throw new \Exception( 'Unknown LMS tool: ' . esc_html( $name ) );
		}

		$by_status = array();
		$total     = 0;
		if ( function_exists( 'wp_count_posts' ) ) {
			$counts = wp_count_posts( self::COURSE_CPT );
			foreach ( array( 'publish', 'draft', 'pending', 'private' ) as $status ) {
				$n                    = isset( $counts->{$status} ) ? (int) $counts->{$status} : 0;
				$by_status[ $status ] = $n;
				$total               += $n;
			}
		}

		return array(
			'provider'          => 'tutor',
			'courses_total'     => $total,
			'courses_by_status' => $by_status,
			'enrolled_students' => self::enrolled_students(),
		);
	}

	private static function get_course_structure( $args ) {
		$course_id = isset( $args['course_id'] ) ? absint( $args['course_id'] ) : 0;
		if ( $course_id <= 0 ) {
			throw new \Exception( 'course_id (a positive integer) is required.' );
		}
		if ( self::COURSE_CPT !== get_post_type( $course_id ) ) {
			throw new \Exception( 'Post ' . (int) $course_id . ' is not a Tutor LMS course.' );
		}

		$topics    = self::child_posts( self::TOPIC_CPT, $course_id );
		$topic_out = array();
		foreach ( $topics as $index => $topic ) {
			$topic_id = (int) $topic->ID;
			$items    = self::topic_items( $topic_id );
			$topic_out[] = array(
				'topic_id'   => $topic_id,
				'name'       => (string) get_the_title( $topic_id ),
				'order'      => (int) $index,
				'item_count' => count( $items ),
				'items'      => $items,
			);
		}

		return array(
			'provider'      => 'tutor',
			'available'     => true,
			'course_id'     => $course_id,
			'course_title'  => get_the_title( $course_id ),
			'categories'    => self::category_names( $course_id ),
			'topic_count'   => count( $topic_out ),
			'topics'        => $topic_out,
		);
	}

	private static function topic_items( $topic_id ) {
		$rows = array();
		foreach ( array(
			self::LESSON_CPT     => 'lesson',
			self::QUIZ_CPT       => 'quiz',
			self::ASSIGNMENT_CPT => 'assignment',
		) as $cpt => $type ) {
			foreach ( self::child_posts( $cpt, $topic_id ) as $post ) {
				$rows[] = array(
					'id'     => (int) $post->ID,
					'type'   => $type,
					'title'  => (string) get_the_title( $post->ID ),
					'_order' => (int) $post->menu_order,
				);
			}
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				if ( $a['_order'] === $b['_order'] ) {
					return $a['id'] <=> $b['id'];
				}
				return $a['_order'] < $b['_order'] ? -1 : 1;
			}
		);
		$out = array();
		foreach ( $rows as $order => $row ) {
			$out[] = array(
				'item_id' => $row['id'],
				'type'    => $row['type'],
				'title'   => $row['title'],
				'order'   => (int) $order,
			);
		}
		return $out;
	}

	private static function child_posts( $cpt, $parent_id ) {
		if ( ! class_exists( '\WP_Query' ) ) {
			return array();
		}
		$q = new \WP_Query(
			array(
				'post_type'        => $cpt,
				'post_parent'      => (int) $parent_id,
				'posts_per_page'   => -1,
				'post_status'      => 'publish',
				'orderby'          => 'menu_order',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => 0,
			)
		);
		return is_array( $q->posts ) ? $q->posts : array();
	}

	private static function category_names( $course_id ) {
		if ( ! function_exists( 'taxonomy_exists' ) || ! taxonomy_exists( self::CATEGORY_TAX ) ) {
			return array();
		}
		$names = wp_get_object_terms( (int) $course_id, self::CATEGORY_TAX, array( 'fields' => 'names' ) );
		if ( is_wp_error( $names ) || ! is_array( $names ) ) {
			return array();
		}
		return array_values( array_map( 'strval', $names ) );
	}

	private static function list_courses( $args ) {
		if ( ! class_exists( '\WP_Query' ) ) {
			return array(
				'provider' => 'tutor',
				'courses'  => array(),
				'total'    => 0,
				'limit'    => 0,
				'offset'   => 0,
			);
		}

		$limit  = self::clamp_limit( isset( $args['limit'] ) ? $args['limit'] : 50 );
		$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;

		$q = new \WP_Query(
			array(
				'post_type'        => self::COURSE_CPT,
				'posts_per_page'   => $limit,
				'offset'           => $offset,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'post_status'      => 'any',
				'no_found_rows'    => true,
				'suppress_filters' => 0,
			)
		);
		$posts = is_array( $q->posts ) ? $q->posts : array();

		$courses = array();
		foreach ( $posts as $post ) {
			$id = isset( $post->ID ) ? (int) $post->ID : 0;
			if ( $id <= 0 ) {
				continue;
			}
			$price     = get_post_meta( $id, self::PRICE_META, true );
			$courses[] = array(
				'id'     => $id,
				'title'  => (string) get_the_title( $id ),
				'slug'   => isset( $post->post_name ) ? (string) $post->post_name : '',
				'status' => isset( $post->post_status ) ? (string) $post->post_status : '',
				'price'  => ( '' === $price || null === $price ) ? null : (float) $price,
			);
		}

		return array(
			'provider' => 'tutor',
			'courses'  => $courses,
			'total'    => count( $courses ),
			'limit'    => $limit,
			'offset'   => $offset,
		);
	}

	private static function clamp_limit( $limit ) {
		$n = (int) $limit;
		if ( $n < 1 ) {
			return 1;
		}
		if ( $n > 100 ) {
			return 100;
		}
		return $n;
	}

	private static function enrolled_students() {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return null;
		}

		
		
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_author) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND post_author > 0",
				self::ENROL_CPT,
				self::ENROL_STATUS
			)
		);
		return null === $count ? null : (int) $count;
	}

	private static function list_enrollments( array $args ) {
		global $wpdb;
		$course_id = self::enrol_course( $args );
		$filter    = isset( $args['status'] ) ? (string) $args['status'] : 'completed';
		if ( ! isset( self::ENROL_FILTERS[ $filter ] ) ) {
			throw new \Exception( 'status must be one of: completed, pending, cancelled, any.' );
		}
		$limit  = self::clamp_limit( isset( $args['limit'] ) ? $args['limit'] : 50 );
		$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			throw new \Exception( 'Tutor LMS enrolments could not be read.' );
		}

		$statuses     = self::ENROL_FILTERS[ $filter ];
		$status_where = '';
		$params       = array( self::ENROL_CPT, $course_id );
		if ( $statuses ) {
			$status_where = ' AND post_status IN (' . implode( ',', array_fill( 0, count( $statuses ), '%s' ) ) . ')';
			$params       = array_merge( $params, $statuses );
		}
		$params[] = $limit;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $wpdb->posts and a placeholder list; values are prepared.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_author, post_status, post_date_gmt FROM {$wpdb->posts} WHERE post_type = %s AND post_parent = %d AND post_author > 0{$status_where} ORDER BY ID DESC LIMIT %d OFFSET %d", ...$params ), ARRAY_A );
		$tally = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_status, COUNT(*) AS n FROM {$wpdb->posts} WHERE post_type = %s AND post_parent = %d AND post_author > 0 GROUP BY post_status",
				self::ENROL_CPT,
				$course_id
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) || ! is_array( $tally ) ) {
			throw new \Exception( 'Tutor LMS enrolments could not be read.' );
		}

		$counts = array(
			'completed' => 0,
			'pending'   => 0,
			'cancelled' => 0,
			'other'     => 0,
		);
		$total  = 0;
		foreach ( $tally as $t ) {
			$status = isset( $t['post_status'] ) ? (string) $t['post_status'] : '';
			$n      = isset( $t['n'] ) ? (int) $t['n'] : 0;
			$bucket = 'other';
			foreach ( array( 'completed', 'pending', 'cancelled' ) as $name ) {
				if ( in_array( $status, self::ENROL_FILTERS[ $name ], true ) ) {
					$bucket = $name;
				}
			}
			$counts[ $bucket ] += $n;
			if ( ! $statuses || in_array( $status, $statuses, true ) ) {
				$total += $n;
			}
		}

		$list = array();
		foreach ( $rows as $row ) {
			$list[] = self::enrolment_row( $row );
		}

		return array(
			'provider'     => 'tutor',
			'course_id'    => $course_id,
			'course_title' => (string) get_the_title( $course_id ),
			'status'       => $filter,
			'counts'       => $counts,
			'total'        => $total,
			'limit'        => $limit,
			'offset'       => $offset,
			'enrollments'  => $list,
		);
	}

	private static function student_progress( array $args ) {
		$course_id = self::enrol_course( $args );
		$user_id   = self::enrol_learner( $args, false );
		$rows      = self::learner_rows( $course_id, $user_id );
		$active    = null === $rows ? null : self::row_with_status( $rows, array( 'completed' ) );

		$progress     = null;
		$completed    = null;
		$completed_at = null;
		$utils        = function_exists( 'tutor_utils' ) ? tutor_utils() : null;
		if ( is_object( $utils ) && method_exists( $utils, 'get_course_completed_percent' ) ) {
			$stats = $utils->get_course_completed_percent( $course_id, $user_id, true );
			if ( is_array( $stats ) ) {
				$progress = array(
					'completed_percent' => isset( $stats['completed_percent'] ) ? (int) $stats['completed_percent'] : null,
					'completed_count'   => isset( $stats['completed_count'] ) ? (int) $stats['completed_count'] : null,
					'total_count'       => isset( $stats['total_count'] ) ? (int) $stats['total_count'] : null,
				);
			}
		}
		if ( is_object( $utils ) && method_exists( $utils, 'is_completed_course' ) ) {
			$record    = $utils->is_completed_course( $course_id, $user_id );
			$completed = ! empty( $record );
			if ( is_object( $record ) && isset( $record->completion_date ) ) {
				$completed_at = (string) $record->completion_date;
			}
		}

		return array(
			'provider'         => 'tutor',
			'course_id'        => $course_id,
			'course_title'     => (string) get_the_title( $course_id ),
			'user_id'          => $user_id,
			'enrolled'         => null === $rows ? null : null !== $active,
			'enrollment'       => ( null === $rows || ! $rows ) ? null : self::enrolment_row( $rows[0] ),
			'progress'         => $progress,
			'course_completed' => $completed,
			'completed_at'     => $completed_at,
		);
	}

	private static function enroll_student( array $args ) {
		$course_id = self::enrol_course( $args );
		$user_id   = self::enrol_learner( $args, true );
		$model     = '\Tutor\Models\EnrollmentModel';
		$utils     = function_exists( 'tutor_utils' ) ? tutor_utils() : null;
		if ( ! class_exists( $model ) || ! method_exists( $model, 'do_enroll' ) || ! is_object( $utils ) || ! method_exists( $utils, 'is_course_purchasable' ) ) {
			throw new \Exception( 'This Tutor LMS version does not offer the enrolment API this tool uses (Tutor 4.0 or later is needed).' );
		}
		$status = function_exists( 'get_post_status' ) ? (string) get_post_status( $course_id ) : '';
		if ( ! in_array( $status, array( 'publish', 'private' ), true ) ) {
			throw new \Exception( esc_html( 'Tutor only enrolls learners in published or private courses; course ' . $course_id . ' is ' . ( '' === $status ? 'in an unknown state' : $status ) . '.' ) );
		}

		$before = self::learner_rows( $course_id, $user_id );
		if ( null === $before ) {
			throw new \Exception( 'Tutor LMS enrolments could not be read.' );
		}
		$active = self::row_with_status( $before, array( 'completed' ) );
		if ( null !== $active ) {
			return array(
				'provider'         => 'tutor',
				'course_id'        => $course_id,
				'user_id'          => $user_id,
				'enrolled'         => true,
				'already_enrolled' => true,
				'enrollment'       => self::enrolment_row( $active ),
				'message'          => 'The user is already enrolled in this course; nothing was changed.',
			);
		}
		if ( null !== self::row_with_status( $before, array( 'pending' ) ) ) {
			throw new \Exception( esc_html( 'User ' . $user_id . ' has a pending enrolment in course ' . $course_id . ' waiting for payment. It completes through its order, not through this tool.' ) );
		}
		if ( $utils->is_course_purchasable( $course_id ) ) {
			throw new \Exception( esc_html( 'Course ' . $course_id . ' is a paid course. This tool only enrolls learners in free courses; paid enrolments come from an order, and granting paid access without one is not offered.' ) );
		}

		$model::do_enroll( $course_id, 0, $user_id );

		$after = self::learner_rows( $course_id, $user_id );
		if ( null === $after ) {
			$state = Write_Verify::UNKNOWN;
			$row   = null;
		} else {
			$row        = self::row_with_status( $after, array( 'completed' ) );
			$before_ids = array_map( 'intval', array_column( $before, 'ID' ) );
			$new_rows   = array_values(
				array_filter(
					$after,
					static function ( $r ) use ( $before_ids ) {
						return ! in_array( (int) ( $r['ID'] ?? 0 ), $before_ids, true );
					}
				)
			);
			if ( null !== $row ) {
				$state = Write_Verify::LANDED;
			} elseif ( $new_rows ) {

				$state = Write_Verify::TRANSFORMED;
				$row   = $new_rows[0];
			} else {
				throw new \Exception( esc_html( 'Tutor did not enroll user ' . $user_id . ' in course ' . $course_id . ': the course is not accessible, or a tutor_allow_course_enrollment filter blocked it. Nothing was changed.' ) );
			}
		}

		return array_merge(
			array(
				'provider'         => 'tutor',
				'course_id'        => $course_id,
				'user_id'          => $user_id,
				'enrolled'         => Write_Verify::LANDED === $state,
				'already_enrolled' => false,
				'enrollment'       => null === $row ? null : self::enrolment_row( $row ),
			),
			Write_Verify::report( $state, 'enrolment of user ' . $user_id . ' in course ' . $course_id, 'Tutor stored the enrolment with a status other than completed, so the learner does not have access yet.' )
		);
	}

	private static function unenroll_student( array $args ) {
		$course_id = self::enrol_course( $args );
		$user_id   = self::enrol_learner( $args, true );
		$utils     = function_exists( 'tutor_utils' ) ? tutor_utils() : null;
		if ( ! is_object( $utils ) || ! method_exists( $utils, 'cancel_course_enrol' ) ) {
			throw new \Exception( 'This Tutor LMS version does not offer the cancel-enrolment call this tool uses.' );
		}

		$before = self::learner_rows( $course_id, $user_id );
		if ( null === $before ) {
			throw new \Exception( 'Tutor LMS enrolments could not be read.' );
		}
		$active = self::row_with_status( $before, array( 'completed' ) );
		if ( null === $active ) {
			if ( null !== self::row_with_status( $before, array( 'pending' ) ) ) {
				throw new \Exception( esc_html( 'User ' . $user_id . ' only has a pending (unpaid) enrolment in course ' . $course_id . '; it is managed through its order.' ) );
			}
			throw new \Exception( esc_html( 'User ' . $user_id . ' has no active enrolment in course ' . $course_id . ', so there is nothing to cancel.' ) );
		}

		
		$utils->cancel_course_enrol( $course_id, $user_id );

		$after = self::learner_rows( $course_id, $user_id );
		if ( null === $after ) {
			$state = Write_Verify::UNKNOWN;
			$row   = null;
		} else {
			if ( null !== self::row_with_status( $after, array( 'completed' ) ) ) {

				throw new \Exception( esc_html( 'Tutor did not cancel the enrolment of user ' . $user_id . ' in course ' . $course_id . '; it is still active. Nothing was changed.' ) );
			}
			$state = Write_Verify::LANDED;
			$row   = null;
			foreach ( $after as $r ) {
				if ( (int) ( $r['ID'] ?? 0 ) === (int) $active['ID'] ) {
					$row = $r;
				}
			}
		}

		return array_merge(
			array(
				'provider'   => 'tutor',
				'course_id'  => $course_id,
				'user_id'    => $user_id,
				'unenrolled' => Write_Verify::LANDED === $state,
				'enrollment' => null === $row ? null : self::enrolment_row( $row ),
			),
			Write_Verify::report( $state, 'cancellation of user ' . $user_id . '\'s enrolment in course ' . $course_id )
		);
	}

	private static function enrol_course( array $args ) {
		$course_id = isset( $args['course_id'] ) ? absint( $args['course_id'] ) : 0;
		if ( $course_id <= 0 ) {
			throw new \Exception( 'course_id (a positive integer) is required.' );
		}
		if ( self::COURSE_CPT !== get_post_type( $course_id ) ) {
			throw new \Exception( esc_html( 'Post ' . $course_id . ' is not a Tutor LMS course.' ) );
		}
		if ( ! current_user_can( 'edit_post', $course_id ) ) {
			throw new \Exception( esc_html( 'You do not have permission to manage enrolments in course ' . $course_id . '.' ) );
		}
		return $course_id;
	}

	private static function enrol_learner( array $args, $write ) {
		$user_id = isset( $args['user_id'] ) ? absint( $args['user_id'] ) : 0;
		if ( $user_id <= 0 ) {
			throw new \Exception( 'user_id (a positive integer) is required.' );
		}
		if ( ! function_exists( 'get_userdata' ) || ! get_userdata( $user_id ) ) {
			throw new \Exception( esc_html( 'No WordPress user with id ' . $user_id . '.' ) );
		}
		if ( $write && ! current_user_can( 'edit_user', $user_id ) ) {
			throw new \Exception( esc_html( 'You do not have permission to change the enrolments of user ' . $user_id . '.' ) );
		}
		return $user_id;
	}

	private static function learner_rows( $course_id, $user_id ) {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return null;
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_author, post_status, post_date_gmt FROM {$wpdb->posts} WHERE post_type = %s AND post_parent = %d AND post_author = %d ORDER BY ID DESC",
				self::ENROL_CPT,
				(int) $course_id,
				(int) $user_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : null;
	}

	private static function row_with_status( array $rows, array $statuses ) {
		foreach ( $rows as $row ) {
			if ( in_array( (string) ( $row['post_status'] ?? '' ), $statuses, true ) ) {
				return $row;
			}
		}
		return null;
	}

	private static function enrolment_row( array $row ) {
		return array(
			'enrollment_id' => (int) ( $row['ID'] ?? 0 ),
			'user_id'       => (int) ( $row['post_author'] ?? 0 ),
			'status'        => (string) ( $row['post_status'] ?? '' ),
			'enrolled_at'   => (string) ( $row['post_date_gmt'] ?? '' ),
		);
	}
}
