<?php
header('Content-Type: application/json');

define("ROKU_PORT", "8060");

$request_method = $_SERVER['REQUEST_METHOD'];

$function = isset($_GET['function']) ? $_GET['function'] : 'device';

$post_data = json_decode(file_get_contents('php://input'),true);

// Roku TV seems to want an IP address for connection? Need to verify
// For now, if a hostname is passed convert it to the IP address
if (filter_var($_GET['host'], FILTER_VALIDATE_IP)) {
    $host = $_GET['host'];
} else {
    $host = gethostbyname($_GET['host']);
}

// where we'll store the responses sent back to caller
$output = array();

// print "request method: " . $_SERVER['REQUEST_METHOD'] . "\n";
// print "function: " . $function . "\n";
// print "host: " . $host . "\n";
// print_r($post_data);

if($function == "device") {
    // get device info
    if ($request_method == "GET") {
        $power_response = get_power($host);

        if(is_array($power_response) && !empty($power_response)) {
            $output = array_merge($output, $power_response);
        }

        // get the input information if the display is turned on
        if($power_response['power_status']) {
            $input_response = get_input($host);

            if(is_array($input_response) && !empty($input_response)) {
                $output = array_merge($output, $input_response);
            }
        }
        // if(array_key_exists("power_status", $power_response) && $power_response['power_status']) {
        //     $input_response = get_input($host);



        //     $volume_response = get_volume($host);

        //     if(is_array($volume_response) && !empty($volume_response)) {
        //         $output = array_merge($output, $volume_response);
        //     }
        //}
    // set device settings
    }elseif($request_method == "PUT") {
        if(array_key_exists("power_state", $post_data)) {
            $desired_power_state = $post_data['power_state'];

            $set_power_response = set_power($host, $desired_power_state);

            if(is_array($set_power_response) && !empty($set_power_response)) {
                $output = array_merge($output, $set_power_response);
            }

        }

        if(array_key_exists("video_input_num", $post_data)) {
            $desired_input = $post_data['video_input_num'];

            $set_input_response = set_input($host, $desired_input);

            if(is_array($set_input_response) && !empty($set_input_response)) {
                $output = array_merge($output, $set_input_response);
            }
        }
    }elseif($request_method == "POST") {
        if(array_key_exists("audio_volume", $post_data)) {
            $volume_adjustment = $post_data['audio_volume'];

            $set_volume_response = set_volume($host, $volume_adjustment);

            if(is_array($set_volume_response) && !empty($set_volume_response)) {
                $output = array_merge($output, $set_volume_response);
            }
        }

        if(array_key_exists("audio_mute", $post_data)) {

            $set_mute_response = set_mute($host);

            if(is_array($set_mute_response) && !empty($set_mute_response)) {
                $output = array_merge($output, $set_mute_response);
            }
        }
    }
}

print json_encode($output, JSON_PRETTY_PRINT);

///////////////////////////////////////////////////
//
//  Functions
//
///////////////////////////////////////////////////

// power states to match PJLink: 0=off, 1=on, 2=cooling, 3=warming
function get_power($host) {

    $url = "http://" . $host . ":" . ROKU_PORT . "/query/device-info"; 
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
    ));
    
    $raw_response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
      echo "cURL Error #:" . $err;
    } else {

        // $xml = simplexml_load_string($raw_response, "SimpleXMLElement", LIBXML_NOCDATA);
        $xml = simplexml_load_string($raw_response, "SimpleXMLElement", LIBXML_NOCDATA);
        $json = json_encode($xml);
        $response = json_decode($json, TRUE);

        // print_r($response);

        if(is_array($response) && array_key_exists("power-mode", $response)) {
            $power_status_raw = $response['power-mode'];

            if($power_status_raw == "Ready") {
                $power_status = 0;
                $power_status_description = "off";

            }elseif($power_status_raw == "PowerOn") {
                $power_status = 1;
                $power_status_description = "on";
            }
            $power_return = array("power_status" => $power_status, "power_status_description" => $power_status_description);
        }
        else {
            $power_return = array("power_status" => NULL, "power_status_description" => NULL, "power_status_error_num" => 29348, "power_status_error" => "unknown power state");
        }
    }

    $output = $power_return;

    return $output;
}

function set_power($host, $state) {

    if($state == "on" || $state === true || $state === 1) {
        $power_state = '/keypress/PowerOn';
    }elseif($state == "off" || $state === false || $state === 0) {
        $power_state = '/keypress/PowerOff';
    }else {
        $output = array("power_status" => NULL, "error_num" => 60, "error_message" => "unknown power command");
        return $output;
    }

    $url = "http://" . $host . ":" . ROKU_PORT . $power_state; 

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
      ));

    // roku doesn't actually return a response
    $raw_response = curl_exec($curl);

    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        // echo "cURL Error #:" . $err;
        $output = array("power_status" => NULL, "error_num" => 60, "error_message" => "curl error " . $err);
    } else {
        // set power was sent successfully, let's check the status
        $output = get_power($host);
    }

    return $output;
}


// Get the video input that the TV is set to.  
// Roku TV doesn't immediately respond with the correct input after it has been changed
// so we first check a cache file created by the set_input function
// if the cached version is more than 5 seconds old, then we'll ignore it
// returns error if the display is off
function get_input($host) {

    // sample raw response from Roku TV
/*
When the TV is ON:
    <?xml version="1.0" encoding="UTF-8" ?>
    <active-app>
        <app id="tvinput.hdmi1" type="tvin" version="1.0.0">HDMI 1</app>
    </active-app>

When the TV is OFF:
<?xml version="1.0" encoding="UTF-8" ?>
<active-app>
  <app>Roku</app>
</active-app>

*/

    // check for cached input data
    $cache_file_name = "/tmp/" . $host . ".json";
    if(file_exists($cache_file_name)) {
        $now_timestamp = time();

        $cache_input_data = json_decode(file_get_contents($cache_file_name), true);
        $cached_timestamp = $cache_input_data['timestamp'];
        $elapsed_seconds = $now_timestamp - $cached_timestamp;
        // if the cached input data is less than 5 seconds old
        if($elapsed_seconds < 5) {
            $input_return = array("video_input_num" => $cache_input_data['video_input_num'], "video_input_type" => $cache_input_data['video_input_type'], "cached" => true, "cache_age" => $elapsed_seconds);
        }
    }
    
    // if we didn't set the input details using the cache, then query the TV
    if(empty($input_return)) {

        $url = "http://" . $host . ":" . ROKU_PORT . "/query/active-app"; 
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
        ));
        
        $raw_response = curl_exec($curl);

        // remove new line and tab characters so we can do hacky regex match
        // using PHP XML processor is overly complex for this small amount of XML
        $raw_response = str_replace("\n", '', $raw_response);
        $raw_response = str_replace("\t", '', $raw_response);

        $err = curl_error($curl);
        
        curl_close($curl);
        
        if ($err) {
        echo "cURL Error #:" . $err;
        } else {

            if(preg_match('/.+<active-app><app id=\\"(tvinput\.)(hdmi)([0-9]+)\\".+/', $raw_response, $matches)) {
                // print_r($matches);
                $input_num = $matches[3];
                $input_return = array("video_input_num" => intval($input_num), "video_input_type" => "hdmi", "cached" => false);
            }else{
                $input_return = array("video_input_num" => NULL, "video_input_type" => NULL);
            }
        } 
    }

    $output = $input_return;

    return $output;

}

function set_input($host, $input_number) {
    // $output = "set input number $input_number";

    $url = "http://" . $host . ":" . ROKU_PORT . "/launch/tvinput.hdmi" . $input_number; 
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
      ));

    // roku doesn't actually return a response
    $raw_response = curl_exec($curl);

    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        // echo "cURL Error #:" . $err;
        $output = array("power_status" => NULL, "error_num" => 60, "error_message" => "curl error " . $err);
    } else { // assume input was successfully set

        // the Roku TV is slow to reflect the current Input.  To work around this, we'll just assume it worked,
        // we'll send back the input that was requested as the current input AND
        // we'll cache this input to a file, so that when the get_input function is called, it will get the
        // "correct" input number

        $output = array("video_input_num" => intval($input_number), "video_input_type" => "hdmi");

        $now_timestamp = time();

        $input_cache = array("timestamp" => $now_timestamp, "video_input_num" => intval($input_number), "video_input_type" => "hdmi");
        $input_cache_json = json_encode($input_cache, JSON_PRETTY_PRINT);
        $cache_file_name = "/tmp/" . $host . ".json";
        file_put_contents($cache_file_name, $input_cache_json, LOCK_EX);

    }

    return $output;

}

function get_volume($host) {

    $url = "http://" . $host . "/sony/audio";

    $post_data = "{\"method\": \"getVolumeInformation\", \"id\": 33, \"params\": [], \"version\": \"1.0\"}";
    $curl = curl_init();
    
    curl_setopt_array($curl, array(
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 10,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => $post_data,
      CURLOPT_HTTPHEADER => array(
        "content-type: application/json"
      ),
    ));
    
    $response_raw = curl_exec($curl);
    $response = json_decode($response_raw, true);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
        $output = array("audio_volume" => NULL, "error_num" => 900, "error_message" => "curl error " . $err);
    } else {
        // echo $raw_response;

        // if we get a normal reponse back from the get volume
        if(array_key_exists("result", $response) && array_key_exists("id", $response) && $response['id'] == 33) {

            $volume = $response['result'][0][0]['volume'];
            $target = $response['result'][0][0]['target'];
            $muted = $response['result'][0][0]['mute'];
            $max_volume = $response['result'][0][0]['maxVolume'];
            $min_volume = $response['result'][0][0]['minVolume'];

            $output = array("audio_volume" => $volume, "audio_target" => $target, "audio_muted" => $muted, "max_volume" => $max_volume, "min_volume" => $min_volume);
        } elseif(array_key_exists("error", $response)) {
            $sony_error_num = $response['error'][0];
            $sony_error_message = $response['error'][1];
            $output = array("audio_volume" => NULL, "error_num" => $sony_error_num, "sony_error_message" => $sony_error_message);

        }else {
            $output = array("audio_volume" => NULL, "error_num" => 901, "error_message" => "failed to get volume");
        }
    }

    return $output;
}

// roku does not allow setting volume to a specific value.  you can only tell it to go up or down
function set_volume($host, $volume_adjustment) {

    if($volume_adjustment == "+" || $volume_adjustment === "up") {
        $volume_up_down = '/keypress/VolumeUp';
        $volume_adjustment_description = "up";
    }elseif($volume_adjustment == "-" || $volume_adjustment === "down") {
        $volume_up_down = '/keypress/VolumeDown';
        $volume_adjustment_description = "down";
    }else {
        $output = array("volume_adjustment" => NULL, "error_num" => 6270, "error_message" => "unknown volume response");
        return $output;
    }

    $url = "http://" . $host . ":" . ROKU_PORT . $volume_up_down; 

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
      ));

    // roku doesn't actually return a response
    $raw_response = curl_exec($curl);

    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        // echo "cURL Error #:" . $err;
        $output = array("volume_adjustment" => NULL, "error_num" => 6271, "error_message" => "curl error " . $err);
    } else {
        $output = array("volume_adjustment" => $volume_adjustment_description);
    }

    return $output;
}

// Roku only allows mute to be toggled, not set a specific mute value
function set_mute($host) {

    $url = "http://" . $host . ":" . ROKU_PORT . "/keypress/VolumeMute"; 

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        ));

    // roku doesn't actually return a response
    $raw_response = curl_exec($curl);

    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        // echo "cURL Error #:" . $err;
        $output = array("volume_mute" => NULL, "error_num" => 6272, "error_message" => "curl error " . $err);
    } else {
        $output = array("volume_mute" => "toggled");
    }

    return $output;
}
?>
