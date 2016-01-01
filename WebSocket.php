<?php
	Class WebSocket{
		var $host = '127.0.0.1';		//绑定主机IP
		var $port = '5000';				//绑定主机端口
		//var $master = [];
		var $socket = null;				//socket句柄
		var $clients = [];				//客户端池
		var $isHandler = [];			//握手池，true为已握手，false为未握手
		var $read  = null;				//是否让socket_select监听文件描述符可读，null为不监听
		var $write = [];				//是否让socket_select监听文件描述符可写，null为不监听
		var $except = null;				//排除不监听的文件描述符，null为监听全部
		var $timeout = 60;				//设置socket_select的超时时间

		function __construct(){
			set_time_limit(0);			//设置php脚本没有超时时间

			$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP) or die('create socket object is failed\n');	//创建socket句柄
			socket_set_nonblock($this->socket) or die('set nonblock is failed\n');										//设置为非阻塞模式					
			socket_bind($this->socket, $this->host, $this->port) or die ('bind socket is failed\n');					//把socket绑定到主机的端口上
			socket_listen($this->socket, 10) or die('listen is failed\n');												//监听开始，最多连接10个文件描述符
			
			while (true) {																								//开始循环
				
				//var_dump($clients);
				//$this->read = array_merge([$this->socket], $this->clients);
				//把主socket句柄和客户端添加到socket_select监听的'读'文件描述符里
				$this->read = [$this->socket] + $this->clients;
				//把活动的可读可写的文件描述符（子socket）返回，如果返回false，表示没有。														
				if(socket_select($this->read, $this->write, $this->except, $this->timeout)){						
					//echo "socket_select is over~!\n";
				
					//var_dump($this->clients);//exit;
					//var_dump($this->isHandler);
					//if(count($this->clients) == 0){
					//	continue;
					//}
					//var_dump($this->socket);
					//echo 'read';var_dump($this->read);echo 'write';var_dump($this->write);sleep(5);
					//遍历可读文件描述符，如果可读描述符里有 主socket，表示有新连接，接收新连接(子socket)，并存储在clients数组里。
					//并设置相应的isHandler(判断是否需要握手)，表示还没有首次握手。
					foreach ($this->read as $index => $client) {
						//var_dump($client);
						if($client == $this->socket){
							$rs = socket_accept($this->socket) or die('accept socket is failed\n');
							echo 'accept '.$rs. " but not handshaked\n";
							
							//echo 'now is accept '.$rs." to upgrade WebSocket...\n";
							$index = (int) $rs;
							$this->clients[$index] = $rs;
							$this->isHandler[$index] = false;
							$this->write[$index]   = $rs;
							continue;
						}
						//var_dump($this->clients);break;
						//如果不是 主socket，表示有内容需要读出来。
						$data = @socket_read($client,1024);
						//var_dump($data);
						echo 'Message is :';var_dump($data);echo "\n";
						//读取数据失败，表示不是有效链接，需要剔除。  PS：该功能有BUG，还需完善。
						if($data == false){
							//$this->removeClient($client, $index);
							//var_dump($this->clients);//exit;
							//var_dump($this->isHandler);
							continue;
						}
						//判断是否握手，如果没有握手，按照WebSocket相关标准来进行握手
						if($this->isHandler[$index] == false){		//判断是否握手，$isHandler[$index]为false表示没有握手
							echo "Now is prcossing to response handshake...\n";
							$this->addClient($client,$data, $index);
							echo 'now is accept '.$rs." to upgrade WebSocket...\n\n";
							continue;
						}
						//$otherClients = array_diff($this->read, [$this->socket, $client]);
						//已经进行握手了，表示是有数据从客户端传送过来，
						foreach ($this->write as $otherClient) {
							$msg = $this->decode($data);
							//echo $msg;
							$msg = $this->frame($msg);
							if(count($this->write) == 1){
								//$data = '只有你一个人在聊天室哦~！';
								$msg = 'You are alone'; 
							}elseif ($otherClient == $client) {
								continue;
							}

							//echo 'Message is :';var_dump($msg);echo "\n";
							//$msg = $this->frame($data);
							//$msg = $this->decode($msg);

							socket_write($otherClient, $msg);
						}
						
					}
					//var_dump($clients);
					//$acc = socket_read($rs, 9999);
					//echo $acc;
				}
			}
		}

		//响应升级协议 等弄好大框架之后再自己写一遍
		private function upgrade($rs, $key) {
		        $encyKey = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
		        $upgrade  = "HTTP/1.1 101 Switching Protocol\r\n" .
		                "Upgrade: websocket\r\n" .
		                "Connection: Upgrade\r\n" .
		                "Sec-WebSocket-Accept: " . $encyKey . "\r\n\r\n";  //必须以两个回车结尾
		        socket_write($rs, $upgrade, strlen($upgrade));
		}

		function frame($s) {
		    $a = str_split($s, 125);
		    if (count($a) == 1) {
		        return "\x81" . chr(strlen($a[0])) . $a[0];
		    }
		    $ns = "";
		    foreach ($a as $o) {
		        $ns .= "\x81" . chr(strlen($o)) . $o;
		    }
		    return $ns;
		}

		// 解析数据帧
		function decode($buffer)  {
		    $len = $masks = $data = $decoded = null;
		    $len = ord($buffer[1]) & 127;
		    if ($len === 126)  {
		        $masks = substr($buffer, 4, 4);
		        $data = substr($buffer, 8);
		    } else if ($len === 127)  {
		        $masks = substr($buffer, 10, 4);
		        $data = substr($buffer, 14);
		    } else  {
		        $masks = substr($buffer, 2, 4);
		        $data = substr($buffer, 6);
		    }
		    for ($index = 0; $index < strlen($data); $index++) {
		        $decoded .= $data[$index] ^ $masks[$index % 4];
		    }
		    return $decoded;
		}

		function addClient($rs,$header, $index){			
			//$header = socket_read($rs, 1024);
			/*if(strlen($header) == 0){
				$this->removeClient($rs);
			}
			*/
			$key = $this->getKey($header);
			$this->upgrade($rs, $key);
			//$this->clients[$index]   = $rs;
			$this->isHandler[$index] = true;
		}

		function removeClient($rs, $index){
			//foreach ($this->clients as $index => $value) {
				//if($value == $rs){
					unset($this->clients[$index]);
					unset($this->isHandler[$index]);
					socket_close($rs);
					var_dump($this->clients);//exit;
					var_dump($this->isHandler);
				//}
			//}
		}

		function getKey($data){
			if(preg_match("/Sec-WebSocket-Key: (.*)\r\n/",$data,$match)){
				$key = $match[1];
			}

			return $key;
		}
	}

	new WebSocket();

?>;