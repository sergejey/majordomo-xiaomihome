<?php
chdir(dirname(__FILE__) . '/../');
include_once("./config.php");
include_once("./lib/loader.php");
include_once("./lib/threads.php");
set_time_limit(0);
// connecting to database
$db = new mysql(DB_HOST, '', DB_USER, DB_PASSWORD, DB_NAME);
include_once("./load_settings.php");
include_once(DIR_MODULES . "control_modules/control_modules.class.php");
$ctl = new control_modules();
include_once(DIR_MODULES . 'xiaomihome/xiaomihome.class.php');
$xiaomihome_module = new xiaomihome();
$xiaomihome_module->getConfig();

$gw_ip = $xiaomihome_module->config['API_IP'];
$bind_ip = $xiaomihome_module->config['API_BIND'];
if (!$bind_ip) {
    $bind_ip = "0.0.0.0";
}

echo date("H:i:s") . " running " . basename(__FILE__) . PHP_EOL;
$latest_check = 0;

$sock = 0;
function xiaomi_socket_connect()
{
    global $sock;
    global $bind_ip;
    //Create a UDP socket
    if (!($sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP))) {
        $errorcode = socket_last_error();
        $errormsg = socket_strerror($errorcode);
        DebMes("Failed to create socket [$errorcode] $errormsg ",'xiaomi');
        die("Couldn't create socket: [$errorcode] $errormsg \n");
    }
    echo "Socket created \n";
    DebMes("Socket created",'xiaomi');
// Bind the source address
    if (!socket_bind($sock, $bind_ip, XIAOMI_MULTICAST_PORT)) {
        $errorcode = socket_last_error();
        $errormsg = socket_strerror($errorcode);
        DebMes("Could not bind socket (Binding IP: $bind_ip) [$errorcode] $errormsg",'xiaomi');
        die("Could not bind socket : [$errorcode] $errormsg \n");
    }
    echo "Socket bind OK \n";
    DebMes("Socket bind OK (Binding IP: $bind_ip)",'xiaomi');
    socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, 1);
    socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 1, "usec" => 0));
    socket_set_option($sock, IPPROTO_IP, IP_MULTICAST_LOOP, true);
    socket_set_option($sock, IPPROTO_IP, IP_MULTICAST_TTL, 32);
    socket_set_option($sock, IPPROTO_IP, MCAST_JOIN_GROUP, array("group" => XIAOMI_MULTICAST_ADDRESS, "interface" => 0, "source" => 0));

    $message = '{"cmd":"whois"}';

    DebMes("Sending discovery packet to ".XIAOMI_MULTICAST_ADDRESS." ($message)",'xiaomi');
    socket_sendto($sock, $message, strlen($message), 0, XIAOMI_MULTICAST_ADDRESS, XIAOMI_MULTICAST_PORT);

}

xiaomi_socket_connect();
$latest_data_received = time();

while (1) {
    if (time() - $checked_time > 5) {
        $checked_time = time();
        setGlobal((str_replace('.php', '', basename(__FILE__))) . 'Run', time(), 1);
    }
    $queue = SQLSelect("SELECT * FROM xiqueue ORDER BY ID");
    if ($queue[0]['ID']) {
        $total = count($queue);
        for ($i = 0; $i < $total; $i++) {
            $data = $queue[$i]['DATA'];
            //echo date('H:i:s') . " Sending " . $data . "\n";
            if ($gw_ip != "") {
                $ip = $gw_ip;
            } else {
                $ip = $queue[$i]['IP'];
            }
            $xiaomihome_module->sendMessage($data, $ip, $sock);
            SQLExec("DELETE FROM xiqueue WHERE ID=" . $queue[$i]['ID']);
        }
    }
    $buf = '';
    @$r = socket_recvfrom($sock, $buf, 1024, 0, $remote_ip, $remote_port);
    if ($buf != '') {
        //echo date('H:i:s')." Message: ".$buf."\n";
        //DebMes("Received message ($buf) from $remote_ip",'xiaomi');
        $gate_ip = $remote_ip;
        $url = BASE_URL . '/ajax/xiaomihome.html?op=process&message=' . urlencode($buf) . "&ip=" . urlencode($remote_ip);
        $res = get_headers($url);
        $latest_data_received = time();
        //$xiaomihome_module->processMessage($buf, $remote_ip, $sock);
    }
    if (file_exists('./reboot') || IsSet($_GET['onetime'])) {
        break;
    }
    if (time()-$latest_data_received>60) {
        // 1 minute timeout reconnect
        DebMes("Xiaomi data timeout...",'xiaomi');
        socket_close($sock);
        xiaomi_socket_connect();
    }
}
socket_close($sock);
DebMes("Close of cycle: " . basename(__FILE__));
$db->Disconnect();