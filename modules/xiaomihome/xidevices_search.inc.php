<?php
/*
* @version 0.1 (wizard)
*/
global $session;
if ($this->owner->name == 'panel') {
    $out['CONTROLPANEL'] = 1;
}
$qry = "1";
// search filters
// QUERY READY
global $save_qry;
if ($save_qry) {
    $qry = $session->data['xidevices_qry'];
} else {
    $session->data['xidevices_qry'] = $qry;
}
if (!$qry) $qry = "1";
$sortby_xidevices = "ID DESC";
$out['SORTBY'] = $sortby_xidevices;
// SEARCH RESULTS
$res = SQLSelect("SELECT * FROM xidevices WHERE $qry ORDER BY " . $sortby_xidevices);
if ($res[0]['ID']) {
    //paging($res, 100, $out); // search result paging
    $total = count($res);
    for ($i = 0; $i < $total; $i++) {
        // some action for every record if required
        $tmp = explode(' ', $res[$i]['UPDATED']);
        $res[$i]['UPDATED'] = fromDBDate($tmp[0]) . " " . $tmp[1];
        $commands = SQLSelect("SELECT * FROM xicommands WHERE DEVICE_ID=" . $res[$i]['ID'] . " AND TITLE!='report' AND TITLE!='heartbeat' AND TITLE!='write_ack' ORDER BY TITLE");
        if ($commands[0]['ID']) {
            $totalc = count($commands);
            for ($ic = 0; $ic < $totalc; $ic++) {
                $res[$i]['COMMANDS'] .= "<nobr>".$commands[$ic]['TITLE'] . ': <i>' . $commands[$ic]['VALUE'] . '</i>';
                if ($commands[$ic]['LINKED_OBJECT'] != '') {
                    $res[$i]['COMMANDS'] .= ' (' . $commands[$ic]['LINKED_OBJECT'];
                    if ($commands[$ic]['LINKED_PROPERTY'] != '') {
                        $res[$i]['COMMANDS'] .= '.' . $commands[$ic]['LINKED_PROPERTY'];
                    } elseif ($commands[$ic]['LINKED_METHOD'] != '') { 
                        $res[$i]['COMMANDS'] .= '.' . $commands[$ic]['LINKED_METHOD'];
                    }
                    $res[$i]['COMMANDS'] .= ')';
                }
                if ($commands[$ic]['TITLE'] == 'battery_level')
                {
                    $res[$i]['POWER'] = $commands[$ic]['VALUE'];
                    $res[$i]['POWER_WARNING'] = "success";
                    if ($res[$i]['POWER']<= 40)
                        $res[$i]['POWER_WARNING'] = "warning";
                    if ($res[$i]['POWER']<= 20)
                        $res[$i]['POWER_WARNING'] = "danger";
                }
                $res[$i]['COMMANDS'] .= ";</nobr> ";
            }
        }

    }
$out['RESULT'] = $res;
}
