<?php
	session_start();
	include("../settings/connect_datebase.php");
	
	$login = $_POST['login'];
	$password = $_POST['password'];
	$max_attempts = 5;

	// Получаем IP клиента (с учетом возможных прокси)
	function getIp() {
		$keys = [
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'REMOTE_ADDR',
		];
		foreach ($keys as $key) {
			if (!empty($_SERVER[$key])) {
				$ipList = explode(',', $_SERVER[$key]);
				$ip = trim(end($ipList));
				if (filter_var($ip, FILTER_VALIDATE_IP)) {
					return $ip;
				}
			}
		}
		return $_SERVER['REMOTE_ADDR'];
	}

	$ip = getIp();

	// 1) Блокировка по логину после 5 неудачных попыток
	$query_attempts_logins = $mysqli->query("SELECT `attempts` FROM `blocked_logins` WHERE `login`='$login'");
	if($query_attempts_logins && $query_attempts_logins->num_rows > 0) {
		$row = $query_attempts_logins->fetch_row();
		$login_attempts = (int)$row[0];
    	if($login_attempts >= $max_attempts) {
    	    echo "";
			http_response_code(501);
    	    exit("");
    	}
	}

	// 2) Блокировка по IP, если слишком частые запросы (менее 1 секунды между двумя последними)
	$query_ip_time = $mysqli->query("SELECT `prelast_attempt`, `last_attempt` FROM `blocked_ips` WHERE `ip`='$ip'");
	if($query_ip_time && $query_ip_time->num_rows > 0) {
        $ip_times = $query_ip_time->fetch_row();
        $prelast_time = $ip_times[0];
        $last_time = $ip_times[1];
        
        if($prelast_time && $last_time) {
            $time_diff = strtotime($last_time) - strtotime($prelast_time);
            if($time_diff < 1) { 
                echo "";
                http_response_code(501);
                exit("Слишком частые запросы с IP '$ip'");
            }
        }
    }

	$id = -1;

	// Поиск пользователя
	$query_user = $mysqli->query("SELECT `id` FROM `users` WHERE `login`='".$login."' AND `password`= '".$password."';");

	while($user_read = $query_user->fetch_row()) {
		$id = $user_read[0];
	}

	if($id != -1) {
		// Успешная авторизация: очищаем блокировки
		$mysqli->query("DELETE FROM `blocked_logins` WHERE `login`='$login'");
    	$mysqli->query("DELETE FROM `blocked_ips` WHERE `ip`='$ip'");

		$_SESSION['user'] = $id;
		echo md5(md5($id));
	}
	else {
		// Неуспешная авторизация: увеличиваем счетчик по логину
    	if($query_attempts_logins && $query_attempts_logins->num_rows > 0) {
        	$mysqli->query("UPDATE `blocked_logins` SET `attempts`=`attempts`+1 WHERE `login`='$login'");
    	} else {
        	$mysqli->query("INSERT INTO `blocked_logins` (`login`, `attempts`) VALUES ('$login', 1)");
    	}
		
		// Обновляем информацию по IP
    	$now = date('Y-m-d H:i:s');
        $query_ip_check = $mysqli->query("SELECT `prelast_attempt`, `last_attempt` FROM `blocked_ips` WHERE `ip`='$ip'");
        
        if($query_ip_check && $query_ip_check->num_rows > 0) {
            $old_times = $query_ip_check->fetch_row();
            $new_prelast = $old_times[1];
            $mysqli->query("UPDATE `blocked_ips` SET `prelast_attempt`='$new_prelast', `last_attempt`='$now' WHERE `ip`='$ip'");
        } else {
            $mysqli->query("INSERT INTO `blocked_ips` (`ip`, `last_attempt`) VALUES ('$ip', '$now')");
        }

		// При неуспехе отдаём пустой ответ как и раньше
		echo "";
	}
?>