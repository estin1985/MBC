<?php
/**
 * Stores each MBC instance
 */
class MBC_Objects
{
    /**
     * Array of all MBC objects
     * @var   array
     * @since 1.0.0
     */
    protected static $columns = array();

    public static function add($column)
    {
        self::$columns[ $column->mbc_id ] = $column;
    }

    public static function remove($column_id)
    {
        if ( array_key_exists( $column_id, self::$columns ) ) {
            unset( self::$columns[ $column_id ] );
        }
    }

    public static function get($column_id)
    {
        if ( empty( self::$columns ) || empty( self::$columns[ $column_id ] ) ) {
            return false;
        }

        return self::$columns[ $column_id ];
    }

    public static function get_all()
    {
        return self::$columns;
    }
}
