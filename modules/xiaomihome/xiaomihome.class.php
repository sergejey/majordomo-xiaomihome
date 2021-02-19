<?php
/**
* Xiaomi Home
* @package project
* @author <sergejey@gmail.com>
* @copyright 2017-2018 http://majordomo.smartliving.ru/ (c)
* @version 2018.08.10
*/

// https://github.com/louisZL/lumi-gateway-local-api
// https://github.com/illxi/lumi-gateway-local-api (english)
// https://github.com/lazcad/homeassistant/blob/master/components/xiaomi.py
// https://github.com/illxi/lumi-gateway-local-api/blob/master/device_read_write.md
// https://github.com/Danielhiversen/homeassistant/pull/6 -- ringtone support
// https://github.com/aqara/opencloud-docs/blob/master/en/development/gateway-LAN-communication.md
// http://docs.opencloud.aqara.cn/en/development/gateway-LAN-communication/


Define('XIAOMI_MULTICAST_PORT', 9898);
Define('XIAOMI_MULTICAST_PEER_PORT', 4321);
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
      $this->name = 'xiaomihome';
      $this->title = 'Xiaomi Home';
      $this->module_category = '<#LANG_SECTION_DEVICES#>';
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
      if (isset($this->id)) {
         $p["id"] = $this->id;
      }
      if (isset($this->view_mode)) {
         $p["view_mode"] = $this->view_mode;
      }
      if (isset($this->edit_mode)) {
         $p["edit_mode"] = $this->edit_mode;
      }
      if (isset($this->data_source)) {
         $p["data_source"] = $this->data_source;
      }
      if (isset($this->tab)) {
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
         if ($this->ajax) {
            return;
         }
      }

      if (isset($this->owner->action)) {
         $out['PARENT_ACTION'] = $this->owner->action;
      }

      if (isset($this->owner->name)) {
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

   function processMessage($message, $ip, $log_debmes = false, $log_gw_heartbeat = false)
   {
      // Т.к. настройки модуля меняются редко, то нет смысла их запрашивать (дергать БД) при каждом вызове функции.
      // Вместо этого передаем их в виде параметров при вызове функции из цикла модуля.
      // $this->getConfig();

      $message_data = json_decode($message, true);
      
      if ($log_debmes) {
         if ($log_gw_heartbeat) {
            // Вместе с heartbeat от шлюзов (все подряд)
            DebMes($message, 'xiaomi');
         } else {
            // Без heartbeat от шлюзов
            if (($message_data['model'] == 'gateway' || $message_data['model'] == 'acpartner.v3') && $message_data['cmd'] == 'heartbeat') {
               //
            } else {
               DebMes($message, 'xiaomi');
            }
         }
      }

      if (isset($message_data['data'])) {
         // Mijia API
         $data_text = str_replace('\\"', '"', $message_data['data']);
         $message_data['data'] = json_decode($data_text, true);
      } else if (isset($message_data['params'])) {
         // Aqara API
         $message_data['data'] = array();
         foreach ($message_data['params'] as $params) {
            $message_data['data'] += $params;
         }
      }

      if ($message_data['sid']) {

         $device = SQLSelectOne("SELECT * FROM xidevices WHERE SID='" . DBSafe($message_data['sid'])."'");
         if (!$device['ID']) {
            $device = SQLSelectOne("SELECT * FROM xidevices WHERE SID='0" . DBSafe($message_data['sid'])."'");
         }

         if (!$device['ID']) {
            $device = array();
            $device['SID'] = $message_data['sid'];
            $device['TYPE'] = $message_data['model'];
            $device['TITLE'] = ucfirst($device['TYPE']) . ' ' . date('Y-m-d');
            $device['ID'] = SQLInsert('xidevices', $device);

            $commands = array();
            $commands[] = 'command';
            
            if ($device['TYPE'] == 'gateway' || $device['TYPE'] == 'acpartner.v3') {
               $commands[] = 'ringtone';
            }

            if ($device['TYPE'] == 'curtain') {
               $commands[] = 'curtain_status';
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

         if ($device['GATE_IP'] != $ip) {
            $device['GATE_IP'] = $ip;
         }

         $device['UPDATED'] = date('Y-m-d H:i:s');
         
         SQLUpdate('xidevices', $device);

         if ( ($message_data['model'] == 'gateway' || $message_data['model'] == 'acpartner.v3') && $message_data['cmd'] == 'heartbeat' ) {
            // ip шлюза (обновляем, чтобы использовать как условие для alive)
            if (isset($message_data['data']['ip'])) {
               $command = 'ip';
               $value = $message_data['data']['ip'];
               $got_commands[] = array('command' => $command, 'value' => $value);
            }
         } else if ($message_data['cmd'] != '') {
            $command = $message_data['cmd'];
            $got_commands = array();

            if (isset($message_data['data']['ip'])) {
               $command = 'ip';
               $value = $message_data['data']['ip'];
               $got_commands[] = array('command' => $command, 'value' => $value);
            }

            if ($message_data['cmd'] == 'write_ack' ||
                $message_data['cmd'] == 'read_ack' ||
                $message_data['cmd'] == 'report' ||
                $message_data['cmd'] == 'write_rsp' ||
                $message_data['cmd'] == 'read_rsp' ||
                $message_data['cmd'] == 'discovery_rsp' ||
                $message_data['cmd'] == 'server_ack' ||
                $message_data['cmd'] == 'server_rsp' ||
                $message_data['cmd'] == 'iam')
            {
               $command = $message_data['cmd'];
               if (isset($message_data['params'])) {
                  $tmp = $message_data;
                  unset($tmp['data']);
                  $value = json_encode($tmp);
               } else {
                  $value = json_encode($message_data);
               }
               $got_commands[] = array('command' => $command, 'value' => $value);
            }

            if ($message_data['cmd'] == 'report' || $message_data['cmd'] == 'read_ack' || $message_data['cmd'] == 'read_rsp') {

               // Mijia Gate
               if ($message_data['model'] == 'gateway') {
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

               // Aqara Gate
               if ($message_data['model'] == 'acpartner.v3') {
                  // Никакой полезной инфы от него пока нет, поэтому ничего не делаем.
                  //foreach($message_data['data'] as $command => $value) {
                     //$got_commands[] = array('command' => $command, 'value' => $value);
                  //}
               }

               // Aqara Door Lock
               if ($message_data['model'] == 'lock.aq1') {
                  foreach($message_data['data'] as $command => $value) {
                     $got_commands[] = array('command' => $command, 'value' => $value);
                  }
               }

               // channel_0
               if (isset($message_data['data']['channel_0'])) {
                  if ($message_data['model'] == 'ctrl_ln2' || 
                      $message_data['model'] == 'ctrl_ln2.aq1' ||
                      $message_data['model'] == 'ctrl_neutral2' ||
                      $message_data['model'] == 'ctrl_neutral2.aq1')
                  {
                     // Если первый канал 2-хканального устройства
                     $command = 'channel_0';
                  } else {
                     // Если устройство одноканальное
                     $command = 'channel';
                  }
                  if ($message_data['data']['channel_0'] == 'on') {
                     $value = 1;
                  } elseif ($message_data['data']['channel_0'] == 'off') {
                     $value = 0;
                  } else {
                     // Если беспроводная кнопка
                     $value = 1;
                     if ($message_data['model'] == '86sw2' ||
                         $message_data['model'] == 'remote.b286acn01' ||
                         $message_data['model'] == 'sensor_86sw2' ||
                         $message_data['model'] == 'sensor_86sw2.aq1')
                     {
                        // Двухклавишная
                        $command = 'left_' . $message_data['data']['channel_0'];
                     } else {
                        // Одноклавишная
                        $command = $message_data['data']['channel_0'];
                     }
                  }
                  $got_commands[] = array('command' => $command, 'value' => $value);
               }

               // channel_1
               if (isset($message_data['data']['channel_1'])) {
                  $command = 'channel_1';
                  if ($message_data['data']['channel_1'] == 'on') {
                     $value = 1;
                  } elseif ($message_data['data']['channel_1'] == 'off') {
                     $value = 0;
                  } else {
                     // Если беспроводная кнопка
                     $value = 1;
                     $command = 'right_' . $message_data['data']['channel_1'];
                  }
                  $got_commands[] = array('command' => $command, 'value' => $value);
               }

               // button_0
               if (isset($message_data['data']['button_0'])) {
                  $value = 1;
                  if ($message_data['model'] == '86sw2' || 
                      $message_data['model'] == 'sensor_86sw2' ||
                      $message_data['model'] == 'sensor_86sw2.aq1')
                  {
                     // Двухклавишная
                     $command = 'left_' . $message_data['data']['button_0'];
                  } else {
                     // Одноклавишная
                     $command = $message_data['data']['button_0'];
                  }
                  $got_commands[] = array('command' => $command, 'value' => $value);
               }

               // button_1
               if (isset($message_data['data']['button_1'])) {
                  $value = 1;
                  $command = 'right_' . $message_data['data']['button_1'];
                  $got_commands[] = array('command' => $command, 'value' => $value);
               }

               // dual_channel
               if (isset($message_data['data']['dual_channel'])) {
                  $value = 1;
                  $command = $message_data['data']['dual_channel'];
                  $got_commands[] = array('command' => $command, 'value' => $value);
               }

               // curtain_level
               if (isset($message_data['data']['curtain_level'])) {
                   $value = $message_data['data']['curtain_level'];
                   $command = 'curtain_level';
                   $got_commands[] = array('command' => $command, 'value' => $value);
               }

               // cube_status, rotate_degree, detect_time (Aqara Cube via Aqara API)
               if (isset($message_data['data']['cube_status'])) {
                  $command = $message_data['data']['cube_status'];
                  if ($command == 'rotate') {
                     $got_commands[] = array('command' => 'rotate', 'value' => $message_data['data']['rotate_degree']);
                     $got_commands[] = array('command' => 'rotate_time', 'value' => $message_data['data']['detect_time']);
                  } else {
                     $value = 1;
                     $got_commands[] = array('command' => $command, 'value' => $value);
                  }
               }

               // window_status
               if (isset($message_data['data']['window_status'])) {
                  $command = 'status';
                  if ($message_data['data']['window_status'] == 'close') {
                     $value = 1;
                  } else if ($message_data['data']['window_status'] == 'open') {
                     $value = 0;
                  }
                  $got_commands[] = array('command' => $command, 'value' => $value);
               }

               // no_close
               if (isset($message_data['data']['no_close'])) {
                  $command = 'no_close';
                  $value = $message_data['data']['no_close'];
                  $got_commands[] = array('command' => $command, 'value' => $value);
               }

               // wleak_status
               if (isset($message_data['data']['wleak_status'])) {
                  $command = 'leak';
                  if ($message_data['data']['wleak_status'] == 'leak') {
                     $value = 1;
                  } else if ($message_data['data']['wleak_status'] == 'normal') {
                     $value = 0;
                  }
                  $got_commands[] = array('command' => $command, 'value' => $value);
               }

               // smoke_status
               if (isset($message_data['data']['smoke_status'])) {
                  $command = 'alarm';
                  if ($message_data['data']['smoke_status'] == 'alarm') {
                     $value = 1;
                  } else if ($message_data['data']['smoke_status'] == 'normal') {
                     $value = 0;
                  }
                  $got_commands[] = array('command' => $command, 'value' => $value);
               }

               // natgas_status
               if (isset($message_data['data']['natgas_status'])) {
                  $command = 'alarm';
                  if ($message_data['data']['natgas_status'] == 'alarm') {
                     $value = 1;
                  } else if ($message_data['data']['natgas_status'] == 'normal') {
                     $value = 0;
                  }
                  $got_commands[] = array('command' => $command, 'value' => $value);
               }

               // alarm
               if (isset($message_data['data']['alarm'])) {
                  $command = 'alarm';
                  $value = $message_data['data']['alarm'];
                  $got_commands[] = array('command' => $command, 'value' => $value);
               }

               // density
               if (isset($message_data['data']['density'])) {
                  $command = 'density';
                  $value = $message_data['data']['density'];
                  $got_commands[] = array('command' => $command, 'value' => $value);
               }

               // motion_status
               if (isset($message_data['data']['motion_status'])) {
                  if ($message_data['data']['motion_status'] == 'motion') {
                     $message_data['data']['no_motion']=0;
                     $value = 1;
                     $command = 'motion';
                     $got_commands[] = array('command' => $command, 'value' => $value);
                  }
               }

               // no_motion
               if (isset($message_data['data']['no_motion'])) {
                  $command = 'no_motion';
                  $value = (int)$message_data['data']['no_motion'];
                  $got_commands[] = array('command' => $command, 'value' => $value);
                  if ($value>=60 && $value<120) { //
                     $got_commands[] = array('command' => 'motion', 'value' => 0);
                  }
               }

               // rotate
               if (isset($message_data['data']['rotate'])) {
                  $tmp = explode(',', $message_data['data']['rotate']);
                  if ($tmp[1]) {
                     $degree = trim($tmp[0]);
                     $time = trim($tmp[1]);
                     $got_commands[] = array('command' => 'rotate', 'value' => $degree);
                     $got_commands[] = array('command' => 'rotate_time', 'value' => $time);
                  } else {
                     $command = 'rotate';
                     $value = 1;
                     $got_commands[] = array('command' => $command, 'value' => $value);
                  }
               }

               // coordination
               if (isset($message_data['data']['coordination'])) {
                  $command = 'coordination';
                  $value = $message_data['data']['coordination'];
                  $got_commands[] = array('command' => $command, 'value' => $value);
               }

               // bed_activity
               if (isset($message_data['data']['bed_activity'])) {
                  $command = 'bed_activity';
                  $value = $message_data['data']['bed_activity'];
                  $got_commands[] = array('command' => $command, 'value' => $value);
               }

               // final_tilt_angle
               if (isset($message_data['data']['final_tilt_angle'])) {
                  $command = 'final_tilt_angle';
                  $value = $message_data['data']['final_tilt_angle'];
                  $got_commands[] = array('command' => $command, 'value' => $value);
               }

               // temperature
               if (isset($message_data['data']['temperature'])) {
                  $this->getConfig();
                  $roundMode = $this->config['API_ROUND'];
                  if ($roundMode != '0' && $roundMode != '1' && $roundMode != '2') $roundMode = 2;

                  $command = 'temperature';
                  $value = round(((int)$message_data['data']['temperature']) / 100, (int)$roundMode);
                  $got_commands[] = array('command' => $command, 'value' => $value);
               }

               // humidity
               if (isset($message_data['data']['humidity'])) {
                  $this->getConfig();
                  $roundMode = $this->config['API_ROUND'];
                  if ($roundMode != '0' && $roundMode != '1' && $roundMode != '2') $roundMode = 2;

                  $command = 'humidity';
                  $value = round(((int)$message_data['data']['humidity']) / 100, (int)$roundMode);
                  $got_commands[] = array('command' => $command, 'value' => $value);
               }

               // pressure
               if (isset($message_data['data']['pressure'])) {
                  $this->getConfig();
                  $roundMode = $this->config['API_ROUND'];
                  if ($roundMode != '0' && $roundMode != '1' && $roundMode != '2') $roundMode = 2;

                  $command = 'pressure_kpa';
                  $value = round(((float)$message_data['data']['pressure']) / 1000, (int)$roundMode);
                  $got_commands[] = array('command' => $command, 'value' => $value);

                  $command = 'pressure_mm';
                  $value = round($value * 7.50062, (int)$roundMode);
                  $got_commands[] = array('command' => $command, 'value' => $value);
               }

               // status
               if ($message_data['cmd'] == 'report' && isset($message_data['data']['status'])) {

                  $status = $message_data['data']['status'];

                  if ($status == 'close') {
                     $command = 'status';
                     $value = 1;
                  } else if ($status == 'open') {
                     $command = 'status';
                     $value = 0;
                  } else if ($status == 'leak') {
                     $command = 'leak';
                     $value = 1;
                  } else if ($status == 'no_leak') {
                     $command = 'leak';
                     $value = 0;
                  } else if ($status == 'on') {
                     $command = 'channel';
                     $value = 1;
                  } else if ($status == 'off') {
                     $command = 'channel';
                     $value = 0;
                  } else if ($status == 'motion') {
                     $command = $status;
                     $value = 1;
                     $got_commands[] = array('command' => 'no_motion', 'value' => 0);
                  } else {
                     // все остальные - click, iam, motion, cube и т. д.
                     $command = $status;
                     $value = 1;
                  }

                  $got_commands[] = array('command' => $command, 'value' => $value);
               }
            }

            // lux
            if (isset($message_data['data']['lux'])) {
               $command = 'lux';
               $value = $message_data['data']['lux'];
               $got_commands[] = array('command' => $command, 'value' => $value);
            }

            // illumination
            if (isset($message_data['data']['illumination'])) {
               $command = 'illumination';
               $value = $message_data['data']['illumination'];
               $got_commands[] = array('command' => $command, 'value' => $value);
            }

            // load_power
            if (isset($message_data['data']['load_power'])) {
               $value = $message_data['data']['load_power'];
               $command = 'load_power';
               $got_commands[] = array('command' => $command, 'value' => $value);
            }

            // load_voltage
            if (isset($message_data['data']['load_voltage'])) {
               $value = $message_data['data']['load_voltage'];
               $command = 'load_voltage';
               $got_commands[] = array('command' => $command, 'value' => $value);
            }

            // power_consumed
            if (isset($message_data['data']['power_consumed'])) {
               $value = $message_data['data']['power_consumed'];
               $command = 'energy_consumed';
               $got_commands[] = array('command' => $command, 'value' => $value);
            }

            // energy_consumed
            if (isset($message_data['data']['energy_consumed'])) {
               $value = $message_data['data']['energy_consumed'];
               $command = 'energy_consumed';
               $got_commands[] = array('command' => $command, 'value' => $value);
            }

            // voltage (Mijia API), battery_voltage (Aqara API)
            if (isset($message_data['data']['voltage']) || isset($message_data['data']['battery_voltage'])) {
               $command = 'voltage';
               if (isset($message_data['data']['voltage'])) {
                  $value = $message_data['data']['voltage'] * 0.001;
                  $mvolts = $message_data['data']['voltage'];
               } else if (isset($message_data['data']['battery_voltage'])) {
                  $value = $message_data['data']['battery_voltage'] * 0.001;
                  $mvolts = $message_data['data']['battery_voltage'];
               }
               $got_commands[] = array('command' => $command, 'value' => $value);
               if ($mvolts  >=    3250) $battery_level = 100;
               else if ($mvolts > 3200) $battery_level = 100 - ((3250 - $mvolts) * 5) / 50;
               else if ($mvolts > 3000) $battery_level = 95 - ((3200 - $mvolts) * 55) / 200;
               else if ($mvolts > 2750) $battery_level = 40 - ((3000 - $mvolts) * 40) / 250;
               else $battery_level = 0;

               $this->getConfig();
               $roundMode = $this->config['API_ROUND'];
               if ($roundMode != '0' && $roundMode != '1' && $roundMode != '2') $roundMode = 2;

               $command = 'battery_level';
               $value = round($battery_level, (int)$roundMode);
               $got_commands[] = array('command' => $command, 'value' => $value);
            }

            // error
            if (isset($message_data['data']['error'])) {
               $value = $message_data['data']['error'];
               $command = 'error';
               $got_commands[] = array('command' => $command, 'value' => $value);
            }
         }

         if (!empty($got_commands)) {
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

               // Привязанное свойство обновляем всегда
               if ($cmd_rec['LINKED_OBJECT'] && $cmd_rec['LINKED_PROPERTY']) {
                  setGlobal($cmd_rec['LINKED_OBJECT'] . '.' . $cmd_rec['LINKED_PROPERTY'], $value, array($this->name => '0'));
               }

               /*
               // Привязанный метод вызываем, когда новое значение метрики не равно старому,
               // и для всех метрик, в которые пишется 1, как обновление статуса,
               // а также для критичных метрик alarm и leak.
               if ($cmd_rec['LINKED_OBJECT'] && $cmd_rec['LINKED_METHOD'] && 
                  ($cmd_rec['VALUE'] != $old_value ||
                  $command == 'motion' ||
                  $command == 'click' ||
                  $command == 'long_click' ||
                  $command == 'double_click' ||
                  $command == 'long_click_press' ||
                  $command == 'long_click_release' ||
                  $command == 'left_click' ||
                  $command == 'right_click' ||
                  $command == 'left_long_click' ||
                  $command == 'right_long_click' ||
                  $command == 'left_double_click' ||
                  $command == 'right_double_click' ||
                  $command == 'both_click' ||
                  $command == 'both_long_click' ||
                  $command == 'both_double_click' ||
                  $command == 'long_both_click' ||
                  $command == 'double_both_click' ||
                  $command == 'flip90' ||
                  $command == 'flip180' ||
                  $command == 'move' ||
                  $command == 'tap_twice' ||
                  $command == 'shake_air' ||
                  $command == 'swing' ||
                  $command == 'alert' ||
                  $command == 'free_fall' ||
                  $command == 'alarm' ||
                  $command == 'iam' ||
                  $command == 'leak' ||
                  $command == 'tilt' ||
                  $command == 'vibrate' ||
                  0)
               ) {
               */

               // Привязанный метод вызываем всегда
               if ($cmd_rec['LINKED_OBJECT'] && $cmd_rec['LINKED_METHOD']) {
                  // В привязанный метод передаем через параметры "сырые" данные метрики,
                  // а также общепринятые в МДМ PROPERTY, OLD_VALUE и NEW_VALUE.
                  $message_data['data']['PROPERTY'] = $command;
                  $message_data['data']['OLD_VALUE'] = $old_value;
                  $message_data['data']['NEW_VALUE'] = $cmd_rec['VALUE'];
                  callMethod($cmd_rec['LINKED_OBJECT'] . '.' . $cmd_rec['LINKED_METHOD'], $message_data['data']);
               }
            }
         }
      }
   }

   function sendMessage($message, $ip, $sock)
   {
      if ($this->config['API_LOG_DEBMES']) {
         DebMes("Sending message ($message) to $ip", 'xiaomi');
      }

      socket_sendto($sock, $message, strlen($message), 0, $ip, XIAOMI_MULTICAST_PORT);
   }

   function admin(&$out)
   {
      $this->getConfig();

      if ((time() - (int)gg('cycle_xiaomihomeRun')) < 15) {
         $out['CYCLERUN'] = 1;
      } else {
         $out['CYCLERUN'] = 0;
      }

      $out['API_BIND'] = $this->config['API_BIND'];
      $out['API_ROUND'] = $this->config['API_ROUND'];
      $out['API_LOG_DEBMES'] = $this->config['API_LOG_DEBMES'];
      $out['API_LOG_CYCLE'] = $this->config['API_LOG_CYCLE'];
      $out['API_LOG_GW_HEARTBEAT'] = $this->config['API_LOG_GW_HEARTBEAT'];

      if ($this->view_mode=='update_settings') {

         global $api_bind;
         $this->config['API_BIND'] = trim($api_bind);

         global $api_round;
         $this->config['API_ROUND'] = $api_round;

         global $api_log_debmes;
         $this->config['API_LOG_DEBMES'] = $api_log_debmes;

         global $api_log_cycle;
         $this->config['API_LOG_CYCLE'] = $api_log_cycle;

         global $api_log_gw_heartbeat;
         $this->config['API_LOG_GW_HEARTBEAT'] = $api_log_gw_heartbeat;

         $this->saveConfig();

         if ($this->config['API_LOG_DEBMES']) {
            DebMes('Init cycle restart', 'xiaomi');
         }
         setGlobal('cycle_xiaomihomeControl', 'restart');

         $this->redirect('?');
      }

      if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
         $out['SET_DATASOURCE'] = 1;
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
            global $log_debmes;
            global $log_gw_heartbeat;
            /*if (preg_match('/open/',$_SERVER['REQUEST_URI'])) {
               DebMes($_SERVER['REQUEST_URI'],'xiaomi_request');
            }*/
            $this->processMessage($message, $ip, $log_debmes, $log_gw_heartbeat);
         }
      } else {
         $this->admin($out);
      }
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
            
            if ($command['TYPE'] != 'gateway' || $command['TYPE'] != 'acpartner.v3') {
               $gate = SQLSelectOne("SELECT * FROM xidevices WHERE (TYPE='gateway' OR TYPE='acpartner.v3') AND GATE_IP='" . $ip . "'");
               if ($gate['ID']) {
                  if ($gate['GATE_KEY'] != '') {
                     $key = $gate['GATE_KEY'];
                  } else {
                     $this->getConfig();
                     if ($this->config['API_LOG_DEBMES']) DebMes('Cannot find gateway key', 'xiaomi');
                  }
                  $token = $gate['TOKEN'];
               }
            } else {
               $token = $command['TOKEN'];
               $key = $command['GATE_KEY'];
            }

            $data = array();
            $data['sid'] = $command['SID'];
            //$data['short_id'] = 0; // only for Mijia API
            $cmd_data = array();

            // command (отправка api-команд read, discovery и др.)
            if ($command['TITLE'] == 'command') {
               if ($value == 'get_update' || $value == 'read') {
                  $data['cmd'] = 'read';
               } else if ($value != '') {
                  $data['cmd'] = $value;
               }
            }

            // Управление реле (розетки, выключатели)
            if ($command['TITLE'] == 'channel' || $command['TITLE'] == 'channel_0' || $command['TITLE'] == 'channel_1') {
               $data['cmd'] = 'write';
               $data['model'] = $command['TYPE'];
               if ($command['TITLE'] == 'channel') {
                  $cmd_name = 'channel_0';
               } else {
                  $cmd_name = $command['TITLE'];
               }
               if ($value) {
                  $cmd_data[$cmd_name] = 'on';
               } else {
                  $cmd_data[$cmd_name] = 'off';
               }
            }

            // curtain_level
            if ($command['TITLE'] == 'curtain_level') {
               $data['cmd'] = 'write';
               $data['model'] = $command['TYPE'];
               $value = (int)$value;
               if ($value < 0) $value = 0;
               if ($value > 100) $value = 100;
               $cmd_data['curtain_level'] = strval($value);
            }

            // curtain_status
            if ($command['TITLE'] == 'curtain_status') {
               if ($value == 'open' || $value == 'close' || $value == 'stop' || $value == 'auto') {
                  $data['cmd'] = 'write';
                  $data['model'] = $command['TYPE'];
                  $cmd_data['curtain_status'] = strval($value);
               } else return;
            }

            // brightness
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

            // rgb
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

            // ringtone
            if ($command['TITLE'] == 'ringtone') {
               $data['cmd'] = 'write';
               $data['model'] = $command['TYPE'];

               if ($value === '' || $value == 'stop') {
                  $cmd_data['mid'] = 10000;
               } else {
                  $vol = '';
                  $tmp = explode(',', $value);
                  if ($tmp[1]) {
                     $mid = trim($tmp[0]);
                     $vol = trim($tmp[1]);
                  } else {
                     $mid = $value;
                  }
                  $cmd_data['mid'] = (int)$mid;
                  if ($vol != '') {
                     $cmd_data['vol'] = (int)$vol;
                  }
               }
            }

            if ($data['cmd']) {

               if ($data['cmd'] == 'write') {
                  if ($gate['TYPE'] == 'gateway') {
                     $cmd_data['key'] = $this->makeSignature($token, $key);
                     $data['data'] = json_encode($cmd_data);
                  } else if ($gate['TYPE'] == 'acpartner.v3') {
                     $data['key'] = $this->makeSignature($token, $key);
                     $data['params'] = array();
                     foreach($cmd_data as $key => $val) {
                        $data['params'][] = [$key => $val];
                     }
                  }
               }

               $que_rec = array();
               $que_rec['DATA'] = json_encode($data);
               $que_rec['IP'] = $ip;
               $que_rec['ADDED'] = date('Y-m-d H:i:s');
               $que_rec['ID'] = SQLInsert('xiqueue', $que_rec);

               if ($command['TITLE'] == 'command' || $command['TITLE'] == 'ringtone') {
                  SQLExec("UPDATE xicommands SET VALUE='" . DBSafe($value) . "', UPDATED='" . date('Y-m-d H:i:s') . "' WHERE ID=" . (int)$properties[$i]['ID']);
               }
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
   function dbInstall($data = '')
   {

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
