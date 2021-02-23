<?php

namespace App\Http\Controllers;

use Redirect;
use App\User;
use Auth;
use DB;
use Cookie;
use App\Mail\ResetPassword;
use Illuminate\Http\Request;

class UserController extends Controller {

    public function __construct() {
	$this->middleware('auth');
    }

    public function mpdSettings(Request $request) {
	include(app_path() . "/phpseclib/Net/SSH2.php");
	$ssh = new \phpseclib\Net\SSH2('localhost');
	$ssh->login('allo', 'allo') or die("Login failed");

	$mpdstatus = $ssh->exec("TERM=linux sudo systemctl is-active mpd | grep -cim1 '^active'");
	if ($mpdstatus == 0) {
		$mpd_status = 'Inactive';
	} elseif ($mpdstatus == 1) {
		$mpd_status = 'Active';
	}
	$ipaddress = $ssh->exec("TERM=linux mawk 'NR==4' /boot/dietpi/.network");
	$current_date = date("M d, Y");
	$current_time = date("h:i a");
	$soxr_status = $ssh->exec("TERM=linux grep -cim1 '^samplerate_converter \"soxr' /etc/mpd.conf");
	$mpdNativeOutput = $ssh->exec("TERM=linux grep -cim1 '^#format ' /etc/mpd.conf");
	if ($mpdNativeOutput == 1) {
		$outputFrequencies = 'Native';
		$bitDepth = 'Native';
	} else {
		$outputFrequencies = $ssh->exec("TERM=linux grep -m1 'format ' /etc/mpd.conf | sed 's/\"//g' | sed 's/:/ /g' | mawk '{print $2}'");
		$bitDepth = $ssh->exec("TERM=linux grep -m1 'format ' /etc/mpd.conf | sed 's/\"//g' | sed 's/:/ /g' | mawk '{print $3}'");
	}
	$urlLink = $ssh->exec("TERM=linux echo http://$ipaddress/ompd");
	$soxrQuality = $ssh->exec("TERM=linux grep -m1 'samplerate_converter \"soxr' /etc/mpd.conf | sed 's/\"//g'");

	return view('frontend.mpd_settings')->with(['mpd_status' => $mpd_status, 'soxrQuality' => $soxrQuality,
	    'outputFrequencies' => $outputFrequencies, 'bitDepth' => $bitDepth, 'current_date' => $current_date,
	    'current_time' => $current_time, 'urlLink' => $urlLink, 'soxr_status' => $soxr_status, 'ipaddress' => $ipaddress]);
    }

    public function changeMpdSettings(Request $request) {
	include(app_path() . "/phpseclib/Net/SSH2.php");
	$ssh = new \phpseclib\Net\SSH2('localhost');
	$ssh->login('allo', 'allo') or die("Login failed");

	$mpd_status = $request->mpd;
	$soxr_status = $request->soxrStatus;
	$bitDepth = $request->bitDepth;
	$frequency = $request->frequency;
	$soxrQuality = $request->soxrQuality;
	// if disabled, status = yes ; if enabled, status = no
	if ($mpd_status == 'yes') {

	    $ssh->exec("TERM=linux; sudo systemctl disable --now mpd; sudo systemctl mask mpd");

	} elseif ($mpd_status == 'no') {

		if ($soxr_status == 'yes') {

			$ssh->exec('TERM=linux sudo sed -i "/samplerate_converter \"/c\#samplerate_converter \"soxr high" /etc/mpd.conf');

		} elseif ($soxr_status == 'no') {

			$quality = $ssh->exec('TERM=linux sudo sed -i "/samplerate_converter \"/c\samplerate_converter \"soxr ' . $soxrQuality . '\"" /etc/mpd.conf');

		}

		// Reset, enable if commented out.
		//	0 = native
		$ssh->exec('TERM=linux sudo sed -i "/^format \"/c\format \"0:0:2\"" /etc/mpd.conf');
		$ssh->exec('TERM=linux sudo sed -i "/^#format \"/c\format \"0:0:2\"" /etc/mpd.conf');
		if ($frequency == 'Native' || $bitDepth == 'Native') {

			$chngOutputFrequency = $ssh->exec('TERM=linux sudo sed -i "/^format \"/c\#format \"0:0:2\"" /etc/mpd.conf');

		} else {

			$chngOutputFrequency = $ssh->exec('TERM=linux sudo sed -i "/^format \"/c\format \"' . $frequency . ':' . $bitDepth . ':2\"" /etc/mpd.conf');

		}

		$ssh->exec("TERM=linux sudo systemctl unmask mpd; TERM=linux sudo systemctl restart mpd");

	}

	return redirect('/user/mpd_settings')->with(['custom_message' => 'Successfully updated']);
    }

    public function roonSettings(Request $request) {
	include(app_path() . "/phpseclib/Net/SSH2.php");
	$ssh = new \phpseclib\Net\SSH2('localhost');
	$ssh->login('allo', 'allo') or die("Login failed");

	$current_date = date("M d, Y");
	$roonStatus = $ssh->exec("TERM=linux sudo systemctl is-active roonbridge | grep -cim1 '^active'");

	return view('frontend.roon_settings')->with(['current_date' => $current_date, 'roonStatus' => $roonStatus]);
    }

    public function changeRoonSettings(Request $request) {
	include(app_path() . "/phpseclib/Net/SSH2.php");
	$ssh = new \phpseclib\Net\SSH2('localhost');
	$ssh->login('allo', 'allo') or die("Login failed");

	$roon = $request->roon;
	// if status to be enabled ,status = no ; if status to be disabled ,status = yes
	if ($roon == 'yes') {
		$ssh->exec("TERM=linux sudo systemctl stop roonbridge; TERM=linux sudo mv /etc/systemd/system/roonbridge.service /etc/systemd/system/roonbridge.service.disable; TERM=linux sudo systemctl daemon-reload");
	} elseif ($roon = 'no') {
		$ssh->exec("TERM=linux sudo mv /etc/systemd/system/roonbridge.service.disable /etc/systemd/system/roonbridge.service; TERM=linux sudo systemctl daemon-reload; TERM=linux sudo systemctl restart roonbridge");
	}

	return redirect('/user/roon_settings')->with(['custom_message' => 'Successfully updated']);
    }

    public function gmrenderSettings(Request $request) {
	include(app_path() . "/phpseclib/Net/SSH2.php");
	$ssh = new \phpseclib\Net\SSH2('localhost');
	$ssh->login('allo', 'allo') or die("Login failed");

	$current_date = date("M d, Y");
	$gmrenderStatus = $ssh->exec("TERM=linux sudo systemctl is-active gmrender | grep -cim1 '^active'");

	return view('frontend.gmrender_settings')->with(['current_date' => $current_date, 'gmrenderStatus' => $gmrenderStatus]);
    }

    public function changeGmrenderSettings(Request $request) {
	include(app_path() . "/phpseclib/Net/SSH2.php");
	$ssh = new \phpseclib\Net\SSH2('localhost');
	$ssh->login('allo', 'allo') or die("Login failed");

	$gmrenderStatus = $request->gmrenderStatus;
	// if status to be enabled ,status = no ; if status to be disabled ,status = yes
	if ($gmrenderStatus == 'yes') {
		$ssh->exec("TERM=linux sudo systemctl disable --now gmrender; TERM=linux sudo mv /etc/systemd/system/gmrender.service /etc/systemd/system/gmrender.service.disable; TERM=linux sudo systemctl daemon-reload");
	} elseif ($gmrenderStatus = 'no') {
		$ssh->exec("TERM=linux sudo mv /etc/systemd/system/gmrender.service.disable /etc/systemd/system/gmrender.service; TERM=linux sudo systemctl daemon-reload; TERM=linux sudo systemctl restart gmrender");
	}

	return redirect('/user/gmrender_settings')->with(['custom_message' => 'Successfully updated']);
    }

    public function netdataSettings(Request $request) {
	include(app_path() . "/phpseclib/Net/SSH2.php");
	$ssh = new \phpseclib\Net\SSH2('localhost');
	$ssh->login('allo', 'allo') or die("Login failed");

	$current_date = date("M d, Y");
	$netdataStatus = $ssh->exec("TERM=linux sudo systemctl is-active netdata | grep -cim1 '^active'");
	$ipLocal = $ssh->exec("TERM=linux mawk 'NR==4' /boot/dietpi/.network");

	return view('frontend.netdata_settings')->with(['current_date' => $current_date, 'netdataStatus' => $netdataStatus, 'ipLocal'=>$ipLocal]);
    }

    public function changeNetdataSettings(Request $request) {
	include(app_path() . "/phpseclib/Net/SSH2.php");
	$ssh = new \phpseclib\Net\SSH2('localhost');
	$ssh->login('allo', 'allo') or die("Login failed");

	$netdataStatus = $request->netdataStatus;
	// if status to be enabled ,status = no ; if status to be disabled ,status = yes
	if ($netdataStatus == 'yes') {
		$ssh->exec("TERM=linux sudo systemctl disable --now netdata; TERM=linux sudo mv /etc/systemd/system/netdata.service /etc/systemd/system/netdata.service.disable; TERM=linux sudo systemctl daemon-reload");
	} elseif ($netdataStatus = 'no') {
		$ssh->exec("TERM=linux sudo mv /etc/systemd/system/netdata.service.disable /etc/systemd/system/netdata.service; TERM=linux sudo systemctl daemon-reload; TERM=linux sudo systemctl restart netdata");
	}

	return redirect('/user/netdata_settings')->with(['custom_message' => 'Successfully updated']);
    }

    public function squeezeliteSettings(Request $request) {
	include(app_path() . "/phpseclib/Net/SSH2.php");
	$ssh = new \phpseclib\Net\SSH2('localhost');
	$ssh->login('allo', 'allo') or die("Login failed");

	$current_date = date("M d, Y");
	$squeezeliteStatus = $ssh->exec("TERM=linux sudo systemctl is-active squeezelite | grep -cim1 '^active'");
	if (file_exists('/etc/systemd/system/squeezelite.service')) {
		$bitDepth = (string) trim($ssh->exec("TERM=linux grep -m1 '^ExecStart=' /etc/systemd/system/squeezelite.service | mawk '{print $3}' | sed 's/:/ /g' | mawk '{print $3}'"));
		$DSD_NATIVE = (string) trim($ssh->exec("TERM=linux grep -m1 '^ExecStart=' /etc/systemd/system/squeezelite.service | mawk '{print $11}' | sed 's/://g'"));
		if ( ! $DSD_NATIVE )
		{
			$DSD_NATIVE = 'disabled';
		}
	} else {
		$bitDepth = 16;
		$DSD_NATIVE = 'disabled';
	}

	return view('frontend.squeezelite_settings')->with(['current_date' => $current_date, 'squeezeliteStatus' => $squeezeliteStatus, 'bitDepth'=>$bitDepth, 'DSD_NATIVE'=>$DSD_NATIVE]);
    }

    public function changeSqueezeliteSettings(Request $request) {
	include(app_path() . "/phpseclib/Net/SSH2.php");
	$ssh = new \phpseclib\Net\SSH2('localhost');
	$ssh->login('allo', 'allo') or die("Login failed");

	$squeezeliteStatus = $request->squeezeliteStatus;
	// if status to be enabled ,status = no ; if status to be disabled ,status = yes
	if ($squeezeliteStatus == 'yes') {
		$ssh->exec("TERM=linux sudo systemctl disable --now squeezelite; TERM=linux sudo mv /etc/systemd/system/squeezelite.service /etc/systemd/system/squeezelite.service.disable; TERM=linux sudo systemctl daemon-reload");
	} elseif ($squeezeliteStatus = 'no') {
		$ssh->exec("TERM=linux sudo mv /etc/systemd/system/squeezelite.service.disable /etc/systemd/system/squeezelite.service");
			$bitDepth = $request->bitDepth;
			$DSD_NATIVE = $request->DSD_NATIVE;
			if ( $DSD_NATIVE == 'disabled' ) {
				$chngbitDepth = $ssh->exec('TERM=linux sudo sed -i "/^ExecStart=/c\ExecStart=/usr/bin/squeezelite -a 4096:8096:' . $bitDepth . ':0 -C 5 -n \'DietPi-Squeezelite\' -f /var/log/squeezelite.log" /etc/systemd/system/squeezelite.service;');
			} else {
				$chngbitDepth = $ssh->exec('TERM=linux sudo sed -i "/^ExecStart=/c\ExecStart=/usr/bin/squeezelite -a 4096:8096:' . $bitDepth . ':0 -C 5 -n \'DietPi-Squeezelite\' -f /var/log/squeezelite.log -D :' . $DSD_NATIVE . '" /etc/systemd/system/squeezelite.service;');
			}
		$ssh->exec("TERM=linux sudo systemctl daemon-reload; TERM=linux sudo systemctl restart squeezelite");
	}

	return redirect('/user/squeezelite_settings')->with(['custom_message' => 'Successfully updated']);
    }

    public function shairPortSettings(Request $request) {
	include(app_path() . "/phpseclib/Net/SSH2.php");
	$ssh = new \phpseclib\Net\SSH2('localhost');
	$ssh->login('allo', 'allo') or die("Login failed");

	$current_date = date("M d, Y");
	$shairPortStatus = $ssh->exec("TERM=linux sudo systemctl is-active shairport-sync | grep -cim1 '^active'");
	$outputFrequencies = $ssh->exec("TERM=linux grep -m1 'output_rate' /usr/local/etc/shairport-sync.conf | sed 's/\///g' | mawk '{print $3}' | sed 's/\;//g'");
	$bitDepth = (string) trim($ssh->exec("TERM=linux grep -m1 'output_format' /usr/local/etc/shairport-sync.conf | sed 's/\///g' | mawk '{print $3}' | sed 's/\;//g' | sed 's/\"//g' | sed 's/S//g'"));

	return view('frontend.shair_port_settings')->with(['current_date' => $current_date, 'shairPortStatus' => $shairPortStatus, 'outputFrequencies' => $outputFrequencies, 'bitDepth' => $bitDepth]);
    }

    public function changeshairPortSettings(Request $request) {
	include(app_path() . "/phpseclib/Net/SSH2.php");
	$ssh = new \phpseclib\Net\SSH2('localhost');
	$ssh->login('allo', 'allo') or die("Login failed");

	$shairPort = $request->shairPort;
	$bitDepth = $request->bitDepth;
	$frequency = $request->frequency;
	$chngOutputFrequency = $ssh->exec('TERM=linux sudo sed -i "/output_rate /c\output_rate = ' . $frequency . ';" /usr/local/etc/shairport-sync.conf' );
	$chngbitDepth = $ssh->exec('TERM=linux sudo sed -i "/output_format /c\output_format = \"S' . $bitDepth . '\";" /usr/local/etc/shairport-sync.conf');
	if ($shairPort == 'yes') {
		$ssh->exec("TERM=linux sudo systemctl disable --now shairport-sync; TERM=linux sudo systemctl mask shairport-sync");
	} elseif ($shairPort = 'no') {
		$ssh->exec("TERM=linux sudo systemctl unmask shairport-sync; TERM=linux sudo systemctl restart shairport-sync");
	}

	return redirect('/user/shair_port_settings')->with(['custom_message' => 'Successfully updated']);
    }

    public function daemonSettings(Request $request) {
	include(app_path() . "/phpseclib/Net/SSH2.php");
	$ssh = new \phpseclib\Net\SSH2('localhost');
	$ssh->login('allo', 'allo') or die("Login failed");

	$current_date = date("M d, Y");
	$daemonStatus = $ssh->exec("TERM=linux sudo systemctl is-active networkaudiod | grep -cim1 '^active'");

	return view('frontend.daemon_settings')->with(['current_date' => $current_date, 'daemonStatus' => $daemonStatus]);
    }

    public function changeDaemonSettings(Request $request) {
	include(app_path() . "/phpseclib/Net/SSH2.php");
	$ssh = new \phpseclib\Net\SSH2('localhost');
	$ssh->login('allo', 'allo') or die("Login failed");

	$daemon = $request->daemon;
	// if status to be enabled ,status = no ; if status to be disabled ,status = yes
	if ($daemon == 'yes') {
		$ssh->exec("TERM=linux sudo systemctl disable --now networkaudiod; TERM=linux sudo systemctl mask networkaudiod");
	} elseif ($daemon = 'no') {
		$ssh->exec("TERM=linux sudo systemctl unmask networkaudiod; TERM=linux sudo systemctl restart networkaudiod");
	}

	return redirect('/user/daemon_settings')->with(['custom_message' => 'Successfully updated']);
    }

    public function wifiSettings(Request $request) {
	include(app_path() . "/phpseclib/Net/SSH2.php");
	$ssh = new \phpseclib\Net\SSH2('localhost');
	$ssh->login('allo', 'allo') or die("Login failed");

	$current_date = date("M d, Y");
	$wifiStatus = $ssh->exec("TERM=linux sudo systemctl is-active hostapd | grep -cim1 '^active'");
	$currentSSID = $ssh->exec("TERM=linux sudo sed -n '/^ssid=/{s/^[^=]*=//p;q}' /etc/hostapd/hostapd.conf");
	$currentPasskey = $ssh->exec("TERM=linux sudo sed -n '/^wpa_passphrase=/{s/^[^=]*=//p;q}' /etc/hostapd/hostapd.conf");
	$ssh->exec("TERM=linux sudo systemctl restart hostapd");

	return view('frontend.wifi_settings')->with(['current_date' => $current_date, 'wifiStatus' => $wifiStatus, 'currentSSID' => $currentSSID, 'currentPasskey' => $currentPasskey]);
    }

    public function changeWifiSettings(Request $request) {
	include(app_path() . "/phpseclib/Net/SSH2.php");
	$ssh = new \phpseclib\Net\SSH2('localhost');
	$ssh->login('allo', 'allo') or die("Login failed");

	$currentSSID = $request->wifiSsid;
	$wifiStatus = $request->wifiStatus;
	$currentPasskey = $request->wifiPasskey;
	// for changing ssid
	$ssh->exec("TERM=linux sudo sed -i '/ssid=/c\ssid=$currentSSID' /etc/hostapd/hostapd.conf");
	// for changing passkey
	$ssh->exec("TERM=linux sudo sed -i '/wpa_passphrase=/c\wpa_passphrase=$currentPasskey' /etc/hostapd/hostapd.conf");
	// if status to be enabled ,status = no ; if status to be disabled ,status = yes
	if ($wifiStatus == 'yes') {
		$ssh->exec("TERM=linux; sudo systemctl disable --now hostapd; sudo systemctl mask hostapd");
	} elseif ($wifiStatus = 'no') {
		$ssh->exec("TERM=linux; sudo systemctl unmask hostapd; sudo systemctl restart hostapd");
	}

	return redirect('/user/wifi_settings')->with(['custom_message' => 'Successfully updated']);
    }

    public function download(Request $request) {
	include(app_path() . "/phpseclib/Net/SSH2.php");
	$ssh = new \phpseclib\Net\SSH2('localhost');
	$ssh->login('allo', 'allo') or die("Login failed");

	$ipLocal = $ssh->exec("TERM=linux mawk 'NR==4' /boot/dietpi/.network");
	$current_date = date("M d, Y");
	unset($output);
	$file_name = "/tmp/Sound_Card_Status.txt";
	$output = "\n\n----- SOUND CARD STATUS -----\n\n";
	$output = "\n\n----- USB DAC INFO -----\n\n";
	$output .= $ssh->exec("lsusb");
	$output .= "\n\n---- USB PORT INFO----\n\n";
	$output .= $ssh->exec("lsusb -t");
	$output .= "\n\n---- SOUND CARDS INFO----\n\n";
	$hw_param = $ssh->exec("cat /proc/asound/card1/stream0");
	if(preg_match('/\bNo such file or directory\b/',$hw_param)) {
		$hw_param = "NO SOUND CARDS";
	}
	$output .= $hw_param;
	$output .= "\n\n---- APLAY INFO ----\n\n";
	$output .= $ssh->exec("sudo aplay -l");
	$output .= "\n\n---- DMESG INFO ----\n\n";
	$output .= $ssh->exec("sudo dmesg");
	$output .= "\n\n\n--------------*******-----------\n\n";
	if (file_exists($file_name)) {
		$del_tmp_file = $ssh->exec("rm $file_name");
	}
	$myfile = fopen($file_name, "w") or die("Unable to open file!");
	fwrite($myfile, $output);
	fclose($myfile);
	if (file_exists($file_name) && is_readable($file_name)) {
		header('Content-Description: File Transfer');
		header('Content-Type: application/txt');
		header("Content-Disposition: attachment; filename=\"Sound_Card_Status.txt\"");
		readfile($file_name);
	} else {
		header("HTTP/1.0 404 Not Found");
		echo "<h1>Error 404: File Not Found: <br /><em>Sound_Card_Status</em></h1>";
	}
    }

    public function status(Request $request) {
	include(app_path() . "/phpseclib/Net/SSH2.php");
	$ssh = new \phpseclib\Net\SSH2('localhost');
	$ssh->login('allo', 'allo') or die("Login failed");

	$ipLocal = $ssh->exec("TERM=linux mawk 'NR==4' /boot/dietpi/.network");
	$current_date = date("M d, Y");
	$status = $ssh->exec("TERM=linux sudo cat /proc/asound/card*/pcm0p/sub0/hw_params");
	$cpu_temp = $ssh->exec("TERM=linux; . /boot/dietpi/func/dietpi-globals; G_INTERACTIVE=0 G_OBTAIN_CPU_TEMP");
	$cpu_usage = $ssh->exec("TERM=linux; . /boot/dietpi/func/dietpi-globals; G_INTERACTIVE=0 G_OBTAIN_CPU_USAGE");
	$total_memory = $ssh->exec("TERM=linux echo \"$(( $(mawk '/^MemTotal:/{print $2;exit}' /proc/meminfo) / 1000 ))\"");
	$free_memory = $ssh->exec("TERM=linux echo \"$(( $(mawk '/^MemFree:/{print $2;exit}' /proc/meminfo) / 1000 ))\"");
	$memory_usage = intval($total_memory) - intval($free_memory);
	$memory_usage_perc = intval($memory_usage) * 100 /intval($total_memory);
	$total_storage = $ssh->exec("TERM=linux df -m | mawk '/\/$/{print $2;exit}'");
	$free_storage = $ssh->exec("TERM=linux df -m | mawk '/\/$/{print $4;exit}'");
	$storage_usage = intval($total_storage) - intval($free_storage);
	$storage_usage_perc = intval($storage_usage) * 100 /intval($total_storage);
	$alsaInfo = $ssh->exec("TERM=linux sudo cat /proc/asound/card*/pcm0p/sub0/hw_params | sed ':a;N;$!ba;s/\'n'/ | /g'");
	$alsaInfo = ltrim($alsaInfo, 'closed |');
	$lsusb = $ssh->exec("TERM=linux lsusb");
	$lsusb_port = $ssh->exec("TERM=linux lsusb -t");
	$hw_param = $ssh->exec("TERM=linux cat /proc/asound/card1/stream0");
	if(preg_match('/\bNo such file or directory\b/',$hw_param)) {
		$hw_param = "No Sound Cards";
	}
	$aplay = $ssh->exec("TERM=linux sudo aplay -l");
	$dmesg = $ssh->exec("TERM=linux sudo dmesg");

	return view('frontend.status')->with(['ipLocal' => $ipLocal, 'current_date' => $current_date, 'status' => $status,'cpu_temp'=>$cpu_temp, 'cpu_usage'=>$cpu_usage,'total_memory'=>$total_memory,'free_memory'=>$free_memory,'memory_usage'=>$memory_usage, 'memory_usage_perc'=>$memory_usage_perc,'total_storage'=>$total_storage,'free_storage'=>$free_storage, 'storage_usage'=>$storage_usage,'storage_usage_perc'=>$storage_usage_perc,'alsaInfo'=>$alsaInfo,'lsusb'=>$lsusb,'lsusb_port'=>$lsusb_port, 'hw_param'=>$hw_param, 'aplay'=>$aplay, 'dmesg'=>$dmesg]); 
    }

     public function systemSettings(Request $request) {
	include(app_path() . "/phpseclib/Net/SSH2.php");
	$ssh = new \phpseclib\Net\SSH2('localhost');
	$ssh->login('allo', 'allo') or die("Login failed");

	$selected = $request->get('selected');
	$selected = $ssh->exec("TERM=linux grep -cim1 'iface eth0 inet dhcp' /etc/network/interfaces");
	$ipaddress = $ssh->exec("TERM=linux ip a s eth0 | grep -m1 '^[[:blank:]]*inet ' | mawk '{print $2}' | sed 's|/.*$||'");
	$current_date = date("M d, Y");
	$current_time = date("h:i a");
	$soundCard = $ssh->exec("TERM=linux sed -n '/^CONFIG_SOUNDCARD=/{s/^[^=]*=//p;q}' /boot/dietpi.txt");
	$amixerCtrlList = (array) null;
	if(trim($soundCard) == "allo-boss2-dac-audio" ) {
		$list = $ssh->exec("TERM=linux sudo amixer -c Boss2 | grep \"Simple mixer control\"  | cut -f1 -d, | cut -f2 -d\' ");
		$ctrlList = explode("\n", $list);
		$amixerCtrlList = array_filter($ctrlList);
	}
	$Master = '';
	$Digital = '';
	$pcm_de_emphasis_filter= '';
	$pcm_filter_speed = '';
	$pcm_high_pass_filter= '';
	$pcm_nonoversample= '';
	$pcm_phase_compensation= '';
	$hv_enable= '';
	if(trim($soundCard) == "allo-boss2-dac-audio" ) {
		if (in_array("Master", $amixerCtrlList)) {
			//error_log("master is thr ",0);
			$Master = $ssh->exec("TERM=linux sudo amixer -c Boss2  get 'Master' | grep 'Front Left:' | cut -f1  -d% | cut -f2 -d[");
		}
		if (in_array("Digital", $amixerCtrlList)) {
			//error_log("Digital is thr ",0);
			$Digital = $ssh->exec("TERM=linux sudo amixer -c Boss2  get 'Digital' | grep 'Front Left:' | cut -f1  -d% | cut -f2 -d[");
		}
		if (in_array("PCM De-emphasis Filter", $amixerCtrlList)) {
			//error_log("PCM De-emphasis Filter is thr ",0);
			$pcm_de_emphasis_filter= $ssh->exec("TERM=linux sudo amixer -c Boss2  get 'PCM De-emphasis Filter' | grep 'Mono:' | cut -f1 -d] | cut -f2 -d[");
		}
		if (in_array("PCM Filter Speed", $amixerCtrlList)) {
			//error_log("PCM Filter Speed is thr ",0);
			$pcm_filter_speed = $ssh->exec("TERM=linux sudo amixer -c Boss2  get 'PCM Filter Speed' | grep 'Item0:' | cut -f2 -d:");
		}
		if (in_array("PCM High-pass Filter", $amixerCtrlList)) {
			//error_log(" PCM High-pass Filter is thr ",0);
			$pcm_high_pass_filter= $ssh->exec("TERM=linux sudo amixer -c Boss2  get 'PCM High-pass Filter' | grep 'Mono:' | cut -f1 -d] | cut -f2 -d[");
		}
		if (in_array("PCM Nonoversample Emulate", $amixerCtrlList)) {
			//error_log("PCM Nonoversample Emulate is thr ",0);
			$pcm_nonoversample= $ssh->exec("TERM=linux sudo amixer -c Boss2  get 'PCM Nonoversample Emulate' | grep 'Mono:' | cut -f1 -d] | cut -f2 -d[");
		}
		if (in_array("PCM Phase Compensation", $amixerCtrlList)) {
			//error_log("PCM Phase Compensation is thr ",0);
			$pcm_phase_compensation= $ssh->exec("TERM=linux sudo amixer -c Boss2  get 'PCM Phase Compensation' | grep 'Mono:' | cut -f1 -d] | cut -f2 -d[");
		}
		if (in_array("HV_Enable", $amixerCtrlList)) {
			//error_log("HV_Enable is thr ",0);
			$hv_enable= $ssh->exec("TERM=linux sudo amixer -c Boss2  get 'HV_Enable' | grep 'Mono:' | cut -f1 -d] | cut -f2 -d[");
		}
	#	error_log(" this is from userControl - system settings----",0);
	#	error_log("Master-- $Master ",0);
	#	error_log("Digital-- $Digital ",0);
	#	error_log("PCM de emphasis-- $pcm_de_emphasis_filter ",0);
	#	error_log("PCM filter speed -- $pcm_filter_speed ",0);
	#	error_log("PCM High pass filter -- $pcm_high_pass_filter ",0);
	#	error_log("PCM nonoversample -- $pcm_nonoversample ",0);
	#	error_log("PCM phase compensation -- $pcm_phase_compensation ",0);
	#	error_log("HV-enable -- $hv_enable ",0);
	}
	$hostName = $ssh->exec("TERM=linux cat /etc/hostname");
	$ipGateway = $ssh->exec("TERM=linux ip r | mawk '/default/{print $3;exit}'");
	$ipMask = '255.255.255.0';
	$ipDns = $ssh->exec("TERM=linux mawk '/nameserver/{print $2;exit}' /etc/resolv.conf");
	$soundCard = preg_replace('/\s+/', '', $soundCard);
	$cpuGovernor = $ssh->exec("TERM=linux sed -n '/^CONFIG_CPU_GOVERNOR=/{s/^[^=]*=//p;q}' /boot/dietpi.txt");
	$cpuGovernor = preg_replace('/\s+/', '', $cpuGovernor);
	$updateDietPiStatus = $ssh->exec("if [ -f '/run/dietpi/.update_available' ]; then TERM=linux cat /boot/dietpi/.update_available; fi");
	$currentversionDietPi = $ssh->exec("TERM=linux; . /boot/dietpi/.version; echo \"\$G_DIETPI_VERSION_CORE.\$G_DIETPI_VERSION_SUB.\$G_DIETPI_VERSION_RC\"");
	$HW_MODEL = $ssh->exec("TERM=linux; . /boot/dietpi/.hw_model; echo \$G_HW_MODEL");
	$rangeVal = $ssh->exec("TERM=linux sed -n '/^AUTO_SETUP_SWAPFILE_SIZE=/{s/^[^=]*=//p;q}' /boot/dietpi.txt");

	return view('frontend.system_settings')->with(['ipAddress' => $ipaddress, 'soundCard' => $soundCard, 'amixerCtrlList' => $amixerCtrlList, 'Master' => $Master, 'Digital' => $Digital, 'pcm_de_emphasis_filter' => $pcm_de_emphasis_filter, 'pcm_filter_speed' => $pcm_filter_speed, 'pcm_high_pass_filter' => $pcm_high_pass_filter, 'pcm_nonoversample' => $pcm_nonoversample, 'pcm_phase_compensation' => $pcm_phase_compensation, 'hv_enable' => $hv_enable, 'hostName' => $hostName,'ipGateway' => $ipGateway, 'ipMask' => $ipMask, 'ipDns' => $ipDns, 'current_date' => $current_date,'current_time' => $current_time, 'selectoption' => $selected, 'updateDietPiStatus' => $updateDietPiStatus, 'currentversionDietPi' => $currentversionDietPi,'cpuGovernor' => $cpuGovernor,'HW_MODEL' => $HW_MODEL,'rangeVal' => $rangeVal]);
    }

    public function changeSystemSettings(Request $request) {
	include(app_path() . "/phpseclib/Net/SSH2.php");
	$ssh = new \phpseclib\Net\SSH2('localhost');
	$ssh->login('allo', 'allo') or die("Login failed");

	$ip = $request->IP;
	$host = $request->host;
	$address = $request->address;
	$gateway = $request->gateway;
	$mask = $request->mask;
	$dns = $request->dns;
	$chag_sort = $request->chag_sort;
	$soundcard = $request->soundcard;
	$cpuGovernor = $request->cpuGovernor;
	$master = $request->master_chng_val;
	$digital = $request->digital_chng_val;
	$pcm_de_emphasis_filter = $request->pcm_de_emphasis_val;
	$pcm_filter_speed = $request->pcm_filter_speed_val;
	$pcm_high_pass_filter = $request->pcm_high_pass_filter_val;
	$pcm_nonoversample = $request->pcm_nonoversample_val;
	$pcm_phase_compensation = $request->pcm_phase_compensation_val;
	$HV_Enable  = $request->hv_enable_val;

	$ssh->exec("TERM=linux G_INTERACTIVE=0 sudo /boot/dietpi/func/dietpi-set_hardware soundcard $soundcard");
	$ssh->exec("TERM=linux G_INTERACTIVE=0 sudo /boot/dietpi/func/change_hostname $host");

	$amixerCtrlList = (array) null;
	if(trim($soundcard) == "allo-boss2-dac-audio" ) {
		$list = $ssh->exec("TERM=linux sudo amixer -c Boss2 | grep \"Simple mixer control\"  | cut -f1 -d, | cut -f2 -d\' ");
		$ctrlList = explode("\n", $list);
		$amixerCtrlList = array_filter($ctrlList);
	}

	if(isset($host) && !empty($host)) {
		if( $soundcard == 'allo-boss2-dac-audio'){
			if (in_array("Master", $amixerCtrlList)) {
				//error_log(" can set master is thr ",0);
				$ssh->exec("TERM=linux sudo amixer -c Boss2 -q set 'Master' '$master%'");
			}
			if (in_array("Digital", $amixerCtrlList)) {
				//error_log(" can set digital is thr ",0);
				$ssh->exec("TERM=linux sudo amixer -c Boss2 -q set 'Digital' '$digital%'");
			}
			if (in_array("PCM De-emphasis Filter", $amixerCtrlList)) {
				//error_log(" can set PCM De-emphasis Filter is thr ",0);
				$ssh->exec("TERM=linux sudo amixer -M -c Boss2 -q set 'PCM De-emphasis Filter' '$pcm_de_emphasis_filter'");
			}
			if (in_array("PCM Filter Speed", $amixerCtrlList)) {
				//error_log(" can set PCM Filter Speed is thr ",0);
				$ssh->exec("TERM=linux sudo amixer -M -c Boss2 -q set 'PCM Filter Speed' '$pcm_filter_speed'");
			}
			if (in_array("PCM High-pass Filter", $amixerCtrlList)) {
				//error_log(" can set PCM High-pass Filter is thr ",0);
				$ssh->exec("TERM=linux sudo amixer -M -c Boss2 -q set  'PCM High-pass Filter' '$pcm_high_pass_filter'");
			}
			if (in_array("PCM Nonoversample Emulate", $amixerCtrlList)) {
				//error_log(" can set PCM Nonoversample Emulate is thr ",0);
				$ssh->exec("TERM=linux sudo amixer -M -c Boss2 -q set  'PCM Nonoversample Emulate' '$pcm_nonoversample'");
			}
			if (in_array("PCM Phase Compensation", $amixerCtrlList)) {
				//error_log(" can set PCM Phase Compensation is thr ",0);
				$ssh->exec("TERM=linux sudo amixer -M -c Boss2 -q set  'PCM Phase Compensation' '$pcm_phase_compensation'");
			}
			if (in_array("HV_Enable", $amixerCtrlList)) {
				//error_log(" can set HV_Enable is thr ",0);
				$ssh->exec("TERM=linux sudo amixer -M -c Boss2 -q set  'HV_Enable' '$HV_Enable'");
			}
			$ssh->exec("TERM=linux sudo alsactl store");
		}
		$ssh->exec("TERM=linux G_INTERACTIVE=0 sudo /boot/dietpi/func/dietpi-set_swapfile $chag_sort");
		$ssh->exec("TERM=linux; sudo sed -i '/CONFIG_CPU_GOVERNOR=/c\CONFIG_CPU_GOVERNOR=$cpuGovernor' /boot/dietpi.txt; G_INTERACTIVE=0 sudo /boot/dietpi/func/dietpi-set_cpu");
		if($ip=='dhcp') {
			$ssh->exec("TERM=linux G_INTERACTIVE=0 sudo /boot/dietpi/func/dietpi-set_software allo eth_dhcp");
			$ssh->exec("TERM=linux sudo systemctl daemon-reload");
		}
		else {
			$ssh->exec("TERM=linux G_INTERACTIVE=0 sudo /boot/dietpi/func/dietpi-set_software allo eth_static $address $gateway $mask $dns");
			$ssh->exec("TERM=linux sudo systemctl daemon-reload");
		}
	} else {
		return redirect('/user/system_settings')->with(['custom_message' => 'Please enter the hostname']);
	}

	return redirect('/user/system_settings')->with(['custom_message' => '<p>NB: If any of the following items have been changed, please reboot your system to apply the new settings:</p>
	    <ul>
		<li>
		    IP Address Change (DHCP/STATIC)
		</li>
		<li>
		    Hostname
		</li>
		<li>
		    Soundcard Selection
		</li>
		<li>
		    Update DietPi
		</li>
	    </ul>'
	]);
    }

    public function updateDietPi(Request $request) {
	include(app_path() . "/phpseclib/Net/SSH2.php");
	$ssh = new \phpseclib\Net\SSH2('localhost');
	$ssh->login('allo', 'allo') or die("Login failed");

	$ssh->setTimeout(0);
	$update = $ssh->exec("TERM=linux G_INTERACTIVE=0 sudo /boot/dietpi/dietpi-update 1");

	return 1;
    }

    public function reeboot(Request $request) {
	include(app_path() . "/phpseclib/Net/SSH2.php");
	$ssh = new \phpseclib\Net\SSH2('localhost');
	$ssh->login('allo', 'allo') or die("Login failed");

	$ssh->setTimeout(1);
	$update = $ssh->exec("TERM=linux; sleep 3; sudo reboot");

	return 1;
    }

    public function power(Request $request) {
	include(app_path() . "/phpseclib/Net/SSH2.php");
	$ssh = new \phpseclib\Net\SSH2('localhost');
	$ssh->login('allo', 'allo') or die("Login failed");

	$update = $ssh->exec("TERM=linux sudo poweroff");

	return 1;
    }


    public function swapFileSize(Request $request) {
	$val = $request->val;
	include(app_path() . "/phpseclib/Net/SSH2.php");
	$ssh = new \phpseclib\Net\SSH2('localhost');
	$ssh->login('allo', 'allo') or die("Login failed");

	$ssh->exec("TERM=linux G_INTERACTIVE=0 sudo /boot/dietpi/func/dietpi-set_swapfile $val");
	$success = $ssh->exec("TERM=linux sed -n '/^[[:blank:]]*AUTO_SETUP_SWAPFILE_SIZE=/{s/^[^=]*=//p;q}' /boot/dietpi.txt");

	return $success;
    }

    public function updateSoundCard(Request $request) {
	$val = $request->val;
	include(app_path() . "/phpseclib/Net/SSH2.php");
	$ssh = new \phpseclib\Net\SSH2('localhost');
	$ssh->login('allo', 'allo') or die("Login failed");

	$success = $ssh->exec("TERM=linux G_INTERACTIVE=0 sudo /boot/dietpi/func/dietpi-set_hardware soundcard $val");

	return $success;
    }

}
