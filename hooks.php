<?php
/**
 * HubApp Notifica WHMCS - Hooks Completo
 * Versão: 1.5.3 (Correção de Variáveis de Nome)
 */

if (!defined("WHMCS")) die("Access Denied");

use WHMCS\Database\Capsule;
use HubAppModule\HubAppClient;

require_once __DIR__ . '/lib/HubAppClient.php';

// --- FUNÇÃO AUXILIAR ---
function hubapp_dispatch($hook, $uid, $replacements, $extId) {
    $tpl = Capsule::table('tbladdonmodules')->where('module', 'hubapp_notifica')->where('setting', 'template_' . $hook)->value('value');
    if (empty($tpl)) return;
    $message = str_replace(array_keys($replacements), array_values($replacements), $tpl);
    return HubAppClient::send($uid, $message, $extId);
}

// --- GATILHOS DE SUPORTE (CORRIGIDO) ---

add_hook('TicketOpen', 1, function($vars) {
    $tpl = Capsule::table('tbladdonmodules')->where('module', 'hubapp_notifica')->where('setting', 'template_TicketOpenAdmin')->value('value');
    if (!$tpl) return;

    // Busca o nome do cliente de forma segura via ID
    $firstName = "Visitante";
    if ($vars['userid']) {
        $firstName = Capsule::table('tblclients')->where('id', $vars['userid'])->value('firstname');
    } elseif (!empty($vars['clientname'])) {
        $firstName = explode(' ', trim($vars['clientname']))[0];
    }

    $msg = str_replace(
        ['{subject}', '{firstname}', '{priority}'], 
        [$vars['subject'], $firstName, $vars['priority']], 
        $tpl
    );
    
    HubAppClient::sendToAdmin($msg, "ADM_TK_" . $vars['ticketid']);
});

add_hook('TicketAdminReply', 1, function($vars) {
    $ticket = Capsule::table('tbltickets')->where('id', $vars['ticketid'])->first();
    $cli = Capsule::table('tblclients')->where('id', $ticket->userid)->first();

    hubapp_dispatch('TicketAdminReply', $cli->id, [
        '{firstname}' => $cli->firstname,
        '{ticketsubject}' => $ticket->title,
        '{ticketno}' => $ticket->tid
    ], "TK_REP_" . $vars['ticketid']);
});

// --- GATILHOS DE FATURAMENTO ---

add_hook('InvoiceCreated', 1, function($vars) {
    $inv = Capsule::table('tblinvoices')->where('id', $vars['invoiceid'])->first();
    $cli = Capsule::table('tblclients')->where('id', $inv->userid)->first();
    $url = Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value') . "viewinvoice.php?id=" . $vars['invoiceid'];
    
    hubapp_dispatch('InvoiceCreated', $cli->id, [
        '{firstname}' => $cli->firstname, 
        '{invoiceid}' => $vars['invoiceid'], 
        '{total}' => $inv->total, 
        '{duedate}' => fromMySQLDate($inv->duedate), 
        '{invoice_url}' => $url
    ], "INV_".$vars['invoiceid']);
});

// --- OUTROS GATILHOS (LOGIN E SERVIÇOS) ---

add_hook('AdminLogin', 1, function($vars) {
    $tpl = Capsule::table('tbladdonmodules')->where('module', 'hubapp_notifica')->where('setting', 'template_AdminLogin')->value('value');
    if ($tpl) {
        HubAppClient::sendToAdmin(str_replace('{username}', $vars['username'], $tpl), "ADM_LGN_" . time());
    }
});

add_hook('AfterModuleCreate', 1, function($vars) {
    $p = $vars['params'];
    $firstName = Capsule::table('tblclients')->where('id', $p['userid'])->value('firstname');
    
    hubapp_dispatch('AfterModuleCreate', $p['userid'], [
        '{firstname}' => $firstName, 
        '{domain}' => $p['domain'], 
        '{username}' => $p['username'], 
        '{password}' => $p['password']
    ], "MOD_CRT_" . $p['accountid']);
});

// Hook de Validação de WhatsApp
add_hook('ClientDetailsValidation', 1, function($vars) {
    $fieldId = Capsule::table('tbladdonmodules')->where('module', 'hubapp_notifica')->where('setting', 'whatsapp_field_id')->value('value');
    if (!$fieldId || $fieldId == 0) return;
    $fieldName = 'customfield' . $fieldId;
    if (isset($_POST[$fieldName]) && !empty($_POST[$fieldName])) {
        $numbersOnly = preg_replace('/\D/', '', $_POST[$fieldName]);
        if (strlen($numbersOnly) < 8 || strlen($numbersOnly) > 15) return "WhatsApp inválido (DDI+DDD+Número).";
    }
});