<?php

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

// Создание логгера
$log = new Logger('dialevent');
$log->pushHandler(new StreamHandler(__DIR__ . '/dialevent.log', Logger::INFO));

// Настройки для подключения к серверу Asterisk
$config = require __DIR__ . '/../callme/config.php';

// Создание объекта для подключения к серверу Asterisk
$client = new \PAMI\Client\Impl\ClientImpl($config['asterisk']);

// Установка соединения с сервером Asterisk
$client->open();

// Обработчик событий
$eventHandler = function (EventMessage $event) use ($log) {
    if ($event instanceof DialBeginEvent) {
        $log->info("Dial begin: {$event->getCallerIDName()} <{$event->getCallerIDNum()}>");
    } elseif ($event instanceof DialEvent) {
        $log->info("Dial status: {$event->getSubEvent()} - {$event->getDialStatus()} -> {$event->getDestination()}");
    } elseif ($event instanceof OriginateResponseEvent) {
        $log->info("Originate response: {$event->getResponse()} - {$event->getReason()}");
    } elseif ($event instanceof HangupEvent) {
        $log->info("Hangup: {$event->getCallerIDName()} <{$event->getCallerIDNum()}>");
    }
};

// Регистрация обработчика событий
$client->registerEventListener($eventHandler, array('Dial', 'OriginateResponse', 'Hangup'));

// Отправка действия PingAction для подтверждения соединения
$client->send(new \PAMI\Message\Action\PingAction());

// Цикл обработки событийа
while ($client->process()) {
    usleep(100000);
}

// Закрытие соединения с сервером Asterisk
$client->close();
