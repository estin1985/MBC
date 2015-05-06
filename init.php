<?php
/**
 * Plugin Name:  MBC
 * Plugin URI:   https://github.com/WebDevStudios/MBC
 * Description:  MBC will create custom sortable columns for categories, post_tags, posts, and custom post types.
 * Author:       WebDevStudios
 * Author URI:   http://webdevstudios.com
 * Contributors: WebDevStudios (@webdevstudios / webdevstudios.com)
 *               Justin Sternberg (@jtsternberg / dsgnwrks.pro)
 *               Jared Atchison (@jaredatch / jaredatchison.com)
 *               Bill Erickson (@billerickson / billerickson.net)
 *               Andrew Norcross (@norcross / andrewnorcross.com)
 *
 * Version:      1.0.0
 *
 * Text Domain:  mbc
 * Domain Path:  languages
 *
 *
 * Released under the GPL license
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * This is an add-on for WordPress
 * http://wordpress.org/
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * **********************************************************************
 */

if ( ! class_exists( 'mbc_bootstrap', false ) ) {

    /**
     * Check for newest version of CMB
     */
    class mbc_bootstrap
    {
        /**
         * Current version number
         * @var   string
         * @since 1.0.0
         */
        const VERSION = '1.0.0';

        public static $mbc_instance = null;

        public static function go()
        {
            if (null === self::$mbc_instance) {
                self::$mbc_instance = new self();
            }

            return self::$mbc_instance;
        }

        private function __construct()
        {
            add_action( 'plugins_loaded', array( $this, 'include_mbc' ), 994 );
            add_action( 'include_mbc', array( $this, 'mbc_init' ), 996 );
        }

        public function include_mbc()
        {
            if ( ! class_exists( 'MBC', false ) ) {

                if ( ! defined( 'MBC_VERSION' ) ) {
                    define( 'MBC_VERSION', self::VERSION );
                }

                if ( ! defined( 'MBC_DIR' ) ) {
                    define( 'MBC_DIR', trailingslashit( dirname( __FILE__ ) ) );
                }

                $this->l10ni18n();

                // Include helper functions
                require_once 'includes/helper-functions.php';
                require_once 'includes/MBC_Objects.php';
                require_once 'includes/MBC.php';

                do_action('include_mbc');
            }
        }

        /**
         * [mbc_init description]
         * @return [type] [description]
         */
        public function mbc_init()
        {
            do_action('mbc_init');

            /**
             * Get all created metaboxes, and instantiate MBC
             * on all columns.
             * @since  1.0.0
             */
            foreach ( MBC_Objects::get_all() as $mbc ) {
                $mbc->add_hooks();
            }
        }

        /**
         * Load CMB text domain
         * @since  2.0.0
         */
        public function l10ni18n()
        {
            $loaded = load_plugin_textdomain( 'mbc', false, '/languages/' );
            if (! $loaded) {
                $loaded = load_muplugin_textdomain( 'mbc', '/languages/' );
            }
            if (! $loaded) {
                $loaded = load_theme_textdomain( 'mbc', '/languages/' );
            }

            if (! $loaded) {
                $locale = apply_filters( 'plugin_locale', get_locale(), 'mbc' );
                $mofile = dirname( __FILE__ ) . '/languages/mbc-' . $locale . '.mo';
                load_textdomain( 'mbc', $mofile );
            }
        }

    }
    mbc_bootstrap::go();

} // class exists check
