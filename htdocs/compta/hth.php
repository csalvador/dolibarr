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

require '../main.inc.php';

require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';

global  $bc, $db, $langs, $user;

$langs->load("companies");
$langs->load("other");
$langs->load("hth");

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

/**
 * List available VAT rates
 *
 * @return array|null
 */
function getVatRates()
{
    $select =  'taux';
    $sql_vat_rates = 'SELECT '. $select;
    $sql_vat_rates .= ' FROM ' . MAIN_DB_PREFIX . 'c_tva';
    $sql_vat_rates .= ' WHERE fk_pays = \'1\' AND active = \'1\'';
    $sql_vat_rates .= ' ORDER BY ' . $select . ' ASC;';
    return sqlQuery($sql_vat_rates, $select);
}

/**
 * List available payment methods
 *
 * @return array|null
 */
function getPaymentMethods()
{
    $select = 'libelle';
    $sql_payments_method = 'SELECT ' . $select;
    $sql_payments_method .= ' FROM ' . MAIN_DB_PREFIX . 'c_paiement';
    $sql_payments_method .= ' WHERE active = \'1\' AND (type = \'0\' OR type = \'2\');';
    return sqlQuery($sql_payments_method, $select);
}

/**
 * List all VAT amounts within the specified boundaries
 *
 * @param timestamp $start_date Period start
 * @param timestamp $end_date   Period end
 *
 * @return array|null
 */
function getVatAmountAndRateByDate($start_date, $end_date)
{
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

    return sqlQuery($sql_vat_total);
}

/**
 * List all payments within the specified boundaries
 *
 * @param timestamp $start_date Period start
 * @param timestamp $end_date   Period end
 *
 * @return array|null
 */
function getPaymentAmountAndMethodByDate($start_date, $end_date)
{
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
    $sql_payment_total .= 'llx_c_paiement AS pt';
    $sql_payment_total .= ' ON ';
    $sql_payment_total .= 'pt.id = p.fk_paiement AND pt.active = \'1\' AND (pt.type = \'0\' OR pt.type = \'2\')';
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

    return sqlQuery($sql_payment_total);
}

/**
 * Build report data
 *
 * @param timestamp $start_date Period start
 * @param timestamp $end_date   Period end
 * @param array     $rates      VAT rates of interest
 * @param array     $methods    Payment methods of interest
 *
 * @return null
 */
function getReportValues($start_date, $end_date, $rates, $methods)
{
    $sql_vat = getVatAmountAndRateByDate($start_date, $end_date);
    $sql_payment = getPaymentAmountAndMethodByDate($start_date, $end_date);

    // Combine both
    $values = null; //array('date' => 'amounts');
    $vat = null; // array('rate' => 'amount');
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
    $payment = null;// array('method' => 'amount');
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

/**
 * Simple SQL query
 *
 * @param string $sql   Query string
 * @param null   $field Result field to extract
 *
 * @return array|null
 */
function sqlQuery($sql, $field = null)
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
        return null;
    }
}

// Protection
if ($user->societe_id > 0
    || ! $user->rights->compta->resultat->lire
) {
    accessforbidden();
}

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
$report_name = $langs->Trans("Report");

llxHeader('', $report_name, '');

// Information message
dol_htmloutput_mesg($mesg);

$form = new Form($db);

if (GETPOST("optioncss") !== 'print') {
    echo '<div class="warning">',
        $langs->trans("CreditNotesDepositsAndPartialPaymentsNotSupported"),
        '</div>';

    // Period selection
    // TODO: add presets for day, month and quarter
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
} else {
    echo '<h1>',
        $report_name,
        '</h1>',
        '<br>',
        dol_print_date($start_date, 'daytext'), ' - ', dol_print_date($end_date, 'daytext');
}

$rates = getVatRates();
$methods = getPaymentMethods();
$values = getReportValues($start_date, $end_date, $rates, $methods);

// TODO: add per line verifications
// TODO: add total verifications

// Report
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
