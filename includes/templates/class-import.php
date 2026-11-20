<?php
/**
 * Register the import tab and any sub-tabs.
 *
 * @package Launch With Words
 */

namespace LWW\Includes\Templates;

use LWW\Includes\Functions;

/**
 * Output the import tab and content.
 */
class Import {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'lww_admin_tabs', array( $this, 'add_import_tab' ), 1, 1 );
		add_filter( 'lww_admin_sub_tabs', array( $this, 'add_import_main_sub_tab' ), 1, 3 );
		add_filter( 'lww_output_import', array( $this, 'output_import_content' ), 1, 3 );
	}

	/**
	 * Add the imoort tab and callback actions.
	 *
	 * @param array $tabs Array of tabs.
	 *
	 * @return array of tabs.
	 */
	public function add_import_tab( $tabs ) {
		$tabs[] = array(
			'get'    => 'import',
			'action' => 'lww_output_import',
			'url'    => Functions::get_settings_url( 'import' ),
			'label'  => _x( 'Import Content Pack', 'Tab label as Import Content Pack', 'launch-with-words' ),
			'icon'   => 'file-import',
		);
		return $tabs;
	}

	/**
	 * Add the import main tab and callback actions.
	 *
	 * @param array  $tabs        Array of tabs.
	 *
	 * @return array of tabs.
	 */
	public function add_import_main_sub_tab( $tabs ) {
		return $tabs;
	}

	/**
	 * Maximum uploaded pack size in bytes.
	 */
	const MAX_IMPORT_BYTES = 2097152;

	/**
	 * Maximum number of posts in one pack.
	 */
	const MAX_IMPORT_ITEMS = 100;

	/**
	 * Begin Home routing for the various outputs.
	 *
	 * @param string $tab     Current tab.
	 * @param string $sub_tab Current sub-tab.
	 */
	public function output_import_content( $tab, $sub_tab = '' ) {
		if ( 'import' === $tab ) {
			if ( empty( $sub_tab ) || 'import' === $sub_tab ) {
				if ( isset( $_POST['submit'] ) && check_admin_referer( 'import-lww', 'import_lww' ) ) {
					$this->handle_import();
				}
				?>
				<div id="lww-import-options">
					<h2><?php esc_html_e( 'Import Launch With Words Content Packs', 'launch-with-words' ); ?></h2>
					<div class="notice-info notice info">
						<p>
								<strong>
									<a href="https://launchwithwords.com/shop/" taret="_blank">
										<?php
										esc_html_e( 'Need more content packs? We have you covered.', 'launch-with-words' );
										?>
									</a>
								</strong>
							</p>
					</div>
					<form action="<?php echo esc_url( Functions::get_settings_url( 'import' ) ); ?>" method="post" enctype="multipart/form-data">
					<?php
						wp_nonce_field( 'import-lww', 'import_lww' );
					?>
					<table class="form-table" role="presentation">
						<tbody>
							<tr class="launch-with-words-authors">
								<th scope="row"><?php esc_html_e( 'Content Author', 'launch-with-words' ); ?></th>
								<td>
									<select name="lww-user">
									<?php
										$authors = new \WP_User_Query(
											array(
												'role__in' => array(
													'author',
													'editor',
													'administrator',
												),
											)
										);
									foreach ( $authors->get_results() as $author ) {
										?>
											<option value="<?php echo absint( $author->ID ); ?>"><?php echo esc_html( $author->display_name ); ?></option>
											<?php
									}
									?>

									</select>
								</td>
							</tr>
							<tr class="launch-with-words-blog-categories">
								<th scope="row"><?php esc_html_e( 'Blog Category', 'launch-with-words' ); ?></th>
								<td>
									<select name="lww-category">
									<?php
										$terms = get_terms(
											array(
												'taxonomy' => 'category',
												'hide_empty' => false,
											)
										);
									foreach ( $terms as $term ) {
										?>
											<option value="<?php echo absint( $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></option>
											<?php
									}
									?>

									</select>
								</td>
							</tr>
							<tr class="launch-with-words-blog-upload">
								<th scope="row">
									<label for="lww-file">
										<?php esc_html_e( 'Import File', 'launch-with-words' ); ?>
									</label>
								</th>
								<td>
									<input type="file" value="" name="lww-file" id="lww-file" />
								</td>
							</tr>
						</tbody>
					</table>
					<?php
						submit_button(
							__( 'Import', 'launch-with-words' ),
							'submit',
							'submit'
						);
					?>
					</form>
				</div>
				<?php
			}
		}
	}

	/**
	 * Create draft posts from a validated content pack.
	 */
	private function handle_import() {
		check_admin_referer( 'import-lww', 'import_lww' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to import content packs.', 'launch-with-words' ) );
		}

		$file       = array();
		$author_raw = isset( $_POST['lww-user'] ) ? wp_unslash( $_POST['lww-user'] ) : 0;
		$term_raw   = isset( $_POST['lww-category'] ) ? wp_unslash( $_POST['lww-category'] ) : 0;
		$author_id  = is_scalar( $author_raw ) ? absint( $author_raw ) : 0;
		$term_id    = is_scalar( $term_raw ) ? absint( $term_raw ) : 0;
		if ( isset( $_FILES['lww-file'] ) && is_array( $_FILES['lww-file'] ) ) {
			$file = $_FILES['lww-file'];
		}

		$json_content = $this->parse_uploaded_pack( $file );
		if ( is_wp_error( $json_content ) ) {
			$this->render_import_error();
			return;
		}

		if ( ! $this->is_allowed_author( $author_id ) ) {
			$this->render_import_error( __( 'Select a valid content author.', 'launch-with-words' ) );
			return;
		}

		if ( ! $this->is_category( $term_id ) ) {
			$this->render_import_error( __( 'Select a valid blog category.', 'launch-with-words' ) );
			return;
		}

		$post_import_content_count = $this->insert_pack_posts( $json_content['content'], $author_id, $term_id );
		if ( $post_import_content_count < 1 ) {
			$this->render_import_error();
			return;
		}

		$this->render_import_success( $post_import_content_count );
	}

	/**
	 * Read and validate an uploaded Launch With Words JSON pack.
	 *
	 * @param array $file One entry from $_FILES.
	 *
	 * @return array|\WP_Error Decoded pack, or an error when the upload is rejected.
	 */
	private function parse_uploaded_pack( $file ) {
		$invalid = new \WP_Error(
			'lww_import_file',
			__( 'The import file must be a valid Launch With Words JSON file.', 'launch-with-words' )
		);

		if ( empty( $file['tmp_name'] ) || ! is_string( $file['tmp_name'] ) ) {
			return $invalid;
		}

		if ( ! isset( $file['error'] ) || ! is_numeric( $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return $invalid;
		}

		$tmp_name = $file['tmp_name'];
		if ( ! is_uploaded_file( $tmp_name ) ) {
			return $invalid;
		}

		$bytes = filesize( $tmp_name );
		if ( false === $bytes || $bytes < 1 || $bytes > self::MAX_IMPORT_BYTES ) {
			return $invalid;
		}

		$name = '';
		if ( isset( $file['name'] ) && is_string( $file['name'] ) ) {
			$name = sanitize_file_name( wp_unslash( $file['name'] ) );
		}
		$checked = wp_check_filetype(
			basename( $name ),
			array(
				'json' => 'application/json',
			)
		);
		if ( empty( $checked['ext'] ) || 'json' !== $checked['ext'] ) {
			return $invalid;
		}

		$json_content = wp_json_file_decode(
			$tmp_name,
			array(
				'associative' => true,
			)
		);
		if ( ! is_array( $json_content ) || ! isset( $json_content['content'] ) || ! is_array( $json_content['content'] ) ) {
			return $invalid;
		}

		$item_count = count( $json_content['content'] );
		if ( $item_count < 1 || $item_count > self::MAX_IMPORT_ITEMS ) {
			return $invalid;
		}

		return $json_content;
	}

	/**
	 * Whether the user ID is an author, editor, or administrator.
	 *
	 * @param int $author_id User ID from the import form.
	 *
	 * @return bool
	 */
	private function is_allowed_author( $author_id ) {
		$author_id = absint( $author_id );
		if ( ! $author_id ) {
			return false;
		}

		$authors = new \WP_User_Query(
			array(
				'role__in' => array(
					'author',
					'editor',
					'administrator',
				),
				'include'  => array( $author_id ),
				'fields'   => 'ID',
				'number'   => 1,
			)
		);

		return in_array( $author_id, array_map( 'absint', $authors->get_results() ), true );
	}

	/**
	 * Whether the term ID is a category.
	 *
	 * @param int $term_id Term ID from the import form.
	 *
	 * @return bool
	 */
	private function is_category( $term_id ) {
		$term_id = absint( $term_id );
		if ( ! $term_id ) {
			return false;
		}

		$term = get_term( $term_id, 'category' );
		if ( is_wp_error( $term ) || ! ( $term instanceof \WP_Term ) ) {
			return false;
		}

		return 'category' === $term->taxonomy;
	}

	/**
	 * Insert draft posts for pack items that have a title and content string.
	 *
	 * @param array $items     Pack content list.
	 * @param int   $author_id Author user ID.
	 * @param int   $term_id   Category term ID.
	 *
	 * @return int Number of posts created.
	 */
	private function insert_pack_posts( $items, $author_id, $term_id ) {
		$count = 0;

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['title'], $item['content'] ) ) {
				continue;
			}
			if ( ! is_string( $item['title'] ) || ! is_string( $item['content'] ) ) {
				continue;
			}

			$title = sanitize_text_field( $item['title'] );
			$body  = wp_kses_post( $item['content'] );

			$post_id = wp_insert_post(
				wp_slash(
					array(
						'post_author'    => $author_id,
						'post_content'   => $body,
						'post_title'     => $title,
						'post_status'    => 'draft',
						'post_type'      => 'post',
						'comment_status' => 'closed',
						'ping_status'    => 'closed',
						'post_category'  => array(
							$term_id,
						),
					)
				),
				true
			);

			if ( ! is_wp_error( $post_id ) && $post_id ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Output the import error notice.
	 *
	 * @param string $message Notice text. Defaults to the invalid-file message.
	 */
	private function render_import_error( $message = '' ) {
		if ( '' === $message ) {
			$message = __( 'The import file must be a valid Launch With Words JSON file.', 'launch-with-words' );
		}
		?>
		<div class="notice error">
			<p>
				<strong><?php echo esc_html( $message ); ?></strong>
			</p>
		</div>
		<?php
	}

	/**
	 * Output the import success notice.
	 *
	 * @param int $count Number of posts created.
	 */
	private function render_import_success( $count ) {
		?>
		<div class="notice updated success">
			<p>
				<strong>
					<?php
					printf(
						/* Translators: %d is the number of posts imported. */
						esc_html__( '%d Posts Have Been Created.', 'launch-with-words' ),
						absint( $count )
					);
					?>
				</strong>
			</p>
		</div>
		<?php
	}
}
