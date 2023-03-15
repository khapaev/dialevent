<?php

class Helper
{
    public function getUserIDByCallerIDNum($callerIDNum)
    {
        $result = $this->callBitrixApi(array('FILTER' => array('UF_PHONE_INNER' => $callerIDNum)), 'user.get');
        if ($result) {
            return $result['result'][0]['ID'];
        }
        return false;
    }

    public function uploadRecordedFile($callID, $recordedFile, $callerIDNum, $duration, $disposition)
    {
        switch ($disposition) {
            case 'ANSWER|ANSWERED':
                $statusCode = 200;
                break;
            case 'NO ANSWER':
                $statusCode = 304;
                break;
            case 'BUSY':
                $statusCode = 486;
                break;
            default:
                $statusCode = 603;
                if (empty($disposition)) {
                    $statusCode = 304;
                }
                break;
        }

        $result = $this->callBitrixApi(array(
            'USER_PHONE_INNER' => $callerIDNum,
            'CALL_ID' => $callID,
            'STATUS_CODE' => $statusCode,
            'DURATION' => $duration,
            'RECORD_URL' => $recordedFile
        ), 'telephony.externalcall.finish');

        if ($result) {
            return $result;
        }

        return false;
    }

    public function runOutputCall($callerIDNum, $extension)
    {
        $result = $this->callBitrixApi(array(
            'USER_PHONE_INNER' => $callerIDNum,
            'PHONE_NUMBER' => $extension,
            'TYPE' => 1,
            'CALL_START_DATE' => date("Y-m-d H:i:s"),
            'CRM_CREATE' => 1,
            'SHOW' => 0,
        ), 'telephony.externalcall.register');
        if ($result) {
            return $result['result']['CALL_ID'];
        }
        return false;
    }

    public function getCrmContactNameByExtension($extension)
    {
        $result = $this->callBitrixApi(array(
            'FILTER' => array('PHONE' => $extension),
            'SELECT' => array('NAME', 'LAST_NAME')
        ), 'crm.contact.list');
        $fillName = $extension;
        if ($result) {
            if (isset($result['total']) && $result['total'] > 0) {
                $fullName = $this->transliterate($result['result'][0]['NAME'] . '_' . $result['result'][0]['LAST_NAME']);
            }
        }
        return $fullName;
    }

    public function showOutputCall($callerIDNum, $callID)
    {
        $userID = $this->getUserIDByCallerIDNum($callerIDNum);
        if ($userID) {
            $result = $this->callBitrixApi(array(
                'CALL_ID' => $callID,
                'USER_ID' => $userID
            ), 'telephony.externalcall.show');
            return $result;
        }
        return false;
    }

    public function hideOutputCall($callerIDNum, $callID)
    {
        $userID = $this->getUserIDByCallerIDNum($callerIDNum);
        if ($userID) {
            $result = $this->callBitrixApi(array(
                'CALL_ID' => $callID,
                'USER_ID' => $userID
            ), 'telephony.externalcall.hide');
            return $result;
        }
        return false;
    }

    public function isJson($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    public function callBitrixApi($data, $method)
    {
        $url = $this->getConfigValue('bitrixApiUrl');
        if (!$url) {
            return false;
        }
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POST => true,
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => "{$url}{$method}.json",
            CURLOPT_POSTFIELDS => http_build_query($data)
        ));
        $result = curl_exec($ch);
        curl_close($ch);

        if ($this->isJson($result)) {
            return json_decode($result, true);
        }

        return false;
    }

    public function removeItemFromArray(&$data, $needle, $what)
    {
        if ($what === 'value') {
            if (($key = array_search($needle, $data)) !== false) {
                unset($data[$key]);
            }
        } elseif ($what === 'key') {
            if (array_key_exists($needle, $data)) {
                unset($data[$needle]);
            }
        }
    }

    public function getConfigValue($key)
    {
        $config = require __DIR__ . '/../../callme/config.php';
        return $config[$key] ?? null;
    }

    public function transliterate($string)
    {
        $table = array(
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh',
            'З' => 'Z', 'И' => 'I', 'Й' => 'J', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O',
            'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'Ts',
            'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sch', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'Yu',
            'Я' => 'Ya', 'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo',
            'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'j', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h',
            'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e',
            'ю' => 'yu', 'я' => 'ya'
        );
        $result = '';
        for ($i = 0; $i < mb_strlen($string); $i++) {
            $char = mb_substr($string, $i, 1);
            if (isset($table[$char])) {
                $result .= $table[$char];
            } else {
                $result .= $char;
            }
        }
        return $result;
    }
}
