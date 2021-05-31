<?php

namespace App\Http\Controllers;

use Redirect;
use App\User;
use Auth;
use App\Mail\ResetPassword;
use Illuminate\Http\Request;

class AccountController extends Controller {

    public function __construct() {
	$this->middleware('auth');
    }

    public function Index() {
        // echo "<pre>";print_r('hi');die;
        include(app_path() . "/phpseclib/Net/SSH2.php");
	$ssh = new \phpseclib\Net\SSH2('localhost');
	$ssh->login('allo', 'allo') or die("Login failed");

        $current_date = date("M d, Y");
        $current_time = date("h:i a");
        $ipaddress = $ssh->exec("TERM=linux ip a s eth0 | grep -m1 '^[[:blank:]]*inet ' | mawk '{print $2}' | sed 's|/.*$||'");
        $hostName = $ssh->exec("TERM=linux sudo cat /etc/hostname");
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
			//error_log(" can set master is thr ",0);
			$Master = $ssh->exec("TERM=linux sudo amixer -c Boss2  get 'Master' | grep 'Front Left:' | cut -f1  -d% | cut -f2 -d[");
		}
		if (in_array("Digital", $amixerCtrlList)) {
			//error_log(" can set digital is thr ",0);
			$Digital = $ssh->exec("TERM=linux sudo amixer -c Boss2  get 'Digital' | grep 'Front Left:' | cut -f1  -d% | cut -f2 -d[");
		}
		if (in_array("PCM De-emphasis Filter", $amixerCtrlList)) {
			//error_log(" can set PCM De-emphasis Filter is thr ",0);
			$pcm_de_emphasis_filter= $ssh->exec("TERM=linux sudo amixer -c Boss2  get 'PCM De-emphasis Filter' | grep 'Mono:' | cut -f1 -d] | cut -f2 -d[");
		}
		if (in_array("PCM Filter Speed", $amixerCtrlList)) {
			//error_log(" can set PCM Filter Speed is thr ",0);
			$pcm_filter_speed = $ssh->exec("TERM=linux sudo amixer -c Boss2  get 'PCM Filter Speed' | grep 'Item0:' | cut -f2 -d:");
		}
		if (in_array("PCM High-pass Filter", $amixerCtrlList)) {
			//error_log(" can set PCM High-pass Filter is thr ",0);
			$pcm_high_pass_filter= $ssh->exec("TERM=linux sudo amixer -c Boss2  get 'PCM High-pass Filter' | grep 'Mono:' | cut -f1 -d] | cut -f2 -d[");
		}
		if (in_array("PCM Nonoversample Emulate", $amixerCtrlList)) {
			//error_log(" can set PCM Nonoversample Emulate is thr ",0);
			$pcm_nonoversample= $ssh->exec("TERM=linux sudo amixer -c Boss2  get 'PCM Nonoversample Emulate' | grep 'Mono:' | cut -f1 -d] | cut -f2 -d[");
		}
		if (in_array("PCM Phase Compensation", $amixerCtrlList)) {
			//error_log(" can set PCM Phase Compensation is thr ",0);
			$pcm_phase_compensation= $ssh->exec("TERM=linux sudo amixer -c Boss2  get 'PCM Phase Compensation' | grep 'Mono:' | cut -f1 -d] | cut -f2 -d[");
		}
		if (in_array("HV_Enable", $amixerCtrlList)) {
			//error_log(" can set HV_Enable is thr ",0);
			$hv_enable= $ssh->exec("TERM=linux sudo amixer -c Boss2  get 'HV_Enable' | grep 'Mono:' | cut -f1 -d] | cut -f2 -d[");
		}
	}

        $mpdstatus = $ssh->exec("TERM=linux sudo systemctl is-active mpd | grep -cim1 '^active'");
        if ($mpdstatus == 0) {
		$mpd_status = 'Inactive';
        } elseif ($mpdstatus == 1) {
		$mpd_status = 'Active';
        }
	$mpdNativeOutput = $ssh->exec("TERM=linux grep -cim1 '^#format ' /etc/mpd.conf");
	if ($mpdNativeOutput == 1) {
		$outputFrequencies = 'Native';
		$bitDepth = 'Native';
	} else {
		$outputFrequencies = $ssh->exec("TERM=linux grep -m1 'format ' /etc/mpd.conf | sed 's/\"//g' | sed 's/:/ /g' | mawk '{print $2}'");
		$outputFrequencies = "$outputFrequencies Hz";
		$bitDepth = $ssh->exec("TERM=linux grep -m1 'format ' /etc/mpd.conf | sed 's/\"//g' | sed 's/:/ /g' | mawk '{print $3}'");
	}

        $roonStatus = $ssh->exec("TERM=linux sudo systemctl is-active roonbridge | grep -cim1 '^active'");
        if ($roonStatus == 0) {
            $roon_status = 'Inactive';
        } elseif ($roonStatus == 1) {
            $roon_status = 'Active';
        }

        $daemonStatus = $ssh->exec("TERM=linux sudo systemctl is-active networkaudiod | grep -cim1 '^active'");
        if ($daemonStatus == 0) {
            $daemon_status = 'Inactive';
        } elseif ($daemonStatus == 1) {
            $daemon_status = 'Active';
        }

        $wifi_status = $ssh->exec("TERM=linux sudo systemctl is-active hostapd | grep -cim1 '^active'");
        if ($wifi_status == 0) {
            $wifiStatus = 'Inactive';
        } elseif ($wifi_status == 1) {
            $wifiStatus = 'Active';
        }

        $currentSSID = $ssh->exec("TERM=linux sudo sed -n '/^ssid=/{s/^[^=]*=//p;q}' /etc/hostapd/hostapd.conf");
        $currentPasskey = $ssh->exec("TERM=linux sudo sed -n '/^wpa_passphrase=/{s/^[^=]*=//p;q}' /etc/hostapd/hostapd.conf");

        $shairPortStatus = $ssh->exec("TERM=linux sudo systemctl is-active shairport-sync | grep -cim1 '^active'");
        if ($shairPortStatus == 0) {
            $shairPortStatus = 'Inactive';
        } elseif ($shairPortStatus == 1) {
            $shairPortStatus = 'Active';
        }
        $outputFrequencies_shair = $ssh->exec("TERM=linux sudo grep -m1 'output_rate' /usr/local/etc/shairport-sync.conf | sed 's/\///g' | mawk '{print $3}' | sed 's/\;//g'");
        $bitDepth_shair = $ssh->exec("TERM=linux sudo grep -m1 'output_format' /usr/local/etc/shairport-sync.conf | sed 's/\///g' | mawk '{print $3}' | sed 's/\;//g' | sed 's/\"//g' | sed 's/S//g'");

        $cpu_temp = $ssh->exec("TERM=linux . /boot/dietpi/func/dietpi-globals; G_INTERACTIVE=0 G_OBTAIN_CPU_TEMP");
        // $cpu_usage = $ssh->exec("TERM=linux G_INTERACTIVE=0 sudo /boot/dietpi/dietpi-cpuinfo 3");

		$gmrenderStatus = $ssh->exec("TERM=linux sudo systemctl is-active gmrender | grep -cim1 '^active'");
        if ($gmrenderStatus == 0) {
            $gmrenderStatus = 'Inactive';
        } elseif ($gmrenderStatus == 1) {
            $gmrenderStatus = 'Active';
        }

		$netdataStatus = $ssh->exec("TERM=linux sudo systemctl is-active netdata | grep -cim1 '^active'");
        if ($netdataStatus == 0) {
            $netdataStatus = 'Inactive';
        } elseif ($netdataStatus == 1) {
            $netdataStatus = 'Active';
        }

		$squeezeliteStatus = $ssh->exec("TERM=linux sudo systemctl is-active squeezelite | grep -cim1 '^active'");
        if ($squeezeliteStatus == 0) {
            $squeezeliteStatus = 'Inactive';
        } elseif ($squeezeliteStatus == 1) {
            $squeezeliteStatus = 'Active';
        }

		if (file_exists('/lib/systemd/system/squeezelite.service'))
		{
			$bitDepth_squeezelite = (string) trim($ssh->exec("TERM=linux sudo grep -m1 '^ExecStart=' /lib/systemd/system/squeezelite.service | mawk '{print $3}' | sed 's/:/ /g' | mawk '{print $3}'"));
		}
		else
		{
			$bitDepth_squeezelite = 16;
		}


        return view('frontend.dashboard')->with(['ipAddress' => $ipaddress, 'current_date' => $current_date, 'current_time' => $current_time, 'ipaddress' => $ipaddress, 'hostName' => $hostName, 'soundCard' => $soundCard, 'Master' => $Master, 'Digital' => $Digital, 'pcm_de_emphasis_filter' => $pcm_de_emphasis_filter, 'pcm_filter_speed' => $pcm_filter_speed, 'pcm_high_pass_filter' => $pcm_high_pass_filter, 'pcm_nonoversample' => $pcm_nonoversample, 'pcm_phase_compensation' => $pcm_phase_compensation, 'hv_enable' => $hv_enable, 'mpd_status'=>$mpd_status, 'outputFrequencies'=>$outputFrequencies,'bitDepth'=>$bitDepth,'roon_status'=>$roon_status, 'daemon_status' =>$daemon_status,'wifiStatus'=>$wifiStatus,'currentSSID'=>$currentSSID, 'currentPasskey'=>$currentPasskey,'shairPortStatus'=>$shairPortStatus,'cpu_temp' =>$cpu_temp, 'outputFrequencies_shair'=>$outputFrequencies_shair,'bitDepth_shair'=>$bitDepth_shair, 'mpdNativeOutput'=>$mpdNativeOutput, 'gmrenderStatus'=>$gmrenderStatus, 'netdataStatus'=>$netdataStatus, 'squeezeliteStatus'=>$squeezeliteStatus, 'bitDepth_squeezelite'=>$bitDepth_squeezelite
			]);
        
    }

    public function ssh_login(Request $request) {
        if (!empty($request->all())) {
            include(app_path() . "/phpseclib/Net/SSH2.php");
            $ssh = new \phpseclib\Net\SSH2('localhost');
            $ssh->login('allo', 'allo') or die("Login failed");
            echo 'Hotspot Password is: ' . $ssh->exec("TERM=linux sudo sed -n '/^wpa_passphrase=/{s/^[^=]*=//p;q}' /etc/hostapd/hostapd.conf");
            $hotspotstatus = $ssh->exec("TERM=linux; sudo systemctl unmask hostapd; sudo systemctl restart hostapd");
            if ($hotspotstatus == 0) {
                $hotspot_status = 'Inactive';
            } elseif ($hotspotstatus == 1) {
                $hotspot_status = 'Active';
            }
        } else {
            return view('ssh_login');
        }
    }

}
