Запуск на локальном ПК
1. скопировать каталоги Client и Server к себе на ПК
2. запустить /Server/start.bat
3. в браузере открыить /Client/index.html

Настройка
запуск на локальном ПК не требует настройки. 
достаточно чтобы не блокировался порт 8889

настроить адрес и порт можно в файлах
/Server/echows.php
/Client/Controllers/main.js


P.S. запуск серверной части производится по средствам PHP7, возможно, для корректной работы нужно будет установить Visual Studio
(Ошибка PHP7 : Missing VCRUNTIME140.dll http://stackoverflow.com/questions/30811668/php7-missing-vcruntime140-dll)