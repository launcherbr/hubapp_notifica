# 📱 HubApp Notifica WHMCS

O **HubApp Notifica** é um módulo Addon para WHMCS desenvolvido para automatizar a comunicação com seus clientes e administradores via WhatsApp. Ele integra o WHMCS à API da HubApp, permitindo notificações automáticas de faturamento, suporte e segurança, além de uma central para envios manuais.

---

## ✨ Funcionalidades

* **Automação de Faturamento**: Avisos de fatura gerada, confirmação de pagamento e lembretes de atraso (1º, 2º e 3º aviso).
* **Suporte em Tempo Real**: Notificações de respostas em tickets para clientes e alertas de novos tickets para administradores.
* **Gestão de Serviços**: Alertas de ativação de conta, suspensão de serviços e avisos de expiração de domínios.
* **Segurança**: Alerta imediato via WhatsApp para logins administrativos no painel WHMCS.
* **Central de Mensagens**: Interface integrada no addon para envio de mensagens manuais personalizadas.
* **Validação Inteligente**: Hook de validação que garante números formatados corretamente (8 a 15 dígitos), aceitando ou limpando o prefixo `+`.
* **Compatibilidade de Emojis**: Templates pré-configurados com emojis seguros para evitar erros de codificação (`????`) no banco de dados.

---

## 🚀 Instalação

1.  Faça o download ou clone este repositório.
2.  Envie a pasta `hubapp_notifica` para o diretório `/modules/addons/` do seu WHMCS.
3.  Certifique-se de que a estrutura de pastas está correta:
    * `modules/addons/hubapp_notifica/hubapp_notifica.php`
    * `modules/addons/hubapp_notifica/hooks.php`
    * `modules/addons/hubapp_notifica/lib/HubAppClient.php`
4.  No painel administrativo, vá em **Ajustes > Módulos Addon** e ative o **HubApp Notifica WHMCS**.
5.  Clique em **Configurar**, insira seu **Endpoint** e **Bearer Token** da HubApp.

---

## ⚙️ Configuração e Variáveis

Você pode personalizar as mensagens diretamente na página do módulo em **Complementos > HubApp Notifica**. As seguintes variáveis são suportadas nos templates automáticos:

| Variável | Descrição |
| :--- | :--- |
| `{firstname}` | Primeiro nome do cliente |
| `{invoiceid}` | ID da fatura |
| `{total}` | Valor total da fatura |
| `{duedate}` | Data de vencimento formatada |
| `{invoice_url}` | Link direto para a fatura online |
| `{ticketsubject}` | Assunto do ticket de suporte |
| `{domain}` | Nome do domínio ou serviço ativado/suspenso |
| `{username}` | Usuário (para alertas de login ou dados de acesso) |

---

## 🛠️ Requisitos

* WHMCS v8.x ou superior.
* PHP 7.4 ou 8.x.
* Conexão ativa com a API HubApp.
* Banco de dados MySQL (preferencialmente com Collation `utf8mb4` para suporte total a emojis, embora o módulo use padrões seguros).

---

## 📄 Licença e Suporte

Desenvolvido por **[Licencas.Digital](https://licencas.digital)**.

Para suporte ou dúvidas sobre a integração, acesse nosso site oficial.

---
⭐ **Gostou do projeto? Considere dar uma estrela no repositório!**
