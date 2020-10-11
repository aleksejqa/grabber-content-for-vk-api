<?php
###########################################
# Добавляем контент в группу соц. сети ВК #
###########################################

header('Content-Type: application/json; charset=utf-8');

// Подключаем конфигурацию настроек
require_once 'config.php';

// Подключаем класс граббера
require_once 'Grabber.php';
   
	
	$start = microtime(true);
	echo "<meta charset=\"utf-8\">";

	$grab = new Grabber;
	$grab->publishContent();
	//print_r($result);
	
	echo "<br><br>Время выполнения: ".(microtime(true)-$start)." секунд.";
	echo "<br><br>Группа (Паблик): ".ID_GROUP."<br><br>";