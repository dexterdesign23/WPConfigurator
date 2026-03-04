<?php

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('PBC_Schema_Store')) {
    class PBC_Schema_Store
    {
        public const OPTION_KEY = 'pbc_schema';

        /**
         * Get schema from options.
         */
        public static function get_schema(): array
        {
            $schema = get_option(self::OPTION_KEY, array());

            return is_array($schema) ? $schema : array();
        }

        /**
         * Persist schema to options.
         */
        public static function save_schema(array $schema): bool
        {
            return (bool) update_option(self::OPTION_KEY, $schema, false);
        }

        /**
         * Seed default schema if empty.
         */
        public static function seed_default_schema(): void
        {
            $existing = get_option(self::OPTION_KEY, null);

            if (null !== $existing) {
                return;
            }

            add_option(
                self::OPTION_KEY,
                array(
                    'version' => 1,
                    'steps'   => array(),
                    'rules'   => array(),
                ),
                '',
                false
            );
        }
    }
}
