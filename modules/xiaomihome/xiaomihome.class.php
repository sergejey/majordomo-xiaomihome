<?php
/**
 * XiaomiHome
 * @package project
 * @author Wizard <sergejey@gmail.com>
 * @copyright http://majordomo.smartliving.ru/ (c)
 * @version 0.1 (wizard, 17:01:07 [Jan 28, 2017])
 */
//
// https://github.com/louisZL/lumi-gateway-local-api
// https://github.com/illxi/lumi-gateway-local-api (english)
// https://github.com/lazcad/homeassistant/blob/master/components/xiaomi.py
// https://github.com/illxi/lumi-gateway-local-api/blob/master/device_read_write.md
// https://github.com/Danielhiversen/homeassistant/pull/6 -- ringtone support

Define('XIAOMI_MULTICAST_PORT', 9898);
Define('XIAOMI_MULTICAST_ADDRESS', '224.0.0.50');


class xiaomihome extends module
{
    /**
     * xiaomihome
     *
     * Module class constructor
     *
     * @access private
     */
    function xiaomihome()
    {
        $this->name = "xiaomihome";
        $this->title = "XiaomiHome";
        $this->module_category = "<#LANG_SECTION_DEVICES#>";
        $this->checkInstalled();
    }

    /**
     * saveParams
     *
     * Saving module parameters
     *
     * @access public
     */
    function saveParams($data = 0)
    {
        $p = array();
        if (IsSet($this->id)) {
            $p["id"] = $this->id;
        }
        if (IsSet($this->view_mode)) {
            $p["view_mode"] = $this->view_mode;
        }
        if (IsSet($this->edit_mode)) {
            $p["edit_mode"] = $this->edit_mode;
        }
        if (IsSet($this->data_source)) {
            $p["data_source"] = $this->data_source;
        }
        if (IsSet($this->tab)) {
            $p["tab"] = $this->tab;
        }
        return parent::saveParams($p);
    }

    /**
     * getParams
     *
     * Getting module parameters from query string
     *
     * @access public
     */
    function getParams()
    {
        global $id;
        global $mode;
        global $view_mode;
        global $edit_mode;
        global $data_source;
        global $tab;
        if (isset($id)) {
            $this->id = $id;
        }
        if (isset($mode)) {
            $this->mode = $mode;
        }
        if (isset($view_mode)) {
            $this->view_mode = $view_mode;
        }
        if (isset($edit_mode)) {
            $this->edit_mode = $edit_mode;
        }
        if (isset($data_source)) {
            $this->data_source = $data_source;
        }
        if (isset($tab)) {
            $this->tab = $tab;
        }
    }

    /**
     * Run
     *
     * Description
     *
     * @access public
     */
    function run()
    {
        global $session;
        $out = array();
        if ($this->action == 'admin') {
            $this->admin($out);
        } else {
            $this->usual($out);
        }
        if (IsSet($this->owner->action)) {
            $out['PARENT_ACTION'] = $this->owner->action;
        }
        if (IsSet($this->owner->name)) {
            $out['PARENT_NAME'] = $this->owner->name;
        }
        $out['VIEW_MODE'] = $this->view_mode;
        $out['EDIT_MODE'] = $this->edit_mode;
        $out['MODE'] = $this->mode;
        $out['ACTION'] = $this->action;
        $out['DATA_SOURCE'] = $this->data_source;
        $out['TAB'] = $this->tab;
        $this->data = $out;
        $p = new parser(DIR_TEMPLATES . $this->name . "/" . $this->name . ".html", $this->data, $this);
        $this->result = $p->result;
    }

    function processMessage($message, $ip)
    {

        //DebMes($message, 'xiaomi');

        echo date('Y-m-d H:i:s') . "\n";
        echo "IP: " . $ip . "\n";
        echo "MSG: " . $message . "\n";


        $message_data = json_decode($message, true);
        if (isset($message_data['data'])) {
            $data_text = str_replace('\\"', '"', $message_data['data']);
            $message_data['data'] = json_decode($data_text, true);
        }

        if ($message_data['sid']) {
            $device = SQLSelectOne("SELECT * FROM xidevices WHERE SID='" . DBSafe($message_data['sid']) . "'");
            if (!$device['ID']) {
                $device = array();
                $device['SID'] = $message_data['sid'];
                $device['TYPE'] = $message_data['model'];
                $device['TITLE'] = ucfirst($device['TYPE']) . ' ' . date('Y-m-d');
                $device['ID'] = SQLInsert('xidevices', $device_type);

                $commands = array();
                if ($device['TYPE'] == 'gateway') {
                    $commands[] = 'ringtone';
                }
                if (count($commands) > 0) {
                    foreach ($commands as $command) {
                        $cmd_rec = SQLSelectOne("SELECT * FROM xicommands WHERE DEVICE_ID=" . $device['ID'] . " AND TITLE LIKE '" . DBSafe($command) . "'");
                        if (!$cmd_rec['ID']) {
                            $cmd_rec = array();
                            $cmd_rec['DEVICE_ID'] = $device['ID'];
                            $cmd_rec['TITLE'] = $command;
                            $cmd_rec['ID'] = SQLInsert('xicommands', $cmd_rec);
                        }
                    }
                }

            }
            if ($message_data['token'] != '') {
                $device['TOKEN'] = $message_data['token'];
            }
            $device['GATE_IP'] = $ip;
            $device['UPDATED'] = date('Y-m-d H:i:s');
            SQLUpdate('xidevices', $device);

            if ($message_data['cmd'] != '') {

                $command = $message_data['cmd'];
                $got_commands = array();

                if (isset($message_data['data']['ip'])) {
                    $command = 'ip';
                    $value = $message_data['data']['ip'];
                    $got_commands[] = array('command' => $command, 'value' => $value);
                }

                if ($message_data['cmd'] == 'write_ack' || $message_data['cmd'] == 'read_ack' || $message_data['cmd'] == 'report') {
                    $command = $message_data['cmd'];
                    $value = json_encode($message_data);
                    $got_commands[] = array('command' => $command, 'value' => $value);
                }

                if ($message_data['cmd'] == 'report' && $message_data['model'] == 'gateway') {
                    if (isset($message_data['data']['rgb'])) {
                        $command = 'rgb';
                        $value_str = str_pad(dechex($message_data['data']['rgb']), 8, '0', STR_PAD_LEFT);
                        $value = substr($value_str, -6);
                        $got_commands[] = array('command' => $command, 'value' => $value);

                        $command = 'brightness';
                        $value = hexdec(substr($value_str, 0, 2));
                        $got_commands[] = array('command' => $command, 'value' => $value);
                    }
                }
                if (isset($message_data['data']['lux'])) {
                    $command = 'lux';
                    $value = $message_data['data']['lux'];
                    $got_commands[] = array('command' => $command, 'value' => $value);
                }
                if (isset($message_data['data']['illumination'])) {
                    $command = 'illumination';
                    $value = $message_data['data']['illumination'];
                    $got_commands[] = array('command' => $command, 'value' => $value);
                }

                if (isset($message_data['data']['temperature'])) {
                    $command = 'temperature';
                    $value = round(((int)$message_data['data']['temperature']) / 100, 2);
                    $got_commands[] = array('command' => $command, 'value' => $value);
                }
                if (isset($message_data['data']['humidity'])) {
                    $command = 'humidity';
                    $value = round(((int)$message_data['data']['humidity']) / 100, 2);
                    $got_commands[] = array('command' => $command, 'value' => $value);
                }
                if (isset($message_data['data']['pressure'])) {
                    $command = 'pressure_kpa';
                    $value = round(((float)$message_data['data']['pressure'])/1000,2);
                    $got_commands[] = array('command' => $command, 'value' => $value);

                    $command = 'pressure_mm';
                    $value = round($value * 7.50062,2);
                    $got_commands[] = array('command' => $command, 'value' => $value);
                }
                if ($message_data['cmd'] == 'report' && ($message_data['model'] == 'switch' || $message_data['model'] == 'sensor_switch.aq2')) {
                    $value = 1;
                    $command = $message_data['data']['status'];
                    $got_commands[] = array('command' => $command, 'value' => $value);
                }
                if ($message_data['cmd'] == 'report' && isset($message_data['data']['channel_0'])) {
                    $command = 'channel_0';
                    if ($message_data['data']['channel_0'] == 'on') {
                        $value = 1;
                    } elseif ($message_data['data']['channel_0'] == 'off') {
                        $value = 0;
                    } else {
                        $value = 1;
                        $command = $message_data['data']['channel_0'] . '0';
                    }
                    $got_commands[] = array('command' => $command, 'value' => $value);
                }

                if ($message_data['cmd'] == 'report' && isset($message_data['data']['channel_1'])) {
                    $command = 'channel_1';
                    if ($message_data['data']['channel_1'] == 'on') {
                        $value = 1;
                    } elseif ($message_data['data']['channel_1'] == 'off') {
                        $value = 0;
                    } else {
                        $value = 1;
                        $command = $message_data['data']['channel_1'] . '1'; //
                    }
                    $got_commands[] = array('command' => $command, 'value' => $value);
                }

                if ($message_data['cmd'] == 'report' && isset($message_data['data']['dual_channel'])) {
                    $command = $message_data['data']['dual_channel'];
                    $value = 1;
                    $got_commands[] = array('command' => $command, 'value' => $value);
                }

                if (isset($message_data['data']['alarm'])) {
                    $command = 'alarm';
                    $value = $message_data['data']['alarm'];
                    $got_commands[] = array('command' => $command, 'value' => $value);
                }
                if (isset($message_data['data']['density'])) {
                    $command = 'density';
                    $value = $message_data['data']['density'];
                    $got_commands[] = array('command' => $command, 'value' => $value);
                }
                if ($message_data['cmd'] == 'report' && isset($message_data['data']['status']) && $message_data['model'] == 'cube') {
                    $value = 1;
                    $command = $message_data['data']['status'];
                    $got_commands[] = array('command' => $command, 'value' => $value);
                }
                if (isset($message_data['data']['rotate'])) {
                    $value = $message_data['data']['rotate'];
                    $command = 'rotate';
                    $got_commands[] = array('command' => $command, 'value' => $value);
                }
                if ($message_data['cmd'] == 'report' && $message_data['model'] == 'plug' && isset($message_data['data']['status'])) {
                    if ($message_data['data']['status'] == 'on') {
                        $value = 1;
                    } else {
                        $value = 0;
                    }
                    $command = 'status';
                    $got_commands[] = array('command' => $command, 'value' => $value);
                }
                if (isset($message_data['data']['load_voltage'])) {
                    $value = $message_data['data']['load_voltage'];
                    $command = 'load_voltage';
                    $got_commands[] = array('command' => $command, 'value' => $value);
                }
                if (isset($message_data['data']['load_power'])) {
                    $value = $message_data['data']['load_power'];
                    $command = 'load_power';
                    $got_commands[] = array('command' => $command, 'value' => $value);
                }
                if (isset($message_data['data']['power_consumed'])) {
                    $value = $message_data['data']['power_consumed'];
                    $command = 'power_consumed';
                    $got_commands[] = array('command' => $command, 'value' => $value);
                }
                if ($message_data['cmd'] == 'report' && $message_data['data']['status'] == 'motion') {
                    $value = 1;
                    $command = 'motion';
                    $got_commands[] = array('command' => $command, 'value' => $value);
                }

                if (isset($message_data['data']['no_motion'])) {
                    $command = 'no_motion';
                    $value = $message_data['data']['no_motion'];
                    $got_commands[] = array('command' => $command, 'value' => $value);
                }

                if ($message_data['data']['status'] == 'iam') {
                    $command = 'iam';
                    $value = 1;
                    $got_commands[] = array('command' => $command, 'value' => $value);
                }
                if ($message_data['data']['status'] == 'leak') {
                    $command = 'leak';
                    $value = 1;
                    $got_commands[] = array('command' => $command, 'value' => $value);
                }
                if ($message_data['data']['status'] == 'no_leak') {
                    $command = 'leak';
                    $value = 0;
                    $got_commands[] = array('command' => $command, 'value' => $value);
                }

                if (isset($message_data['data']['no_close'])) {
                    $command = 'no_close';
                    $value = $message_data['data']['no_close'];
                    $got_commands[] = array('command' => $command, 'value' => $value);
                }
                if (isset($message_data['data']['voltage'])) {
                    $command = 'voltage';
                    $value = $message_data['data']['voltage'];
                    $got_commands[] = array('command' => $command, 'value' => $value);
                }
                if ($message_data['cmd'] == 'report' && isset($message_data['data']['status']) && ($message_data['model'] == 'magnet' || $message_data['model'] == 'sensor_magnet.aq2')) {
                    if ($message_data['data']['status'] == 'close') {
                        $value = 1;
                    } else {
                        $value = 0;
                    }
                    $command = 'status';
                    $got_commands[] = array('command' => $command, 'value' => $value);
                }

                /*
                if (!isset($value)) {
                    $value=json_encode($message_data['data']);
                }
                */
                if (!isset($commands[0])) {
                    $commands[] = array('command' => $message_data['cmd'], 'value' => json_encode($message_data));
                }


                foreach ($got_commands as $c) {
                    $command = $c['command'];
                    $value = $c['value'];
                    $cmd_rec = SQLSelectOne("SELECT * FROM xicommands WHERE DEVICE_ID=" . $device['ID'] . " AND TITLE LIKE '" . DBSafe($command) . "'");
                    if (!$cmd_rec['ID']) {
                        $cmd_rec = array();
                        $cmd_rec['DEVICE_ID'] = $device['ID'];
                        $cmd_rec['TITLE'] = $command;
                        $cmd_rec['ID'] = SQLInsert('xicommands', $cmd_rec);
                    }
                    $old_value = $cmd_rec['VALUE'];
                    $cmd_rec['VALUE'] = $value;
                    $cmd_rec['UPDATED'] = date('Y-m-d H:i:s');
                    SQLUpdate('xicommands', $cmd_rec);
                    if ($cmd_rec['LINKED_OBJECT'] && $cmd_rec['LINKED_PROPERTY']) {
                        setGlobal($cmd_rec['LINKED_OBJECT'] . '.' . $cmd_rec['LINKED_PROPERTY'], $value, array($this->name => '0'));
                    }
                    if ($cmd_rec['LINKED_OBJECT'] && $cmd_rec['LINKED_METHOD']
                        && ($cmd_rec['VALUE'] != $old_value ||
                            $command == 'motion' ||
                            $command == 'click0' ||
                            $command == 'click1' ||
                            $command == 'both_click' ||
                            $command == 'alarm' ||
                            $command == 'iam' ||
                            $command == 'leak' ||
                            $device['TYPE'] == 'sensor_switch.aq2' ||
                            $device['TYPE'] == 'switch' ||
                            $device['TYPE'] == 'cube' ||
                            0
                        )
                    ) {
                        callMethod($cmd_rec['LINKED_OBJECT'] . '.' . $cmd_rec['LINKED_METHOD'], $message_data['data']);
                    }
                }


            }

        }
    }

    function sendMessage($message, $ip, $sock)
    {
        socket_sendto($sock, $message, strlen($message), 0, $ip, XIAOMI_MULTICAST_PORT);
    }

    function admin(&$out)
    {

        $this->getConfig();
        $out['API_IP']=$this->config['API_IP'];
        $out['API_BIND']=$this->config['API_BIND'];
        if ($this->view_mode=='update_settings') {
            global $api_ip;
            $this->config['API_IP']=trim($api_ip);
            global $api_bind;
            $this->config['API_BIND']=trim($api_bind);
            $this->saveConfig();
            setGlobal('cycle_xiaomihomeControl', 'restart');
            $this->redirect("?");
        }

        if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
            $out['SET_DATASOURCE'] = 1;
        }

        if ($this->mode == 'test') {


            $command = SQLSelectOne("SELECT xicommands.*, xidevices.TOKEN, xidevices.GATE_IP, xidevices.SID, xidevices.GATE_KEY FROM xicommands LEFT JOIN xidevices ON xicommands.DEVICE_ID=xidevices.ID WHERE xicommands.ID=5");
            $token = $command['TOKEN'];
            $key = $command['GATE_KEY'];
            $ip = $command['GATE_IP'];

            $data = array();
            $data['sid'] = $command['SID'];
            $data['short_id'] = 0;
            $cmd_data = array();

            /*
            if ($command['TITLE']=='rgb') {
                $value=preg_replace('/^#/','',$value);
                if (strlen($value)<8 && hexdec($value)>0) {
                    $value='ff'.$value;
                }
                $sendvalue=hexdec($value);
                $data['cmd']='write';
                $data['model']='gateway';
                $cmd_data['rgb']=$sendvalue;
            }
            */

            $data['cmd'] = 'read';
            $data['model'] = 'gateway';
            //$data['method']='play_specify_fm';
            //$cmd_data=array('id'=>93, 'type'=>0, 'url'=>'http://live.xmcdn.com/live/1853/64.m3u8');
            //$cmd_data['id']=65025;
            //$cmd_data['from']=4;
            $cmd_data['method'] = 'get_prop_fm';

            // method: get_prop_fm

            //print_r($data);exit;

            if ($data['cmd']) {
                $cmd_data['key'] = $this->makeSignature($token, $key);
                $data['data'] = (json_encode($cmd_data));
                $que_rec = array();
                $que_rec['DATA'] = json_encode($data);
                $que_rec['IP'] = $ip;
                $que_rec['ADDED'] = date('Y-m-d H:i:s');
                $que_rec['ID'] = SQLInsert('xiqueue', $que_rec);
            }

            echo "Sent: " . json_encode($que_rec);
            exit;

        }

        if ($this->data_source == 'xidevices' || $this->data_source == '') {
            if ($this->view_mode == '' || $this->view_mode == 'search_xidevices') {
                $this->search_xidevices($out);
            }
            if ($this->view_mode == 'edit_xidevices') {
                $this->edit_xidevices($out, $this->id);
            }
            if ($this->view_mode == 'delete_xidevices') {
                $this->delete_xidevices($this->id);
                $this->redirect("?data_source=xidevices");
            }
        }
        if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
            $out['SET_DATASOURCE'] = 1;
        }
        if ($this->data_source == 'xicommands') {
            if ($this->view_mode == '' || $this->view_mode == 'search_xicommands') {
                $this->search_xicommands($out);
            }
            if ($this->view_mode == 'edit_xicommands') {
                $this->edit_xicommands($out, $this->id);
            }
        }
    }

    /**
     * FrontEnd
     *
     * Module frontend
     *
     * @access public
     */
    function usual(&$out)
    {
        if ($this->ajax) {
            global $op;
            if ($op == 'process') {
                global $message;
                global $ip;
                $this->processMessage($message, $ip);
            }
        }
        $this->admin($out);
    }

    /**
     * xidevices search
     *
     * @access public
     */
    function search_xidevices(&$out)
    {
        require(DIR_MODULES . $this->name . '/xidevices_search.inc.php');
    }

    /**
     * xidevices edit/add
     *
     * @access public
     */
    function edit_xidevices(&$out, $id)
    {
        require(DIR_MODULES . $this->name . '/xidevices_edit.inc.php');
    }

    /**
     * xidevices delete record
     *
     * @access public
     */
    function delete_xidevices($id)
    {
        $rec = SQLSelectOne("SELECT * FROM xidevices WHERE ID='$id'");
        // some action for related tables
        SQLExec("DELETE FROM xicommands WHERE DEVICE_ID='" . $rec['ID'] . "'");
        SQLExec("DELETE FROM xidevices WHERE ID='" . $rec['ID'] . "'");
    }

    /**
     * xicommands search
     *
     * @access public
     */
    function search_xicommands(&$out)
    {
        require(DIR_MODULES . $this->name . '/xicommands_search.inc.php');
    }

    /**
     * xicommands edit/add
     *
     * @access public
     */
    function edit_xicommands(&$out, $id)
    {
        require(DIR_MODULES . $this->name . '/xicommands_edit.inc.php');
    }

    function propertySetHandle($object, $property, $value)
    {
        $properties = SQLSelect("SELECT ID FROM xicommands WHERE xicommands.LINKED_OBJECT LIKE '" . DBSafe($object) . "' AND xicommands.LINKED_PROPERTY LIKE '" . DBSafe($property) . "'");
        $total = count($properties);
        if ($total) {
            for ($i = 0; $i < $total; $i++) {
                $command = SQLSelectOne("SELECT xicommands.*, xidevices.TYPE, xidevices.TOKEN, xidevices.GATE_IP, xidevices.SID, xidevices.GATE_KEY FROM xicommands LEFT JOIN xidevices ON xicommands.DEVICE_ID=xidevices.ID WHERE xicommands.ID=" . (int)$properties[$i]['ID']);
                $ip = $command['GATE_IP'];
                if ($command['TYPE'] != 'gateway') {
                    $gate = SQLSelectOne("SELECT * FROM xidevices WHERE TYPE='gateway' AND GATE_IP='" . $ip . "'");
                    if ($gate['ID']) {
                        $key = $gate['GATE_KEY'];
                        $token = $gate['TOKEN'];
                    } else {
                        DebMes('Cannot find gateway key');
                    }
                } else {
                    $token = $command['TOKEN'];
                    $key = $command['GATE_KEY'];
                }

                $data = array();
                $data['sid'] = $command['SID'];
                $data['short_id'] = 0;
                $cmd_data = array();

                if ($command['TITLE'] == 'status' && $command['TYPE'] == 'plug') {
                    $data['cmd'] = 'write';
                    $data['model'] = $command['TYPE'];
                    if ($value) {
                        $cmd_data['status'] = 'on';
                    } else {
                        $cmd_data['status'] = 'off';
                    }
                }
                if ($command['TITLE'] == 'channel_0') {
                    $data['cmd'] = 'write';
                    $data['model'] = $command['TYPE'];
                    if ($value) {
                        $cmd_data['channel_0'] = 'on';
                    } else {
                        $cmd_data['channel_0'] = 'off';
                    }
                }
                if ($command['TITLE'] == 'channel_1') {
                    $data['cmd'] = 'write';
                    $data['model'] = $command['TYPE'];
                    if ($value) {
                        $cmd_data['channel_1'] = 'on';
                    } else {
                        $cmd_data['channel_1'] = 'off';
                    }
                }
                if ($command['TITLE'] == 'brightness') {
                    $rgb_cmd = SQLSelectOne("SELECT xicommands.* FROM xicommands WHERE TITLE='rgb' AND DEVICE_ID=" . $command['DEVICE_ID']);
                    if ($rgb_cmd['ID']) {
                        $rgb_value = $rgb_cmd['VALUE'];
                        $value = str_pad(dechex($value), 2, '0', STR_PAD_LEFT) . $rgb_value;
                    }
                    $sendvalue = hexdec($value);
                    $data['cmd'] = 'write';
                    $data['model'] = 'gateway';
                    $cmd_data['rgb'] = $sendvalue;
                }

                if ($command['TITLE'] == 'rgb') {
                    $value = preg_replace('/^#/', '', $value);
                    if (strlen($value) < 8 && hexdec($value) > 0) {
                        $br_cmd = SQLSelectOne("SELECT xicommands.* FROM xicommands WHERE TITLE='brightness' AND DEVICE_ID=" . $command['DEVICE_ID']);
                        $br_value = $br_cmd['VALUE'];
                        if ($br_value) {
                            $value = str_pad(dechex($br_value), 2, '0', STR_PAD_LEFT) . $value;
                        } else {
                            $value = 'ff' . $value;
                        }
                    }
                    $sendvalue = hexdec($value);
                    $data['cmd'] = 'write';
                    $data['model'] = 'gateway';
                    $cmd_data['rgb'] = $sendvalue;
                }

                if ($command['TITLE'] == 'ringtone') {
                    $data['cmd'] = 'write';
                    $data['model'] = 'gateway';
                    if ($value === '' || $value == 'stop') {
                        $cmd_data['mid'] = 10000;
                    } else {
                        $vol = '';
                        $tmp = explode(',', $value);
                        if ($tmp[1]) {
                            $value = trim($tmp[0]);
                            $vol = trim($tmp[1]);
                        }
                        $cmd_data['mid'] = (int)$value;
                        if ($vol != '') {
                            $cmd_data['vol'] = (int)$vol;
                        }
                    }
                }


                if ($data['cmd']) {
                    $cmd_data['key'] = $this->makeSignature($token, $key);
                    $data['data'] = (json_encode($cmd_data));
                    $que_rec = array();
                    $que_rec['DATA'] = json_encode($data);
                    //echo "Adding: ".$que_rec['DATA'];
                    $que_rec['IP'] = $ip;
                    $que_rec['ADDED'] = date('Y-m-d H:i:s');
                    $que_rec['ID'] = SQLInsert('xiqueue', $que_rec);
                }
            }
        }
    }


    function makeSignature($data, $key)
    {
        $iv = hex2bin('17996d093d28ddb3ba695a2e6f58562e');
        $bin_data = base64_decode(openssl_encrypt($data, 'AES-128-CBC', $key, OPENSSL_ZERO_PADDING, $iv));
        $res = bin2hex($bin_data);
        return $res;
    }

    /**
     * Install
     *
     * Module installation routine
     *
     * @access private
     */
    function install($data = '')
    {
        setGlobal('cycle_xiaomihomeControl', 'restart');
        parent::install();
    }

    /**
     * Uninstall
     *
     * Module uninstall routine
     *
     * @access public
     */
    function uninstall()
    {
        SQLExec('DROP TABLE IF EXISTS xidevices');
        SQLExec('DROP TABLE IF EXISTS xicommands');
        parent::uninstall();
    }

    /**
     * dbInstall
     *
     * Database installation routine
     *
     * @access private
     */
    function dbInstall()
    {
        /*
        xidevices -
        xicommands -
        */
        $data = <<<EOD
 xidevices: ID int(10) unsigned NOT NULL auto_increment
 xidevices: TITLE varchar(100) NOT NULL DEFAULT ''
 xidevices: TYPE varchar(100) NOT NULL DEFAULT '' 
 xidevices: SID varchar(255) NOT NULL DEFAULT ''
 xidevices: GATE_KEY varchar(255) NOT NULL DEFAULT ''
 xidevices: GATE_IP varchar(255) NOT NULL DEFAULT '' 
 xidevices: TOKEN varchar(255) NOT NULL DEFAULT ''  
 xidevices: PARENT_ID int(10) unsigned NOT NULL DEFAULT '0' 
 xidevices: UPDATED datetime
 
 xicommands: ID int(10) unsigned NOT NULL auto_increment
 xicommands: TITLE varchar(100) NOT NULL DEFAULT ''
 xicommands: VALUE varchar(255) NOT NULL DEFAULT ''
 xicommands: DEVICE_ID int(10) NOT NULL DEFAULT '0'
 xicommands: LINKED_OBJECT varchar(100) NOT NULL DEFAULT ''
 xicommands: LINKED_PROPERTY varchar(100) NOT NULL DEFAULT ''
 xicommands: LINKED_METHOD varchar(100) NOT NULL DEFAULT ''
 xicommands: UPDATED datetime
 
 xiqueue: ID int(10) unsigned NOT NULL auto_increment
 xiqueue: IP varchar(100) NOT NULL DEFAULT '' 
 xiqueue: DATA text 
 xiqueue: ADDED datetime 
 
EOD;
        parent::dbInstall($data);
    }
// --------------------------------------------------------------------
}
/*
*
* TW9kdWxlIGNyZWF0ZWQgSmFuIDI4LCAyMDE3IHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
*
*/
