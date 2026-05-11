<?php
/**
 *
 * Copyright (C) 2007,2008  Arie Nugraha (dicarve@yahoo.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

/* Overdues Report */

// key to authenticate
define('INDEX_AUTH', '1');

// main system configuration
require '../../../../sysconfig.inc.php';
// IP based access limitation
require LIB . 'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-circulation');
// start the session
require SB . 'admin/default/session.inc.php';
require SB . 'admin/default/session_check.inc.php';
require SIMBIO . 'simbio_UTILS/simbio_date.inc.php';
require MDLBS . 'membership/member_base_lib.inc.php';
require MDLBS . 'circulation/circulation_base_lib.inc.php';

// privileges checking
$can_read = utility::havePrivilege('circulation', 'r') || utility::havePrivilege('reporting', 'r');
$can_write = utility::havePrivilege('circulation', 'w') || utility::havePrivilege('reporting', 'w');

if (!$can_read) {
    die('<div class="errorBox">' . __('You don\'t have enough privileges to access this area!') . '</div>');
}

require SIMBIO . 'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO . 'simbio_GUI/form_maker/simbio_form_element.inc.php';
require SIMBIO . 'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO . 'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require MDLBS . 'reporting/report_dbgrid.inc.php';

$page_title = 'Overdued List Report';
$reportView = false;
$num_recs_show = 20;
if (isset($_GET['reportView'])) {
    $reportView = true;
}

if (!$reportView) {
    ?>
    <!-- filter -->
    <div>
        <div class="per_title">
            <h2><?php echo __('Overdued List'); ?></h2>
        </div>
        <div class="infoBox">
          <?php echo __('Report Filter'); ?>
        </div>
        <div class="sub_section">
            <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" target="reportView">
                <div id="filterForm">
                    <div class="divRow">
                        <div class="divRowLabel"><?php echo __('Member ID') . '/' . __('Member Name'); ?></div>
                        <div class="divRowContent">
                          <?php
                            echo simbio_form_element::textField('text', 'id_name', '', 'class="form-control" style="width: 50%"');
                                ?>
                        </div>
                    </div>
                    <div class="form-group divRow">
                        <div class="divRowContent">
                            <div>
                                <label style="width: 195px;"><?php echo __('Loan Date From'); ?></label>
                                <label><?php echo __('Loan Date Until'); ?></label>
                            </div>
                            <div id="range">
                                <input type="text" name="startDate" value="2000-01-01">
                                <span><?= __('to') ?></span>
                                <input type="text" name="untilDate" value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                    </div>
                    <div class="divRow">
                        <div class="divRowLabel"><?php echo __('Record each page'); ?></div>
                        <div class="divRowContent"><input type="text" name="recsEachPage" class="form-control col-1" size="3" maxlength="3" value="<?php echo $num_recs_show; ?>"/> <?php echo __('Set between 20 and 200'); ?>
                        </div>
                    </div>
                </div>
                <div style="padding-top: 10px; clear: both;">
                    <input type="button" name="moreFilter" class="btn btn-default"  value="<?php echo __('Show More Filter Options'); ?>"/>
                    <input type="submit" class="btn btn-primary" name="applyFilter" value="<?php echo __('Apply Filter'); ?>"/>
                    <input type="hidden" name="reportView" value="true"/>
                </div>
            </form>
        </div>
    </div>
    <script>
        $(document).ready(function(){
            const elem = document.getElementById('range');
            const dateRangePicker = new DateRangePicker(elem, {
                language: '<?= substr($sysconf['default_lang'], 0,2) ?>',
                format: 'yyyy-mm-dd',
            });
        })
    </script>
    <!-- filter end -->
    <div class="dataListHeader" style="padding: 3px;"><span id="pagingBox"></span></div>
    <iframe name="reportView" id="reportView" src="<?php echo $_SERVER['PHP_SELF'] . '?reportView=true'; ?>"
            frameborder="0" style="width: 100%; height: 500px;"></iframe>
  <?php
} else {
    ob_start();
    // table spec
    $table_spec = 'member AS m
      LEFT JOIN loan AS l ON m.member_id=l.member_id';

    // create datagrid
    $reportgrid = new report_datagrid();
    $reportgrid->setSQLColumn('m.member_id AS \'' . __('Member ID') . '\'');
    $reportgrid->setSQLorder('MAX(l.due_date) DESC');
    $reportgrid->sql_group_by = 'm.member_id';

    $overdue_criteria = ' (l.is_lent=1 AND l.is_return=0 AND TO_DAYS(due_date) < TO_DAYS(\'' . date('Y-m-d') . '\')) ';
    // is there any search
    if (isset($_GET['id_name']) and $_GET['id_name']) {
        $keyword = $dbs->escape_string(trim($_GET['id_name']));
        $words = explode(' ', $keyword);
        if (count($words) > 1) {
            $concat_sql = ' (';
            foreach ($words as $word) {
                $concat_sql .= " (m.member_id LIKE '%$word%' OR m.member_name LIKE '%$word%') AND";
            }
            // remove the last AND
            $concat_sql = substr_replace($concat_sql, '', -3);
            $concat_sql .= ') ';
            $overdue_criteria .= ' AND ' . $concat_sql;
        } else {
            $overdue_criteria .= " AND m.member_id LIKE '%$keyword%' OR m.member_name LIKE '%$keyword%'";
        }
    }
    // loan date
    if (isset($_GET['startDate']) and isset($_GET['untilDate'])) {
        $date_criteria = ' AND (TO_DAYS(l.loan_date) BETWEEN TO_DAYS(\'' . $_GET['startDate'] . '\') AND
          TO_DAYS(\'' . $_GET['untilDate'] . '\'))';
        $overdue_criteria .= $date_criteria;
    }
    if (isset($_GET['recsEachPage'])) {
        $recsEachPage = (integer) $_GET['recsEachPage'];
        $num_recs_show = ($recsEachPage >= 5 && $recsEachPage <= 200) ? $recsEachPage : $num_recs_show;
    }
    $reportgrid->setSQLCriteria($overdue_criteria);

    // set table and table header attributes
    $reportgrid->table_attr = 'align="center" class="dataListPrinted" cellpadding="5" cellspacing="0"';
    $reportgrid->table_header_attr = 'class="dataListHeaderPrinted"';
    $reportgrid->column_width = array('1' => '80%');

    // callback function to show overdued list
    function showOverduedList($obj_db, $array_data)
    {
        global $date_criteria, $sysconf;

        $circulation = new circulation($obj_db, $array_data[0]);
        $circulation->ignore_holidays_fine_calc = $sysconf['ignore_holidays_fine_calc'];
        $circulation->holiday_dayname = $_SESSION['holiday_dayname'];
        $circulation->holiday_date = $_SESSION['holiday_date'];

        // member name
        $member_q = $obj_db->query('SELECT m.member_name, m.member_email, m.member_phone, m.member_mail_address, mmt.fine_each_day 
                                           FROM member m 
                                           LEFT JOIN mst_member_type mmt on m.member_type_id = mmt.member_type_id
                                           WHERE m.member_id=\'' . $array_data[0] . '\'');
        $member_d = $member_q->fetch_row();
        $member_name = $member_d[0];
        $member_mail_address = $member_d[3];
        unset($member_q);

        $ovd_title_q = $obj_db->query('SELECT l.loan_id, l.item_code, i.price, i.price_currency,
          b.title, l.loan_date,
          l.due_date, (TO_DAYS(DATE(NOW()))-TO_DAYS(due_date)) AS \'Overdue Days\', mlr.fine_each_day
          FROM loan AS l
              LEFT JOIN item AS i ON l.item_code=i.item_code
              LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
              LEFT JOIN mst_loan_rules mlr on l.loan_rules_id = mlr.loan_rules_id
          WHERE (l.is_lent=1 AND l.is_return=0 AND TO_DAYS(due_date) < TO_DAYS(\'' . date('Y-m-d') . '\')) AND l.member_id=\'' . $array_data[0] . '\'' . (!empty($date_criteria) ? $date_criteria : ''));
        $_buffer = '<div style="font-weight: bold; color: black; font-size: 10pt; margin-bottom: 3px;">' . $member_name . ' (' . $array_data[0] . ')</div>';
        $_buffer .= '<div style="color: black; font-size: 10pt; margin-bottom: 3px;">' . $member_mail_address . '</div>';
       $_buffer .= '<div style="font-size: 10pt; margin-bottom: 3px;"><div id="' . $array_data[0] . 'emailStatus"></div>' . __('E-mail') . ': <a href="mailto:' . $member_d[1] . '">' . $member_d[1] . '</a> - <a class="usingAJAX btn btn-outline-danger btn-sm" style="vertical-align: middle; display: inline-flex; align-items: center; gap: 5px;" href="' . MWB . 'membership/overdue_mail.php' . '" postdata="memberID=' . $array_data[0] . '" loadcontainer="' . $array_data[0] . 'emailStatus"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>&nbsp;' . __('Send Notification e-mail') . '</a> <a class="usingAJAX btn btn-success btn-sm" style="margin-left: 5px; vertical-align: middle; display: inline-flex; align-items: center; gap: 5px; padding-left: 8px; padding-right: 8px;" href="' . MWB . 'membership/overdue_wa.php' . '" postdata="memberID=' . $array_data[0] . '" loadcontainer="' . $array_data[0] . 'emailStatus"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 448 512"><path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"/></svg>&nbsp;' . __('Kirim Pemberitahuan Whatsapp') . '</a> - ' . __('Phone Number') . ': ' . $member_d[2] . '</div>';
$_buffer .= '<table width="100%" cellspacing="0">';

        while ($ovd_title_d = $ovd_title_q->fetch_assoc()) {

            //calculate Fines
            $overdue_days = $circulation->countOverdueValue($ovd_title_d['loan_id'], date('Y-m-d'))['days'];
            // because SLiMS have a grace periode feature in circulation modules,
            // make sure $overdue_days is numeric or not, if not then set it to 0
            // or if its bool then cast to integer
            $overdue_days = !is_numeric($overdue_days) ? 0 : (int)$overdue_days;
            $fines = currency($overdue_days * $member_d[4]);
            if (!is_null($ovd_title_d['fine_each_day'])) $fines = $overdue_days * $ovd_title_d['fine_each_day'];
            // format number
            $overdue_days = number_format($overdue_days, '0', ',', '.');

            $_buffer .= '<tr>';
            $_buffer .= '<td valign="top" width="10%">' . $ovd_title_d['item_code'] . '</td>';
            $_buffer .= '<td valign="top" width="40%">' . $ovd_title_d['title'] . '<div>' . __('Book Price') . ': ' . currency($ovd_title_d['price']) . '</div></td>';
            $_buffer .= '<td width="20%"><div>' . __('Overdue') . ': ' . $overdue_days . ' ' . __('day(s)') . '</div><div>'.__('Fines').': '.$fines.'</div></td>';
            $_buffer .= '<td width="30%">' . __('Loan Date') . ': ' . $ovd_title_d['loan_date'] . ' &nbsp; ' . __('Due Date') . ': ' . $ovd_title_d['due_date'] . '</td>';
            $_buffer .= '</tr>';
        }
        $_buffer .= '</table>';
        return $_buffer;
    }

    // modify column value
    $reportgrid->modifyColumnContent(0, 'callback{showOverduedList}');

    // put the result into variables
    echo $reportgrid->createDataGrid($dbs, $table_spec, $num_recs_show);

    ?>
    <script type="text/javascript" src="<?php echo JWB . 'jquery.js'; ?>"></script>
    <script type="text/javascript" src="<?php echo JWB . 'updater.js'; ?>"></script>
    <script type="text/javascript">
        // registering event for send email button
        $(document).ready(function () {
            parent.$('#pagingBox').html('<?php echo str_replace(array("\n", "\r", "\t"), '', $reportgrid->paging_set) ?>');
            $('a.usingAJAX').click(function (evt) {
                evt.preventDefault();
                var anchor = $(this);
                // get anchor href
                var url = anchor.attr('href');
                var postData = anchor.attr('postdata');
                var loadContainer = anchor.attr('loadcontainer');
                if (loadContainer) {
                    container = jQuery('#' + loadContainer);
                    container.html('<div class="alert alert-info"><?= __('Please wait') ?>....</div>');
                }
                // set ajax
                if (postData) {
                    container.simbioAJAX(url, {method: 'post', addData: postData});
                } else {
                    container.simbioAJAX(url, {addData: {ajaxload: 1}});
                }
            });
        });
    </script>
  <?php

    $content = ob_get_clean();
    // include the page template
    require SB . '/admin/' . $sysconf['admin_template']['dir'] . '/printed_page_tpl.php';
}
