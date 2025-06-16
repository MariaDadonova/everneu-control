<?php


class MySql
{
    public $fp;
    var $backup_filename;
    var $core_table_names = array();
    var $errors           = array();
    var $basename;


    function __construct() {
        global $table_prefix, $wpdb;

        $table_prefix = ( isset( $table_prefix ) ) ? $table_prefix : $wpdb->prefix;
        $this->backup_filename = DB_NAME . "_$table_prefix.sql";

        $possible_names = array(
            'categories',
            'commentmeta',
            'comments',
            'link2cat',
            'linkcategories',
            'links',
            'options',
            'post2cat',
            'postmeta',
            'posts',
            'terms',
            'term_taxonomy',
            'term_relationships',
            'termmeta',
            'users',
            'usermeta',
        );

        foreach ( $possible_names as $name ) {
            if ( isset( $wpdb->{$name} ) ) {
                $this->core_table_names[] = $wpdb->{$name};
            }
        }

    }




    function backup_table( $table, $segment = 'none' ) {
        global $wpdb;

        $table_structure = $wpdb->get_results( "DESCRIBE $table" );
        if ( ! $table_structure ) {
            $this->error( __( 'Error getting table details', 'wp-db-backup' ) . ": $table" );
            return false;
        }

        if ( ( $segment == 'none' ) || ( $segment == 0 ) ) {
            // Add SQL statement to drop existing table
            $this->stow( "\n\n" );
            $this->stow( "#\n" );
            $this->stow( '# ' . sprintf( __( 'Delete any existing table %s', 'wp-db-backup' ), $this->backquote( $table ) ) . "\n" );
            $this->stow( "#\n" );
            $this->stow( "\n" );
            $this->stow( 'DROP TABLE IF EXISTS ' . $this->backquote( $table ) . ";\n" );

            // Table structure
            // Comment in SQL-file
            $this->stow( "\n\n" );
            $this->stow( "#\n" );
            $this->stow( '# ' . sprintf( __( 'Table structure of table %s', 'wp-db-backup' ), $this->backquote( $table ) ) . "\n" );
            $this->stow( "#\n" );
            $this->stow( "\n" );

            $create_table = $wpdb->get_results( "SHOW CREATE TABLE $table", ARRAY_N );
            if ( false === $create_table ) {
                $err_msg = sprintf( __( 'Error with SHOW CREATE TABLE for %s.', 'wp-db-backup' ), $table );
                $this->error( $err_msg );
                $this->stow( "#\n# $err_msg\n#\n" );
            }
            $this->stow( $create_table[0][1] . ' ;' );

            if ( false === $table_structure ) {
                $err_msg = sprintf( __( 'Error getting table structure of %s', 'wp-db-backup' ), $table );
                $this->error( $err_msg );
                $this->stow( "#\n# $err_msg\n#\n" );
            }

            // Comment in SQL-file
            $this->stow( "\n\n" );
            $this->stow( "#\n" );
            $this->stow( '# ' . sprintf( __( 'Data contents of table %s', 'wp-db-backup' ), $this->backquote( $table ) ) . "\n" );
            $this->stow( "#\n" );
        }

        if ( ( $segment == 'none' ) || ( $segment >= 0 ) ) {
            $defs = array();
            $ints = array();
            foreach ( $table_structure as $struct ) {
                if ( ( 0 === strpos( $struct->Type, 'tinyint' ) ) ||
                    ( 0 === strpos( strtolower( $struct->Type ), 'smallint' ) ) ||
                    ( 0 === strpos( strtolower( $struct->Type ), 'mediumint' ) ) ||
                    ( 0 === strpos( strtolower( $struct->Type ), 'int' ) ) ||
                    ( 0 === strpos( strtolower( $struct->Type ), 'bigint' ) ) ) {
                    $defs[ strtolower( $struct->Field ) ] = ( null === $struct->Default ) ? 'NULL' : $struct->Default;
                    $ints[ strtolower( $struct->Field ) ] = '1';
                }
            }

            // Batch by $row_inc

           if ( $segment == 'none' ) {
                $row_start = 0;
                $row_inc   = 25;
            } else {
                $row_start = $segment * 25;
                $row_inc   = 25;
            }

            do {
                // don't include extra stuff, if so requested

                if ( ! ini_get( 'safe_mode' ) ) {
                    @set_time_limit( 15 * 60 );
                }
                $table_data = $wpdb->get_results( "SELECT * FROM $table LIMIT {$row_start}, {$row_inc}", ARRAY_A );
                //$table_data = $wpdb->get_results( "SELECT * FROM $table", ARRAY_A );

                $entries = 'INSERT INTO ' . $this->backquote( $table ) . ' VALUES (';
                //    \x08\\x09, not required
                $search  = array( "\x00", "\x0a", "\x0d", "\x1a" );
                $replace = array( '\0', '\n', '\r', '\Z' );

                if ( $table_data ) {
                    foreach ( $table_data as $row ) {
                        $values = array();
                        foreach ( $row as $key => $value ) {
                            if ( ! empty( $ints[ strtolower( $key ) ] ) ) {
                                // make sure there are no blank spots in the insert syntax,
                                // yet try to avoid quotation marks around integers
                                $value    = ( null === $value || '' === $value ) ? $defs[ strtolower( $key ) ] : $value;
                                $values[] = ( '' === $value ) ? "''" : $value;
                            } else {
                                $values[] = "'" . str_replace( $search, $replace, $this->sql_addslashes( $value ) ) . "'";
                            }
                        }
                        $this->stow( " \n" . $entries . implode( ', ', $values ) . ');' );
                    }
                    $row_start += $row_inc;
                }
            } while ( ( count( $table_data ) > 0 ) and ( $segment == 'none' ) );
        }

        if ( ( $segment == 'none' ) || ( $segment < 0 ) ) {
            // Create footer/closing comment in SQL-file
            $this->stow( "\n" );
            $this->stow( "#\n" );
            $this->stow( '# ' . sprintf( __( 'End of data contents of table %s', 'wp-db-backup' ), $this->backquote( $table ) ) . "\n" );
            $this->stow( "# --------------------------------------------------------\n" );
            $this->stow( "\n" );
        }
    } // end backup_table()



    /**
     * Add backquotes to tables and db-names in
     * SQL queries. Taken from phpMyAdmin.
     */
    function backquote( $a_name ) {
        if ( ! empty( $a_name ) && $a_name != '*' ) {
            if ( is_array( $a_name ) ) {
                $result = array();
                reset( $a_name );
                while ( list($key, $val) = each( $a_name ) ) {
                    $result[ $key ] = '`' . $val . '`';
                }
                return $result;
            } else {
                return '`' . $a_name . '`';
            }
        } else {
            return $a_name;
        }
    }

    function open( $filename = '', $mode = 'w' ) {
        if ( '' == $filename ) {
            return false;
        }
        $fp = @fopen( $filename, $mode );
        return $fp;
    }

    function close( $fp ) {
        fclose( $fp );
    }

    /**
     * Logs any error messages
     * @param array $args
     * @return bool
     */
    function error( $args = array() ) {
        if ( is_string( $args ) ) {
            $args = array( 'msg' => $args );
        }

        $args = array_merge(
            array(
                'loc'  => 'main',
                'kind' => 'warn',
                'msg'  => '',
            ),
            $args
        );

        $this->errors[ $args['kind'] ][] = $args['msg'];

        if ( 'fatal' == $args['kind'] || 'frame' == $args['loc'] ) {
            $this->error_display( $args['loc'] );
        }

        return true;
    }

    function db_backup($core_tables) {
        global $table_prefix, $wpdb;

        $backup_dir = $_SERVER['DOCUMENT_ROOT'].'/wp-content/';

        if ( is_writable( $backup_dir ) ) {
            $this->fp = $this->open( $backup_dir . $this->backup_filename );
            if ( ! $this->fp ) {
                $this->error( __( 'Could not open the backup file for writing!', 'wp-db-backup' ) );
                return false;
            }
        } else {
            $this->error( __( 'The backup directory is not writeable!', 'wp-db-backup' ) );
            return false;
        }

        //Begin new backup of MySql
        $this->stow( '# ' . __( 'WordPress MySQL database backup', 'wp-db-backup' ) . "\n" );
        $this->stow( "#\n" );
        $this->stow( '# ' . sprintf( __( 'Generated: %s', 'wp-db-backup' ), date( 'l j. F Y H:i T' ) ) . "\n" );
        $this->stow( '# ' . sprintf( __( 'Hostname: %s', 'wp-db-backup' ), DB_HOST ) . "\n" );
        $this->stow( '# ' . sprintf( __( 'Database: %s', 'wp-db-backup' ), $this->backquote( DB_NAME ) ) . "\n" );
        $this->stow( "# --------------------------------------------------------\n" );


            $tables = $core_tables;

        foreach ( $tables as $table ) {
            // Increase script execution time-limit to 15 min for every table.
            if ( ! ini_get( 'safe_mode' ) ) {
                @set_time_limit( 15 * 60 );
            }
            // Create the SQL statements
            $this->stow( "# --------------------------------------------------------\n" );
            $this->stow( '# ' . sprintf( __( 'Table: %s', 'wp-db-backup' ), $this->backquote( $table ) ) . "\n" );
            $this->stow( "# --------------------------------------------------------\n" );
            $this->backup_table( $table );
        }

        $this->close( $this->fp );

        if ( count( $this->errors ) ) {
            return false;
        } else {
            return $this->backup_filename;
        }

    } //wp_db_backup


    /**
     * Write to the backup file
     * @param string $query_line the line to write
     * @return null
     */
    function stow( $query_line ) {
        if ( false === @fwrite( $this->fp, $query_line ) ) {
            $this->error( __( 'There was an error writing a line to the backup script:', 'wp-db-backup' ) . '  ' . $query_line . '  ' . $php_errormsg );
        }
    }

    /**
     * Get an array of all tables on the current WP install.
     *
     * @return array
     */
    function get_tables() {
        global $wpdb;

        $all_tables = $wpdb->get_results( 'SHOW TABLES', ARRAY_N );

        return array_map(
            function( $a ) {
                return $a[0];
            },
            $all_tables
        );
    }


    /**
     * Better addslashes for SQL queries.
     * Taken from phpMyAdmin.
     */
    function sql_addslashes( $a_string = '', $is_like = false ) {
        $a_string = (string) $a_string;

        if ( $is_like ) {
            $a_string = str_replace( '\\', '\\\\\\\\', $a_string );
        } else {
            $a_string = str_replace( '\\', '\\\\', $a_string );
        }

        return str_replace( '\'', '\\\'', $a_string );
    }


}