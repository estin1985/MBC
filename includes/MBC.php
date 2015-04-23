<?php
/**
 * Add Custom Columns for Meta Boxes
 */
class MBC
{
    /**
     * Current columns's ID
     * @var   string
     * @since 2.0.0
     */
    protected $mbc_id = '';

    /**
     * Type of object to create columns for. (e.g., posts, comments, or category)
     * @var   string
     * @since 1.0.0
     */
    protected $object_type;

    /**
     * Columns
     * @var   array
     * @since 1.0.0
     */
    protected $columns = array();

    /**
     * Column Object Defaults
     * @var   array
     * @since 1.0.1
     */
    protected $mbc_defaults = array(
        'id'                 => '',         // Column ID (Must Be Unique)
        'title'              => '',         // Column Title
        'sortable'           => false,      // Make Column Sortable
        'show_on_cb'         => '',         // Conditional Show On Callback. Displays on True
        'populate'           => array(
            'meta_key'          => '',              // Meta Key Used To Populate Block
            'meta_object_type'  => '',              // Taxonomy / Object Type the Meta Key Should Be Pulled From
            'callback'          => '',              // Callback Function for Populating Block
        ),
        'row_action'          => array(),   // On Hover Row Action
    );

    public function __construct($mbc_object)
    {
        if (empty( $mbc_object['id'] )) {
            wp_die(__('Object ID is Required', 'mbc'));
        }

        if (empty( $mbc_object['object_type'] )) {
            wp_die(__('Object Type is Required', 'mbc'));
        }

        if (in_array($mbc_object['id'], array('mbc_unset', 'mbc_rename'))) {
            $this->columns = $mbc_object['columns'];
        }

        $this->validate_columns($mbc_object['columns']);
        $this->object_type = $mbc_object['object_type'];
        $this->mbc_id      = $mbc_object['id'];

        MBC_Objects::add($this);
    }

    /**
     * Set Columns into Class Variable After Validation
     * @since 1.0.0
     * @param  array $columns Array of columns
     * @return array          Validated array of columns
     */
    public function validate_columns($columns)
    {
        if (empty($this->columns)) {
            $this->columns = array_map(array( $this, 'validate_column' ), $columns);
        }
        return;
    }

    /**
     * Validate Required arguments are Present
     * @param  array $column Array of column arguments
     * @return array If it passes the checks, it returns the same array for processing.
     */
    public function validate_column($column_args)
    {
        // Merge With Defaults
        $column_args = wp_parse_args($column_args, $this->mbc_defaults);

        // Die If No ID
        if (!$column_args['id']) {
            wp_die(__('Column required to have an ID parameter', 'mbc'));
        }

        // Die If No Title
        if (!$column_args['title']) {
            wp_die(__('Column required to have a Title parameter', 'mbc'));
        }

        // Die Show On Callback Not Exists
        if (!empty($column_args['show_on_cb']) && !is_callable($column_args['show_on_cb'])) {
            wp_die(__('Column Callback Not Callable.', 'mbc'));
        }

        // Die If No Populate Attribute
        if (empty($column_args['populate']['meta_key']) && empty($column_args['populate']['callback'])) {
            wp_die(__('Column required to have either "meta_key" or "callback" specified.', 'mbc'));
        }

        // Die If Both Meta and Callback
        if (!empty($column_args['populate']['meta_key']) && !empty($column_args['populate']['callback'])) {
            wp_die(__('Column required to have either "meta_key" or "callback" specified, Not Both.', 'mbc'));
        }

        // If Populate Callback Exists - Check if Callable
        if (!empty($column_args['populate']['callback']) && !is_callable($column_args['populate']['callback'])) {
            wp_die(__('Populate Callback Not Callable.', 'mbc'));
        }

        // If Meta Key Exists - Verify and Set Meta Tax
        if (!empty($column_args['populate']['meta_key'])) {
            $column_args['populate']['meta_object_type'] = $this->object_type($column_args['populate']['meta_object_type']);
        }

        // Filter Populate Attributes
        if (is_array($column_args['populate'])) {
            $column_args['populate'] = array_filter($column_args['populate']);
        }

        return $column_args;
    }

    /**
     * Register Filter / Action Hooks
     * @since 1.0.0
     */
    public function add_hooks()
    {
        switch ($this->object_type) {

            /**
             * Comments Column Filters
             */
            case 'comment':
                add_filter("manage_edit-{$this->object_type}s_columns", array( $this, "manage_edit_taxonomy_columns"          ), 10, 1);
                add_filter("manage_{$this->object_type}s_custom_column", array( $this, "manage_taxonomy_custom_column"         ), 10, 2); // Original Method Name
                add_filter("manage_edit-{$this->object_type}s_sortable_columns", array( $this, "manage_edit_taxonomy_sortable_columns" ));
                break;

            /**
             * Category Column Filters
             */
            case 'category':
            case 'post_tag':
                add_filter("manage_edit-{$this->object_type}_columns", array( $this, "manage_edit_taxonomy_columns"          ), 10, 1);
                add_filter("manage_{$this->object_type}_custom_column", array( $this, "manage_tag_category_custom_column"     ), 10, 3); // Original Method Name
                add_filter("manage_edit-{$this->object_type}_sortable_columns", array( $this, "manage_edit_taxonomy_sortable_columns" ));
                break;

            /**
             * Regular Post Column Filters
             */
            case 'post':
                add_filter("manage_{$this->object_type}_posts_columns", array( $this, "manage_edit_taxonomy_columns"          ), 10, 1);
                add_filter("manage_{$this->object_type}_posts_custom_column", array( $this, "manage_taxonomy_custom_column"         ), 10, 2); // Original Method Name
                add_filter("manage_edit-{$this->object_type}_sortable_columns", array( $this, "manage_edit_taxonomy_sortable_columns" ));
                break;

            /**
             * Custom Post Type Column Filters
             */
            default:
                add_filter("manage_edit-{$this->object_type}_columns", array( $this, "manage_edit_taxonomy_columns"          ), 10, 1);
                add_filter("manage_{$this->object_type}_posts_custom_column", array( $this, "manage_taxonomy_custom_column"         ), 10, 2); // Original Method Name
                add_filter("manage_edit-{$this->object_type}_sortable_columns", array( $this, "manage_edit_taxonomy_sortable_columns" ));
                break;
        }

        add_filter("pre_get_posts", array( $this, "manage_taxonomy_sortable_columns_query" ), 10, 1);
    }

    /**
     * Add Column Titles to Table
     * @since  1.0.0
     * @param  array $columns Array of table columns
     * @return array Modified Array of columns
     */
    public function manage_edit_taxonomy_columns($columns)
    {
        // If this is a modification request return modified columns.
        if ($modify = $this->maybe_modify_columns($columns)) {
            return $modify;
        }

        $titles_ids = wp_list_pluck($this->columns, 'title', 'id');

        return array_slice($columns, 0, 2, true) + $titles_ids + array_slice($columns, 2, count($columns) - 1, true);
    }

    /**
     * Set Column ID's for Sortable Columns
     * @since  1.0.0
     * @param  array $columns Array of table columns
     * @return array Modified Array of columns
     */
    public function manage_edit_taxonomy_sortable_columns($columns)
    {
        foreach ($this->columns as $mbc_column) {
            if (isset($mbc_column['sortable']) && ( $mbc_column['sortable'] === true )) {
                $columns[ $mbc_column['id'] ] = $mbc_column['id'];
            }
        }

        return $columns;
    }

    /**
     * Set Sortable Columns to Sort By Meta Value
     * @param  object $query WP_Query Object
     * @return Object Modified WP_Query Object
     */
    public function manage_taxonomy_sortable_columns_query($query)
    {
        $do_not_pass_go = (
            ! is_admin()
            || ! $query->is_main_query()
            || ! $orderby = $query->get('orderby')
        );

        if ($do_not_pass_go) {
            // do not collect $200
            return;
        }

        /**
         * TODO : Find a way to determine if the meta value is an int or string
         */

        foreach ($this->columns as $mbc_column) {
            if ($mbc_column['id'] === $orderby) {
                $query->set('meta_key', $mbc_column['populate']['meta_key']);
                $query->set('orderby', 'meta_value_num meta_value');
            }
        }
    }

    /**
     * Populate Custom Columns with Data
     * @since  1.0.0
     * @param string $column    Column name
     * @param int    $object_id object_id
     */
    public function manage_taxonomy_custom_column($column, $object_id)
    {
        foreach ($this->columns as $mbc_column) {
            // If Column Matches - Go!
            if ($mbc_column['id'] === $column) {
                // If column is requesting to be conditionally shown
                if (isset($mbc_column['show_on_cb']) && is_callable($mbc_column['show_on_cb']) && ! call_user_func($mbc_column['show_on_cb'], $object_id)) {
                    return;
                }

                // If column block is requesting to be conditionally shown
                if (isset( $mbc_column['populate']['callback'] )) {
                    $content = $this->populate_with_callback($object_id, $mbc_column);
                } else {
                    $content = $this->populate_with_meta($object_id, $mbc_column);
                }

                if (!empty( $content )) {
                    echo $content . $this->maybe_action_row($object_id, $mbc_column);
                }
            }
        }
    }

    /**
     * Wrapper for Post Tags and Categories Column Data
     * @since  1.0.0
     * @param string $column    Column name
     * @param int    $object_id object_id
     */
    public function manage_tag_category_custom_column($depreciated, $column, $term_id)
    {
        $this->manage_taxonomy_custom_column($column, $term_id);
    }

    /**
     * Populate column row with a custom callback
     * @param  integer $object_id   The object id
     * @param  array   $column_args The column configuration array
     * @return string  String representing the callbacks data.
     */
    public function populate_with_callback($object_id, $column_args)
    {
        if (is_callable($column_args['populate']['callback'])) {
            ob_start();
            echo @call_user_func($column_args['populate']['callback'], $object_id, $column_args);
            $rowdata = ob_get_contents();
            ob_end_clean();

            return apply_filters("before_output_{$column_args['id']}_filter", $rowdata);
        }
    }

    /**
     * Populate column row with a specified meta_value
     * @param  integer $object_id   The object id
     * @param  array   $column_args The column configuration array
     * @return string  Meta value data as string.
     */
    public function populate_with_meta($object_id, $column_args)
    {
        if ($this->object_type != $column_args['populate']['meta_object_type']) {
            $object_id = $this->object_id($object_id, $column_args['populate']['meta_object_type']);
        }

        $meta_key = $column_args['populate']['meta_key'];
        $metadata = get_metadata($column_args['populate']['meta_object_type'], $object_id, $meta_key, true);

        return apply_filters("before_output_{$column_args['id']}_filter", $metadata);
    }

    /**
     * Include an action row within the specified column row
     * @param  integer $object_id   The object id
     * @param  array   $column_args The column configuration array
     * @return string  Action row as string.
     */
    public function maybe_action_row($object_id, $column_args)
    {
        if (!isset( $column_args['row_action'] ) || empty( $column_args['row_action'] )) {
            return '';
        }

        foreach ($column_args['row_action']['args'] as $key => $value) {
            if ($value === '#object_id#') {
                $column_args['row_action']['args'][$key] = $object_id;
            }
        }

        $attrs = wp_parse_args($column_args['row_action'], array(
            'base'  => '',
            'args'  => array(),
            'rel'   => 'permalink',
            'title' => '',
        ));

        $query_url = build_query($attrs['args']);
        $action_url = "<div class=\"row-actions\"><span class=\"view\"><a href=\"{$attrs['base']}?{$query_url}\" title=\"{$attrs['title']}\" rel=\"{$attrs['rel']}\">{$attrs['title']}</a></span></div>";

        return $action_url;
    }

    /**
     * Set Object ID when pulling meta values from alternative object types.
     * @param  integer $object_id        The object id
     * @param  array   $meta_object_type The column configuration array
     * @return integer Object ID for the referenced taxonomy / object type.
     */
    public function object_id($object_id, $meta_object_type)
    {
        switch ($this->object_type) {
            case 'comment':
                if ($meta_object_type === 'post') {
                    return get_comment($object_id)->comment_post_ID;
                } else {
                    return $object_id;
                }
                break;

            case 'post':
            case 'category':
                return $object_id;
                break;

            default:
                return $object_id;
                break;
        }
    }

    /**
     * Check if Object Type Exists
     * @since  1.0.0
     * @param  integer $object_type Taxonomy name
     * @return integer $object_id Taxonomy name
     */
    public function object_type($object_type = '')
    {
        $cpt = get_post_types(array('public' => true, '_builtin' => false));

        if (in_array($object_type, array( 'post', 'comment', 'category', 'post_tag' ), true)) {
            return $object_type;
        } elseif (in_array($object_type, $cpt, true)) {
            return $object_type;
        } else {
            return 'post';
        }
    }

    /**
     * Unset or Rename an Admin Column
     * @param  array $columns Array of columns
     * @return array          Modified Columns Array
     */
    public function maybe_modify_columns($columns)
    {
        if (in_array($this->mbc_id, array('mbc_rename', 'mbc_unset'))) {
            switch ($this->mbc_id) {
                case 'mbc_unset':
                    if (isset($columns[$this->columns[0]['id']])) {
                        unset($columns[$this->columns[0]['id']]);
                    }
                    return $columns;
                    break;

                case 'mbc_rename':
                    if (isset($columns[$this->columns[0]['id']])) {
                        $columns[$this->columns[0]['id']] = sanitize_title($this->columns[0]['title']);
                    }
                    return $columns;
                    break;

                default:
                    return $columns;
                    break;
            }
        }
        return;
    }

    /**
     * Magic getter for our object.
     * @param  string    $field
     * @throws Exception Throws an exception if the column is invalid.
     * @return mixed
     */
    public function __get($field)
    {
        switch ($field) {
            case 'mbc_id':
            case 'columns':
                return $this->{$field};
            default:
                throw new Exception('Invalid ' . __CLASS__ . ' property: ' . $field);
        }
    }
}
