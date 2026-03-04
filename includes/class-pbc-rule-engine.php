<?php

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('PBC_Rule_Engine')) {
    class PBC_Rule_Engine
    {
        /**
         * Evaluate rules against selected options.
         */
        public static function evaluate(array $schema, array $selection): array
        {
            if (! isset($schema['rules']) || ! is_array($schema['rules'])) {
                return array('valid' => true, 'messages' => array());
            }

            $messages = array();

            foreach ($schema['rules'] as $rule) {
                if (! is_array($rule)) {
                    continue;
                }

                $field = isset($rule['field']) ? (string) $rule['field'] : '';
                $value = $rule['value'] ?? null;

                if ($field && array_key_exists($field, $selection) && $selection[$field] !== $value) {
                    $messages[] = sprintf(
                        /* translators: 1: field name, 2: required value */
                        __('Field %1$s must equal %2$s.', 'printer-builder-configurator'),
                        $field,
                        (string) $value
                    );
                }
            }

            return array(
                'valid'    => empty($messages),
                'messages' => $messages,
            );
        }
    }
}
