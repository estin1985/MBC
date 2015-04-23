MBC
==============

MBC is a tool to simplify the creation and population of admin columns in Wordpress.
--------------

**Version: ** 1.0.0

Example Usage:
--------------
```php
function mbc_register_columns() {
    $post_columns = new_mbc_column(array(
        'id'          => 'post_revisions_column',    // Unique ID For Internal Use
        'object_type' => 'post',                // Object Type To Render Columns For
        'columns' => array(
            array(
                'id'                 => 'editor_rating',    // Column ID (Must Be Unique)
                'title'              => 'Editor Rating',    // Column Title
                'sortable'           => true,               // Make Column Sortable
                'show_on_cb'         => ‘is_single’,        // Conditional Show On Callback. Displays on True
                'populate'           => array(
                    'meta_key'         => _last_edit’,     // Meta Key Used To Populate Block
                    'meta_object_type' => 'post',          // Taxonomy The Meta Key Should Be Pulled From
                    'callback'         => '',              // Callback Function for Populating Block
                ),
                'row_action'          => array(),          // On Hover Row Action
            ),
         ),
    ));
}
add_action('mbc_init', 'mbc_register_columns');

```


    Add a indent and this will end up as code