<?php
/**
 * Plugin Name: WP CRUD Plugin
 * Description: A plugin to perform CRUD operations on custom tables.
 * Version: 2.0
 * Author: Your Name
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Activation hook to create table
register_activation_hook(__FILE__, 'wp_crud_plugin_activate');
function wp_crud_plugin_activate() {
    // No initial table creation, users can create tables dynamically
}

// Uninstall hook to delete all custom tables
register_uninstall_hook(__FILE__, 'wp_crud_plugin_uninstall');
function wp_crud_plugin_uninstall() {
    global $wpdb;
    
    // Get all custom tables created by the plugin
    $tables = get_option('wp_crud_plugin_tables', array());
    foreach ($tables as $table_name) {
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }

    // Delete the option storing the table names
    delete_option('wp_crud_plugin_tables');
}

function enqueue_custom_table_scripts() {
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-dialog');
    wp_enqueue_style('jquery-ui-css', '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
    wp_enqueue_script('custom-table-crud', plugin_dir_url(__FILE__) . 'custom-table-crud.js', array('jquery', 'jquery-ui-dialog'), null, true);
}
add_action('wp_enqueue_scripts', 'enqueue_custom_table_scripts');
add_action('admin_enqueue_scripts', 'enqueue_custom_table_scripts');









// Function to create a new table
function wp_crud_create_table($table_name, $columns) {
    global $wpdb;

    $table_name = $wpdb->prefix . sanitize_text_field($table_name);
    $charset_collate = $wpdb->get_charset_collate();

    $columns_sql = '';
    foreach ($columns as $column) {
        $columns_sql .= sanitize_text_field($column['name']) . ' ' . sanitize_text_field($column['type']) . ', ';
    }

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        $columns_sql
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Store the table name in the options table
    $tables = get_option('wp_crud_plugin_tables', array());
    $tables[] = $table_name;
    update_option('wp_crud_plugin_tables', $tables);
}

// Admin menu
add_action('admin_menu', 'wp_crud_plugin_menu');
function wp_crud_plugin_menu() {
    add_menu_page('CRUD Plugin', 'CRUD Plugin', 'manage_options', 'wp-crud-plugin', 'wp_crud_plugin_page');
    add_submenu_page('wp-crud-plugin', 'Create Table', 'Create Table', 'manage_options', 'wp-crud-create-table', 'wp_crud_create_table_page');
}

// Plugin page to display data and add new rows
function wp_crud_plugin_page() {

    $total_amount=0;
    // Get table to display
    if (isset($_POST['selected_table'])) {
        $selected_table = sanitize_text_field($_POST['selected_table']);
    } else {
        $selected_table = '';
    }

    // Add new row to the table
    if (isset($_POST['add_row'])) {
        global $wpdb;
        $table_name = sanitize_text_field($_POST['selected_table']);
        $columns = wp_crud_get_columns($table_name);
        $data = [];
        foreach ($columns as $column) {
            if ($column != 'id' && $column != 'created_at') {
                $data[$column] = sanitize_text_field($_POST[$column]);
            }
        }
        $wpdb->insert($table_name, $data);
        echo '<div class="updated"><p>Row added successfully!</p></div>';
    }

    if(isset($_POST['delete_id'])){
        handle_custom_table_delete();
    }
    if(isset($_POST['update_table'])){
        handle_custom_table_update();
    }
    // Display form to select table and search by date
    ?>
    <div class="wrap">
        <h1>WP CRUD Plugin</h1>
        <form method="post" action="">
            <select name="selected_table">
                <?php
                $tables = get_option('wp_crud_plugin_tables', array());
                foreach ($tables as $table) {
                    echo '<option value="' . esc_attr($table) . '" ' . selected($selected_table, $table, false) . '>' . esc_html($table) . '</option>';
                }
                ?>
            </select>
            <input type="date" name="search_date" value="<?php echo isset($_POST['search_date']) ? esc_attr($_POST['search_date']) : ''; ?>" placeholder="Search by date">
            <input type="submit" value="Select Table">
        </form>
    </div>
    <?php

    if ($selected_table) {
        // Display data from the selected table
        global $wpdb;

        if (isset($_POST['search_date']) && $_POST['search_date'] !== "") {
            $search_date = sanitize_text_field($_POST['search_date']);
            $results = wp_crud_search_data_by_date($selected_table, $search_date);
        } else {
            $results = wp_crud_get_all_data($selected_table);
        }

        ?>
        <div class="wrap">
            <!-- <form method="post" action="">
                <input type="hidden" name="selected_table" value="<?php echo esc_attr($selected_table); ?>">
                <input type="date" name="search_date" value="<?php echo isset($search_date) ? esc_attr($search_date) : ''; ?>" placeholder="Search by date">
                <input type="submit" value="Search">
            </form> -->

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <?php
                        $columns = $wpdb->get_results("SHOW COLUMNS FROM $selected_table");
                        foreach ($columns as $column) {
                            echo '<th>' . esc_html($column->Field) . '</th>';
                        }
                        ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($results): ?>
                        <?php foreach ($results as $row): ?>

                              <?php 
                                   if($row->Amount){
                                                
                                        $total_amount +=   intval($row->Amount) ;

                                      }
                                  // var_dump($column);
                               ?>

                            <tr>
                                <?php foreach ($columns as $column): ?>
                                    <td><?php echo esc_html($row->{$column->Field}); ?></td>

                                 
                                <?php endforeach; ?>
                                <td>
                                        <button class="ceymulticall-open-modal" data-table="<?php echo esc_attr($selected_table) ?>" data-row="<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8')?>">Update</button>


                                        <form method="post" action="" style="display:inline;">

                                            <input type="hidden" name="delete_id" value="<?php echo esc_attr($row->id) ?>">
                                            <input type="hidden" name="delete_table" value="<?php echo esc_attr($selected_table)?>">
                                            <button type="submit">Delete</button>

                                        </form>

                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo count($columns); ?>">No data found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div>
              Total is:     <?php echo $total_amount; ?>                         
            </div>
            <div id="ceymulticall-update-modal" title="Update Record" style="display:none;">

                    <form id="ceymulticall-update-form" method="post" action="">

                            <input type="hidden" name="action" value="custom_table_update">
                            <input type="hidden" name="update_table" id="ceymulticall-modal-table">
                            <div id="ceymulticall-modal-fields"></div>
                            <button type="submit">Save Changes</button>

                    </form>
             </div>




            <h2>Add New Row</h2>
            <form method="post" action="">
                <input type="hidden" name="selected_table" value="<?php echo esc_attr($selected_table); ?>">
                <?php
                foreach ($columns as $column) {
                    if ($column->Field != 'id' && $column->Field != 'created_at') {
                        echo '<p><label for="' . esc_attr($column->Field) . '">' . esc_html($column->Field) . '</label>';
                        echo '<input type="text" name="' . esc_attr($column->Field) . '" id="' . esc_attr($column->Field) . '"></p>';
                    }
                }
                ?>
                <input type="submit" name="add_row" value="Add Row">
            </form>
        </div>
        <?php
    }
}

// Plugin page to create a new table
function wp_crud_create_table_page() {
    if (isset($_POST['create_table'])) {
        $table_name = sanitize_text_field($_POST['table_name']);
        $columns = array();
        for ($i = 0; $i < count($_POST['column_name']); $i++) {
            $columns[] = array(
                'name' => sanitize_text_field($_POST['column_name'][$i]),
                'type' => sanitize_text_field($_POST['column_type'][$i])
            );
        }

        wp_crud_create_table($table_name, $columns);
        echo '<div class="updated"><p>Table created successfully!</p></div>';
    }

    ?>
    <div class="wrap">
        <h1>Create Table</h1>
        <form method="post" action="">
            <table>
                <tr>
                    <th>Table Name</th>
                    <td><input type="text" name="table_name" required></td>
                </tr>
                <tr>
                    <th>Columns</th>
                    <td>
                        <div id="columns">
                            <div>
                                <input type="text" name="column_name[]" placeholder="Column Name" required>
                                <select name="column_type[]" required>
                                    <option value="VARCHAR(255)">VARCHAR(255)</option>
                                    <option value="TEXT">TEXT</option>
                                    <option value="INT">INT</option>
                                    <option value="MEDIUMINT">MEDIUMINT</option>
                                </select>
                            </div>
                        </div>
                        <button type="button" onclick="addColumn()">Add Column</button>
                    </td>
                </tr>
            </table>
            <input type="submit" name="create_table" value="Create Table">
        </form>
    </div>
    <script>
        function addColumn() {
            var div = document.createElement('div');
            div.innerHTML = '<input type="text" name="column_name[]" placeholder="Column Name" required> <select name="column_type[]" required><option value="VARCHAR(255)">VARCHAR(255)</option><option value="TEXT">TEXT</option><option value="INT">INT</option><option value="MEDIUMINT">MEDIUMINT</option></select>';
            document.getElementById('columns').appendChild(div);
        }
    </script>
    <?php
}

// Function to search data by date
function wp_crud_search_data_by_date($table_name, $search_date) {
    global $wpdb;

    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE DATE(created_at) = %s",
            $search_date
        )
    );

    return $results;
}

// Function to get all data
function wp_crud_get_all_data($table_name) {
    global $wpdb;

    $results = $wpdb->get_results("SELECT * FROM $table_name");

    return $results;
}

// Function to get columns of a table
function wp_crud_get_columns($table_name) {
    global $wpdb;

    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
    $column_names = array();
    foreach ($columns as $column) {
        $column_names[] = $column->Field;
    }

    return $column_names;
}

// Shortcode to display table data on the front end
add_shortcode('wp_crud_display_table', 'wp_crud_display_table_shortcode');
function wp_crud_display_table_shortcode($atts) {
    $atts = shortcode_atts(array('table' => ''), $atts, 'wp_crud_display_table');
    $table_name = sanitize_text_field($atts['table']);

    if (!$table_name) {
        return 'No table specified.';
    }

    if (isset($_POST['search_date'])) {
        $search_date = sanitize_text_field($_POST['search_date']);
        $results = wp_crud_search_data_by_date($table_name, $search_date);
    } else {
        $results = wp_crud_get_all_data($table_name);
    }

    ob_start();
    ?>
    <form method="post" action="">
        <input type="date" name="search_date" value="<?php echo isset($search_date) ? esc_attr($search_date) : ''; ?>" placeholder="Search by date">
        <input type="submit" value="Search">
    </form>

    <table>
        <thead>
            <tr>
                <?php
                global $wpdb;
                $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
                foreach ($columns as $column) {
                    echo '<th>' . esc_html($column->Field) . '</th>';
                }
                ?>
            </tr>
        </thead>
        <tbody>
            <?php if ($results): ?>
                <?php foreach ($results as $row): ?>
                    <tr>
                        <?php foreach ($columns as $column): ?>
                            <td><?php echo esc_html($row->{$column->Field}); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?php echo count($columns); ?>">No data found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
    return ob_get_clean();
}

// Handle update actions
function handle_custom_table_update() {
    global $wpdb;

    if (!empty($_POST['update_table'])) {
        $table_name = sanitize_text_field($_POST['update_table']);
        $data = $_POST;
        unset($data['update_table'], $data['action']);

        $id = intval($data['id']);
        unset($data['id']);

        // Sanitize data
        foreach ($data as $key => $value) {
            $data[$key] = sanitize_text_field($value);
        }

        $wpdb->update(
            $table_name,
            $data,
            array('id' => $id)
        );
    }

    echo '<div class="updated"><p>Row updated successfully!</p></div>';

  //  wp_redirect($_SERVER['HTTP_REFERER']);
   // exit;
}

// Handle delete actions
function handle_custom_table_delete() {
    global $wpdb;

    if (!empty($_POST['delete_id']) && $_POST['delete_id'] !== false) {
        $id = intval( $_POST['delete_id']);
        $deleteTable=sanitize_text_field($_POST['delete_table']);
        $wpdb->delete(
            $deleteTable,
            array('id' => $id),
            array('%d')
        );
    }
    echo '<div class="updated"><p>Row deleted successfully!</p></div>';

//wp_redirect($_SERVER['HTTP_REFERER']);
  //  exit;
}

?>
