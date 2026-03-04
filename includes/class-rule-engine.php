<?php
/**
 * Rule engine for schema decisions.
 *
 * @package WPConfigurator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPConfigurator_Rule_Engine {
	/**
	 * Evaluate schema rules against selections.
	 *
	 * @param array $schema Schema payload.
	 * @param array $selections User selections map decision_id => option_id.
	 * @return array
	 */
	public static function evaluateRules( $schema, $selections ) {
		$result = array(
			'requiredMissing'           => array(),
			'conflicts'                 => array(),
			'warnings'                  => array(),
			'disabledOptionsByDecision' => array(),
		);

		if ( ! is_array( $schema ) || empty( $schema['decisions'] ) || ! is_array( $schema['decisions'] ) ) {
			$result['warnings'][] = array(
				'code'    => 'invalid_schema',
				'message' => __( 'Schema is missing decisions.', 'wpconfigurator' ),
			);
			return $result;
		}

		$selections = is_array( $selections ) ? $selections : array();
		$selected   = self::normalize_selections( $schema, $selections );

		foreach ( $schema['decisions'] as $decision ) {
			$decision_id = isset( $decision['id'] ) ? (string) $decision['id'] : '';
			if ( '' === $decision_id ) {
				continue;
			}

			if ( ! empty( $decision['required'] ) && empty( $selected[ $decision_id ] ) ) {
				$result['requiredMissing'][] = $decision_id;
			}

			$result['disabledOptionsByDecision'][ $decision_id ] = self::disabled_options_for_decision( $decision, $selected );
		}

		$result['conflicts'] = self::collect_conflicts( $schema, $selected );
		$result['warnings']  = array_merge( $result['warnings'], self::collect_warnings( $schema, $selected ) );

		ksort( $result['disabledOptionsByDecision'] );
		sort( $result['requiredMissing'] );

		return $result;
	}

	/**
	 * Normalize selection map to known option IDs only.
	 *
	 * @param array $schema Schema.
	 * @param array $selections Selections.
	 * @return array
	 */
	protected static function normalize_selections( $schema, $selections ) {
		$allowed = array();

		foreach ( $schema['decisions'] as $decision ) {
			if ( empty( $decision['id'] ) || empty( $decision['options'] ) || ! is_array( $decision['options'] ) ) {
				continue;
			}
			$decision_id = (string) $decision['id'];
			$allowed[ $decision_id ] = wp_list_pluck( $decision['options'], 'id' );
		}

		$normalized = array();
		foreach ( $selections as $decision_id => $option_id ) {
			$decision_id = (string) $decision_id;
			$option_id   = (string) $option_id;
			if ( isset( $allowed[ $decision_id ] ) && in_array( $option_id, $allowed[ $decision_id ], true ) ) {
				$normalized[ $decision_id ] = $option_id;
			}
		}

		ksort( $normalized );
		return $normalized;
	}

	/**
	 * Collect disabled options per decision with reasons.
	 *
	 * @param array $decision Decision node.
	 * @param array $selected Selected options.
	 * @return array
	 */
	protected static function disabled_options_for_decision( $decision, $selected ) {
		$disabled = array();
		$options  = isset( $decision['options'] ) && is_array( $decision['options'] ) ? $decision['options'] : array();

		foreach ( $options as $option ) {
			$option_id = isset( $option['id'] ) ? (string) $option['id'] : '';
			if ( '' === $option_id ) {
				continue;
			}

			$reasons = array();

			if ( ! empty( $option['requires'] ) && is_array( $option['requires'] ) ) {
				foreach ( $option['requires'] as $required_decision => $required_option ) {
					if ( ! isset( $selected[ $required_decision ] ) || (string) $selected[ $required_decision ] !== (string) $required_option ) {
						$reasons[] = array(
							'type'          => 'requires',
							'decisionId'    => (string) $required_decision,
							'requiredOption'=> (string) $required_option,
						);
					}
				}
			}

			if ( ! empty( $option['excludes'] ) && is_array( $option['excludes'] ) ) {
				foreach ( $option['excludes'] as $excluded_decision => $excluded_option ) {
					if ( isset( $selected[ $excluded_decision ] ) && (string) $selected[ $excluded_decision ] === (string) $excluded_option ) {
						$reasons[] = array(
							'type'            => 'excludes',
							'decisionId'      => (string) $excluded_decision,
							'excludedOption'  => (string) $excluded_option,
						);
					}
				}
			}

			if ( ! empty( $reasons ) ) {
				$disabled[ $option_id ] = $reasons;
			}
		}

		ksort( $disabled );
		return $disabled;
	}

	/**
	 * Collect explicit conflicts.
	 *
	 * @param array $schema Schema.
	 * @param array $selected Selected map.
	 * @return array
	 */
	protected static function collect_conflicts( $schema, $selected ) {
		$conflicts = array();

		foreach ( $schema['decisions'] as $decision ) {
			$decision_id = isset( $decision['id'] ) ? (string) $decision['id'] : '';
			if ( '' === $decision_id || empty( $selected[ $decision_id ] ) || empty( $decision['options'] ) ) {
				continue;
			}

			$current_option = self::find_option_by_id( $decision['options'], $selected[ $decision_id ] );
			if ( ! $current_option || empty( $current_option['excludes'] ) || ! is_array( $current_option['excludes'] ) ) {
				continue;
			}

			foreach ( $current_option['excludes'] as $other_decision => $other_option ) {
				if ( isset( $selected[ $other_decision ] ) && (string) $selected[ $other_decision ] === (string) $other_option ) {
					$conflicts[] = array(
						'decisionId'      => $decision_id,
						'optionId'        => (string) $selected[ $decision_id ],
						'conflictWith'    => array(
							'decisionId' => (string) $other_decision,
							'optionId'   => (string) $other_option,
						),
					);
				}
			}
		}

		usort(
			$conflicts,
			static function ( $a, $b ) {
				return strcmp( wp_json_encode( $a ), wp_json_encode( $b ) );
			}
		);

		return $conflicts;
	}

	/**
	 * Collect warnings for unknown/partial data.
	 *
	 * @param array $schema Schema.
	 * @param array $selected Selected options.
	 * @return array
	 */
	protected static function collect_warnings( $schema, $selected ) {
		$warnings = array();

		foreach ( $schema['decisions'] as $decision ) {
			if ( empty( $decision['id'] ) || empty( $selected[ $decision['id'] ] ) ) {
				continue;
			}

			$option = self::find_option_by_id( $decision['options'], $selected[ $decision['id'] ] );
			if ( ! $option ) {
				continue;
			}

			if ( ! empty( $option['warnings'] ) && is_array( $option['warnings'] ) ) {
				foreach ( $option['warnings'] as $warning ) {
					$warnings[] = array(
						'decisionId' => (string) $decision['id'],
						'optionId'   => (string) $option['id'],
						'message'    => sanitize_text_field( (string) $warning ),
					);
				}
			}
		}

		usort(
			$warnings,
			static function ( $a, $b ) {
				return strcmp( wp_json_encode( $a ), wp_json_encode( $b ) );
			}
		);

		return $warnings;
	}

	/**
	 * Find option by ID.
	 *
	 * @param array  $options Option list.
	 * @param string $option_id Option ID.
	 * @return array|null
	 */
	protected static function find_option_by_id( $options, $option_id ) {
		if ( ! is_array( $options ) ) {
			return null;
		}

		foreach ( $options as $option ) {
			if ( isset( $option['id'] ) && (string) $option['id'] === (string) $option_id ) {
				return $option;
			}
		}

		return null;
	}
}
