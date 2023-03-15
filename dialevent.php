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
use PAMI\Message\Event\NewchannelEvent;
use PAMI\Message\Event\VarSetEvent;
use PAMI\Message\Event\DialBeginEvent;
use PAMI\Message\Event\DialEndEvent;
use PAMI\Message\Event\HangupEvent;

// Создание логгера
$log = new Logger('dialevent');
$log->pushHandler(new StreamHandler(__DIR__ . '/dialevent.log', Logger::INFO));

$helper = new Helper();

// Получаем экземпляр класса Globals через метод getInstance()
$globalsObj = Globals::getInstance();

// Создание объекта для подключения к серверу Asterisk
$client = new \PAMI\Client\Impl\ClientImpl($helper->getConfigValue('asterisk'));

// Установка соединения с сервером Asterisk
$client->open();

$client->registerEventListener(function (EventMessage $event) use ($log, $globalsObj) {
    if ($event instanceof NewchannelEvent) {
        // Получаем параметры звонка
        $uniqueID = $event->getUniqueID();
        // $callerIDNum = $event->getCallerIDNum();
        // $channel = $event->getChannel();
        $extension = $event->getExtension();

        // Добавляем звонок в массив экземлора класса
        $globalsObj->uniqueIDs[] = $uniqueID;
        $globalsObj->callerIDNums[$uniqueID] = $event->getCallerIDNum();

        // Логируем параметры звонка
        $log->info("Новый вызов события NewchannelEvent");
        $log->info("callerIDNum: {$globalsObj->callerIDNums[$uniqueID]}, uniqueID: {$uniqueID}, extension: {$extension}");

        // Выбираем из битрикса полное имя контакта по номеру телефона и логируем
        // $calleeName = $helper->getCrmContactNameByExtension($extension);
        // $log->info("Имя вызываемого абонента в Битрикс24");
        // $log->info("calleeName: {$calleeName}");
        // $message = new SetVarAction('calleeName', $calleeName, $event->getChannel());
        // $log->info("Попытка установить имя вызываемого абонента");
        // $log->info("getChannel: {$event->getChannel()}");
        // $client = new \PAMI\Client\Impl\ClientImpl($helper->getConfigValue('asterisk'));
        // $client->open();
        // $resultFromB24 = $client->send($message);
        // $log->info("Попытка установить имя вызываемого абонента: второй шаг");
        // $log->info("resultFromB24: {$resultFromB24}");
        // $client->close();
    }
}, function (EventMessage $event) {
    return
        $event instanceof NewchannelEvent
        && preg_match('/^\d{3}$/', $event->getCallerIDNum());
});

$client->registerEventListener(function (EventMessage $event) use ($log, $globalsObj) {
    if ($event instanceof VarSetEvent) {
        $uniqueID = $event->getUniqueID();

        if (preg_match('/^http.+$/', $event->getValue())) {
            $globalsObj->fullFnameUrls[$uniqueID] = $event->getValue();
        }

        if (preg_match('/^\d+$/', $event->getValue())) {
            $globalsObj->durations[$uniqueID] = $event->getValue();
        }

        if (preg_match('/^[A-Z\ ]+$/', $event->getValue())) {
            $globalsObj->dispositions[$uniqueID] = $event->getValue();
        }

        // Логируем параметры звонка
        $log->info("Новый вызов события VarSetEvent - получение значений fullFnameUrl, duration, disposition");
        $log->info("fullFnameUrls: {$globalsObj->fullFnameUrls[$uniqueID]}, durations: {$globalsObj->durations[$uniqueID]}, dispositions: {$globalsObj->dispositions[$uniqueID]}");
    }
}, function (EventMessage $event) use ($globalsObj) {
    return
        $event instanceof VarSetEvent
        && ($event->getVariableName() === 'FullFname'
            || $event->getVariableName() === 'CallMeDURATION'
            || $event->getVariableName() === 'CallMeDISPOSITION')
        && in_array($event->getUniqueID(), $globalsObj->uniqueIDs);
});

$client->registerEventListener(function (EventMessage $event) use ($log, $helper, $globalsObj) {
    if ($event instanceof DialBeginEvent) {
        $uniqueID = $event->getUniqueid();
        $destCallerIDNum = $event->getDestCallerIDNum();
        // $callerIDNum = $event->getCallerIDNum();
        $callerIDNum = $globalsObj->callerIDNum[$uniqueID];

        // Регистрируем звонок в битриксе
        $globalsObj->calls[$uniqueID] = $helper->runOutputCall($callerIDNum, $destCallerIDNum);

        // Показываем карточку пользователю
        $helper->showOutputCall($callerIDNum, $globalsObj->calls[$uniqueID]);

        $log->info("Новый исходящий звонок");
        $log->info("callerIDNum: {$callerIDNum}, uniqueID: {$uniqueID}, CALL_ID: {$globalsObj->calls[$uniqueID]}");
    }
}, function (EventMessage $event) use ($globalsObj) {
    return
        $event instanceof DialBeginEvent
        && in_array($event->getUniqueID(), $globalsObj->uniqueIDs);
});

$client->registerEventListener(function (EventMessage $event) use ($log, $helper, $globalsObj) {
    if ($event instanceof DialEndEvent) {
        $uniqueID = $event->getUniqueid();

        // $globalsObj->callerIDNums[$uniqueID] = $event->getCallerIDNum();
        $destCallerIDNum = $event->getDestCallerIDNum();

        switch ($event->getDialStatus()) {
            case 'ANSWER|ANSWERED':
                $log->info("Исходящий звонок - ANSWER|ANSWERED.");
                $log->info("callerIDNum: {$event->getCallerIDNum()}, destCallerIDNum: {$destCallerIDNum}, uniqueID: {$uniqueID}, CALL_ID: {$globalsObj->calls[$uniqueID]}");
                break;
            case 'BUSY':
                $log->info("Исходящий звонок - BUSY.");
                $log->info("callerIDNum: {$event->getCallerIDNum()}, destCallerIDNum: {$destCallerIDNum}, uniqueID: {$uniqueID}, CALL_ID: {$globalsObj->calls[$uniqueID]}");
                $helper->hideOutputCall($globalsObj->callerIDNums[$uniqueID], $globalsObj->calls[$uniqueID]);
                break;
            case 'CANCEL':
                $log->info("Исходящий звонок - CANCEL.");
                $log->info("callerIDNum: {$event->getCallerIDNum()}, destCallerIDNum: {$destCallerIDNum}, uniqueID: {$uniqueID}, CALL_ID: {$globalsObj->calls[$uniqueID]}");
                $helper->hideOutputCall($globalsObj->callerIDNums[$uniqueID], $globalsObj->calls[$uniqueID]);
                break;
            default:
                break;
        }
    }
}, function (EventMessage $event) use ($globalsObj) {
    return
        $event instanceof DialEndEvent
        && in_array($event->getUniqueID(), $globalsObj->uniqueIDs);
});

$client->registerEventListener(function (EventMessage $event) use ($log, $helper, $globalsObj) {
    if ($event instanceof HangupEvent) {
        $uniqueID = $event->getUniqueID();
        $fullFname = $globalsObj->fullFnameUrls[$uniqueID];
        $duration = $globalsObj->durations[$uniqueID];
        $disposition = $globalsObj->dispositions[$uniqueID];
        $callID = $globalsObj->calls[$uniqueID];
        $callerIDNum = $globalsObj->callerIDNums[$uniqueID];

        $log->info("Новое событие HangupEvent первый шаг - URL записи файла, внутренний номер, продолжительность, состояние (disposition)");
        $log->info("uniqueID: {$uniqueID}, fullFname: {$fullFname}, callID: {$callID}, duration: {$duration}, disposition: {$disposition}, callerIDNum: {$callerIDNum}");

        $resultFromB24 = $helper->uploadRecordedFile($callID, $fullFname, $callerIDNum, $duration, $disposition);
        $log->info("Новое событие HangupEvent второй шаг - загрузка файла");
        $log->info("resultFromB24: {$resultFromB24}");

        // Удаляем из массивов тот вызов, который завершился
        $helper->removeItemFromArray($globalsObj->uniqueIDs, $uniqueID, 'value');
        $helper->removeItemFromArray($globalsObj->callerIDNums, $uniqueID, 'key');
        $helper->removeItemFromArray($globalsObj->fullFnameUrls, $uniqueID, 'key');
        $helper->removeItemFromArray($globalsObj->durations, $uniqueID, 'key');
        $helper->removeItemFromArray($globalsObj->dispositions, $uniqueID, 'key');
        $helper->removeItemFromArray($globalsObj->calls, $uniqueID, 'key');
    }
}, function (EventMessage $event) use ($globalsObj) {
    return
        $event instanceof HangupEvent
        && in_array($event->getUniqueID(), $globalsObj->uniqueIDs);
});

// Цикл обработки событий
while (true) {
    $client->process();
    usleep($helper->getConfigValue('listener_timeout'));
}

// Закрытие соединения с сервером Asterisk
$client->close();
