<?php

$f3=require('lib/base.php');

$db=new DB\SQL(
    'mysql:host=localhost;port=3306;dbname=fastvps',
    'fastvps',
    'W8bP7NS2Xbj7X338'
);

$f3->set('CACHE',TRUE);
$f3->set('CACHE','memcached=localhost:11211');

// внимание! использую 2 глобальные переменные!
// работа с базой данных ведётся без ID

// 86400 <- время кэширования
// загрузка->смотрим в кэше->смотрим в базе->загружаем


// класс для работы с поставщиком данных, с неправильной архитектурой
class MoneyCourse {

	// обновляем информацию о валюте
	function updateDataByName($keycode,$output='json') {
	
		global $f3;
		global $db;
		

		$data = $this->file_get_contents_curl();
		
		$xml =  new SimpleXMLElement($data);
		
		// перебор, из-за того, что не хочется делать массив из SimpleXMLElement
		foreach ($xml->Valute as $k=>$v) {
			
			if ($v->CharCode == $keycode) {
			
				$result = array ();
				$result['status'] = 'success';
				$result['value']  = $v->Value;
				$result['nominal']  = $v->Nominal;
				
				// преобразуем наш SimpleXMLElement в Json, иначе возникает ошибка сериализации при попытке записи в кэш
				// вида Serialization of 'SimpleXMLElement' is not allowed
				// решения тут http://stackoverflow.com/questions/14029050/how-to-remove-simplexmlelement-object-from-php-array
				
				$upd_time = date("Y-m-d H:i:s");
				
				$result['updtime'] = $upd_time;
				
				$result = json_encode($result);
				
				//Result_For_DB
				$rfdb = json_decode($result, true);
				$value = str_replace(',','.',$rfdb['value'][0]);

				$nominal = $rfdb['nominal'][0];
				
				// работаем с транзакциями фреймворка, поэтому 3 запроса
				$db->begin();
				$db->exec("UPDATE coins SET value='$value' WHERE keycode='$keycode'");
				$db->exec("UPDATE coins SET nominal='$nominal' WHERE keycode='$keycode'");
				$db->exec("UPDATE coins SET updtime='$upd_time' WHERE keycode='$keycode'");
				$db->commit();

				if ($output != 'json') {
					
					$responce = array();
					$responce['keycode'] = $keycode;
					$responce['nominal'] = $nominal;
					$responce['value'] = $value;
					$responce['updtime'] = $upd_time;
					
					return $responce;
					
				} else return $result;
				
			}
	
		}

	}
	
	
	function file_get_contents_curl($url='http://www.cbr.ru/scripts/XML_daily.asp') {

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, $url);
		$data = curl_exec($ch);
		return $data;

	}

}


$moneyCourse = new MoneyCourse();

// обработчики запросов

$f3->route('GET /',
    function() {
		$template=new Template;
        echo $template->render('ui/main.html');
    }
);

$f3->route('GET /add',
	function() {
	
		global $db;
		global $f3;
		global $moneyCourse;
		
		// добавить фильтрацию!
		
		$name = $db->quote($_REQUEST['name']);
		$keycode = $db->quote($_REQUEST['keycode']);

		$result = array();
		
		if (!$db->exec("SELECT * FROM coins WHERE name={$name} and keycode={$keycode}")) {
		
			$db->exec("INSERT INTO coins (name,keycode) VALUES ({$name},{$keycode})");
			
			// Очищаем общий кэш валютных данных
			$f3->clear('stuff');
			
			$data = $moneyCourse->updateDataByName($_REQUEST['keycode'],'array');
		
			$result['status']='success';
			$result['value']=$data['value'];
			$result['nominal']=$data['nominal'];
			$result['updtime']=$data['updtime'];
		
		} else $result['status']='duplicate';

		echo json_encode($result);
    }
);

$f3->route('GET /codes',
    function() {
	
		global $db;
		global $f3;
		global $moneyCourse;
	
		$keycode = strtoupper($_REQUEST['keycode']);
		
		$f3->clear('stuff');
		
		echo ($moneyCourse->updateDataByName($keycode));
    }
);


$f3->route('GET /delete',
    function() {
		
		global $db;
		global $f3;
	
		$name = $db->quote($_REQUEST['name']);
		$keycode = $db->quote(strtoupper($_REQUEST['keycode']));
		
		$db->exec("DELETE FROM coins WHERE name={$name} and keycode={$keycode}");
	
		// Очищаем общий кэш валютных данных
		$f3->clear('stuff');
	
		$result = array();
		$result['status']='success';
		
		echo json_encode($result);
		
    }
);

$f3->route('GET /stuff',

    function() {
	
		global $db;
		global $f3;
		global $moneyCourse;
		
		// проверяем кэш
		if (!$f3->exists('stuff')) {
		
			$stuff_db = $db->exec('SELECT * FROM coins');

			if ($stuff_db && is_array($stuff_db)) {
		
				foreach ($stuff_db as $key=>$data) {
				
					if (abs(strtotime($data['updtime'])-time())>86400) {
					
						// не самая логичная работа с XML документом, 
						// но, кажется, менее ресурсоёмкая чем преборазование всего документа в массив
						$stuff_db[$key] = $moneyCourse->updateDataByName($data['keycode'],'array');
					
					}
				
				}
			
			}
		
			$result = array();
			$result['status']='success';
			$result['items']=$stuff_db;
			
			$result = json_encode($result);
			
			// пишем в кэш на сутки
			$f3->set('stuff',$result,86400);

			echo $result;
			
		} else echo $f3->get('stuff');

    }
	
);


$f3->run();
