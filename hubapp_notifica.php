<?php
/**
 * HubApp Notifica WHMCS - Unificado (v1.5.9)
 * Suporta: Whaticket, Evolution API e Z-Pro
 * @author     HubApp / Licencas.Digital
 */

if (!defined("WHMCS")) die("Access Denied");

use WHMCS\Database\Capsule;

function hubapp_notifica_config() {
    $customFields = [0 => "Usar telefone padrão"];
    try {
        $fields = Capsule::table('tblcustomfields')->where('type', 'client')->get(['id', 'fieldname']);
        foreach ($fields as $field) { $customFields[$field->id] = $field->fieldname; }
    } catch (\Exception $e) {}

    return [
        "name" => "HubApp Notifica WHMCS",
        "description" => "Módulo unificado para notificações via Whaticket, Evolution API e Z-Pro.",
        "author" => "HubApp",
        "version" => "1.5.9",
        "fields" => [
            "gateway_type" => [
                "FriendlyName" => "Gateway de Envio",
                "Type" => "dropdown",
                "Options" => [
                    "whaticket" => "Whaticket", 
                    "evolution" => "Evolution API (Baileys)", 
                    "zpro" => "Z-Pro"
                ],
            ],
            "api_endpoint" => ["FriendlyName" => "Endpoint (URL)", "Type" => "text", "Size" => "70", "Description" => "URL da rota de envio de texto."],
            "api_token" => ["FriendlyName" => "Token / ApiKey", "Type" => "password", "Size" => "70"],
            "whatsapp_field_id" => ["FriendlyName" => "Campo WhatsApp", "Type" => "dropdown", "Options" => $customFields],
            "admin_whatsapp" => ["FriendlyName" => "WhatsApp Admin", "Type" => "text", "Size" => "20", "Description" => "Número com DDI (Ex: 5534999999999)"],
        ]
    ];
}

function hubapp_notifica_output($vars) {
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">';
    require_once __DIR__ . '/lib/HubAppClient.php';
    
    $templates = [
        'InvoiceCreated' => ['name' => 'Fatura Gerada', 'default' => 'Olá {firstname}, sua fatura #{invoiceid} de R$ {total} foi gerada. Vencimento: {duedate}. Pague aqui: {invoice_url}'],
        'InvoicePaid' => ['name' => 'Pagamento Confirmado', 'default' => '✅ Obrigado {firstname}! Recebemos o pagamento da fatura #{invoiceid}. Seus serviços seguem ativos.'],
        'InvoicePaymentReminderFirst' => ['name' => '1º Aviso de Atraso', 'default' => '⚠️ Olá {firstname}, lembramos que a fatura #{invoiceid} venceu em {duedate}. Evite bloqueios: {invoice_url}'],
        'InvoicePaymentReminderSecond' => ['name' => '2º Aviso de Atraso', 'default' => '⚠️ Oi {firstname}, o pagamento da fatura #{invoiceid} ainda não consta em nosso sistema. Precisa de ajuda?'],
        'InvoicePaymentReminderThird' => ['name' => 'Aviso Crítico (3º)', 'default' => '❌ ATENÇÃO {firstname}! A fatura #{invoiceid} está com atraso crítico. O serviço será suspenso em breve.'],
        'TicketAdminReply' => ['name' => 'Resposta em Ticket', 'default' => 'ℹ️ Olá {firstname}, seu ticket #{ticketno} ({ticketsubject}) foi respondido. Veja na sua área do cliente.'],
        'TicketOpenAdmin' => ['name' => 'Admin: Novo Ticket', 'default' => '⚠️ Novo Ticket: {subject} | Cliente: {firstname} | Prioridade: {priority}'],
        'AfterModuleCreate' => ['name' => 'Serviço Ativado', 'default' => '✅ Boas notícias {firstname}! Seu serviço {domain} está ativo. User: {username} | Pass: {password}'],
        'AfterModuleSuspend' => ['name' => 'Serviço Suspenso', 'default' => '❌ Atenção {firstname}, informamos que o serviço {domain} foi suspenso por pendências.'],
        'DomainRenewalNotice' => ['name' => 'Expiração de Domínio', 'default' => 'ℹ️ Olá {firstname}, seu domínio {domain} expira em {x} dias ({expirydate}). Renove agora para não perder!'],
        'AdminLogin' => ['name' => 'Alerta de Login Admin', 'default' => '⚠️ Segurança: O usuário {username} acessou o painel administrativo do WHMCS neste momento.'],
    ];

    // Lógica: Salvar Templates
    if (isset($_POST['save_templates'])) {
        foreach ($templates as $key => $data) {
            Capsule::table('tbladdonmodules')->updateOrInsert(
                ['module' => 'hubapp_notifica', 'setting' => 'template_' . $key],
                ['value' => $_POST['tpl_' . $key]]
            );
        }
        echo '<div class="alert alert-success"><i class="fas fa-save"></i> Configurações e Templates salvos com sucesso!</div>';
    }

    // Lógica: Teste de Conexão com externalKey
    if (isset($_POST['test_connection'])) {
        $result = \HubAppModule\HubAppClient::sendToAdmin("🚀 *HubApp Notifica*\nTeste de conexão unificado realizado com sucesso!", "TEST_" . time());
        echo '<div class="alert alert-info"><strong>Resposta da API:</strong><br><pre>' . htmlspecialchars($result) . '</pre></div>';
    }

    // Lógica: Envio Manual
    if (isset($_POST['send_manual_msg']) && !empty($_POST['manual_body'])) {
        \HubAppModule\HubAppClient::send($_POST['target_client'], $_POST['manual_body'], "MANUAL_" . time());
        echo '<div class="alert alert-success"><i class="fas fa-check"></i> Mensagem personalizada enviada com sucesso!</div>';
    }

    echo '<h2><i class="fab fa-whatsapp" style="color:#25D366"></i> Central HubApp Notifica</h2>';

    // Bloco de Teste e Envio Manual (Coluna Única para Scannability)
    echo '<div class="panel panel-default">
        <div class="panel-heading"><h3 class="panel-title"><i class="fas fa-plug"></i> Testar Conexão</h3></div>
        <div class="panel-body">
            <form method="post">
                <button type="submit" name="test_connection" class="btn btn-info"><i class="fas fa-paper-plane"></i> Enviar Teste para WhatsApp Admin</button>
            </form>
        </div>
    </div>';

    echo '<div class="panel panel-info">
        <div class="panel-heading"><h3 class="panel-title"><i class="fas fa-user-edit"></i> Envio Avulso para Cliente</h3></div>
        <div class="panel-body">
            <form method="post">
                <div class="form-group">
                    <label>Cliente:</label>
                    <select name="target_client" class="form-control">
                        <option value="">-- Selecione o Cliente --</option>';
                        $clients = Capsule::table('tblclients')->orderBy('firstname', 'asc')->get(['id', 'firstname', 'lastname']);
                        foreach ($clients as $c) { echo '<option value="'.$c->id.'">#'.$c->id.' - '.$c->firstname.' '.$c->lastname.'</option>'; }
    echo '          </select>
                </div>
                <div class="form-group">
                    <label>Mensagem:</label>
                    <textarea name="manual_body" class="form-control" rows="3" placeholder="Digite sua mensagem..."></textarea>
                </div>
                <button type="submit" name="send_manual_msg" class="btn btn-primary btn-block">Enviar WhatsApp Agora</button>
            </form>
        </div>
    </div>';

    // Gerenciador de Templates
    echo '<form method="post"><div class="panel panel-default">
        <div class="panel-heading"><h3 class="panel-title"><i class="fas fa-robot"></i> Configuração de Automações</h3></div>
        <table class="table table-striped">
            <thead><tr><th width="25%">Evento WHMCS</th><th>Mensagem Customizada (Texto Simples)</th></tr></thead>
            <tbody>';
    foreach ($templates as $key => $data) {
        $val = Capsule::table('tbladdonmodules')->where('module', 'hubapp_notifica')->where('setting', 'template_' . $key)->value('value');
        $display = (!empty($val)) ? $val : $data['default'];
        echo '<tr><td><strong>'.$data['name'].'</strong></td><td><textarea name="tpl_'.$key.'" class="form-control" rows="2">'.htmlspecialchars($display).'</textarea></td></tr>';
    }
    echo '</tbody></table>
        <div class="panel-footer"><button type="submit" name="save_templates" class="btn btn-success"><i class="fas fa-save"></i> Salvar Todos os Templates</button></div>
    </div></form>';

    echo '<div class="text-center" style="margin-top: 20px; color: #888;">
        <small>HubApp Notifica v1.5.9 | Suporte: <a href="https://licencas.digital" target="_blank">licencas.digital</a></small>
    </div>';
}