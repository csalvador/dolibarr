<?php
/* Copyright (C) 2013 Raphaël Doursenaud <rdoursenaud@gpcsolutions.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

//if (! defined('NOREQUIREUSER'))  define('NOREQUIREUSER','1');
//if (! defined('NOREQUIREDB'))    define('NOREQUIREDB','1');
//if (! defined('NOREQUIRESOC'))   define('NOREQUIRESOC','1');
//if (! defined('NOREQUIRETRAN'))  define('NOREQUIRETRAN','1');
//if (! defined('NOCSRFCHECK'))    define('NOCSRFCHECK','1');			// Do not check anti CSRF attack test
//if (! defined('NOSTYLECHECK'))   define('NOSTYLECHECK','1');			// Do not check style html tag into posted data
//if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL','1');		// Do not check anti POST attack test
//if (! defined('NOREQUIREMENU'))  define('NOREQUIREMENU','1');			// If there is no need to load and show top and left menu
//if (! defined('NOREQUIREHTML'))  define('NOREQUIREHTML','1');			// If we don't need to load the html.form.class.php
//if (! defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX','1');
//if (! defined("NOLOGIN"))        define("NOLOGIN",'1');				// If this page is public (can be called outside logged session)

require './main.inc.php';

require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';

global $langs, $bc;

$langs->load("companies");
$langs->load("other");

// Get parameters
$start_date = GETPOST('start_date');
$end_date = GETPOST('end_date');


$start_time = dol_mktime(
    0, 0, 0,
    $_REQUEST["start_datemonth"], $_REQUEST["start_dateday"], $_REQUEST["start_dateyear"]
);
$end_time = dol_mktime(
    23, 59, 59,
    $_REQUEST["end_datemonth"], $_REQUEST["end_dateday"], $_REQUEST["end_dateyear"]
);


// Protection if external user
if ($user->societe_id > 0) {
    accessforbidden();
}

// FIXME: add rights

/*******************************************************************
 * ACTIONS
 ********************************************************************/
$mesg = ""; // Dropdown message

// Look for reverse period selection
if ($end_time < $start_time) {
    $mesg = "<font class=\"warning\">" . $langs->trans("ForwardPeriod") . "</font>";
    // Don't do anything
    $action = '';
}

// Period management
if ($start_date === '' && $end_date === '') {
    // Defaul period is the previous month
    $current_year = strftime("%Y", dol_now());
    $current_month = strftime("%m", dol_now());
    $past_month_year = $current_year;
    if ($current_month === 0) {
        $current_month = 12;
        $past_month_year --;
    }

    $start_date = dol_get_first_day($past_month_year, $current_month, false);
    $end_date = dol_get_last_day($past_month_year, $current_month, false);
} else {
    // We get the POST dates
    $start_date = $start_time;
    $end_date = $end_time;
}

/***************************************************
 * VIEW
 ****************************************************/
// FIXME: find a better page name
llxHeader('', 'Rapport', '');

// Information message
dol_htmloutput_mesg($mesg);

$form = new Form($db);

// Period selection
echo '<form method="POST" id="sort">';
echo '	<fieldset>';
echo '		<legend>' . $langs->trans("Period") . '</legend>';

echo $form->select_date($start_date, 'start_date', 0, 0, 0, '', 1, 0, 1);
echo' - ';
echo $form->select_date($end_date, 'end_date', 0, 0, 0, '', 1, 0, 1);

echo '	</fieldset>';
echo '<div class="tabsAction">';
echo '	<input class="butAction" type="submit" value="' . $langs->trans("Generate") . '">';
echo '</div>';
echo '<form>';

function getVatRates()
{
    $select =  'taux';
    $sql_vat_rates = 'SELECT '. $select;
    $sql_vat_rates .= ' FROM ' . MAIN_DB_PREFIX . 'c_tva';
    $sql_vat_rates .= ' WHERE fk_pays = \'1\' AND active = \'1\'';
    $sql_vat_rates .= ' ORDER BY ' . $select . ' ASC;';
    return sqlRequest($sql_vat_rates, $select);
}

function getPaymentMethods()
{
    $select = 'libelle';
    $sql_payments_method = 'SELECT ' . $select;
    $sql_payments_method .= ' FROM ' . MAIN_DB_PREFIX . 'c_paiement';
    $sql_payments_method .= ' WHERE active = \'1\' AND (type = \'0\' OR type = \'2\');';
    return sqlRequest($sql_payments_method, $select);
}

function getVatAmountAndRateByDate($start_date, $end_date) {
    global $conf, $db;

    $sql_vat_total = 'SELECT ';
    $sql_vat_total .= 'f.datef AS date,';
    $sql_vat_total .= 'fd.tva_tx AS rate,';
    $sql_vat_total .= 'SUM(fd.total_ttc) AS vat';
    $sql_vat_total .= ' FROM ';
    $sql_vat_total .= MAIN_DB_PREFIX . 'facture AS f';
    $sql_vat_total .= ' LEFT JOIN ';
    $sql_vat_total .= MAIN_DB_PREFIX . 'facturedet AS fd ON fd.fk_facture = f.rowid';
    // Filter milestones out
    $sql_vat_total .= ' AND ';
    $sql_vat_total .= 'fd.special_code = \'0\'';
    $sql_vat_total .= ' WHERE ';
    $sql_vat_total .= 'f.type = \'0\'';
    $sql_vat_total .= ' AND ';
    $sql_vat_total .= 'f.paye = \'1\'';
    $sql_vat_total .= ' AND ';
    $sql_vat_total .= 'f.fk_statut = \'2\'';
    $sql_vat_total .= ' AND ';
    $sql_vat_total .= 'f.entity = \'' . $conf->entity . '\'';
    $sql_vat_total .= ' AND ';
    $sql_vat_total .= 'f.datef >= \'' .$db->idate($start_date) . '\'';
    $sql_vat_total .= ' AND ';
    $sql_vat_total .= 'f.datef <= \'' . $db->idate($end_date) . '\'';
    $sql_vat_total .= ' GROUP BY ';
    $sql_vat_total .= 'date, fd.tva_tx';
    $sql_vat_total .= ' ORDER BY ';
    $sql_vat_total .= 'date;';

    return sqlRequest($sql_vat_total);
}

function getPaymentAmountAndMethodByDate($start_date, $end_date) {
    global $conf, $db;

    $sql_payment_total = 'SELECT ';
    $sql_payment_total .= 'f.datef AS date,';
    $sql_payment_total .= 'SUM(pf.amount) AS payment,';
    $sql_payment_total .= 'pt.libelle AS method';
    $sql_payment_total .= ' FROM ';
    $sql_payment_total .= 'llx_facture AS f';
    $sql_payment_total .= ' LEFT JOIN ';
    $sql_payment_total .= 'llx_paiement_facture AS pf ON pf.fk_facture = f.rowid';
    $sql_payment_total .= ' LEFT JOIN ';
    $sql_payment_total .= 'llx_paiement AS p ON p.rowid = pf.fk_paiement';
    $sql_payment_total .= ' LEFT JOIN ';
    $sql_payment_total .= 'llx_c_paiement AS pt ON pt.id = p.fk_paiement AND pt.active = \'1\' AND (pt.type = \'0\' OR pt.type = \'2\')';
    $sql_payment_total .= ' WHERE ';
    $sql_payment_total .= 'f.type = \'0\'';
    $sql_payment_total .= ' AND ';
    $sql_payment_total .= 'f.paye = \'1\'';
    $sql_payment_total .= ' AND ';
    $sql_payment_total .= 'f.fk_statut = \'2\'';
    $sql_payment_total .= ' AND ';
    $sql_payment_total .= 'f.entity = \'' . $conf->entity . '\'';
    $sql_payment_total .= ' AND ';
    $sql_payment_total .= 'f.datef >= \'' .$db->idate($start_date) . '\'';
    $sql_payment_total .= ' AND ';
    $sql_payment_total .= 'f.datef <= \'' . $db->idate($end_date) . '\'';
    $sql_payment_total .= ' GROUP BY ';
    $sql_payment_total .= 'date, method';
    $sql_payment_total .= ' ORDER BY ';
    $sql_payment_total .= 'date;';

    return sqlRequest($sql_payment_total);
}

function getReportValues($start_date, $end_date, $rates, $methods) {
    $sql_vat = getVatAmountAndRateByDate($start_date, $end_date);
    $sql_payment = getPaymentAmountAndMethodByDate($start_date, $end_date);

    // Combine both
    //$values = array('date' => 'amounts');
    //$vat = array('rate' => 'amount');
    $date = null;

    foreach ($sql_vat as $v) {
        if ($date != $v->date) {
            foreach ($rates as $r) {
                $vat[price($r)] = price('0');
            }
        }
        $values['total'][price($v->rate)] += $v->vat;
        $vat[price($v->rate)] = price($v->vat);
        $values[$v->date]['vat'] = $vat;
        $date = $v->date;
    }
    //$payment = array('method' => 'amount');
    foreach ($sql_payment as $p) {
        if ($date != $p->date) {
            foreach ($methods as $m) {
                $payment[$m] = price('0');
            }
        }
        $values['total'][$p->method] += $p->payment;
        $payment[$p->method] = price($p->payment);
        $values[$p->date]['payment'] = $payment;
        $date = $p->date;
    }



    return $values;
}

function sqlRequest($sql, $field = null)
{
    global $db;
    $result = array();

    dol_syslog(__FILE__ . " sql=" . $sql, LOG_DEBUG);
    $resql = $db->query($sql);
    if ($resql) {
        $num = $db->num_rows($resql);
        $i = 0;;
        if ($num) {
            while ($i < $num) {
                $obj = $db->fetch_object($resql);
                if ($obj) {
                    if ($field) {
                        $result[$i] = $obj->$field;
                    } else {
                        $result[$i] = $obj;
                    }
                }
                $i++;
            }
        }
        return $result;
    } else {
        dol_print_error($db);
    }
}


$rates = getVatRates();
$methods = getPaymentMethods();
$values = getReportValues($start_date, $end_date, $rates, $methods);

// TODO: Report
if ($values) {
    print '<table class="noborder">' . "\n";

    // Headers
    print '<tr class="liste_titre">';
    print_liste_field_titre(
        $langs->trans('Date'),
        $_SERVER['PHP_SELF']
    );
    foreach ($rates as $r) {
        print_liste_field_titre(
            $langs->trans('VAT') . ' ' . price($r) . '%',
            $_SERVER['PHP_SELF'],
            null,
            null,
            null,
            'align="right"'
        );
    }
    foreach ($methods as $m) {
        print_liste_field_titre(
            $langs->trans($m),
            $_SERVER['PHP_SELF'],
            null,
            null,
            null,
            'align="right"'
        );
    }
    print '</tr>';

    // Values
    foreach ($values as $date => $numbers) {
        // Line background color management
        $var=!$var;
        // Skip the total
        if ($date == 'total') {
            continue;
        }
        print '<tr ' . $bc[$var] . '>';
        print '<td>';
        print dol_print_date($date, 'day');
        print '</td>';
        foreach ($rates as $r) {
            print '<td align="right">' . $numbers['vat'][price($r)] . '</td>';
        }
        foreach ($methods as $m) {
            print '<td align="right">' . $numbers['payment'][$m] . '</td>';
        }
        print '</tr>';
    }

    // Total
    print '<tr class="liste_total">';
    print '<td>' . $langs->trans("Total") . '</td>';
    foreach ($rates as $r) {
        print '<td align="right">';
        if ($values['total'][price($r)]) {
            print $values['total'][price($r)];
        } else {
            print price('0');
        }
        print '</td>';
    }
    foreach ($methods as $m) {
        print '<td align="right">';
        if ($values['total'][$m]) {
            print $values['total'][$m];
        } else {
            print price('0');
        }
        print '</td>';
    }
    print '</tr>';

    print '</table>' . "\n";
} else {
    print $langs->trans("NoData");
}

// End of page
llxFooter();
$db->close();
