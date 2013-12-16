#!/usr/bin/php
<?php
/* Copyright (C) 2012-2013 Raphaël Doursenaud <rdoursenaud@gpcsolutions.fr>
 *
 * Product stock import script for Dolibarr
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/* This script uses a CSV file with 2 columns and a header line
+---------------+
| Ref     | Qty |
+---------+-----+
| string  | int |
+---------------+
*/

$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path = dirname(__FILE__) . '/';

// Test if batch mode
if (substr($sapi_type, 0, 3) == 'cgi') {
    echo "Error: You are using PHP for CGI. To execute ",
    $script_file,
    " from command line, you must use PHP for CLI mode.\n";
    exit;
}

// Global variables
$version = '0.1.0';
$error = 0;

// Include Dolibarr environment
require_once $path . "../../htdocs/master.inc.php";
// After this $db, $mysoc, $langs and $conf->entity are defined. Opened database handler will be closed at end of file.

require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/stock/class/mouvementstock.class.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.product.class.php';


@set_time_limit(0); // No timeout for this script

function printLine($line)
{
    echo "Line ", $line, ": ";
}

// TODO: infoline
echo "***** ", $script_file, " (", $version, ") *****\n";
if (!isset($argv[1])) { // Check parameters
    echo "Usage: ", $script_file, " file.csv [username] [entity] [warehouse]\n";
    exit;
}
echo 'Processing ', $argv[1], "\n";

// Parse arguments
if (!isset($argv[2])) {
    $username = 'admin';
} else {
    $username = $argv[2];
}
if (!isset($argv[3])) {
    $conf->entity = 1;
} else {
    $conf->entity = $argv[3];
}
if (!isset($argv[4])) {
    $warehouse_id = 1;
} else {
    $warehouse_id = $argv[4];
}

// Load user and its permissions
$result = $user->fetch('', $username); // Load user for login 'admin'. Comment line to run as anonymous user.
if (!$result > 0) {
    dol_print_error('', $user->error . " " . $username);
    exit;
}
$user->getrights();
unset($result);

$fname = $argv[1];

// Start of transaction
$db->begin();

if (($handle = fopen($fname, 'r')) !== false) {
    $line = 0; // Line counter
    $count = 0; // Element counter
    while (($data = fgetcsv($handle)) !== false) {
        $line++;
        if ($line == 1) {
            continue; // Ignores first line
            // TODO: Test that first line is what we expect
            // TODO: Make first line skipping optional
        }

        // Extract data
        $product_ref = trim($data[0]);
        $product_qty = trim($data[1]);

        // Prepare objects
        $product = new Product($db);
        $stock = new MouvementStock($db);
        $supplier_product = new ProductFournisseur($db);

        // Skip line if stock is 0
        if (empty($product_qty)) {
            printLine($line);
            echo "Product stock is empty, null or zero, skipping\n";
            continue;
        }

        // Search and retrieve product
        $sql = 'SELECT rowid';
        $sql .= ' ';
        $sql .= 'FROM ' . MAIN_DB_PREFIX . 'product';
        $sql .= ' ';
        $sql .= 'WHERE ref="' . $product_ref . '"';
        $sql .= ' ';
        $sql .= 'AND entity IN (' . $conf->entity . ')';
        $resql = $db->query($sql);
        unset($sql);
        if ($resql && ($db->num_rows($resql) != 0)) {
            // FIXME: Check unicity !!!
            $res = $db->fetch_array($resql);
            $product_id = $res['rowid'];
            $product->fetch($product_id);
            $db->free($resql);
            unset($res);
        } else {
            printLine($line);
            echo "Warning: product not found, skipping\n";
            continue;
        }
        unset($resql);

        // Get purchase price to set PMP
        $supplier_prices = $supplier_product->list_product_fournisseur_price($product->id);
        $last_price_index = count($supplier_prices) - 1;

        // Add stock mouvement
        $result = $stock->_create(
            $user,
            $product->id,
            $warehouse_id,
            $product_qty,
            3,
            $supplier_prices[$last_price_index]->fourn_price,
            "Inventaire décembre 2013"
        );

        if ($result > 0) {
            $count++;
        } else {
            echo "Error: unable to create stock movement for " . $product->ref . "\n";
            $error++;
        }

    }
    fclose($handle);
} else {
    $error++;
    echo "Unable to access file <", $fname, ">\n";
}

if ($error == 0) {
    $db->commit();
    echo($line - 1), " lines parsed\n";
    echo($count), " product stock imported\n";
    echo "Import complete\n";
} else {
    echo "Error ", $error, "\n";
    echo "Import aborted";
    if (isset($line)) {
        echo " at line ", $line, "\n";
    }
    echo "Nothing imported\n";
    $db->rollback();
}

$db->close(); // Close database handler

return $error;
