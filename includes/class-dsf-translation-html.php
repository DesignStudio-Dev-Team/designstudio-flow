<?php
/**
 * Bounded rich-text node extraction and replacement.
 *
 * A WYSIWYG value is never handed to a translator as one opaque HTML blob.
 * This walker pulls out only the visible text nodes and a small allowlist of
 * accessible-name attributes, in document order, and can put translated values
 * back at exactly the same positions. Markup, attributes, URLs, classes, inline
 * styles, and embedded code therefore survive translation untouched.
 *
 * Parsing failures fail closed: zero nodes are reported, so the field is left
 * in its source language instead of being corrupted.
 *
 * @package DesignStudioFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSF_Translation_Html {

	const MAX_HTML_BYTES = 200000;
	const MAX_NODES      = 400;

	/**
	 * Attributes that carry an accessible name a visitor can perceive.
	 *
	 * @return string[]
	 */
	public static function translatable_attributes() {
		return array( 'alt', 'title', 'aria-label', 'placeholder' );
	}

	/**
	 * Elements whose contents are code or styling, never visitor copy.
	 *
	 * @return string[]
	 */
	public static function skipped_elements() {
		return array( 'script', 'style', 'template', 'code', 'pre', 'svg', 'iframe', 'noscript' );
	}

	/**
	 * Extract translatable nodes in document order.
	 *
	 * @param string $html Stored rich-text value.
	 * @return array<int,array<string,mixed>> Nodes keyed by ordinal position.
	 */
	public static function extract( $html ) {
		$nodes = array();
		self::walk(
			$html,
			static function ( $index, $value, $kind, $name ) use ( &$nodes ) {
				$nodes[] = array(
					'index' => $index,
					'value' => $value,
					'kind'  => $kind,
					'name'  => $name,
				);
				return null;
			}
		);
		return $nodes;
	}

	/**
	 * Replace nodes by ordinal position and return the rebuilt HTML.
	 *
	 * Positions that are absent from the replacement map keep their original
	 * value, so a partial translation is always safe.
	 *
	 * @param string $html         Stored rich-text value.
	 * @param array  $replacements Ordinal position to replacement string.
	 * @return string Original HTML when nothing could be replaced.
	 */
	public static function replace( $html, $replacements ) {
		$replacements = is_array( $replacements ) ? $replacements : array();
		if ( empty( $replacements ) ) {
			return (string) $html;
		}

		$applied = false;
		$result  = self::walk(
			$html,
			static function ( $index, $value, $kind, $name ) use ( $replacements, &$applied ) {
				unset( $kind, $name );
				if ( ! array_key_exists( $index, $replacements ) || ! is_scalar( $replacements[ $index ] ) ) {
					return null;
				}
				$replacement = (string) $replacements[ $index ];
				if ( '' === trim( $replacement ) || $replacement === $value ) {
					return null;
				}
				$applied = true;
				return $replacement;
			},
			true
		);

		return $applied && is_string( $result ) ? $result : (string) $html;
	}

	/**
	 * Walk a rich-text value, optionally rewriting the nodes it visits.
	 *
	 * @param string   $html     Stored rich-text value.
	 * @param callable $visitor  Receives (index, value, kind, name); returns a
	 *                           replacement string or null to keep the value.
	 * @param bool     $rebuild  Whether to serialize and return the document.
	 * @return string|null Rebuilt HTML when rebuilding, otherwise null.
	 */
	private static function walk( $html, $visitor, $rebuild = false ) {
		$html = is_scalar( $html ) ? (string) $html : '';
		if ( '' === trim( $html ) || self::MAX_HTML_BYTES < strlen( $html ) || ! class_exists( 'DOMDocument' ) ) {
			return null;
		}

		$document = new DOMDocument( '1.0', 'UTF-8' );
		$previous = libxml_use_internal_errors( true );
		$loaded   = $document->loadHTML(
			'<?xml encoding="utf-8" ?><dsf-root>' . $html . '</dsf-root>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return null;
		}

		$roots = $document->getElementsByTagName( 'dsf-root' );
		$root  = $roots->length ? $roots->item( 0 ) : null;
		if ( ! $root ) {
			return null;
		}

		$index   = 0;
		$skipped = self::skipped_elements();
		$allowed = self::translatable_attributes();
		$queue   = array( $root );

		while ( ! empty( $queue ) && $index < self::MAX_NODES ) {
			$node = array_shift( $queue );

			if ( XML_ELEMENT_NODE === $node->nodeType ) {
				foreach ( $allowed as $attribute ) {
					if ( $index >= self::MAX_NODES ) {
						break;
					}
					if ( ! $node->hasAttribute( $attribute ) ) {
						continue;
					}
					$value = (string) $node->getAttribute( $attribute );
					if ( ! self::is_translatable_value( $value ) ) {
						continue;
					}
					$replacement = call_user_func( $visitor, $index, $value, 'attribute', $attribute );
					if ( is_string( $replacement ) ) {
						$node->setAttribute( $attribute, $replacement );
					}
					++$index;
				}
			}

			$children = array();
			foreach ( $node->childNodes as $child ) {
				$children[] = $child;
			}

			foreach ( $children as $child ) {
				if ( $index >= self::MAX_NODES ) {
					break;
				}
				if ( XML_TEXT_NODE === $child->nodeType ) {
					$value = (string) $child->nodeValue;
					if ( ! self::is_translatable_value( $value ) ) {
						continue;
					}
					$replacement = call_user_func( $visitor, $index, trim( $value ), 'text', '' );
					if ( is_string( $replacement ) ) {
						$child->nodeValue = self::preserve_spacing( $value, $replacement );
					}
					++$index;
					continue;
				}
				if ( XML_ELEMENT_NODE === $child->nodeType && ! in_array( strtolower( $child->nodeName ), $skipped, true ) ) {
					$queue[] = $child;
				}
			}
		}

		if ( ! $rebuild ) {
			return null;
		}

		$rebuilt = '';
		foreach ( $root->childNodes as $child ) {
			$rebuilt .= $document->saveHTML( $child );
		}
		return $rebuilt;
	}

	/**
	 * Whether a value contains language a translator can work with.
	 *
	 * @param string $value Raw node value.
	 * @return bool
	 */
	public static function is_translatable_value( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value || 5000 < strlen( $value ) ) {
			return false;
		}
		// Numbers, punctuation, entities, and separators carry no language.
		return (bool) preg_match( '/\p{L}/u', $value );
	}

	/**
	 * Reapply the original node's surrounding whitespace to a replacement.
	 *
	 * @param string $original    Original node value.
	 * @param string $replacement Translated value.
	 * @return string
	 */
	private static function preserve_spacing( $original, $replacement ) {
		$leading  = '';
		$trailing = '';
		if ( preg_match( '/^(\s+)/u', $original, $matches ) ) {
			$leading = $matches[1];
		}
		if ( preg_match( '/(\s+)$/u', $original, $matches ) ) {
			$trailing = $matches[1];
		}
		return $leading . trim( $replacement ) . $trailing;
	}
}
