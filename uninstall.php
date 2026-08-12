<?php
/**
 * Uninstall handler.
 *
 * Deleting the plugin does not delete anyone's work. Pages, layouts, popups,
 * forms, entries, saved blocks, templates and every translation stay exactly
 * where they are, so reinstalling restores the site as it was.
 *
 * Destructive cleanup is opt-in and explicit. An administrator asks for it by
 * ticking the option in Settings, or by defining the constant in wp-config.php
 * before deleting the plugin:
 *
 *     define( 'DSF_REMOVE_ALL_DATA', true );
 *
 * Even then only DesignStudio Flow's own configuration and bookkeeping tables
 * are removed; content is never touched.
 *
 * @package DesignStudioFlow
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Whether the administrator explicitly asked for a destructive cleanup.
 *
 * @return bool
 */
function dsf_uninstall_is_destructive() {
	if ( defined( 'DSF_REMOVE_ALL_DATA' ) && DSF_REMOVE_ALL_DATA ) {
		return true;
	}
	return (bool) get_option( 'dsf_remove_all_data_on_uninstall', false );
}

/**
 * Options DesignStudio Flow owns.
 *
 * Content lives in posts and post meta and is deliberately absent from this
 * list.
 *
 * @return string[]
 */
function dsf_uninstall_option_names() {
	return array(
		'dsf_multilingual_settings',
		'dsf_multilingual_migration',
		'dsf_multilingual_prefix_history',
		'dsf_multilingual_network_activation_epoch',
		'dsf_multilingual_site_activation_epoch',
		'dsf_notification_bar',
		'dsf_notification_bar_translations',
		'dsf_translation_provider',
		'dsf_translation_relationships_db_version',
		'dsf_translation_routes_db_version',
		'dsf_translation_workflow_db_version',
		'dsf_translation_dependencies_db_version',
		'dsf_history_db_version',
		'dsf_default_colors',
		'dsf_typography',
		'dsf_seo_defaults',
		'dsf_default_header_id',
		'dsf_default_footer_id',
		'dsf_global_header_footer',
		'dsf_products_enabled',
		'dsf_redirects',
		'dsf_needs_rewrite_flush',
		'dsf_remove_all_data_on_uninstall',
	);
}

/**
 * Bookkeeping tables the plugin created.
 *
 * These hold relationships, routes, review facts, dependency edges and version
 * history — never the content those records point at.
 *
 * @return string[]
 */
function dsf_uninstall_table_suffixes() {
	return array(
		'dsf_translation_relationships',
		'dsf_translation_groups',
		'dsf_translation_routes',
		'dsf_translation_workflow',
		'dsf_translation_dependencies',
		'dsf_history',
	);
}

/**
 * Remove one site's plugin configuration and bookkeeping tables.
 */
function dsf_uninstall_clean_site() {
	global $wpdb;

	foreach ( dsf_uninstall_option_names() as $option ) {
		delete_option( $option );
	}

	foreach ( dsf_uninstall_table_suffixes() as $suffix ) {
		$table = $wpdb->prefix . $suffix;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Table names are built from the trusted wpdb prefix and a fixed suffix list; uninstall is a schema operation by definition.
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
	}

	wp_clear_scheduled_hook( 'dsf_run_multilingual_migration' );
}

if ( ! dsf_uninstall_is_destructive() ) {
	// The default: leave everything in place.
	return;
}

if ( is_multisite() ) {
	$dsf_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( (array) $dsf_site_ids as $dsf_site_id ) {
		switch_to_blog( (int) $dsf_site_id );
		dsf_uninstall_clean_site();
		restore_current_blog();
	}
	delete_site_option( 'dsf_multilingual_network_activation_epoch' );
} else {
	dsf_uninstall_clean_site();
}
