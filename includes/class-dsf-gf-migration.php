<?php
/**
 * Gravity Forms → DSF Forms migration.
 *
 * Converts an existing Gravity Form (fields, confirmations, notifications,
 * and any Zapier / webhook feeds) into a native DSF form, so the same
 * connection story keeps working after the switch. The mapped definition is
 * run through the standard DSF_Forms sanitizers before it is stored.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_GF_Migration {

	const ACTION = 'dsf_migrate_gf_form';

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_migrate' ) );
		add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
	}

	/**
	 * Whether Gravity Forms is available on this site.
	 */
	public static function is_gravity_forms_active() {
		return class_exists( 'GFAPI' );
	}

	/* -----------------------------------------------------------------
	 * Admin action
	 * ----------------------------------------------------------------- */

	/**
	 * admin-post handler: convert one Gravity Form into a DSF form.
	 */
	public function handle_migrate() {
		if ( ! current_user_can( 'edit_pages' ) || ! current_user_can( 'publish_pages' ) ) {
			wp_die( esc_html__( 'You are not allowed to migrate forms.', 'designstudio-flow' ) );
		}

		$gf_id = isset( $_GET['gf_form_id'] ) ? absint( $_GET['gf_form_id'] ) : 0;
		if ( ! $gf_id ) {
			wp_die( esc_html__( 'Invalid request.', 'designstudio-flow' ) );
		}

		check_admin_referer( self::ACTION . '_' . $gf_id );

		$redirect = add_query_arg(
			array(
				'page' => 'dsf-tools',
				'tab'  => 'forms',
			),
			admin_url( 'admin.php' )
		);

		if ( ! self::is_gravity_forms_active() ) {
			wp_safe_redirect( add_query_arg( 'dsf_gf_migrate', 'no_gf', $redirect ) );
			exit;
		}

		$gf_form = GFAPI::get_form( $gf_id );
		if ( ! $gf_form ) {
			wp_safe_redirect( add_query_arg( 'dsf_gf_migrate', 'not_found', $redirect ) );
			exit;
		}

		// GF_Field objects become plain arrays so the mapper stays framework-free.
		$gf_data = json_decode( wp_json_encode( $gf_form ), true );
		if ( ! is_array( $gf_data ) ) {
			wp_safe_redirect( add_query_arg( 'dsf_gf_migrate', 'not_found', $redirect ) );
			exit;
		}

		$mapped = $this->map_gf_form( $gf_data );

		$feed_connections = $this->collect_feed_connections( $gf_id );
		if ( $feed_connections ) {
			$mapped['settings']['connections'] = array_merge( $mapped['settings']['connections'], $feed_connections );
		}

		$clean = DSF_Forms::get_instance()->sanitize_imported_form( $mapped['rows'], $mapped['settings'] );

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'dsf_form',
				'post_status' => 'publish',
				'post_title'  => sanitize_text_field( $mapped['title'] ),
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			wp_safe_redirect( add_query_arg( 'dsf_gf_migrate', 'failed', $redirect ) );
			exit;
		}

		update_post_meta( $post_id, '_dsf_form_rows', $clean['rows'] );
		update_post_meta( $post_id, '_dsf_form_settings', $clean['settings'] );
		$published = DSF_Multilingual::get_instance()->get_publish_gate()->finalize_new_post_publication( $post_id );
		if ( is_wp_error( $published ) ) {
			wp_safe_redirect( add_query_arg( 'dsf_gf_migrate', 'failed', $redirect ) );
			exit;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'dsf_gf_migrate'     => 'done',
					'dsf_gf_form'        => (int) $post_id,
					'dsf_gf_skipped'     => count( $mapped['skipped'] ),
					'dsf_gf_connections' => count( $feed_connections ),
				),
				$redirect
			)
		);
		exit;
	}

	/**
	 * Success / error notices after a migration redirect.
	 */
	public function show_admin_notices() {
		if ( ! current_user_can( 'edit_pages' ) || ! isset( $_GET['dsf_gf_migrate'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice from redirect flags.
			return;
		}

		$state = sanitize_key( wp_unslash( $_GET['dsf_gf_migrate'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'done' === $state ) {
			$form_id     = isset( $_GET['dsf_gf_form'] ) ? absint( $_GET['dsf_gf_form'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$skipped     = isset( $_GET['dsf_gf_skipped'] ) ? absint( $_GET['dsf_gf_skipped'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$connections = isset( $_GET['dsf_gf_connections'] ) ? absint( $_GET['dsf_gf_connections'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$builder_url = admin_url( 'admin.php?page=dsf-form-builder&form_id=' . $form_id );
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php esc_html_e( 'Gravity Form migrated to a DSF form.', 'designstudio-flow' ); ?>
					<a href="<?php echo esc_url( $builder_url ); ?>"><?php esc_html_e( 'Open it in the form builder', 'designstudio-flow' ); ?></a>
					<?php
					printf(
						/* translators: %d: form ID for the embed shortcode */
						esc_html__( 'or embed it with [dsform id=\'%d\'].', 'designstudio-flow' ),
						(int) $form_id
					);
					?>
				</p>
				<?php if ( $connections > 0 ) : ?>
					<p>
						<?php
						printf(
							/* translators: %d: number of imported connections */
							esc_html( _n( '%d Zapier/webhook feed was imported as a connection. It is disabled until you review and enable it, so nothing double-fires while the Gravity Form is still live.', '%d Zapier/webhook feeds were imported as connections. They are disabled until you review and enable them, so nothing double-fires while the Gravity Form is still live.', $connections, 'designstudio-flow' ) ),
							(int) $connections
						);
						?>
					</p>
				<?php endif; ?>
				<?php if ( $skipped > 0 ) : ?>
					<p>
						<?php
						printf(
							/* translators: %d: number of skipped fields */
							esc_html( _n( '%d field type had no DSF equivalent and was skipped.', '%d field types had no DSF equivalent and were skipped.', $skipped, 'designstudio-flow' ) ),
							(int) $skipped
						);
						?>
					</p>
				<?php endif; ?>
			</div>
			<?php
			return;
		}

		$messages = array(
			'no_gf'     => __( 'Gravity Forms is not active, so the form could not be migrated.', 'designstudio-flow' ),
			'not_found' => __( 'That Gravity Form could not be found.', 'designstudio-flow' ),
			'failed'    => __( 'The DSF form could not be created.', 'designstudio-flow' ),
		);
		if ( isset( $messages[ $state ] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $messages[ $state ] ) . '</p></div>';
		}
	}

	/**
	 * Render the "Migrate from Gravity Forms" section on the Tools → Forms tab.
	 */
	public function render_tools_section() {
		?>
		<div class="card" style="padding:20px;max-width:760px;margin-top:20px;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Migrate from Gravity Forms', 'designstudio-flow' ); ?></h2>
			<?php if ( ! self::is_gravity_forms_active() ) : ?>
				<p><?php esc_html_e( 'Gravity Forms is not active on this site.', 'designstudio-flow' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Create a ready-to-use DSF copy of a Gravity Form: fields, required flags, choices, conditional logic, confirmation, admin notification, and any Zapier/webhook feeds (imported disabled for review). The original Gravity Form is not changed.', 'designstudio-flow' ); ?></p>
				<?php
				$forms = GFAPI::get_forms();
				if ( empty( $forms ) ) {
					echo '<p>' . esc_html__( 'No Gravity Forms found.', 'designstudio-flow' ) . '</p>';
				} else {
					?>
					<table class="widefat striped" style="max-width:720px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Form', 'designstudio-flow' ); ?></th>
								<th><?php esc_html_e( 'Fields', 'designstudio-flow' ); ?></th>
								<th><?php esc_html_e( 'Status', 'designstudio-flow' ); ?></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $forms as $form ) : ?>
								<?php
								$gf_id = isset( $form['id'] ) ? (int) $form['id'] : 0;
								if ( ! $gf_id ) {
									continue;
								}
								$migrate_url = wp_nonce_url(
									add_query_arg(
										array(
											'action'     => self::ACTION,
											'gf_form_id' => $gf_id,
										),
										admin_url( 'admin-post.php' )
									),
									self::ACTION . '_' . $gf_id
								);
								?>
								<tr>
									<td><?php echo esc_html( $form['title'] ?? ( '#' . $gf_id ) ); ?></td>
									<td><?php echo esc_html( is_array( $form['fields'] ?? null ) ? count( $form['fields'] ) : 0 ); ?></td>
									<td><?php echo empty( $form['is_active'] ) ? esc_html__( 'Inactive', 'designstudio-flow' ) : esc_html__( 'Active', 'designstudio-flow' ); ?></td>
									<td><a class="button" href="<?php echo esc_url( $migrate_url ); ?>"><?php esc_html_e( 'Migrate to DSF Form', 'designstudio-flow' ); ?></a></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php
				}
				?>
			<?php endif; ?>
		</div>
		<?php
	}

	/* -----------------------------------------------------------------
	 * Pure mapping (unit-testable; no WordPress/GF calls)
	 * ----------------------------------------------------------------- */

	/**
	 * Map a Gravity Forms definition (as a plain array) to a DSF form
	 * definition. The result still goes through DSF_Forms sanitizers before
	 * being stored, so this only needs to translate structure.
	 *
	 * @param array $gf Gravity Form as an array (fields as arrays).
	 * @return array{title: string, rows: array, settings: array, skipped: array}
	 */
	public function map_gf_form( $gf ) {
		$gf     = is_array( $gf ) ? $gf : array();
		$fields = is_array( $gf['fields'] ?? null ) ? $gf['fields'] : array();

		// First pass: primary DSF field id per GF field id, so conditional
		// logic can reference fields defined later in the form.
		$id_map = array();
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || ! isset( $field['id'] ) ) {
				continue;
			}
			$gf_field_id            = (int) $field['id'];
			$id_map[ $gf_field_id ] = $this->primary_dsf_id( $field );
		}

		$rows             = array();
		$skipped          = array();
		$used             = array();
		$pending_half_row = null;

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$mapped_rows = $this->map_gf_field( $field, $id_map, $used );
			if ( null === $mapped_rows ) {
				$skipped[]        = (string) ( $field['type'] ?? 'unknown' );
				$pending_half_row = null;
				continue;
			}
			$this->append_mapped_rows( $rows, $mapped_rows, $field, $pending_half_row );
		}

		return array(
			'title'    => trim( (string) ( $gf['title'] ?? '' ) ) !== '' ? (string) $gf['title'] : 'Migrated form',
			'rows'     => $rows,
			'settings' => $this->map_gf_settings( $gf ),
			'skipped'  => $skipped,
		);
	}

	/**
	 * Append mapped rows while preserving a Gravity Forms two-column row.
	 *
	 * Modern Gravity Forms exports a shared layoutGroupId plus a six-column
	 * span for each half-width field. Older forms use adjacent gf_left_half /
	 * gf_right_half CSS-ready classes. DSF supports two columns, so only these
	 * equal-width pairs are joined; thirds and quarters intentionally remain
	 * full-width to avoid a misleading layout conversion.
	 *
	 * @param array      $rows             DSF rows, by reference.
	 * @param array      $mapped_rows      Rows produced for the current GF field.
	 * @param array      $gf_field         Source GF field.
	 * @param array|null $pending_half_row Previous eligible half-width row.
	 * @return void
	 */
	private function append_mapped_rows( &$rows, $mapped_rows, $gf_field, &$pending_half_row ) {
		$layout                   = $this->gf_half_width_layout( $gf_field );
		$is_single_mappable_field = 1 === count( $mapped_rows )
			&& isset( $mapped_rows[0]['fields'] )
			&& is_array( $mapped_rows[0]['fields'] )
			&& 1 === count( $mapped_rows[0]['fields'] )
			&& ! in_array( $mapped_rows[0]['fields'][0]['type'] ?? '', array( 'hidden', 'html', 'page_break' ), true );

		if ( ! $layout || ! $is_single_mappable_field ) {
			foreach ( $mapped_rows as $row ) {
				$rows[] = $row;
			}
			$pending_half_row = null;
			return;
		}

		$field = $mapped_rows[0]['fields'][0];
		if ( $pending_half_row && $this->gf_half_layouts_pair( $pending_half_row['layout'], $layout ) ) {
			$first_field                                      = $rows[ $pending_half_row['row_index'] ]['fields'][0];
			$first_field['width']                             = 'half';
			$field['width']                                   = 'half';
			$rows[ $pending_half_row['row_index'] ]['fields'] = array( $first_field, $field );
			$pending_half_row                                 = null;
			return;
		}

		$field['width']   = 'half';
		$rows[]           = array( 'fields' => array( $field ) );
		$pending_half_row = array(
			'layout'    => $layout,
			'row_index' => count( $rows ) - 1,
		);
	}

	/**
	 * Identify a GF half-width layout marker without trusting it as output.
	 *
	 * @param array $field GF field.
	 * @return array|null
	 */
	private function gf_half_width_layout( $field ) {
		$group_id = trim( (string) ( $field['layoutGroupId'] ?? '' ) );
		$span     = isset( $field['layoutGridColumnSpan'] ) ? (int) $field['layoutGridColumnSpan'] : 0;
		if ( '' !== $group_id && 6 === $span ) {
			return array(
				'type'  => 'modern',
				'group' => $group_id,
			);
		}

		$classes = preg_split( '/\s+/', trim( (string) ( $field['cssClass'] ?? '' ) ) );
		$classes = is_array( $classes ) ? $classes : array();
		if ( in_array( 'gf_left_half', $classes, true ) ) {
			return array( 'type' => 'legacy-left' );
		}
		if ( in_array( 'gf_right_half', $classes, true ) ) {
			return array( 'type' => 'legacy-right' );
		}

		return null;
	}

	/**
	 * Whether two parsed GF layout markers form one DSF 50/50 row.
	 *
	 * @param array $first  First field layout marker.
	 * @param array $second Second field layout marker.
	 * @return bool
	 */
	private function gf_half_layouts_pair( $first, $second ) {
		if ( 'modern' === $first['type'] && 'modern' === $second['type'] ) {
			return $first['group'] === $second['group'];
		}

		return 'legacy-left' === $first['type'] && 'legacy-right' === $second['type'];
	}

	/**
	 * The DSF field id a GF field's conditional-logic rules should target.
	 * Composite fields (name, address) resolve to their first sub-field.
	 *
	 * @param array $field GF field.
	 * @return string
	 */
	private function primary_dsf_id( $field ) {
		$gf_id = (int) ( $field['id'] ?? 0 );
		$type  = $this->effective_gf_type( $field );
		if ( 'name' === $type ) {
			return $this->primary_composite_dsf_id(
				$field,
				$gf_id,
				array(
					'.2' => 'prefix',
					'.3' => 'first',
					'.4' => 'middle',
					'.6' => 'last',
					'.8' => 'suffix',
				),
				'first'
			);
		}
		if ( 'address' === $type ) {
			return $this->primary_composite_dsf_id(
				$field,
				$gf_id,
				array(
					'.1' => 'street',
					'.2' => 'street_2',
					'.3' => 'city',
					'.4' => 'state',
					'.5' => 'zip',
					'.6' => 'country',
				),
				'street'
			);
		}
		return 'gf-' . $gf_id;
	}

	/**
	 * Resolve Gravity Forms' effective field type.
	 *
	 * Gravity Forms can hide an otherwise ordinary field with its Visibility
	 * setting. Those fields must become real DSF hidden inputs instead of
	 * retaining their visible text/select type. Administrative fields likewise
	 * stay out of the public UI while preserving a configured default value.
	 *
	 * @param array $field GF field.
	 * @return string
	 */
	private function effective_gf_type( $field ) {
		$visibility = strtolower( (string) ( $field['visibility'] ?? '' ) );
		if ( in_array( $visibility, array( 'hidden', 'administrative', 'admin' ), true ) || ! empty( $field['adminOnly'] ) ) {
			return 'hidden';
		}

		return strtolower( (string) ( $field['type'] ?? '' ) );
	}

	/**
	 * Find the first visible composite input for conditional-logic references.
	 *
	 * @param array  $field      GF field.
	 * @param int    $gf_id      GF field id.
	 * @param array  $suffix_map Input suffix => DSF id suffix.
	 * @param string $fallback   Fallback DSF id suffix.
	 * @return string
	 */
	private function primary_composite_dsf_id( $field, $gf_id, $suffix_map, $fallback ) {
		$inputs = is_array( $field['inputs'] ?? null ) ? $field['inputs'] : array();
		foreach ( $inputs as $input ) {
			if ( ! is_array( $input ) || ! empty( $input['isHidden'] ) ) {
				continue;
			}
			$suffix = $this->gf_input_suffix( $input['id'] ?? '' );
			if ( isset( $suffix_map[ $suffix ] ) ) {
				return 'gf-' . $gf_id . '-' . $suffix_map[ $suffix ];
			}
		}

		return 'gf-' . $gf_id . '-' . $fallback;
	}

	/**
	 * Map one GF field to zero or more DSF rows. Returns null when the type
	 * has no DSF equivalent (reported as skipped).
	 *
	 * @param array $field  GF field as an array.
	 * @param array $id_map GF field id => primary DSF field id.
	 * @param array $used   By-ref set of used machine names (uniqueness).
	 * @return array[]|null
	 */
	private function map_gf_field( $field, $id_map, &$used ) {
		$gf_id = (int) ( $field['id'] ?? 0 );
		$type  = $this->effective_gf_type( $field );
		$logic = $this->map_conditional_logic( $field['conditionalLogic'] ?? null, $id_map );

		$simple_types = array(
			'text'         => 'single_line_text',
			'textarea'     => 'paragraph_text',
			'select'       => 'drop_down',
			'multiselect'  => 'checkboxes',
			'checkbox'     => 'checkboxes',
			'radio'        => 'radio_buttons',
			'number'       => 'number',
			'phone'        => 'phone',
			'date'         => 'date',
			'email'        => 'email',
			'website'      => 'website',
			'fileupload'   => 'file_upload',
			'hidden'       => 'hidden',
			'time'         => 'single_line_text',
			'list'         => 'paragraph_text',
			'post_title'   => 'single_line_text',
			'post_content' => 'paragraph_text',
			'post_excerpt' => 'paragraph_text',
			'post_tags'    => 'single_line_text',
			'quantity'     => 'number',
		);

		if ( isset( $simple_types[ $type ] ) ) {
			$dsf = $this->base_field( 'gf-' . $gf_id, $simple_types[ $type ], $field, $used );

			$dsf['options']          = $this->map_choices( $field['choices'] ?? null );
			$dsf['conditionalLogic'] = $logic;
			if ( 'hidden' === $dsf['type'] && '' === $dsf['defaultValue'] ) {
				foreach ( $dsf['options'] as $option ) {
					if ( ! empty( $option['selected'] ) ) {
						$dsf['defaultValue'] = '' !== $option['value'] ? $option['value'] : $option['label'];
						break;
					}
				}
			}

			return array( array( 'fields' => array( $dsf ) ) );
		}

		if ( 'consent' === $type ) {
			$dsf           = $this->base_field( 'gf-' . $gf_id, 'checkboxes', $field, $used );
			$consent_label = '';
			$choices       = is_array( $field['choices'] ?? null ) ? $field['choices'] : array();
			if ( isset( $choices[0]['text'] ) ) {
				$consent_label = (string) $choices[0]['text'];
			}
			if ( '' === $consent_label ) {
				$consent_label = 'I agree';
			}
			$dsf['options']          = array(
				array(
					'label'    => $consent_label,
					'value'    => '',
					'selected' => false,
				),
			);
			$dsf['conditionalLogic'] = $logic;
			return array( array( 'fields' => array( $dsf ) ) );
		}

		if ( 'html' === $type ) {
			$dsf                     = $this->base_field( 'gf-' . $gf_id, 'html', $field, $used );
			$dsf['html']             = (string) ( $field['content'] ?? '' );
			$dsf['conditionalLogic'] = $logic;
			return array( array( 'fields' => array( $dsf ) ) );
		}

		if ( 'section' === $type ) {
			$dsf                     = $this->base_field( 'gf-' . $gf_id, 'html', $field, $used );
			$heading                 = (string) ( $field['label'] ?? '' );
			$description             = (string) ( $field['description'] ?? '' );
			$dsf['html']             = '' !== $heading ? '<h3>' . $heading . '</h3>' : '';
			$dsf['html']            .= '' !== $description ? '<p>' . $description . '</p>' : '';
			$dsf['conditionalLogic'] = $logic;
			return array( array( 'fields' => array( $dsf ) ) );
		}

		if ( 'page' === $type ) {
			$dsf = $this->base_field( 'gf-' . $gf_id, 'page_break', $field, $used );
			return array( array( 'fields' => array( $dsf ) ) );
		}

		if ( 'name' === $type ) {
			return $this->map_composite_field(
				$field,
				$used,
				$logic,
				array(
					'.2' => array(
						'slug'  => 'prefix',
						'label' => 'Prefix',
					),
					'.3' => array(
						'slug'  => 'first',
						'label' => 'First Name',
					),
					'.4' => array(
						'slug'  => 'middle',
						'label' => 'Middle Name',
					),
					'.6' => array(
						'slug'  => 'last',
						'label' => 'Last Name',
					),
					'.8' => array(
						'slug'  => 'suffix',
						'label' => 'Suffix',
					),
				),
				array( '.3', '.6' )
			);
		}

		if ( 'address' === $type ) {
			return $this->map_composite_field(
				$field,
				$used,
				$logic,
				array(
					'.1' => array(
						'slug'  => 'street',
						'label' => 'Street Address',
					),
					'.2' => array(
						'slug'  => 'street_2',
						'label' => 'Address Line 2',
					),
					'.3' => array(
						'slug'  => 'city',
						'label' => 'City',
					),
					'.4' => array(
						'slug'  => 'state',
						'label' => 'State / Province',
					),
					'.5' => array(
						'slug'  => 'zip',
						'label' => 'ZIP / Postal Code',
					),
					'.6' => array(
						'slug'  => 'country',
						'label' => 'Country',
					),
				),
				array( '.1', '.2', '.3', '.4', '.5', '.6' )
			);
		}

		return null;
	}

	/**
	 * Shared skeleton for a mapped DSF field.
	 *
	 * @param string $dsf_id         Deterministic DSF field id.
	 * @param string $dsf_type       DSF field type.
	 * @param array  $field          Source GF field.
	 * @param array  $used           By-ref set of used machine names.
	 * @param string $label_fallback Label when the GF label is empty.
	 * @return array
	 */
	private function base_field( $dsf_id, $dsf_type, $field, &$used, $label_fallback = '' ) {
		$label = trim( (string) ( $field['label'] ?? '' ) );
		if ( '' !== $label_fallback ) {
			$label = $label_fallback;
		} elseif ( '' === $label ) {
			$label = ucwords( str_replace( '_', ' ', $dsf_type ) );
		}

		return array(
			'id'               => $dsf_id,
			'type'             => $dsf_type,
			'label'            => $label,
			'name'             => $this->unique_name( '' !== $label ? $label : $dsf_id, $dsf_id, $used ),
			'width'            => 'full',
			'required'         => ! empty( $field['isRequired'] ),
			'placeholder'      => (string) ( $field['placeholder'] ?? '' ),
			'defaultValue'     => is_scalar( $field['defaultValue'] ?? '' ) ? (string) ( $field['defaultValue'] ?? '' ) : '',
			'paramName'        => ! empty( $field['allowsPrepopulate'] ) && is_scalar( $field['inputName'] ?? '' ) ? (string) $field['inputName'] : '',
			'helpText'         => (string) ( $field['description'] ?? '' ),
			'options'          => array(),
			'html'             => '',
			'conditionalLogic' => array(
				'enabled' => false,
				'rules'   => array(),
			),
		);
	}

	/**
	 * Convert each visible input of a GF Name or Address field into a DSF field.
	 * Rows are capped at two fields to match the DSF form schema.
	 *
	 * @param array $field            GF composite field.
	 * @param array $used             By-ref set of used machine names.
	 * @param array $logic            Mapped conditional logic.
	 * @param array $definitions      GF suffix definitions.
	 * @param array $default_suffixes Suffixes used by older/minimal exports.
	 * @return array[]|null
	 */
	private function map_composite_field( $field, &$used, $logic, $definitions, $default_suffixes ) {
		$gf_id        = (int) ( $field['id'] ?? 0 );
		$inputs       = is_array( $field['inputs'] ?? null ) ? $field['inputs'] : array();
		$input_by_key = array();

		foreach ( $inputs as $input ) {
			if ( ! is_array( $input ) || ! empty( $input['isHidden'] ) ) {
				continue;
			}
			$suffix = $this->gf_input_suffix( $input['id'] ?? '' );
			if ( isset( $definitions[ $suffix ] ) ) {
				$input_by_key[ $suffix ] = $input;
			}
		}

		$suffixes = $inputs ? array_keys( $input_by_key ) : $default_suffixes;
		$mapped   = array();
		foreach ( $suffixes as $suffix ) {
			if ( ! isset( $definitions[ $suffix ] ) ) {
				continue;
			}
			$definition = $definitions[ $suffix ];
			$input      = $input_by_key[ $suffix ] ?? array();
			$input_type = ( 'radio' === ( $input['inputType'] ?? '' ) ) ? 'radio_buttons' : 'single_line_text';
			$sub_field  = $this->base_field( 'gf-' . $gf_id . '-' . $definition['slug'], $input_type, $field, $used, $definition['label'] );

			$custom_label              = trim( (string) ( $input['customLabel'] ?? '' ) );
			$input_label               = trim( (string) ( $input['label'] ?? '' ) );
			$sub_field['label']        = '' !== $custom_label ? $custom_label : ( '' !== $input_label ? $input_label : $definition['label'] );
			$sub_field['placeholder']  = (string) ( $input['placeholder'] ?? '' );
			$sub_field['defaultValue'] = is_scalar( $input['defaultValue'] ?? '' ) ? (string) ( $input['defaultValue'] ?? '' ) : '';
			$sub_field['paramName']    = is_scalar( $input['name'] ?? '' ) ? (string) ( $input['name'] ?? '' ) : '';
			$sub_field['options']      = $this->map_choices( $input['choices'] ?? null );
			$mapped[]                  = $sub_field;
		}

		if ( ! $mapped ) {
			return null;
		}

		$mapped[0]['conditionalLogic'] = $logic;
		$rows                          = array();
		foreach ( array_chunk( $mapped, 2 ) as $chunk ) {
			if ( 2 === count( $chunk ) ) {
				$chunk[0]['width'] = 'half';
				$chunk[1]['width'] = 'half';
			}
			$rows[] = array( 'fields' => $chunk );
		}

		return $rows;
	}

	/**
	 * Return the decimal suffix from a GF composite input id (for example .3).
	 *
	 * @param mixed $input_id GF input id.
	 * @return string
	 */
	private function gf_input_suffix( $input_id ) {
		$input_id = (string) $input_id;
		$dot      = strpos( $input_id, '.' );
		return false === $dot ? '' : substr( $input_id, $dot );
	}

	/**
	 * Build a unique machine name from a label.
	 *
	 * @param string $label  Preferred source text.
	 * @param string $dsf_id Fallback id.
	 * @param array  $used   By-ref set of used names.
	 * @return string
	 */
	private function unique_name( $label, $dsf_id, &$used ) {
		$name = strtolower( trim( (string) $label ) );
		$name = preg_replace( '/[^a-z0-9]+/', '_', $name );
		$name = trim( (string) $name, '_' );
		if ( '' === $name ) {
			$name = 'field_' . preg_replace( '/[^a-z0-9_]/', '', str_replace( '-', '_', strtolower( $dsf_id ) ) );
		}

		$candidate = $name;
		$suffix    = 2;
		while ( isset( $used[ $candidate ] ) ) {
			$candidate = $name . '_' . $suffix;
			++$suffix;
		}
		$used[ $candidate ] = true;

		return $candidate;
	}

	/**
	 * GF choices → DSF options.
	 *
	 * @param mixed $choices GF choices array.
	 * @return array
	 */
	private function map_choices( $choices ) {
		if ( ! is_array( $choices ) ) {
			return array();
		}

		$options = array();
		foreach ( $choices as $choice ) {
			if ( ! is_array( $choice ) ) {
				continue;
			}
			$label = trim( (string) ( $choice['text'] ?? '' ) );
			$value = (string) ( $choice['value'] ?? '' );
			if ( '' === $label && '' === $value ) {
				continue;
			}
			$options[] = array(
				'label'    => '' !== $label ? $label : $value,
				'value'    => $value === $label ? '' : $value,
				'selected' => ! empty( $choice['isSelected'] ),
			);
			if ( count( $options ) >= 50 ) {
				break;
			}
		}

		return $options;
	}

	/**
	 * GF conditional logic → DSF conditional logic (field ids remapped).
	 *
	 * @param mixed $logic  GF conditionalLogic array.
	 * @param array $id_map GF field id => DSF field id.
	 * @return array
	 */
	private function map_conditional_logic( $logic, $id_map ) {
		$disabled = array(
			'enabled' => false,
			'rules'   => array(),
		);

		if ( ! is_array( $logic ) || empty( $logic['rules'] ) || ! is_array( $logic['rules'] ) ) {
			return $disabled;
		}

		$operator_map = array(
			'is'          => 'equals',
			'isnot'       => 'not_equals',
			'contains'    => 'contains',
			'starts_with' => 'contains',
			'ends_with'   => 'contains',
			'>'           => 'greater_than',
			'<'           => 'less_than',
		);

		$rules = array();
		foreach ( $logic['rules'] as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			// Checkbox rules reference input ids like "3.1" — the integer part
			// is the field id.
			$gf_field_id = (int) ( $rule['fieldId'] ?? 0 );
			if ( ! isset( $id_map[ $gf_field_id ] ) ) {
				continue;
			}
			$operator = (string) ( $rule['operator'] ?? 'is' );
			$rules[]  = array(
				'fieldId'  => $id_map[ $gf_field_id ],
				'operator' => $operator_map[ $operator ] ?? 'equals',
				'value'    => (string) ( $rule['value'] ?? '' ),
			);
		}

		if ( empty( $rules ) ) {
			return $disabled;
		}

		return array(
			'enabled'   => true,
			'action'    => ( 'hide' === ( $logic['actionType'] ?? '' ) ) ? 'hide' : 'show',
			'logicType' => ( 'any' === ( $logic['logicType'] ?? '' ) ) ? 'any' : 'all',
			'rules'     => $rules,
		);
	}

	/**
	 * GF confirmations / notifications / button → DSF form settings.
	 *
	 * @param array $gf Gravity Form array.
	 * @return array
	 */
	public function map_gf_settings( $gf ) {
		$settings = array(
			'connections' => array(),
		);

		$button = is_array( $gf['button'] ?? null ) ? $gf['button'] : array();
		if ( ! empty( $button['text'] ) ) {
			$settings['submitLabel'] = (string) $button['text'];
		}

		// Default (or first) confirmation.
		$confirmation = null;
		foreach ( ( is_array( $gf['confirmations'] ?? null ) ? $gf['confirmations'] : array() ) as $candidate ) {
			if ( ! is_array( $candidate ) ) {
				continue;
			}
			if ( null === $confirmation || ! empty( $candidate['isDefault'] ) ) {
				$confirmation = $candidate;
			}
			if ( ! empty( $candidate['isDefault'] ) ) {
				break;
			}
		}
		if ( $confirmation ) {
			if ( 'redirect' === ( $confirmation['type'] ?? '' ) && ! empty( $confirmation['url'] ) ) {
				$settings['confirmationType'] = 'redirect_url';
				$settings['redirectUrl']      = (string) $confirmation['url'];
			} elseif ( ! empty( $confirmation['message'] ) ) {
				$settings['confirmationType']    = 'message';
				$settings['confirmationMessage'] = $this->strip_merge_tags( (string) $confirmation['message'] );
			}
		}

		// Active admin notification → DSF admin notification.
		foreach ( ( is_array( $gf['notifications'] ?? null ) ? $gf['notifications'] : array() ) as $notification ) {
			if ( ! is_array( $notification ) || ( isset( $notification['isActive'] ) && ! $notification['isActive'] ) ) {
				continue;
			}
			$to = (string) ( $notification['to'] ?? '' );
			if ( '' === $to ) {
				continue;
			}

			$emails = array();
			foreach ( explode( ',', $to ) as $email ) {
				$email = trim( $email );
				if ( '{admin_email}' === $email && function_exists( 'get_option' ) ) {
					$email = (string) get_option( 'admin_email' );
				}
				if ( '' !== $email && false === strpos( $email, '{' ) ) {
					$emails[] = $email;
				}
			}

			if ( $emails ) {
				$settings['sendAdminNotifications'] = true;
				$settings['adminEmails']            = $emails;
				if ( ! empty( $notification['subject'] ) ) {
					$subject = $this->strip_merge_tags( (string) $notification['subject'] );
					if ( '' !== $subject ) {
						$settings['notificationSubject'] = $subject;
					}
				}
				break;
			}
		}

		return $settings;
	}

	/**
	 * Remove GF merge tags such as {form_title} or {Name (First):1.3}.
	 *
	 * @param string $text Text possibly containing merge tags.
	 * @return string
	 */
	private function strip_merge_tags( $text ) {
		$text = preg_replace( '/\{[^{}]*\}/', '', (string) $text );
		return trim( preg_replace( '/[ \t]{2,}/', ' ', (string) $text ) );
	}

	/* -----------------------------------------------------------------
	 * Zapier / webhook feed discovery
	 * ----------------------------------------------------------------- */

	/**
	 * Read Zapier and Webhooks add-on feeds attached to a Gravity Form and
	 * convert them into DSF connections. Imported connections are disabled by
	 * default so nothing double-fires while the Gravity Form is still live.
	 *
	 * @param int $gf_form_id Gravity Form id.
	 * @return array[]
	 */
	public function collect_feed_connections( $gf_form_id ) {
		global $wpdb;

		$connections = array();
		$seen_urls   = array();

		// Modern add-on framework feeds (Zapier v4+, Webhooks add-on).
		$feed_table = $wpdb->prefix . 'gf_addon_feed';
		if ( $this->table_exists( $feed_table ) ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off read of GF add-on feeds during an explicit admin migration.
				$wpdb->prepare(
					'SELECT addon_slug, meta FROM %i WHERE form_id = %d AND addon_slug IN (%s, %s)',
					$feed_table,
					$gf_form_id,
					'gravityformszapier',
					'gravityformswebhooks'
				),
				ARRAY_A
			);

			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$meta = json_decode( (string) ( $row['meta'] ?? '' ), true );
				if ( ! is_array( $meta ) ) {
					continue;
				}
				$label = (string) ( $meta['feedName'] ?? $meta['feed_name'] ?? '' );
				foreach ( $this->find_urls_in_value( $meta ) as $url ) {
					if ( isset( $seen_urls[ $url ] ) ) {
						continue;
					}
					$seen_urls[ $url ] = true;
					$connections[]     = $this->feed_connection( $url, $label );
				}
			}
		}

		// Legacy standalone Zapier plugin table.
		$legacy_table = $wpdb->prefix . 'gf_zapier';
		if ( $this->table_exists( $legacy_table ) ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off read of legacy GF Zapier feeds during an explicit admin migration.
				$wpdb->prepare( 'SELECT name, url FROM %i WHERE form_id = %d', $legacy_table, $gf_form_id ),
				ARRAY_A
			);
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$url = (string) ( $row['url'] ?? '' );
				if ( '' === $url || isset( $seen_urls[ $url ] ) || 0 !== strpos( $url, 'https://' ) ) {
					continue;
				}
				$seen_urls[ $url ] = true;
				$connections[]     = $this->feed_connection( $url, (string) ( $row['name'] ?? '' ) );
			}
		}

		return array_slice( $connections, 0, 20 );
	}

	/**
	 * Build one imported DSF connection from a feed URL.
	 *
	 * @param string $url   Feed endpoint URL.
	 * @param string $label Feed name.
	 * @return array
	 */
	private function feed_connection( $url, $label ) {
		$host      = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$is_zapier = 'zapier.com' === $host || '.zapier.com' === substr( $host, -11 );

		return array(
			'id'          => 'gf-feed-' . md5( $url ),
			'enabled'     => false,
			'type'        => $is_zapier ? 'zapier' : 'webhook',
			'label'       => '' !== $label ? $label . ' (imported from Gravity Forms)' : 'Imported from Gravity Forms',
			'endpointUrl' => $url,
			'secret'      => '',
			'timeout'     => 8,
		);
	}

	/**
	 * Recursively collect https URLs from a decoded feed meta value.
	 *
	 * @param mixed $value Feed meta.
	 * @return string[]
	 */
	private function find_urls_in_value( $value ) {
		$urls = array();
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				$urls = array_merge( $urls, $this->find_urls_in_value( $item ) );
			}
			return $urls;
		}
		if ( is_string( $value ) && 0 === strpos( $value, 'https://' ) && false === strpos( $value, ' ' ) ) {
			$urls[] = $value;
		}
		return $urls;
	}

	/**
	 * Whether a database table exists.
	 *
	 * @param string $table Table name.
	 * @return bool
	 */
	private function table_exists( $table ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema lookup during an explicit admin migration.
	}
}
