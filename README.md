# yaws - Yet Another Waterius Server

Простой web-сервер для приема и отображения данных с импульсных счетчиков воды [Waterius](https://github.com/dontsovcmc/waterius/).

![screenshot](https://user-images.githubusercontent.com/3234045/145560453-d1dd23e7-108b-43ff-b07a-1859e99d2755.png)

## Требования к системе

- Apache + PHP
- SQLite (PDO_SQLITE)

## Установка

1. Поместить `index.php` в желаемую директорию web-сервера
2. Убедиться, что у web-сервера есть возможность записи в эту директорию (для создания БД SQLite)
3. Сгенерировать файл с логином и паролем, вызвав `index.php` из командной строки
    ```
    # php ./index.php
    ```
4. Для дублирования данных в Личный кабинет на сайте waterius.ru установить константу `WATERIUS_RU` в `true` и положить в директорию [сертификат УЦ waterius.ru](https://github.com/dontsovcmc/waterius/blob/7b233852652dc2ae4530cdb70d8802027b2f3791/ESP8266/src/cert.h)
