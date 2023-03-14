<?php

// Проверяем, запущен ли скрипт из CLI, иначе выводим ошибку и прекращаем выполнение
if (php_sapi_name() !== "cli") {
    die("Этот скрипт может быть запущен только из командной строки");
}

// Подключение автозагрузчика Composer
require __DIR__ . '/vendor/autoload.php';

// Импорт необходимых классов
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use PAMI\Message\Event\EventMessage;
use PAMI\Message\Event\OriginateResponseEvent;
use PAMI\Message\Event\DialBeginEvent;
use PAMI\Message\Event\DialEvent;
use PAMI\Message\Event\HangupEvent;
use PAMI\Message\Event\NewchannelEvent;

// Создание логгера
$log = new Logger('dialevent');
$log->pushHandler(new StreamHandler(__DIR__ . '/dialevent.log', Logger::INFO));

// Получаем экземпляр класса Globals через метод getInstance()
$globalsObj = Globals::getInstance();

// Настройки для подключения к серверу Asterisk
$config = require __DIR__ . '/../callme/config.php';

// Создание объекта для подключения к серверу Asterisk
$client = new \PAMI\Client\Impl\ClientImpl($config['asterisk']);

// Установка соединения с сервером Asterisk
$client->open();

$client->registerEventListener(function (EventMessage $event) use ($log, $globalsObj) {
    if ($event instanceof NewchannelEvent) {
        $globalsObj->uniqueids[] = $event->getUniqueID();

        $log->info("Privelege: {$event->getPrivilege()}");
        $log->info("Channel: {$event->getChannel()}");
        $log->info("ChannelState: {$event->getChannelState()}");
        $log->info("ChannelStateDesc: {$event->getChannelStateDesc()}");
        $log->info("CallerIDNum: {$event->getCallerIDNum()}");
        $log->info("CallerIDName: {$event->getCallerIDName()}");
        $log->info("AccountCode: {$event->getAccountCode()}");
        $log->info("UniqueID: {$event->getUniqueID()}");
        $log->info("Context: {$event->getContext()}");
        $log->info("Extension: {$event->getExtension()}");
    }
}, function (EventMessage $event) {
    return $event instanceof NewchannelEvent;
});

// Цикл обработки событий
while (true) {
    $client->process();
    usleep($config['listener_timeout']);
}

// Закрытие соединения с сервером Asterisk
$client->close();
