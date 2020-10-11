<?php

/**
*
* Класс парсинга контента из групп VK
*
*/
class Grabber
{
	
	
	/**
	* Получаем из списка группу для парсинга
	* @return id группы
	*
	*/
	private function getGroupParsing ()
	{
		// Группы откуда будем брать записи
		$arrayGroups = file(PATH_GROUPS_LUSTING, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		// Ключ из массива в рандомном порядке
		$randomKey = mt_rand(0, count($arrayGroups)-1);
		//Получаем ID группы
		$result = trim($arrayGroups[$randomKey]);
		return $result;
		
	} // End: function getParsingGroupContent
	
	
	
	
	/**
	* Получаем контент группы
	* @return массив с контентом
	*
	*/
	private function getContent ()
	{
		$id_group = $this->getGroupParsing();	
		$query = $this->curl (
			'https://api.vk.com/method/wall.get?',
			'owner_id=-'.$id_group.''
			.'&count='.MAX_POST.''
			.'&v='.VERSION_API.''
			.'&access_token='.TOKEN
		);
		
		$result = json_decode ($query, TRUE);
		return $result;
		
	} // End: function getContent
	
	
	
	
	/**
	* Получаем статью
	* @return array
	*
	*/
	private function getArticle()
	{
		
		$content = $this->getContent();
		
		$count = rand (0, MAX_POST);
		
		$text = $content[response][items][$count][text];

		// Если в тексте стоп слово найдено, идем на второй круг
		if ($this->stopWords($text) == TRUE)  die('Сработало стоп слово');
		
		if (isset($content[response][items][$count][attachments]))
		{
			foreach ($content[response][items][$count][attachments] as $key => &$value)
			{
				$type      = $content[response][items][$count][attachments][$key][type];
				$owner_id  = $type.$content[response][items][$count][attachments][$key][$type][owner_id];
				$id        = $content[response][items][$count][attachments][$key][$type][id];
				$sizes     = $content[response][items][$count][attachments][$key][$type][sizes];
				
				if ($type == 'photo')
				{
					foreach ($sizes as $sizesKey)
					{
						// Получаем ссылку на изображения
						if (in_array('x', $sizesKey, TRUE)) $urlImage = $sizesKey['url'];
					}
					
					// Сохраняем фото в директорию
					$this->uploadImageDir($urlImage, $key);
					$attachments = 'photo';
				}
				else 
				{
					$attachments = $owner_id.'_'.$id.",";
					$attachments = substr ($attachments, 0, -1);
				}
			}
		}
		return ['text' => $text, 'attachments' => $attachments];
		
	} // End: function getArticle
	
	
	
	
	/**
	* Постим в свою группу
	* @return результат постинга
	*
	*/
	public function publishContent()
	{
		$content = $this->getArticle();
		
		$text = $content['text'];
		
		if($content['attachments'] == 'photo')
		{
			$attachments = $this->uploadImageServerVK();
			$this->deleteImageDir();
		} 
		else $attachments = $content['attachments'];
		
		if(isset($attachments))
		{
			$query = $this->curl (
				'https://api.vk.com/method/wall.post?',
				'owner_id=-'.ID_GROUP.''
				.'&from_group=1'
				.'&message='.urlencode ($text).''
				.'&attachments='.$attachments.''
				.'&v='.VERSION_API.''
				.'&access_token='.TOKEN
			);
			
		}
		
		else
		{
			$query = $this->curl (
				'https://api.vk.com/method/wall.post?',
				'owner_id=-'.ID_GROUP.''
				.'&from_group=1'
				.'&message='.urlencode ($text).''
				.'&v='.VERSION_API.''
				.'&access_token='.TOKEN
			);
		}
		
		$result = json_decode ($query, TRUE);
		return $result;
		
	} // End: function publishContent
	
	
	
	
	/**
	* Проверка на стоп-слова
	* @param $text - текст группы донора
	* @return TRUE или FALSE
	*
	*/
	private function stopWords ($text = NULL)
	{
		
		// Читаем файл
		$stopWordsFile = file(__DIR__ . '/stopwords.txt');
		foreach ($stopWordsFile as $key)
		{
			$result = preg_match('~\b'. trim($key) .'\b~iu', $text);
			if ($result) break;
		}
		
		return $result;
		
	} // End: function stopWords
	
	
	
	
	/**
	* Загружаем изображение в директорию на сервере
	*
	*/
	private function uploadImageDir ($url, $number)
	{
		
		// Проверим HTTP в адресе ссылки
		if (!preg_match("/^https?:/i", $url) && filter_var($url, FILTER_VALIDATE_URL)) {
			die('Укажите корректную ссылку на удалённый файл.');
		}

		// Запустим cURL с нашей ссылкой
		$ch = curl_init($url);

		// Укажем настройки для cURL
		curl_setopt_array($ch, [

			// Укажем максимальное время работы cURL
			CURLOPT_TIMEOUT => 60,

			// Разрешим следовать перенаправлениям
			CURLOPT_FOLLOWLOCATION => 1,

			// Разрешим результат писать в переменную
			CURLOPT_RETURNTRANSFER => 1,

			// Включим индикатор загрузки данных
			CURLOPT_NOPROGRESS => 0,

			// Укажем размер буфера 1 Кбайт
			CURLOPT_BUFFERSIZE => 1024,

			// Напишем функцию для подсчёта скачанных данных
			CURLOPT_PROGRESSFUNCTION => function ($ch, $dwnldSize, $dwnld, $upldSize, $upld) {

				// Когда будет скачано больше 5 Мбайт, cURL прервёт работу
				if ($dwnld > 1024 * 1024 * 5) {
					return -1;
				}
			},

			// Включим проверку сертификата (по умолчанию)
			CURLOPT_SSL_VERIFYPEER => 1,

			// Проверим имя сертификата и его совпадение с указанным хостом (по умолчанию)
			CURLOPT_SSL_VERIFYHOST => 2,

			// Укажем сертификат проверки
			// Скачать: https://curl.haxx.se/docs/caextract.html
			CURLOPT_CAINFO => __DIR__ . '/cacert.pem',
		]);

		$raw   = curl_exec($ch);    // Скачаем данные в переменную
		$info  = curl_getinfo($ch); // Получим информацию об операции
		$error = curl_errno($ch);   // Запишем код последней ошибки

		// Завершим сеанс cURL
		curl_close($ch);

		// Проверим ошибки cURL и доступность файла
		if ($error === CURLE_OPERATION_TIMEDOUT)  die('Превышен лимит ожидания.');
		if ($error === CURLE_ABORTED_BY_CALLBACK) die('Размер не должен превышать 5 Мбайт.');
		if ($info['http_code'] !== 200)           die('Файл не доступен.');

		// Создадим ресурс FileInfo
		$fi = finfo_open(FILEINFO_MIME_TYPE);

		// Получим MIME-тип используя содержимое $raw
		$mime = (string) finfo_buffer($fi, $raw);

		// Закроем ресурс FileInfo
		finfo_close($fi);

		// Проверим ключевое слово image (image/jpeg, image/png и т. д.)
		if (strpos($mime, 'image') === false) die('Можно загружать только изображения.');

		// Возьмём данные изображения из его содержимого
		$image = getimagesizefromstring($raw);

		// Зададим ограничения для картинок
		$limitWidth  = 1280;
		$limitHeight = 768;

		// Проверим нужные параметры
		if ($image[1] > $limitHeight) die('Высота изображения не должна превышать 768 точек.');
		if ($image[0] > $limitWidth)  die('Ширина изображения не должна превышать 1280 точек.');

		// Сгенерируем новое имя из MD5-хеша изображения
		$name = 'img_'.$number;

		// Сгенерируем расширение файла на основе типа картинки
		$extension = image_type_to_extension($image[2]);

		// Сократим .jpeg до .jpg
		$format = str_replace('jpeg', 'jpg', $extension);

		// Сохраним картинку с новым именем и расширением в папку /upload
		if (!file_put_contents(PATH_IMAGES. $name . $format, $raw)) {
			die('При сохранении изображения на диск произошла ошибка.');
		}
		
	} // End: function uploadDir
	
	
	
	
	/**
	* Удаляем в директории на сервере файлы
	*
	*/
	private function deleteImageDir()
	{
		
		$dir = scandir (PATH_IMAGES);
		foreach ($dir as $key)
		{
			if (is_file(PATH_IMAGES.$key)) unlink(PATH_IMAGES.$key);
		}
		
	}
	
	
	
	
	/**
	* Загрузка фото на сервер VK
	* @return
	*
	*/
	private function uploadImageServerVK()
	{
		
		$query = $this->curl (
			'https://api.vk.com/method/photos.getWallUploadServer?',
			'group_id='.ID_GROUP.''
			.'&access_token='.TOKEN.''
			.'&v='.VERSION_API
		);
		
		$array = json_decode($query, TRUE);
		$url = $array[response][upload_url];
		
		$dir = scandir (PATH_IMAGES);
		$i = -1;
		
		foreach ($dir as $key)
		{
			if (is_file(PATH_IMAGES.$key)) $post['file'.$i] = new CURLFile(PATH_IMAGES.$key);
			$i++;
		}
		
		$query = $this->curl ($url, $post);
		$result = json_decode($query,true);
		
		$query = $this->curl (
			'https://api.vk.com/method/photos.saveWallPhoto?',
			'group_id='.ID_GROUP.''
			.'&server='.$result['server'].''
			.'&photo='.$result['photo'].''
			.'&hash='.$result['hash'].''
			.'&access_token='.TOKEN.''
			.'&v='.VERSION_API
		);
		
		$result = json_decode($query,true);
		
		foreach ($result[response] as $key)
		{
			$photo[] = 'photo'.$key['owner_id'].'_'.$key['id'];
		}
		
		$result = implode(',', $photo);
		return $result;
		
	} // End: function uploadImageServerVK
	
	
	
	
	/**
	*
	*
	*
	*/
	private function curl ($url, $post = NULL) 
	{
		
		$ch = curl_init ($url);
		curl_setopt ($ch, CURLOPT_TIMEOUT, 60); // в секундах на передачу данных
		curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 60); // в секундах на установку соединения
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt ($ch, CURLOPT_POST, TRUE);//Используем POST запрос
		curl_setopt ($ch, CURLOPT_POSTFIELDS, $post);//Данные для POST запроса
		$response = curl_exec ($ch);
		curl_close ($ch);
		return $response;
		
	} // End: function curl
	
	
} // End: class Grabber