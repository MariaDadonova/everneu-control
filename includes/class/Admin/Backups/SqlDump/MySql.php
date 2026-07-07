<?php

class MySql
{
    public $fp;
    var $backup_filename;
    var $errors           = array();

    function __construct() {
        global $table_prefix, $wpdb;

        $table_prefix = ( isset( $table_prefix ) ) ? $table_prefix : $wpdb->prefix;

        // Use timestamp in filename to avoid double-prefix issue (e.g. wp_dbname_wp_.sql)
        $this->backup_filename = DB_NAME . "_" . date('Y-m-d_H-i-s') . ".sql";
    }

    /**
     * Backup a single table.
     *
     * String values are exported via MySQL's HEX() function so that PHP never
     * touches the raw bytes - no escaping, no substitution, no risk of
     * corrupting serialized data (Beaver Builder, ACF, Elementor, etc.).
     * MySQL's 0x<hex> literal is standard SQL and restores the exact byte
     * sequence on import regardless of content.
     *
     * NULL  > SQL NULL
     * ''    > ''
     * other > 0x<hex from MySQL HEX()>
     *
     * Rows are grouped into a single INSERT ... VALUES (...), (...) per batch
     * (100 rows) - same approach as phpMyAdmin, reducing statement count and
     * file size compared to one INSERT per row.
     */
    function backup_table( $table, $segment = 'none' ) {
        global $wpdb;

        // ----------------------------------------------------------------
        // Table structure
        // ----------------------------------------------------------------
        $columns = $wpdb->get_results( "SHOW COLUMNS FROM `$table`", ARRAY_A );
        if ( ! $columns ) {
            $this->error( __( 'Error getting table details', 'wp-db-backup' ) . ": $table" );
            return false;
        }

        // Build per-column type map and SELECT list
        $col_types    = array();  // 'int' | 'str'
        $select_parts = array();

        foreach ( $columns as $col ) {
            $name = $col['Field'];
            $type = strtolower( $col['Type'] );

            $is_int = (
                strpos( $type, 'tinyint' )   === 0 ||
                strpos( $type, 'smallint' )  === 0 ||
                strpos( $type, 'mediumint' ) === 0 ||
                strpos( $type, 'int' )       === 0 ||
                strpos( $type, 'bigint' )    === 0
            );

            $col_types[ $name ] = $is_int ? 'int' : 'str';

            // Integer columns: select as-is; string/blob columns: HEX() on MySQL side
            $select_parts[] = $is_int
                ? '`' . $name . '`'
                : 'HEX(`' . $name . '`) AS `' . $name . '`';
        }

        $select_sql = implode( ', ', $select_parts );

        if ( ( $segment == 'none' ) || ( $segment == 0 ) ) {
            $this->stow( "\n\n" );
            $this->stow( "#\n" );
            $this->stow( '# ' . sprintf( __( 'Delete any existing table %s', 'wp-db-backup' ), $this->backquote( $table ) ) . "\n" );
            $this->stow( "#\n\n" );
            $this->stow( 'DROP TABLE IF EXISTS ' . $this->backquote( $table ) . ";\n" );

            $this->stow( "\n\n" );
            $this->stow( "#\n" );
            $this->stow( '# ' . sprintf( __( 'Table structure of table %s', 'wp-db-backup' ), $this->backquote( $table ) ) . "\n" );
            $this->stow( "#\n\n" );

            $create_table = $wpdb->get_results( "SHOW CREATE TABLE `$table`", ARRAY_N );
            if ( false === $create_table ) {
                $err_msg = sprintf( __( 'Error with SHOW CREATE TABLE for %s.', 'wp-db-backup' ), $table );
                $this->error( $err_msg );
                $this->stow( "#\n# $err_msg\n#\n" );
            } else {
                $this->stow( $create_table[0][1] . ";\n" );
            }

            $this->stow( "\n\n" );
            $this->stow( "#\n" );
            $this->stow( '# ' . sprintf( __( 'Data contents of table %s', 'wp-db-backup' ), $this->backquote( $table ) ) . "\n" );
            $this->stow( "#\n\n" );
        }

        // ----------------------------------------------------------------
        // Table data
        // ----------------------------------------------------------------
        if ( ( $segment == 'none' ) || ( $segment >= 0 ) ) {

            $row_inc = 100; // fetch and group 100 rows per INSERT statement

            if ( $segment == 'none' ) {
                $row_start = 0;
            } else {
                $row_start = $segment * $row_inc;
            }

            do {
                if ( ! ini_get( 'safe_mode' ) ) {
                    @set_time_limit( 15 * 60 );
                }

                $table_data = $wpdb->get_results(
                    "SELECT {$select_sql} FROM `$table` LIMIT {$row_start}, {$row_inc}",
                    ARRAY_A
                );

                if ( empty( $table_data ) ) break;

                $insert_header = 'INSERT INTO ' . $this->backquote( $table ) . ' VALUES';
                $value_groups  = array();

                foreach ( $table_data as $row ) {
                    $values = array();

                    foreach ( $row as $key => $raw ) {
                        if ( $col_types[ $key ] === 'int' ) {
                            // Integer column - selected as-is from MySQL
                            if ( $raw === null || $raw === '' ) {
                                $values[] = 'NULL';
                            } else {
                                $values[] = (string)(int) $raw;
                            }
                        } else {
                            // String/blob column - MySQL returned HEX() value
                            // HEX(NULL) > NULL, HEX('') > '' (empty string)
                            if ( $raw === null ) {
                                $values[] = 'NULL';
                            } elseif ( $raw === '' ) {
                                // Empty string: HEX('') returns empty string in MySQL
                                $values[] = "''";
                            } else {
                                // Non-empty: raw is already the hex digits from MySQL HEX()
                                // Prepend 0x - MySQL restores exact bytes on import
                                $values[] = '0x' . $raw;
                            }
                        }
                    }

                    $value_groups[] = '(' . implode( ', ', $values ) . ')';
                }

                if ( ! empty( $value_groups ) ) {
                    $this->stow(
                        $insert_header . "\n" .
                        implode( ",\n", $value_groups ) .
                        ";\n"
                    );
                }

                $row_start += $row_inc;

            } while ( count( $table_data ) === $row_inc );
        }

        if ( ( $segment == 'none' ) || ( $segment < 0 ) ) {
            $this->stow( "\n" );
            $this->stow( "#\n" );
            $this->stow( '# ' . sprintf( __( 'End of data contents of table %s', 'wp-db-backup' ), $this->backquote( $table ) ) . "\n" );
            $this->stow( "# --------------------------------------------------------\n\n" );
        }
    }

    /**
     * Add backquotes to tables and db-names in SQL queries.
     */
    function backquote( $a_name ) {
        if ( ! empty( $a_name ) && $a_name != '*' ) {
            if ( is_array( $a_name ) ) {
                return array_map( fn($val) => '`' . $val . '`', $a_name );
            }
            return '`' . $a_name . '`';
        }
        return $a_name;
    }

    function open( $filename = '', $mode = 'w' ) {
        if ( '' == $filename ) return false;
        return @fopen( $filename, $mode );
    }

    function close( $fp ) {
        fclose( $fp );
    }

    function error( $args = array() ) {
        if ( is_string( $args ) ) {
            $args = array( 'msg' => $args );
        }

        $args = array_merge(
            array( 'loc' => 'main', 'kind' => 'warn', 'msg' => '' ),
            $args
        );

        $this->errors[ $args['kind'] ][] = $args['msg'];

        if ( 'fatal' == $args['kind'] || 'frame' == $args['loc'] ) {
            $this->error_display( $args['loc'] );
        }

        return true;
    }

    function db_backup( $core_tables, $backup_dir = '' ) {
        error_log("==== Everneu plugin log ====");
        error_log("==== MySQL db_backup ====");

        global $wpdb;

        if ( empty( $backup_dir ) ) {
            $backup_dir = $_SERVER['DOCUMENT_ROOT'] . '/wp-content/';
        }

        $backup_dir = rtrim( $backup_dir, '/' ) . '/';

        if ( is_writable( $backup_dir ) ) {
            $this->fp = $this->open( $backup_dir . $this->backup_filename );
            if ( ! $this->fp ) {
                $this->error( __( 'Could not open the backup file for writing!', 'wp-db-backup' ) );
                error_log( 'MySql db_backup: failed to open file - ' . $backup_dir . $this->backup_filename );
                error_log("==== End Everneu plugin log ====");
                return false;
            }
        } else {
            $this->error( __( 'The backup directory is not writeable!', 'wp-db-backup' ) );
            error_log( 'MySql db_backup: directory not writable - ' . $backup_dir );
            error_log("==== End Everneu plugin log ====");
            return false;
        }

        $this->stow( '# ' . __( 'WordPress MySQL database backup', 'wp-db-backup' ) . "\n" );
        $this->stow( "#\n" );
        $this->stow( '# ' . sprintf( __( 'Generated: %s', 'wp-db-backup' ), date( 'l j. F Y H:i T' ) ) . "\n" );
        $this->stow( '# ' . sprintf( __( 'Hostname: %s', 'wp-db-backup' ), DB_HOST ) . "\n" );
        $this->stow( '# ' . sprintf( __( 'Database: %s', 'wp-db-backup' ), $this->backquote( DB_NAME ) ) . "\n" );
        $this->stow( "# --------------------------------------------------------\n\n" );

        // Disable foreign key checks so that tables with FOREIGN KEY constraints
        // (e.g. Rank Math map_variations > maps) can be created in any order.
        $this->stow( "SET FOREIGN_KEY_CHECKS=0;\n\n" );

        foreach ( $core_tables as $table ) {
            if ( ! ini_get( 'safe_mode' ) ) {
                @set_time_limit( 15 * 60 );
            }
            $this->stow( "# --------------------------------------------------------\n" );
            $this->stow( '# ' . sprintf( __( 'Table: %s', 'wp-db-backup' ), $this->backquote( $table ) ) . "\n" );
            $this->stow( "# --------------------------------------------------------\n" );
            $this->backup_table( $table );
        }

        $this->stow( "\nSET FOREIGN_KEY_CHECKS=1;\n" );

        $this->close( $this->fp );

        if ( count( $this->errors ) ) {
            error_log( 'MySql db_backup: completed with errors - ' . print_r( $this->errors, true ) );
            error_log("==== End Everneu plugin log ====");
            return false;
        }

        return $this->backup_filename;
    }

    function stow( $query_line ) {
        if ( false === @fwrite( $this->fp, $query_line ) ) {
            $this->error(
                __( 'There was an error writing a line to the backup script:', 'wp-db-backup' ) .
                '  ' . $query_line . '  ' . $php_errormsg
            );
        }
    }

    function get_tables() {
        global $wpdb;
        $all_tables = $wpdb->get_results( 'SHOW TABLES', ARRAY_N );
        return array_map( fn($a) => $a[0], $all_tables );
    }

}