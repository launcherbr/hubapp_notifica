<?php
/**
 * HubApp Notifica WHMCS
 * @author     HubApp / Licencas.Digital
 * @version    1.5.3
 */

if (!defined("WHMCS")) die("Access Denied");

use WHMCS\Database\Capsule;

function hubapp_notifica_config() {
    $customFields = [0 => "Usar apenas telefone padrão"];
    try {
        $fields = Capsule::table('tblcustomfields')->where('type', 'client')->get(['id', 'fieldname']);
        foreach ($fields as $field) { $customFields[$field->id] = $field->fieldname; }
    } catch (\Exception $e) {}

    return [
        "name" => "HubApp Notifica WHMCS",
        "description" => "Notificações via WhatsApp. Envie mensagens manuais e gerencie todos os templates automáticos.",
        "author" => "HubApp",
        "version" => "1.5.3",
        "fields" => [
            "api_endpoint" => ["FriendlyName" => "Endpoint HubApp", "Type" => "text", "Size" => "60"],
            "api_token" => ["FriendlyName" => "Bearer Token", "Type" => "password", "Size" => "60"],
            "whatsapp_field_id" => ["FriendlyName" => "Campo WhatsApp", "Type" => "dropdown", "Options" => $customFields],
            "admin_whatsapp" => ["FriendlyName" => "WhatsApp Admin", "Type" => "text", "Size" => "20"],
            "close_ticket" => [
                "FriendlyName" => "Fechar Ticket após envio?",
                "Type" => "dropdown",
                "Options" => ["false" => "Não", "true" => "Sim"],
                "Default" => "false",
            ],
        ]
    ];
}

function hubapp_notifica_output($vars) {
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">';
    $version = $vars['version'];
    
    // TODOS os Templates Restaurados
    $templates = [
        'InvoiceCreated' => ['name' => 'Fatura Gerada', 'default' => 'ℹ️ Olá {firstname}, sua fatura #{invoiceid} foi gerada no valor de R$ {total}. Vencimento: {duedate}. Pague aqui: {invoice_url}'],
        'InvoicePaid' => ['name' => 'Pagamento Confirmado', 'default' => '✅ Obrigado {firstname}! Confirmamos o pagamento da sua fatura #{invoiceid}. Seus serviços seguem ativos.'],
        'InvoicePaymentReminderFirst' => ['name' => '1º Aviso de Atraso', 'default' => '⚠️ Olá {firstname}, lembramos que a fatura #{invoiceid} venceu em {duedate}. Evite o bloqueio pagando agora: {invoice_url}'],
        'InvoicePaymentReminderSecond' => ['name' => '2º Aviso de Atraso', 'default' => '⚠️ Oi {firstname}, o pagamento da fatura #{invoiceid} ainda não foi identificado. Precisamos de ajuda com algo?'],
        'InvoicePaymentReminderThird' => ['name' => 'Aviso Crítico', 'default' => '❌ ATENÇÃO {firstname}! A fatura #{invoiceid} está muito atrasada. O serviço será suspenso em breve se não for regularizado.'],
        'TicketAdminReply' => ['name' => 'Resposta em Ticket', 'default' => 'ℹ️ Olá {firstname}, nossa equipe respondeu ao seu ticket: {ticketsubject}. Acesse a central para ler a resposta.'],
        'TicketOpenAdmin' => ['name' => 'Admin: Novo Ticket', 'default' => '⚠️ Novo Ticket: {subject} | Cliente: {firstname} | Prioridade: {priority}'],
        'AdminLogin' => ['name' => 'Alerta de Login Admin', 'default' => '⚠️ Alerta: O usuário {username} acabou de acessar o painel administrativo do WHMCS.'],
        'AfterModuleCreate' => ['name' => 'Serviço Ativado', 'default' => '✅ Boas notícias {firstname}! Seu serviço {domain} está ativo. Usuário: {username} | Senha: {password}'],
        'AfterModuleSuspend' => ['name' => 'Serviço Suspenso', 'default' => '❌ Atenção {firstname}, informamos que o serviço {domain} foi suspenso. Entre em contato para mais detalhes.'],
        'DomainRenewalNotice' => ['name' => 'Expiração de Domínio', 'default' => 'ℹ️ Olá {firstname}, seu domínio {domain} expira em {x} dias ({expirydate}). Renove agora para não perder!']
    ];

    // Lógica de Envio Manual
    if (isset($_POST['send_manual_msg'])) {
        require_once __DIR__ . '/lib/HubAppClient.php';
        $userid = $_POST['target_client'];
        $msg = $_POST['manual_body'];
        if ($userid && !empty($msg)) {
            \HubAppModule\HubAppClient::send($userid, $msg, "MANUAL_" . time());
            echo '<div class="alert alert-success">✅ Mensagem personalizada enviada!</div>';
        }
    }

    // Salvar Templates
    if (isset($_POST['save_templates'])) {
        foreach ($templates as $key => $data) {
            Capsule::table('tbladdonmodules')->updateOrInsert(
                ['module' => 'hubapp_notifica', 'setting' => 'template_' . $key],
                ['value' => $_POST['tpl_' . $key]]
            );
        }
        echo '<div class="alert alert-success"><i class="fas fa-save"></i> Templates salvos com sucesso!</div>';
    }

    echo '<h2><i class="fab fa-whatsapp" style="color:#25D366"></i> Central HubApp Notifica</h2>';

    // Bloco Envio Manual
    echo '<div class="panel panel-info">
        <div class="panel-heading"><h3 class="panel-title"><i class="fas fa-comment-dots"></i> Enviar Mensagem Personalizada (Sem variáveis)</h3></div>
        <div class="panel-body">
            <form method="post">
                <select name="target_client" class="form-control selectize" style="margin-bottom:10px;">
                    <option value="">-- Escolha o Cliente --</option>';
                    $clients = Capsule::table('tblclients')->orderBy('firstname', 'asc')->limit(1000)->get(['id', 'firstname', 'lastname']);
                    foreach ($clients as $c) { echo '<option value="'.$c->id.'">#'.$c->id.' - '.$c->firstname.' '.$c->lastname.'</option>'; }
    echo '      </select>
                <textarea name="manual_body" class="form-control" rows="3" placeholder="Escreva o texto personalizado aqui..."></textarea>
                <br><button type="submit" name="send_manual_msg" class="btn btn-primary">Enviar WhatsApp Avulso</button>
            </form>
        </div>
    </div>';

    // Tabela de Todos os Templates
    echo '<form method="post"><div class="panel panel-default">
        <div class="panel-heading"><h3 class="panel-title">Configurar Notificações Automáticas</h3></div>
        <table class="table table-striped">
            <thead><tr><th width="20%">Evento</th><th>Mensagem</th></tr></thead>
            <tbody>';
    foreach ($templates as $key => $data) {
        $val = Capsule::table('tbladdonmodules')->where('module', 'hubapp_notifica')->where('setting', 'template_' . $key)->value('value');
        $displayValue = (!empty(trim($val))) ? $val : $data['default'];
        echo '<tr><td><strong>'.$data['name'].'</strong></td><td><textarea name="tpl_'.$key.'" class="form-control" rows="2">'.htmlspecialchars($displayValue).'</textarea></td></tr>';
    }
    echo '</tbody></table>
        <div class="panel-footer"><button type="submit" name="save_templates" class="btn btn-success">Salvar Todos os Templates</button></div>
    </div></form>';

    echo '<div class="text-center" style="margin-top: 30px; padding: 20px; border-top: 1px solid #eee;">
        <small>Versão ' . $version . ' | Suporte: <a href="https://licencas.digital" target="_blank">licencas.digital</a></small>
    </div>';
}