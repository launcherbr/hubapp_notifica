<?php
/**
 * HubApp Notifica WHMCS - Hooks
 * Versão: 1.5.5
 */

if (!defined("WHMCS")) die("Access Denied");

use WHMCS\Database\Capsule;
use HubAppModule\HubAppClient;

require_once __DIR__ . '/lib/HubAppClient.php';

/**
 * Validação de WhatsApp no Cadastro
 * Ignora o sinal de + e valida entre 8 e 15 dígitos.
 */
add_hook('ClientDetailsValidation', 1, function($vars) {
    $fieldId = Capsule::table('tbladdonmodules')->where('module', 'hubapp_notifica')->where('setting', 'whatsapp_field_id')->value('value');
    if (!$fieldId || $fieldId == 0) return;

    $fieldName = 'customfield' . $fieldId;
    if (isset($_POST[$fieldName]) && !empty($_POST[$fieldName])) {
        $numbersOnly = preg_replace('/\D/', '', $_POST[$fieldName]);
        if (strlen($numbersOnly) < 8 || strlen($numbersOnly) > 15) {
            return "WhatsApp inválido. Informe o número com DDI e DDD (Ex: +5511999998888).";
        }
    }
});

/**
 * Função Auxiliar de Disparo
 * Processa templates salvos no banco e realiza o replace de variáveis.
 */
function hubapp_dispatch($hook, $uid, $replacements, $extId) {
    $tpl = Capsule::table('tbladdonmodules')->where('module', 'hubapp_notifica')->where('setting', 'template_' . $hook)->value('value');
    if (empty($tpl)) return;
    $message = str_replace(array_keys($replacements), array_values($replacements), $tpl);
    return HubAppClient::send($uid, $message, $extId);
}

// --- GATILHOS DE FATURAMENTO (CORRIGIDOS) ---

/**
 * Substitui o InvoiceCreated para evitar o erro de Imutabilidade.
 */
add_hook('InvoiceCreationPreEmail', 1, function($vars) {
    $invoiceId = $vars['invoiceid'];
    try {
        $inv = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
        if (!$inv) return;

        $cli = Capsule::table('tblclients')->where('id', $inv->userid)->first();
        $systemUrl = Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value');
        
        hubapp_dispatch('InvoiceCreated', $cli->id, [
            '{firstname}' => $cli->firstname,
            '{invoiceid}' => $invoiceId,
            '{total}' => $inv->total,
            '{duedate}' => fromMySQLDate($inv->duedate),
            '{invoice_url}' => $systemUrl . "viewinvoice.php?id=" . $invoiceId
        ], "INV_NEW_" . $invoiceId);
    } catch (\Exception $e) {
        logActivity("HubApp Error [InvoiceCreated]: " . $e->getMessage());
    }
});

add_hook('InvoicePaid', 1, function($vars) {
    $inv = Capsule::table('tblinvoices')->where('id', $vars['invoiceid'])->first();
    $cli = Capsule::table('tblclients')->where('id', $inv->userid)->first();
    hubapp_dispatch('InvoicePaid', $cli->id, [
        '{firstname}' => $cli->firstname, 
        '{invoiceid}' => $vars['invoiceid']
    ], "PAID_" . $vars['invoiceid']);
});

add_hook('InvoicePaymentReminder', 1, function($vars) {
    $inv = Capsule::table('tblinvoices')->where('id', $vars['invoiceid'])->first();
    $cli = Capsule::table('tblclients')->where('id', $inv->userid)->first();
    $type = ($vars['type'] == 'first') ? 'First' : (($vars['type'] == 'second') ? 'Second' : 'Third');
    
    hubapp_dispatch('InvoicePaymentReminder' . $type, $cli->id, [
        '{firstname}' => $cli->firstname,
        '{invoiceid}' => $vars['invoiceid'],
        '{duedate}' => fromMySQLDate($inv->duedate)
    ], "REM_" . $type . "_" . $vars['invoiceid']);
});

// --- GATILHOS DE SUPORTE ---

add_hook('TicketOpen', 1, function($vars) {
    $tpl = Capsule::table('tbladdonmodules')->where('module', 'hubapp_notifica')->where('setting', 'template_TicketOpenAdmin')->value('value');
    if (!$tpl) return;

    $firstName = "Visitante"; // Fallback para tickets de não-clientes
    if ($vars['userid']) {
        $firstName = Capsule::table('tblclients')->where('id', $vars['userid'])->value('firstname');
    }

    $msg = str_replace(['{subject}', '{firstname}', '{priority}'], [$vars['subject'], $firstName, $vars['priority']], $tpl);
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

// --- GATILHOS DE SEGURANÇA E SERVIÇOS ---

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

add_hook('AfterModuleSuspend', 1, function($vars) {
    $p = $vars['params'];
    $firstName = Capsule::table('tblclients')->where('id', $p['userid'])->value('firstname');
    hubapp_dispatch('AfterModuleSuspend', $p['userid'], [
        '{firstname}' => $firstName, 
        '{domain}' => $p['domain']
    ], "MOD_SUS_" . $p['accountid']);
});