<?php

error_reporting(E_ALL); //Выводим все ошибки и предупреждения
//set_time_limit(300);	//Время выполнения скрипта ограничено 300 секундами
set_time_limit(0);
ob_implicit_flush();	//Включаем вывод без буферизации 

$starttime = round(microtime(true),2);

echo "try to start...<br />";
$socket = stream_socket_server("tcp://127.0.0.1:8889", $errno, $errstr);

if (!$socket) {
	echo "socket unavailable<br />";
    die($errstr. "(" .$errno. ")\n");
}


$connects = array();
$GLOBALS['users'] = array();
$GLOBALS['messages'] = array();

while (true) {
	echo "main while...<br />";
    //формируем массив прослушиваемых сокетов:
    $read = $connects;
    $read []= $socket;
    $write = $except = null;

    if (!stream_select($read, $write, $except, null)) {//ожидаем сокеты доступные для чтения (без таймаута)
        break;
    }

    if (in_array($socket, $read)) {//есть новое соединение то обязательно делаем handshake
        if (($connect = stream_socket_accept($socket, -1)) && $info = handshake($connect)) {//принимаем новое соединение и производим рукопожатие:
			echo "new connection...<br />";            
			echo "connect=".$connect." <br />OK<br />";          
			print_r($info); 
			print_r($connect); 
			
			$connects[] = $connect;//добавляем его в список необходимых для обработки
            onOpen($connects, $info);//вызываем пользовательский сценарий
        }
        unset($read[ array_search($socket, $read) ]);
    }

    foreach($read as $connect) {//обрабатываем все соединения
        $data = fread($connect, 10000000);
        if (!$data) { //соединение было закрыто
			echo "connection closed...<br />";  
			fclose($connect);
            unset($connects[ array_search($connect, $connects) ]);
            onClose($connects,$connect);//вызываем пользовательский сценарий
            continue;
		}
		
		$f = decode($data);
		if ($f['type'] == 'close'){
			$f['payload'] = 'Пользователь закрыл подключение';
			onClose($connects,$connect);
		}
		onMessage($connects,$connect,$f);//вызываем пользовательский сценарий
    }
/*
	if( ( round(microtime(true),2) - $starttime) > 100) { 
		echo "time = ".(round(microtime(true),2) - $starttime); 
		echo "exit <br />\r\n"; 
		fclose($socket);
		echo "connection closed OK<br />\r\n"; 
		exit();
	}
*/	
}

fclose($socket);


function handshake($connect) { //Функция рукопожатия
    $info = array();

    $line = fgets($connect);
    $header = explode(' ', $line);
    $info['method'] = $header[0];
    $info['uri'] = $header[1];

    //считываем заголовки из соединения
    while ($line = rtrim(fgets($connect))) {
        if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
            $info[$matches[1]] = $matches[2];
        } else {
            break;
        }
    }

    $address = explode(':', stream_socket_get_name($connect, true)); //получаем адрес клиента
    $info['ip'] = $address[0];
    $info['port'] = $address[1];

    if (empty($info['Sec-WebSocket-Key'])) {
        return false;
    }

    //отправляем заголовок согласно протоколу вебсокета
    $SecWebSocketAccept = base64_encode(pack('H*', sha1($info['Sec-WebSocket-Key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
    $upgrade = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
        "Upgrade: websocket\r\n" .
        "Connection: Upgrade\r\n" .
        "Sec-WebSocket-Accept:".$SecWebSocketAccept."\r\n\r\n";
    fwrite($connect, $upgrade);
    return $info;
}

function encode($payload, $type = 'text', $masked = false) 
{
    $frameHead = array();
    $payloadLength = strlen($payload);

    switch ($type) {
        case 'text':
            // first byte indicates FIN, Text-Frame (10000001):
            $frameHead[0] = 129;
            break;

        case 'close':
            // first byte indicates FIN, Close Frame(10001000):
            $frameHead[0] = 136;
            break;

        case 'ping':
            // first byte indicates FIN, Ping frame (10001001):
            $frameHead[0] = 137;
            break;

        case 'pong':
            // first byte indicates FIN, Pong frame (10001010):
            $frameHead[0] = 138;
            break;
    }

    // set mask and payload length (using 1, 3 or 9 bytes)
    if ($payloadLength > 65535) {
        $payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
        $frameHead[1] = ($masked === true) ? 255 : 127;
        for ($i = 0; $i < 8; $i++) {
            $frameHead[$i + 2] = bindec($payloadLengthBin[$i]);
        }
        // most significant bit MUST be 0
        if ($frameHead[2] > 127) {
            return array('type' => '', 'payload' => '', 'error' => 'frame too large (1004)');
        }
    } elseif ($payloadLength > 125) {
        $payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
        $frameHead[1] = ($masked === true) ? 254 : 126;
        $frameHead[2] = bindec($payloadLengthBin[0]);
        $frameHead[3] = bindec($payloadLengthBin[1]);
    } else {
        $frameHead[1] = ($masked === true) ? $payloadLength + 128 : $payloadLength;
    }

    // convert frame-head to string:
    foreach (array_keys($frameHead) as $i) {
        $frameHead[$i] = chr($frameHead[$i]);
    }
    if ($masked === true) {
        // generate a random mask:
        $mask = array();
        for ($i = 0; $i < 4; $i++) {
            $mask[$i] = chr(rand(0, 255));
        }

        $frameHead = array_merge($frameHead, $mask);
    }
    $frame = implode('', $frameHead);

    // append payload to frame:
    for ($i = 0; $i < $payloadLength; $i++) {
        $frame .= ($masked === true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
    }

    return $frame;
}

function decode($data)
{
    $unmaskedPayload = '';
    $decodedData = array();

    // estimate frame type:
    $firstByteBinary = sprintf('%08b', ord($data[0]));
    $secondByteBinary = sprintf('%08b', ord($data[1]));
    $opcode = bindec(substr($firstByteBinary, 4, 4));
    $isMasked = ($secondByteBinary[0] == '1') ? true : false;
    $payloadLength = ord($data[1]) & 127;

    // unmasked frame is received:
    if (!$isMasked) {
        return array('type' => '', 'payload' => '', 'error' => 'protocol error (1002)');
    }

    switch ($opcode) {
        // text frame:
        case 1:
            $decodedData['type'] = 'text';
            break;

        case 2:
            $decodedData['type'] = 'binary';
            break;

        // connection close frame:
        case 8:
            $decodedData['type'] = 'close';
            break;

        // ping frame:
        case 9:
            $decodedData['type'] = 'ping';
            break;

        // pong frame:
        case 10:
            $decodedData['type'] = 'pong';
            break;

        default:
            return array('type' => '', 'payload' => '', 'error' => 'unknown opcode (1003)');
    }

    if ($payloadLength === 126) {
        $mask = substr($data, 4, 4);
        $payloadOffset = 8;
        $dataLength = bindec(sprintf('%08b', ord($data[2])) . sprintf('%08b', ord($data[3]))) + $payloadOffset;
    } elseif ($payloadLength === 127) {
        $mask = substr($data, 10, 4);
        $payloadOffset = 14;
        $tmp = '';
        for ($i = 0; $i < 8; $i++) {
            $tmp .= sprintf('%08b', ord($data[$i + 2]));
        }
        $dataLength = bindec($tmp) + $payloadOffset;
        unset($tmp);
    } else {
        $mask = substr($data, 2, 4);
        $payloadOffset = 6;
        $dataLength = $payloadLength + $payloadOffset;
    }

    /**
     * We have to check for large frames here. socket_recv cuts at 1024 bytes
     * so if websocket-frame is > 1024 bytes we have to wait until whole
     * data is transferd.
     */
    if (strlen($data) < $dataLength) {
        return false;
    }

    if ($isMasked) {
        for ($i = $payloadOffset; $i < $dataLength; $i++) {
            $j = $i - $payloadOffset;
            if (isset($data[$i])) {
                $unmaskedPayload .= $data[$i] ^ $mask[$j % 4];
            }
        }
        $decodedData['payload'] = $unmaskedPayload;
    } else {
        $payloadOffset = $payloadOffset - 4;
        $decodedData['payload'] = substr($data, $payloadOffset);
    }

    return $decodedData;
}


function onOpen($connects, $info) {
	foreach ($connects as $connect){
		fwrite($connect, encode('Пользователь присоединился'));
	}	
}

function onClose($connects,$connect) {
	$user = '';
	if (isset($GLOBALS['users'][(int)$connect])){
		$user = $GLOBALS['users'][(int)$connect];
		unset($GLOBALS['users'][(int)$connect]);
		
		$msg['user'] = 'INFO';
		$msg['message'] = 'пользователь "'.$user.'" покинул нас';
		$msg['datatype'] = 'message';		
		foreach ($connects as $c){
			fwrite($c, encode(json_encode($msg)));				
		}
		
		print_r($GLOBALS['users']);
	}
	foreach ($connects as $с){
		fwrite($с, encode('соединение с пользователем "'.$user.'" разорвано '));
		sendusers($с);	
	}	
    echo "close OK<br />\n";
}


function onMessage($connects,$connect,$data) {
	$obj = array();
#проверяем на JSON	
	try {
		$obj = json_decode($data['payload'], true);
	}
	catch (Exception $e) {
		echo "Message: not json";
		$obj['action'] = 'info';
		$obj['text'] = $data['payload'];
	}	
#----------------
	if ($obj['action'] == 'auth' and isset($obj['name'])){
		$GLOBALS['users'][(int)$connect] = $obj['name'];

		sendmessages($connect);	
		
		$msg['user'] = 'INFO';
		$msg['message'] = 'пользователь "'.$obj['name'].'" присоединился к нам';
		$msg['datatype'] = 'message';		
		foreach ($connects as $c){
			if($c != $connect){
				fwrite($c, encode(json_encode($msg)));				
			}
		}
		
		foreach ($connects as $c){
			sendusers($c);		
		}
	}
	elseif ($obj['action'] == 'getusers'){
		sendusers($connect);	
	}
	elseif ($obj['action'] == 'getmessages'){
		sendmessages($connect);	
	}
	elseif($obj['action'] == 'sendmessage'){
		$msg = array();
		$msg['user'] = $obj['user'];
		$msg['message'] = $obj['message'];
		$GLOBALS['messages'][] = $msg;
		$msg['datatype'] = 'message';
		foreach ($connects as $c){
			fwrite($c, encode(json_encode($msg)));				
		}
	}
	
	else{

		foreach ($connects as $c){
	//		$f = decode($data);
			$f = $data;
			
			if (isset($GLOBALS['users'][$connect])){
			$f['payload']  = $GLOBALS['users'][$connect].': '.$f['payload'];
			}
			
			echo "Message:";
			echo $f['payload'] . "<br />\n";
			print_r($f);
			fwrite($c, encode($f['payload']));
		}
	}
}
function sendusers($connect){
		$usr['users'] = array();
		$usr['datatype'] = 'userslist';
		foreach ($GLOBALS['users'] as $key=>$value){
			$usr['users'][]=$value;
		}
			fwrite($connect, encode(json_encode($usr)));		
}

function sendmessages($connect){
		$msg['messages'] = $GLOBALS['messages'];
		$msg['datatype'] = 'messageslist';
		fwrite($connect, encode(json_encode($msg)));		
}

