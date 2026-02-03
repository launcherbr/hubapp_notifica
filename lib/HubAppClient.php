<?php
namespace HubAppModule;
use WHMCS\Database\Capsule;

if (!defined("WHMCS")) die("Access Denied");

class HubAppClient {
    
    public static function getValidNumber($clientId) {
        $config = Capsule::table('tbladdonmodules')->where('module', 'hubapp_notifica')->pluck('value', 'setting');
        $fieldId = (int)$config['whatsapp_field_id'];
        
        // Prioriza o campo personalizado configurado
        $num = ($fieldId > 0) ? Capsule::table('tblcustomfieldsvalues')->where('fieldid', $fieldId)->where('relid', $clientId)->value('value') : '';
        
        // Se vazio, usa o telefone padrão do WHMCS
        if (empty(trim($num))) $num = Capsule::table('tblclients')->where('id', $clientId)->value('phonenumber');
        
        return $num;
    }

    public static function send($clientId, $message, $externalKey) {
        $num = self::getValidNumber($clientId);
        return self::execute($num, $message, $externalKey);
    }

    public static function sendToAdmin($message, $externalKey) {
        $config = Capsule::table('tbladdonmodules')->where('module', 'hubapp_notifica')->pluck('value', 'setting');
        return self::execute($config['admin_whatsapp'], $message, $externalKey);
    }

    private static function execute($num, $msg, $key) {
        $config = Capsule::table('tbladdonmodules')->where('module', 'hubapp_notifica')->pluck('value', 'setting');
        
        // Remove tudo que não for número (limpa o + e espaços)
        $cleanNumber = preg_replace('/\D/', '', $num);

        // Formatação para Brasil (adiciona 55 se tiver 10 ou 11 dígitos)
        if (strlen($cleanNumber) >= 10 && strlen($cleanNumber) <= 11) {
            if (substr($cleanNumber, 0, 1) === '0') $cleanNumber = substr($cleanNumber, 1);
            if (substr($cleanNumber, 0, 2) !== '55') $cleanNumber = '55' . $cleanNumber;
        }

        $token = trim(str_replace('Bearer ', '', $config['api_token']));
        $isClosed = ($config['close_ticket'] === "true"); // Respeita a configuração do painel

        $payload = [
            "body" => $msg,
            "number" => $cleanNumber,
            "externalKey" => (string)$key,
            "isClosed" => $isClosed
        ];

        $ch = curl_init($config['api_endpoint']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token, 'Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        curl_close($ch);
        
        return $res;
    }
}