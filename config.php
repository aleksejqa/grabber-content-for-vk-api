<?php

define (ROOT, $_SERVER['DOCUMENT_ROOT']);
// Токен
define (TOKEN, 'Здесь ваш токен приложения');
// ID моей группы/обязательно перед id группы должен стоять минус
define (ID_GROUP, 'Id вашей группы');
// Cколько последних записей парсить
define (MAX_POST, '100');
// Версия API
define (VERSION_API, '5.101');
// Путь до списка групп для граббинга
define (PATH_GROUPS_LUSTING, 'groups_for_grabbing.txt');
// Путь до директории с файлами изображений
define (PATH_IMAGES, __DIR__.'/upload/');