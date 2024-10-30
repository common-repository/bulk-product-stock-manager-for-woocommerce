<?php
/**
 * Plugin Name: Bulk Product Stock Manager for Woocommerce
 * Plugin URI: https://outseller.net
 * Description: Bulk Product Stock Manager for Woocommerce
 * Version: 1.0.1
 * Author: Hassan Zobeen
 * Author URI: http://outseller.net
 * License: GPL2
 * Text Domain: bulk-product-stock-manager
 */


if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

/**
 * Check if WooCommerce is active
 */
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    if (!class_exists('BulkProductStockManager')) {
        class BulkProductStockManager {
            protected $nonce = 'osgbpsmnonce';

            function __construct() {
                add_action( 'admin_menu', array( $this, 'initiateBPSM_nav' ) );
                add_action( 'admin_enqueue_scripts', array( $this, 'osg_RegisterScripts' ) );
                add_action('wp_ajax_my_action', array($this, 'osg_processstock_data') );
                add_action( 'wp_ajax_nopriv_my_action', array( $this, 'osg_processstock_data' ) );
                add_action('wp_ajax_osg_processrestore', array($this, 'osg_processrestoreData') );
                add_action( 'wp_ajax_nopriv_osg_processrestore', array( $this, 'osg_processrestoreData' ) );
            }

            /* Initiate Menus */
            function initiateBPSM_nav() {
                add_menu_page('Bulk Stock Mgmt', 'Bulk Stock Mgmt', 'manage_options', 'osg-bulk-stock-mgr', array( $this, 'osg_bulk_prod_stock_mgr' ), 'dashicons-chart-area' );
                add_submenu_page( 'osg-bulk-stock-mgr', 'Stock Transactions', 'Stock Transactions', 'manage_options', 'osg-product-stock-history', array( $this, 'osg_transactions_data' ) );
            }
            /* Initiate Menus Ends*/

            /* Register Scripts Starts */
            function osg_RegisterScripts() {
                wp_enqueue_style('osgbpsmStyle', plugins_url('/assets/css/styles.css', __FILE__) );
                wp_enqueue_script( 'osgbpsmScript', plugins_url( '/assets/js/script.js', __FILE__ ), true );
                wp_localize_script( 'osgbpsmScript', 'osgbpsmScriptAjax', [
                    'ajax_url' => admin_url( 'admin-ajax.php' ), 
                    'nonce'    => wp_create_nonce( $this->nonce ),
                  ]);
            }
            /* Register Scripts Ends */

            /* Bulk Stock Manager Function */
            function osg_bulk_prod_stock_mgr() {
                echo $this->osg_initialheaderData();

                echo "<div class='wrap'><div id='icon-options-general' class='icon32'><br></div>
                <h2>Update Product Stock</h2></div>";
                $args = array(
                    'orderby'    => 'name',
                    'order'      => 'asc',
                    'hide_empty'   => false,
                );
                echo "<div class='cmtStockResetter'><div class='alert' id='divOSGBPSM'>Select Categories to reset stock</div><fieldset class='cmtcheckboxgroup'>";
                $categories= get_terms( 'product_cat', $args );
                    foreach($categories as $category):
                ?>
                <label class="chkItemStyle"><input type="checkbox" name="mychecky" class="osgbpsm-checkbox" value="<?php echo $category->slug; ?>"> <?php echo $category->name; ?></label>
                <?php
                    endforeach;
                $loadingImg = plugins_url('/assets/css/preloader.gif', __FILE__);
                echo "<div class='osgProcessBtn'><div class='osgQuantityField'><label>Quanity*</label><input type='number' id='osgQuantityField' class='regular-text' min='0' value='0' style='width:15%; border:1px solid #000;'></div>";
                echo "<div class='osgRemarkfield'><label>Remarks</label><input type='text' id='osgRemarkfield' class='regular-text'></div>";
                echo "<button id='idOSGBPSM' class='button button-primary button-large'>Update Stock</button></div><div class='loaderOsgDiv' id='loaderOsgDiv'>Please wait it will take sometime<br><img src='{$loadingImg}'></div>";
                echo "</fieldset></div>";
            }
            /* Bulk Stock Manager Function Ends */

            /* Sanitize Array Data */
            function osg_sanitizeccarray($arr = null ) {
                $output = null;
                if ( is_array( $arr ) ) {
                    foreach ( $arr as $taxonomy ) {
                            $output[] = sanitize_text_field( $taxonomy );
                    }
                }
                return $output;
            }
            /* Sanitize Array Data Ends */

            /* Process Stock Data Start */
            function osg_processstock_data() {
                global $product;
                $CountProducts = 0;
                $finalResult = null;
                $oldStock = null;
                $totalCategories = 0;
                $out_of_stock_staus = 'outofstock';

                $check_params = $this->osg_sanitizeccarray( wp_unslash( $_POST['param'] ) );
                $check_remarks = sanitize_text_field($_POST['pramRemarks']);
                $check_quanity = (int)sanitize_text_field($_POST['osgpramVal']);
                $ValidateNonce = sanitize_text_field($_REQUEST['osgbpsm_nonce']);

                if ( ( wp_verify_nonce( $ValidateNonce, $this->nonce ) ) && current_user_can('administrator') ) {
                if(!empty($check_params) && is_int($check_quanity) ){
                    $listCats = $check_params;
                    $OSGTransRemarks = empty($check_remarks)? "N/A": $check_remarks;
                    $OSGValQty = empty($_POST['osgpramVal'])? 0: is_int($_POST['osgpramVal']);
                    if ($OSGValQty > 0) {
                        $out_of_stock_staus = 'instock';
                    } else {
                        $out_of_stock_staus = 'outofstock';  
                    }
                    $totalCategories = count($totalCategories);
                    /* Get Main Procuts Categories */
                    if ( is_array($listCats) ) {
                        $args = array(
                            'posts_per_page'   => -1,
                            'post_type' => array('product', 'product_variation'),
                            'post_status' => 'publish',
                            'category' => $listCats,
                        );
                        $products = wc_get_products($args);
                        if ( is_array($products) ) {
                            foreach($products as $product) {
                                $GetProductType = $product->get_type();
                                $GetProductID = $product->get_id();
                                $GetCurrentProcutStock = $product->get_stock_quantity();
                                /* Process Variable Product Starts */
                                if ($GetProductType == 'variable') {
                                    $Subargs = array(
                                        'posts_per_page'   => -1,
                                        'post_status' => 'publish',
                                        'post_type' => 'product_variation',
                                        'parent' => (int)$GetProductID,
                                    );
                                    $Subproducts = get_posts($Subargs);
                                    if ( is_array($Subproducts) ) {
                                        foreach($Subproducts as $SubProduct) {
                                            $SubProductID = $SubProduct->ID;
                                            $id[] = $SubProductID;
                                            $getSubProductStock = get_post_meta($SubProductID, '_stock', true );
                                            $oldStock[] = array(
                                                "osgProductID" => $SubProductID,
                                                "osgProductStock" => $getSubProductStock,
                                                "osgProductType" => "Variable",
                                                "osgParentProduct" => (int)$GetProductID,
                                            );
                                            // 1. Updating the stock quantity
                                            update_post_meta($SubProductID, '_stock', $OSGValQty);
                                            // 2. Updating the stock quantity
                                            update_post_meta( $SubProductID, '_stock_status', wc_clean( $out_of_stock_staus ) );
                                            update_post_meta( $GetProductID, '_stock_status', wc_clean( $out_of_stock_staus ) );
                                            // 3. Updating post term relationship
                                            wp_set_post_terms( $SubProductID, $out_of_stock_staus, 'product_visibility', true );
                                            // And finally (optionally if needed)
                                            wc_delete_product_transients( $SubProductID ); // Clear/refresh the variation cache
                                        }
                                    }
                                } else {
                                    $id[] = $GetProductID;
                                    $oldStock[] = array(
                                        "osgProductID" => $GetProductID,
                                        "osgProductStock" => $GetCurrentProcutStock,
                                        "osgProductType" => "Single"
                                    );
                                    $product->set_stock_quantity($OSGValQty);
                                    $product->set_stock_status($out_of_stock_staus);
                                    $product->save();
                                }
                                /* Process Variable Product Ends */
                            }
                        }
                    }
                    /* Get Main Procuts Categories Ends */
                    $CountProducts = count($id);
                    /*Process Transaction Start */
                    if ( isset($CountProducts) && ($CountProducts > 0)) {
                        $transArray = array(
                            "osg_TransactionCats" => $totalCategories,
                            "osg_TransactionProducts" => $CountProducts,
                            "osg_remarks" => $OSGTransRemarks,
                            "osg_History" => serialize($oldStock),
                            "osg_DateEncoded" => date("Y-m-d H:i:s")
                        );
                        $this->osg_productstock_history($transArray);
                    }
                    /*Process Transaction Ends */
                    $finalResult = "{$CountProducts} Product(s) have been updated.";
                } else {
                    $finalResult = "You didn't select any thing"; 
                }
                } else {
                    $finalResult = "Only Administrators can do this operation"; 
                }
                esc_html_e($finalResult, 'bulk-product-stock-manager' );
                wp_die();
            }

            /* Process Stock Data Ends */

            /* History Data Start*/
            function osg_productstock_history($arr = null) {
                if ( isset($arr) && is_array($arr) ) {
                    global $wpdb;
                    $tablename = $wpdb->prefix.'osgtransactions';
                    $wpdb->insert($tablename, $arr);
                }
            }
            /* History Data Ends*/

            /* Transaction Data Starts*/
            function osg_transactions_data() {
                echo $this->osg_initialheaderData();
                $result = null;
                wp_enqueue_style('osgbpsmDatatable', plugins_url('/assets/datatables.min.css', __FILE__) );
                wp_enqueue_script('osgbpsmDatatable', plugins_url('/assets/datatables.min.js', __FILE__), array('jquery') );
                
                echo "<div class='wrap'><div id='icon-options-general' class='icon32'><br></div>
                <h2>Stock Transactions</h2></div>";
                echo "<div class='alert' id='divOSGBPSM'>Click <strong>Restore Strock</strong> to restore from transactions</div>";
                $loadingImg = plugins_url('/assets/css/preloader.gif', __FILE__);
                global $wpdb;
                $tablename = $wpdb->prefix.'osgtransactions';
                $result = $wpdb->get_results("SELECT osg_id, osg_TransactionCats, osg_TransactionProducts, osg_remarks, osg_DateEncoded FROM {$tablename} ORDER BY osg_id DESC");
                $print_table = "<div class='osgDatatablebsm'><table id='osgbpsmTable' class='cell-border stripe display' style='width:100%'><thead>";
                $print_table .= "<th width='50%'>Remarks</th>";
                $print_table .= "<th>Total Products</th>";
                $print_table .= "<th>Total Categories</th>";
                $print_table .= "<th>DateEncoded</th>";
                $print_table .= "<th>Manage</th></thead><tbody>";
                if ( isset($result) && is_array($result) )  {
                    foreach($result as $data) {
                        $datedStore = date('d M Y h:i A', strtotime($data->osg_DateEncoded));
                        $print_table .= "<tr>";
                        $print_table .= "<td>{$data->osg_remarks}</td>";
                        $print_table .= "<td style='text-align:center;'>{$data->osg_TransactionProducts}</td>";
                        $print_table .= "<td style='text-align:center;'>{$data->osg_TransactionCats}</td>";
                        $print_table .= "<td style='text-align:center;'>{$datedStore}</td>";
                        $print_table .= "<td style='text-align:center;'><button id='osgbpsmRestore_{$data->osg_id}' class='button button-primary button-large'>Restore Stock</button></td>";
                        $print_table .= "</tr>";
                    }
                } else {
                    $print_table .= "<tr>";
                    $print_table .= "<td colspan='5'>No transaction history found!</td>";
                    $print_table .= "</tr>";
                }
                $print_table .= "</tbody></table><div class='loaderOsgDiv' id='loaderOsgDiv'>Please wait it will take sometime<br><img src='{$loadingImg}'></div></div>";
                echo $print_table;
                echo "<script>jQuery(document).ready(function(){jQuery('#osgbpsmTable').DataTable({'order': []})});</script>";
            }
            /* Transaction Data Ends*/

            /* Restore Function Start */
            function osg_processrestoreData() {
                $responseData = null;
                global $wpdb;
                $check_setvalue = (int)sanitize_text_field($_POST['restoreCatchID']);
                $ValidateNonce = sanitize_text_field($_REQUEST['osgbpsm_nonce']);
                if ( ( wp_verify_nonce( $ValidateNonce, $this->nonce ) ) && current_user_can('administrator') ) {
                    if ( isset($check_setvalue) && is_int($check_setvalue) ) {
                        $restoreDataID = trim($check_setvalue);
                        $oldStock = null;
                        $tablename = $wpdb->prefix.'osgtransactions';
                        $result = $wpdb->get_results("SELECT * FROM {$tablename} WHERE osg_id={$restoreDataID} LIMIT 1");
                        if ( isset($result) && is_array($result) ) {
                            $dataGetTrans = unserialize($result[0]->osg_History);
                            $totalCategories = $result[0]->osg_TransactionCats;
                            $CountProducts = $result[0]->osg_TransactionProducts;
                            $GetParentProduct = $result[0]->osgParentProduct;
                            foreach ($dataGetTrans as $data) {
                                $SubProductID = $data['osgProductID'];
                                $getSubProductStock = get_post_meta($SubProductID, '_stock', true );
                                $GetParentProduct = $data['osgParentProduct'];
                                if ( isset($data['osgParentProduct']) ) {
                                    $oldStock[] = array(
                                        "osgProductID" => $SubProductID,
                                        "osgProductStock" => $data['osgProductStock'],
                                        "osgProductType" => $data['osgProductType'],
                                        "osgParentProduct" => $data['osgParentProduct']  
                                    );
                                } else {
                                    $oldStock[] = array(
                                        "osgProductID" => $SubProductID,
                                        "osgProductStock" => $data['osgProductStock'],
                                        "osgProductType" => $data['osgProductType'],
                                    );
                                }

                                /* Restore Query Start */
                                $stockData = $data['osgProductStock'];
                                if ($stockData > 0) {
                                    $out_of_stock_staus = "instock";
                                } else {
                                    $out_of_stock_staus = "outofstock";
                                }
                                // 1. Updating the stock quantity
                                update_post_meta($SubProductID, '_stock', $stockData);
                                // 2. Updating the stock quantity
                                update_post_meta( $SubProductID, '_stock_status', wc_clean( $out_of_stock_staus ) );
                                if (isset($GetParentProduct) ) {
                                    update_post_meta( $GetParentProduct, '_stock_status', wc_clean( $out_of_stock_staus ) );
                                }
                                // And finally (optionally if needed)
                                wc_delete_product_transients( $SubProductID ); // Clear/refresh the variation cache
                                //$showme[] = array($GetParentProduct, $out_of_stock_staus);

                                /* Restore Query Ends */
                            }

                            /*Process Transaction Start */
                            $CountProducts = count($oldStock);
                            if ( isset($CountProducts) && ($CountProducts > 0)) {
                                $transArray = array(
                                    "osg_TransactionCats" => $totalCategories,
                                    "osg_TransactionProducts" => $CountProducts,
                                    "osg_remarks" => "Restore stock from the transaction history",
                                    "osg_History" => serialize($oldStock),
                                    "osg_DateEncoded" => date("Y-m-d H:i:s")
                                );
                                $this->osg_productstock_history($transArray);
                            }
                            /*Process Transaction Ends */
                            $finalResult = "{$CountProducts} Product(s) have been restored.";
                        }
                    }
                } else {
                    $finalResult = "Only Administrators can do this operation";   
                }
                esc_html_e($finalResult, 'bulk-product-stock-manager' );
                wp_die();
            }
            /* Restore Function Ends */

            /* Activatation Plugin Start */
            function OSG_BPSMGMT_activate() {
                //all processes of plugin activation goes here
                global $wpdb;
                $table_name = $wpdb->prefix."osgtransactions";
                    $sql = "CREATE TABLE IF NOT EXISTS ".$table_name."(
                        osg_id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                        osg_TransactionCats varchar(255) DEFAULT NULL,
                        osg_TransactionProducts varchar(255) DEFAULT NULL,
                        osg_remarks varchar(255) DEFAULT NULL,
                        osg_History longtext,
                        osg_DateEncoded datetime DEFAULT NULL
                      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
                $results = $wpdb->query($sql);
                flush_rewrite_rules();
            }
            /* Activatation Plugin Ends */

            /* Initial Header Data */
            function osg_initialheaderData() {
                $content = "<div class='osg_header_main_data'>";
                $content .= "<div class='osg_header_inner_heading'>Bulk Product Stock Manager <a href='https://outseller.net' target='_blank'>OutSeller Group</a></div>";
                $content .= "<div class='osg_header_inner_section'><div class='osg_inner_section_data'>";
                $content .= "Simple bulk product stock update manager.</div></div>";
                $content .= "</div>";
                return $content;
            }
            /* Initial Header Data Ends */

        } // End of class
    }
    register_uninstall_hook(__FILE__, 'plugin_uninstall');
    if ( class_exists('BulkProductStockManager') ) {
        $osgbpsm_plugin = new BulkProductStockManager;
        register_activation_hook(__FILE__, array($osgbpsm_plugin, 'OSG_BPSMGMT_activate') );
    }
}