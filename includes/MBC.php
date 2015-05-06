<?php
/**
 * Add Custom Columns for Meta Boxes
 */
class MBC
{

    /**
     * Only allow JS registration once
     * @var   bool
     * @since 2.0.0
     */
    protected static $registration_done = false;

    /**
     * Array of all hooks done (to be run once)
     * @var   array
     * @since 2.0.0
     */
    protected static $hooks_completed = array();

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
     * Does This Column Use QuickEdits?
     * @var bool
     */
    protected $has_quickedit;

    /**
     * Stored Column Values
     * @var array
     * @since  1.1.0
     */
    protected $values;

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
        'quick_edit'          => false
    );

    /**
     * Get Started
     * @since  1.0.0
     */
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

        $this->mbc_id      = $mbc_object['id'];
        $this->object_type = $mbc_object['object_type'];
        $this->validate_columns($mbc_object['columns']);

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
     * Register Filter / Action Hooks
     * @since 1.0.0
     */
    public function add_hooks()
    {
        /**
         * If QuickEdit Field is Requested:
         *     - Include JS
         *     - Add Init Hooks
         */
        if ($this->has_quickedit) {

            $this->once('admin_enqueue_scripts', array( __CLASS__, 'register_scripts' ), 8);
        }

        switch ($this->object_type) {

            /**
             * Comments Column Filters
             */
            case 'comment':
                $this->once("manage_edit-{$this->object_type}s_columns", array( $this, 'add_column_titles' ), 10);
                $this->once("manage_{$this->object_type}s_custom_column", array( $this, 'populate_column_data' ), 10, 2);
                $this->once("manage_edit-{$this->object_type}s_sortable_columns", array( $this, 'add_column_sort_ids' ), 10, 1);

                if ($this->has_quickedit) {

                    $this->once("wp_comment_reply", array( $this, "add_comment_quick_edit_fields" ), 10, 2);
                    $this->once("edit_comment", array( $this, "save_comment_quick_edit_data"), 10, 1 );

                }
                break;

            /**
             * Category Column Filters
             */
            case 'category':
            case 'post_tag':
                $this->once("manage_edit-{$this->object_type}_columns", array( $this, 'add_column_titles' ), 10);
                $this->once("manage_{$this->object_type}_custom_column", array( $this, 'populate_tag_column_data' ), 10, 3);
                $this->once("manage_edit-{$this->object_type}s_sortable_columns", array( $this, 'add_column_sort_ids' ), 10, 1);

                if ($this->has_quickedit) {

                    $this->once("quick_edit_custom_box", array( $this, "add_quick_edit_fields" ), 15, 2);
                    $this->once("bulk_edit_custom_box", array( $this, "add_bulk_edit_fields" ), 15, 2);
                    $this->once("edited_{$this->object_type}", array( $this, "save_taxonomy_quick_edit_data"), 10, 2 );

                }
                break;

            /**
             * Post && Custom Post Type Column Filters
             */
            default:
                $this->once("manage_edit-{$this->object_type}_columns", array( $this, 'add_column_titles' ), 10);
                $this->once("manage_{$this->object_type}_posts_custom_column", array( $this, 'populate_column_data' ), 10, 2);
                $this->once("manage_edit-{$this->object_type}_sortable_columns", array( $this, 'add_column_sort_ids' ), 10, 1);

                if ($this->has_quickedit) {
                    $this->once("quick_edit_custom_box", array( $this, 'add_quick_edit_fields' ), 15, 2);
                    $this->once("bulk_edit_custom_box", array( $this, 'add_bulk_edit_fields' ), 15, 2);
                    $this->once("save_post", array( $this, "save_post_quick_edit_data" ), 10, 2);
                }
                break;
        }

        $this->once("pre_get_posts", array( $this, 'manage_taxonomy_sortable_columns_query' ), 10, 1);
    }

    /**
     * Wrapper For Buffering WP_Comment_Reply().
     * @param string $string Null
     * @param array $args   Array of arguments for WP_Comment_Reply.
     */
    public function add_comment_quick_edit_fields($string, $args)
    {
        ob_start();
        $this->get_wp_comment_reply($args);
        $wp_comment_reply = ob_get_contents();
        ob_end_clean();

        return $wp_comment_reply;
    }

    /**
     * Quick Edit Update for Comments
     * @param  integer $comment_id Modified Comment ID
     * @since  1.1.0
     *
     * @todo Sanitize Before Saving - Add Sanitization Function
     */
    public function save_comment_quick_edit_data($comment_id)
    {
        foreach ($this->columns as $column) {
            if ( isset($column['populate']['meta_key'])
                && array_key_exists($column['populate']['meta_key'], $_POST )
                && isset( $column['quick_edit'] )
                && !empty( $column['quick_edit'] )
                )
            {
                $object = $column['populate']['meta_object_type'];
                $meta_key = $column['populate']['meta_key'];
                $meta_value = $_POST[ $column['populate']['meta_key'] ];

                update_metadata($object, $comment_id, $meta_key, $meta_value);
            }
        }
    }

    /**
     * Quick Edit Comment Inline Edit Fields
     * @param string $comment_text Comment Text
     * @param object $comment      WP_Comment Object
     */
    private function get_wp_comment_reply($args)
    {
        global $wp_list_table;

        extract($args);

        $table_row = true;
        /**
         * Filter the in-line comment reply-to form output in the Comments
         * list table.
         *
         * Returning a non-empty value here will short-circuit display
         * of the in-line comment-reply form in the Comments list table,
         * echoing the returned value instead.
         *
         * @since 2.7.0
         *
         * @see wp_comment_reply()
         *
         * @param string $content The reply-to form content.
         * @param array  $args    An array of default args.
         */

        if (! $wp_list_table) {
            if ($mode == 'single') {
                $wp_list_table = _get_list_table('WP_Post_Comments_List_Table');
            } else {
                $wp_list_table = _get_list_table('WP_Comments_List_Table');
            }
        }
        $quicktags_settings = array( 'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,close' );
        ?>

        <form method="get">
        <?php if ( $table_row ) : ?>
        <table style="display:none;"><tbody id="com-reply"><tr id="replyrow" style="display:none;"><td colspan="<?php echo $wp_list_table->get_column_count(); ?>" class="colspanchange">
        <?php else : ?>
        <div id="com-reply" style="display:none;"><div id="replyrow" style="display:none;">
        <?php endif; ?>
            <div id="replyhead" style="display:none;"><h5><?php _e( 'Reply to Comment' ); ?></h5></div>
            <div id="addhead" style="display:none;"><h5><?php _e('Add new Comment'); ?></h5></div>
            <div id="edithead" style="display:none;">
            <div class="inside">
            <label for="author"><?php _e('Name') ?></label>
            <input type="text" name="newcomment_author" size="50" value="" id="author" />
            </div>

            <div class="inside">
            <label for="author-email"><?php _e('E-mail') ?></label>
            <input type="text" name="newcomment_author_email" size="50" value="" id="author-email" />
            </div>

        <?php foreach ($this->columns as $column) : ?>
        <?php if (isset($column['quick_edit']) && $column['quick_edit'] === true) : ?>
        <?php $field_type = isset($column['populate']['field_type']) ? $column['populate']['field_type'] : 'text'; ?>

            <div class="inside">
            <label for="author-<?php echo $column['id']; ?>"><?php echo $column['title']; ?></label>
            <input type="<?php echo $field_type; ?>" name="<?php echo $column['populate']['meta_key']; ?>" value="" id="author-<?php echo $column['populate']['meta_key']; ?>" />
            </div>

        <?php endif; ?>
        <?php endforeach; ?>

                <div class="inside">
                <label for="author-url"><?php _e('URL') ?></label>
                <input type="text" id="author-url" name="newcomment_author_url" class="code" size="103" value="" />
                </div>
                <div style="clear:both;"></div>
            </div>

            <div id="replycontainer">
            <?php
            $quicktags_settings = array( 'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,close' );
            echo wp_editor( '', 'replycontent', array( 'media_buttons' => false, 'tinymce' => false, 'quicktags' => $quicktags_settings ) );
            ?>
            </div>

            <p id="replysubmit" class="submit">
            <a href="#comments-form" class="save button-primary alignright">
            <span id="addbtn" style="display:none;"><?php _e('Add Comment'); ?></span>
            <span id="savebtn" style="display:none;"><?php _e('Update Comment'); ?></span>
            <span id="replybtn" style="display:none;"><?php _e('Submit Reply'); ?></span></a>
            <a href="#comments-form" class="cancel button-secondary alignleft"><?php _e('Cancel'); ?></a>
            <span class="waiting spinner"></span>
            <span class="error" style="display:none;"></span>
            <br class="clear" />
            </p>

            <input type="hidden" name="action" id="action" value="" />
            <input type="hidden" name="comment_ID" id="comment_ID" value="" />
            <input type="hidden" name="comment_post_ID" id="comment_post_ID" value="" />
            <input type="hidden" name="status" id="status" value="" />
            <input type="hidden" name="position" id="position" value="<?php echo $position; ?>" />
            <input type="hidden" name="checkbox" id="checkbox" value="<?php echo $checkbox ? 1 : 0; ?>" />
            <input type="hidden" name="mode" id="mode" value="<?php echo esc_attr($mode); ?>" />
            <?php
                wp_nonce_field( 'replyto-comment', '_ajax_nonce-replyto-comment', false );
                if ( current_user_can( 'unfiltered_html' ) )
                    wp_nonce_field( 'unfiltered-html-comment', '_wp_unfiltered_html_comment', false );
            ?>
        <?php if ( $table_row ) : ?>
        </td></tr></tbody></table>
        <?php else : ?>
        </div></div>
        <?php endif; ?>
        </form>
        <?php
    }

    /**
     * Saves Post / Custom Post Type Meta Data
     * @param  integer $post_id Post ID
     * @param  object $post     Post Object
     * @since 1.1.0
     */
    public function save_post_quick_edit_data($post_id, $post) {
        foreach ($this->columns as $column) {

            if($this->object_type != $post->post_type)
                return;

            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return;
            }

            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
                return;
            }

            if ( 'revision' == $post->post_type ) {
                return;
            }

            if ( array_key_exists($column['populate']['meta_key'], $_POST )
                && isset( $column['quick_edit'] )
                && !empty( $column['quick_edit'] )
                )
            {

                $object = $column['populate']['meta_object_type'];
                $meta_key = $column['populate']['meta_key'];
                $meta_value = $_POST[ $column['populate']['meta_key'] ];

                update_metadata($object, $post_id, $meta_key, $meta_value);
            }
        }
    }

    /**
     * Saves Category / Tag / Custom Taxonomy Meta Data
     * @todo  add sanitization
     *        add before_save callback
     *
     * @param  integer $object_id Taxonomy Object ID
     * @since 1.1.0
     */
    public function save_taxonomy_quick_edit_data($object_id, $taxonomy_id)
    {

        foreach ($this->columns as $column) {
            if ( isset($column['populate']['meta_key'])
                && array_key_exists($column['populate']['meta_key'], $_POST )
                && isset( $column['quick_edit'] )
                && !empty( $column['quick_edit'] )
                )
            {
                $object = $column['populate']['meta_object_type'];
                $meta_key = $column['populate']['meta_key'];
                $meta_value = $_POST[ $column['populate']['meta_key'] ];

                update_metadata($object, $object_id, $meta_key, $meta_value);
            }
        }
    }

    /**
     * Wrapper Function around add_quick_edit_fields
     * @param string $column_name Column ID
     * @param string $post_type   Object / Post Type
     */
    public function add_bulk_edit_fields($column_name, $post_type) {
        $this->add_quick_edit_fields($column_name, $post_type);
    }

    /**
     * Add Quick Edit Fields for All Post Types, Category and Tags
     * @param string $column_name Column ID
     * @param string $post_type   Object / Post Type
     */
    public function add_quick_edit_fields($column_name, $post_type)
    {
        foreach ($this->columns as $column) {
            if ( $column['id'] === $column_name
                && isset($column['populate']['meta_key'])
                && isset( $column['quick_edit'] )
                && !empty( $column['quick_edit'] )
                )
            {
                $markup = array(
                    "<div class=\"inline-edit-col column-%s\">", // Column Name / ID?
                    "<label class=\"inline-edit-group\">",
                    "<span class=\"title\">%s</span><span class=\"input-text-wrap\"><input type=\"%s\" value=\"\" name=\"%s\" class=\"ptitle\" /></span>",
                    "</label/>",
                );

                $field_type = isset($column['populate']['field_type']) ? $column['populate']['field_type'] : 'text';

                $field_markup[] = sprintf(
                    implode("\n", $markup),
                    $column['id'], //column-$id
                    $column['title'], // $title
                    $field_type, // $type
                    $column['populate']['meta_key'] // $id
                );
            }
        }
        if(!empty($field_markup)) {
            print( "<fieldset class=\"inline-edit-col-left\">" );
            print( "<h4>MBC Quick Edit Fields</h4>" );

            echo implode("\n", $field_markup);

            print( "</div>" );
            print( "</fieldset>" );
        }
    }

    /**
     * Adds (unfiltered) Column Meta Data in a hidden.
     * @since 1.1.0
     */
    public function add_hidden_column_fields($column, $value, $object_id)
    {
        if(isset($column['populate']['meta_key'])) {
            return sprintf(
                '<div class="MBC_%1$s hidden">%2$s</div>',
                $column['populate']['meta_key'],
                $value
            );
        }
    }

    /**
     * Add Column Titles to Table
     * @since  1.0.0
     * @param  array $columns Array of table columns
     * @return array Modified Array of columns
     */
    public function add_column_titles($columns)
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
    public function add_column_sort_ids($columns)
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
    public function populate_column_data($column, $object_id)
    {
        foreach ($this->columns as $mbc_column) {
            // If Column Matches - Go!
            if ($mbc_column['id'] === $column) {

                // If column is requesting to be conditionally shown
                if (isset($mbc_column['show_on_cb']) && is_callable($mbc_column['show_on_cb']) && ! call_user_func($mbc_column['show_on_cb'], $object_id)) {
                    return;
                }

                $content = $this->get_populate_data($mbc_column, $object_id);

                $filtered = apply_filters("before_output_{$mbc_column['id']}_filter", $content);

                if ($this->has_quickedit) {
                    $filtered .= $this->add_hidden_column_fields($mbc_column, $content, $object_id);
                }
                if (!empty( $filtered )) {
                    echo $filtered . $this->maybe_action_row($object_id, $mbc_column);
                }
            }
        }
    }

    public function get_populate_data($mbc_column, $object_id)
    {
        if (isset( $mbc_column['populate']['callback'] )) {
            $content = $this->populate_with_callback($object_id, $mbc_column);
        }

        if (isset( $mbc_column['populate']['meta_key'] )) {
            $content = $this->populate_with_meta($object_id, $mbc_column);
        }

        return $content;
    }

    /**
     * Wrapper for Post Tags and Categories Column Data
     * @since  1.0.0
     * @param string $column    Column name
     * @param int    $object_id object_id
     */
    public function populate_tag_column_data($depreciated, $column, $term_id)
    {
        $this->populate_column_data($column, $term_id);
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
            return $rowdata;
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

        return $metadata;
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


                break;

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
     * @todo  Add Category && Post Tag Support for Meta Fields
     */
    public function object_type($object_type = '')
    {
        $cpt = get_post_types(array('public' => true, '_builtin' => false));

        // Add Support for 'category', 'post_tag'
        if (in_array($object_type, array( 'post', 'comment', 'user', 'category', 'post_tag' ) ) ) {
            return $object_type;
        } elseif (in_array($object_type, $cpt, true)) {
            return $object_type;
        } else {
            return false;
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
     * Register Quick Edit JS
     */
    public static function register_scripts()
    {
        if (self::$registration_done) {
            return;
        }

        if (! is_admin()) {
            return;
        }

        wp_enqueue_script('my_js_library', self::get_mbc_url().'/js/mbc_admin.js');

        self::$registration_done = true;
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

        // Filter Populate Attributes
        if (is_array($column_args['populate'])) {
            $column_args['populate'] = array_filter($column_args['populate']);
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
            if(isset($column_args['populate']['meta_object_type'])) {
                $column_args['populate']['meta_object_type'] = $this->object_type($column_args['populate']['meta_object_type']);
            } else {
                $column_args['populate']['meta_object_type'] = $this->object_type;
            }
        }

        // Check for QuickEdits
        if (isset($column_args['quick_edit']) && $column_args['quick_edit'] === true) {
            $this->has_quickedit = true;
        }

        return $column_args;
    }

    /**
     * Returns MBC Relative URL
     * @return string MBC Path
     */
    public static function get_mbc_url()
    {
        $content_dir = str_replace('/', DIRECTORY_SEPARATOR, WP_CONTENT_DIR);
        $content_url = str_replace($content_dir, WP_CONTENT_URL, mbc_dir());
        $mbc_url     = str_replace(DIRECTORY_SEPARATOR, '/', $content_url);
        return $mbc_url;
    }

    /**
     * Ensures WordPress hook only gets fired once
     * @since  2.0.0
     * @param string   $action        The name of the filter to hook the $hook callback to.
     * @param callback $hook          The callback to be run when the filter is applied.
     * @param integer  $priority      Order the functions are executed
     * @param int      $accepted_args The number of arguments the function accepts.
     */
    public function once($action, $hook, $priority = 10, $accepted_args = 1)
    {
        $key = md5(serialize(func_get_args()));

        if (in_array($key, self::$hooks_completed)) {
            return;
        }

        self::$hooks_completed[] = $key;
        add_filter($action, $hook, $priority, $accepted_args);
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
