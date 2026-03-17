<?
    session_start();
	include("../settings/connect_datebase.php");

    $IdUser = $_SESSION['user'];
    $Message = $_POST["Message"];
    $IdPost = $_POST["IdPost"];

    // Получаем IP клиента
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

    // Проверяем частоту отправки комментариев с одного IP
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

    // Обновляем запись по IP (фиксируем время отправки комментария)
    $now = date('Y-m-d H:i:s');
    $query_ip_check = $mysqli->query("SELECT `prelast_attempt`, `last_attempt` FROM `blocked_ips` WHERE `ip`='$ip'");
    
    if($query_ip_check && $query_ip_check->num_rows > 0) {
        $old_times = $query_ip_check->fetch_row();
        $new_prelast = $old_times[1];
        $mysqli->query("UPDATE `blocked_ips` SET `prelast_attempt`='$new_prelast', `last_attempt`='$now' WHERE `ip`='$ip'");
    } else {
        $mysqli->query("INSERT INTO `blocked_ips` (`ip`, `last_attempt`) VALUES ('$ip', '$now')");
    }

    // Добавляем комментарий
    $mysqli->query("INSERT INTO `comments`(`IdUser`, `IdPost`, `Messages`) VALUES ({$IdUser}, {$IdPost}, '{$Message}');");
?>