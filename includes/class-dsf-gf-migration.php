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
		if ( ! current_user_can( 'edit_pages' ) ) {
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

		$rows    = array();
		$skipped = array();
		$used    = array();

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$mapped_rows = $this->map_gf_field( $field, $id_map, $used );
			if ( null === $mapped_rows ) {
				$skipped[] = (string) ( $field['type'] ?? 'unknown' );
				continue;
			}
			foreach ( $mapped_rows as $row ) {
				$rows[] = $row;
			}
		}

		return array(
			'title'    => trim( (string) ( $gf['title'] ?? '' ) ) !== '' ? (string) $gf['title'] : 'Migrated form',
			'rows'     => $rows,
			'settings' => $this->map_gf_settings( $gf ),
			'skipped'  => $skipped,
		);
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
		$type  = (string) ( $field['type'] ?? '' );
		if ( 'name' === $type ) {
			return 'gf-' . $gf_id . '-first';
		}
		if ( 'address' === $type ) {
			return 'gf-' . $gf_id . '-street';
		}
		return 'gf-' . $gf_id;
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
		$type  = (string) ( $field['type'] ?? '' );
		$logic = $this->map_conditional_logic( $field['conditionalLogic'] ?? null, $id_map );

		$simple_types = array(
			'text'        => 'single_line_text',
			'textarea'    => 'paragraph_text',
			'select'      => 'drop_down',
			'multiselect' => 'checkboxes',
			'checkbox'    => 'checkboxes',
			'radio'       => 'radio_buttons',
			'number'      => 'number',
			'phone'       => 'phone',
			'date'        => 'date',
			'email'       => 'email',
			'website'     => 'website',
			'fileupload'  => 'file_upload',
			'hidden'      => 'hidden',
		);

		if ( isset( $simple_types[ $type ] ) ) {
			$dsf = $this->base_field( 'gf-' . $gf_id, $simple_types[ $type ], $field, $used );

			$dsf['options']          = $this->map_choices( $field['choices'] ?? null );
			$dsf['conditionalLogic'] = $logic;

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
			$first = $this->base_field( 'gf-' . $gf_id . '-first', 'single_line_text', $field, $used, 'First Name' );
			$last  = $this->base_field( 'gf-' . $gf_id . '-last', 'single_line_text', $field, $used, 'Last Name' );

			$first['label']            = $this->sub_input_label( $field, '.3', 'First Name' );
			$last['label']             = $this->sub_input_label( $field, '.6', 'Last Name' );
			$first['width']            = 'half';
			$last['width']             = 'half';
			$first['conditionalLogic'] = $logic;

			return array( array( 'fields' => array( $first, $last ) ) );
		}

		if ( 'address' === $type ) {
			$street  = $this->base_field( 'gf-' . $gf_id . '-street', 'single_line_text', $field, $used, 'Street Address' );
			$city    = $this->base_field( 'gf-' . $gf_id . '-city', 'single_line_text', $field, $used, 'City' );
			$state   = $this->base_field( 'gf-' . $gf_id . '-state', 'single_line_text', $field, $used, 'State / Province' );
			$zip     = $this->base_field( 'gf-' . $gf_id . '-zip', 'single_line_text', $field, $used, 'ZIP / Postal Code' );
			$country = $this->base_field( 'gf-' . $gf_id . '-country', 'single_line_text', $field, $used, 'Country' );

			$street['label']  = $this->sub_input_label( $field, '.1', 'Street Address' );
			$city['label']    = $this->sub_input_label( $field, '.3', 'City' );
			$state['label']   = $this->sub_input_label( $field, '.4', 'State / Province' );
			$zip['label']     = $this->sub_input_label( $field, '.5', 'ZIP / Postal Code' );
			$country['label'] = $this->sub_input_label( $field, '.6', 'Country' );

			$city['width']              = 'half';
			$state['width']             = 'half';
			$zip['width']               = 'half';
			$country['width']           = 'half';
			$street['conditionalLogic'] = $logic;

			return array(
				array( 'fields' => array( $street ) ),
				array( 'fields' => array( $city, $state ) ),
				array( 'fields' => array( $zip, $country ) ),
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
	 * GF sub-input label (e.g. First Name of a name field).
	 *
	 * @param array  $field    GF field.
	 * @param string $id_suffix Input id suffix such as ".3".
	 * @param string $fallback Fallback label.
	 * @return string
	 */
	private function sub_input_label( $field, $id_suffix, $fallback ) {
		$inputs = is_array( $field['inputs'] ?? null ) ? $field['inputs'] : array();
		foreach ( $inputs as $input ) {
			if ( ! is_array( $input ) || ! isset( $input['id'] ) ) {
				continue;
			}
			if ( substr( (string) $input['id'], -strlen( $id_suffix ) ) === $id_suffix ) {
				$label = trim( (string) ( $input['label'] ?? '' ) );
				if ( '' !== $label && empty( $input['isHidden'] ) ) {
					return $label;
				}
			}
		}
		return $fallback;
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
