<?php
/**
 * Add New MBC Column
 * @since  2.0.0
 * @return MBC object
 */
function new_mbc_column($column_obj)
{
    // See if we already have an instance of this metabox
    $mbc = MBC_Objects::get($column_obj['id']);
    // If not, we'll initate a new metabox
    $mbc = $mbc ? $mbc : new MBC($column_obj);

    return $mbc;
}

/**
 * Helper Function Used to Alter Existing Columns
 * @param  array $args Array of arguments
 * @return null
 */
function mbc_alter_column($args)
{
    $defaults = array(
        'id'              => '',
        'object_type'     => 'post',
        'column_id'       => '',
        'column_title'    => ' - ',
    );

    $args = array_filter( wp_parse_args($args, $defaults) );


    if (!isset($args['object_type'], $args['id'], $args['column_id'])) {
        wp_die(__('Cannot remove column from undefined object_type or column_id. ', 'mbc'));
    }

    new_mbc_column(array(
        'id'          => $args['id'],
        'object_type' => $args['object_type'],
        'columns'     => array(
            array(
				'id'        => $args['column_id'],
				'title'     => $args['column_title'],
				'populate'    => array(
					'callback' => 'array_filter'
				),
            ),
        ),
    ));
}

/**
 * Helper Function to Unset Columns
 * @since 1.0.0
 * @uses mbc_alter_column
 * @param  array $args Array of Required Arguments.
 * @return null
 */
function mbc_unset_column($args)
{
    if (!isset($args['object_type'], $args['column_id'])) {
        wp_die(__('Cannot remove column from undefined object_type or column_id. ', 'mbc'));
    }

    $args = wp_parse_args($args, array(
		'id'          => 'mbc_unset',
		'object_type' => '',
		'column_id'   => ''
    ));

    mbc_alter_column($args);
}

/**
 * Helper Function to Rename Columns
 * @since 1.0.0
 * @uses mbc_alter_column
 * @param  array $args Array of Required Arguments.
 * @return null
 */
function mbc_rename_column($args)
{
    if (!isset($args['object_type'], $args['column_id'], $args['column_title'])) {
        wp_die('something went terribily wrong!');
    }

    $args = wp_parse_args($args, array(
		'id'           => 'mbc_rename',
		'object_type'  => '',
		'column_id'    => '',
		'column_title' => ''
    ));

    mbc_alter_column($args);
}

