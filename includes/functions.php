<?php
function get_plugin_version() {
    $plugin_data = get_file_data(plugin_dir_path(dirname(__FILE__)) . 'cadastrar-usuarios-em-lote.php', array('Version' => 'Version'), 'plugin');
    return $plugin_data['Version'];
}

function cadastrar_usuarios_em_lote_obter_nome_destinatario($nome) {
    $nome = trim((string) $nome);

    if ($nome === '') {
        return '';
    }

    return $nome;
}

function cadastrar_usuarios_em_lote_replace_placeholders($content, $replacements) {
    $content = (string) $content;

    if ($content === '' || empty($replacements)) {
        return $content;
    }

    $normalized_replacements = [];
    foreach ($replacements as $placeholder => $value) {
        $normalized_replacements[(string) $placeholder] = (string) $value;
    }

    return strtr($content, $normalized_replacements);
}

function cadastrar_usuarios_em_lote_obter_titulo_post($post_id) {
    $post_id = (int) $post_id;

    if ($post_id <= 0) {
        return '';
    }

    $title = get_the_title($post_id);

    return is_string($title) ? $title : '';
}

function cadastrar_usuarios_em_lote_obter_reset_url_usuario($user) {
    if (!($user instanceof WP_User)) {
        return '';
    }

    $reset_key = get_password_reset_key($user);
    if (is_wp_error($reset_key)) {
        return '';
    }

    return network_site_url(
        'wp-login.php?action=rp&key=' . $reset_key . '&login=' . rawurlencode($user->user_login),
        'login'
    );
}

function cadastrar_usuarios_em_lote_montar_placeholders_email($user, $args = []) {
    if (!($user instanceof WP_User)) {
        return [];
    }

    $nome = cadastrar_usuarios_em_lote_obter_nome_destinatario(
        $user->first_name ?: $user->display_name ?: $user->user_login
    );

    return [
        '{nome}' => $nome,
        '{email}' => $user->user_email,
        '{site_nome}' => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
        '{login_url}' => wp_login_url(),
        '{definir_senha_url}' => cadastrar_usuarios_em_lote_obter_reset_url_usuario($user),
        '{senha}' => isset($args['senha']) ? (string) $args['senha'] : '',
        '{curso_nome}' => cadastrar_usuarios_em_lote_obter_titulo_post(isset($args['curso_id']) ? (int) $args['curso_id'] : 0),
        '{grupo_nome}' => cadastrar_usuarios_em_lote_obter_titulo_post(isset($args['grupo_id']) ? (int) $args['grupo_id'] : 0),
    ];
}

function cadastrar_usuarios_em_lote_template_contem_documento_html_completo($html) {
    $html = ltrim((string) $html);

    if ($html === '') {
        return false;
    }

    return (bool) preg_match('/^(<!doctype\s+html\b|<html\b)/i', $html);
}

function cadastrar_usuarios_em_lote_montar_mensagem_email_personalizado($subject, $body_html) {
    $subject = (string) $subject;
    $body_html = (string) $body_html;

    if (cadastrar_usuarios_em_lote_template_contem_documento_html_completo($body_html)) {
        return $body_html;
    }

    $preheader = wp_trim_words(wp_strip_all_tags($body_html), 20, '...');

    return cadastrar_usuarios_em_lote_renderizar_email_html([
        'preheader' => $preheader !== '' ? $preheader : wp_strip_all_tags($subject),
        'greeting' => '',
        'title' => '',
        'custom_html' => $body_html,
        'footer' => 'Atenciosamente,',
    ]);
}

function cadastrar_usuarios_em_lote_renderizar_email_html($args) {
    $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    $preheader = isset($args['preheader']) ? trim((string) $args['preheader']) : '';
    $greeting = isset($args['greeting']) ? trim((string) $args['greeting']) : 'Olá,';
    $title = isset($args['title']) ? trim((string) $args['title']) : '';
    $paragraphs = isset($args['paragraphs']) && is_array($args['paragraphs']) ? $args['paragraphs'] : [];
    $details = isset($args['details']) && is_array($args['details']) ? $args['details'] : [];
    $button_text = isset($args['button_text']) ? trim((string) $args['button_text']) : '';
    $button_url = isset($args['button_url']) ? (string) $args['button_url'] : '';
    $note = isset($args['note']) ? trim((string) $args['note']) : '';
    $secondary_text = isset($args['secondary_text']) ? trim((string) $args['secondary_text']) : '';
    $footer = isset($args['footer']) ? trim((string) $args['footer']) : 'Atenciosamente,';
    $custom_html = isset($args['custom_html']) ? (string) $args['custom_html'] : '';

    $paragraphs_html = '';
    foreach ($paragraphs as $paragraph) {
        $paragraph = trim((string) $paragraph);
        if ($paragraph === '') {
            continue;
        }

        $paragraphs_html .= '<p style="margin:0 0 16px 0; font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:24px; color:#374151;">' . esc_html($paragraph) . '</p>';
    }

    $details_html = '';
    if (!empty($details)) {
        $details_html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse; margin:0 0 20px 0;">';

        foreach ($details as $label => $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            $details_html .= '<tr>';
            $details_html .= '<td valign="top" style="padding:10px 0; border-bottom:1px solid #e5e7eb; font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:20px; color:#6b7280; width:120px;"><strong>' . esc_html($label) . '</strong></td>';
            $details_html .= '<td valign="top" style="padding:10px 0; border-bottom:1px solid #e5e7eb; font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:20px; color:#111827;">' . esc_html($value) . '</td>';
            $details_html .= '</tr>';
        }

        $details_html .= '</table>';
    }

    $button_html = '';
    if ($button_text !== '' && $button_url !== '') {
        $button_html = '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 20px 0; border-collapse:collapse;">';
        $button_html .= '<tr>';
        $button_html .= '<td align="center" bgcolor="#2271b1" style="border-radius:4px;">';
        $button_html .= '<a href="' . esc_url($button_url) . '" target="_blank" style="display:inline-block; padding:12px 22px; font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:18px; font-weight:bold; color:#ffffff; text-decoration:none;">' . esc_html($button_text) . '</a>';
        $button_html .= '</td>';
        $button_html .= '</tr>';
        $button_html .= '</table>';
    }

    $note_html = '';
    if ($note !== '') {
        $note_html = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse; margin:0 0 20px 0;">';
        $note_html .= '<tr>';
        $note_html .= '<td style="padding:14px 16px; background-color:#f9fafb; border:1px solid #e5e7eb; font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:22px; color:#4b5563;">' . esc_html($note) . '</td>';
        $note_html .= '</tr>';
        $note_html .= '</table>';
    }

    $secondary_html = '';
    if ($secondary_text !== '' && $button_url !== '') {
        $secondary_html = '<p style="margin:0 0 8px 0; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:20px; color:#6b7280;">' . esc_html($secondary_text) . '</p>';
        $secondary_html .= '<p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:20px; color:#2271b1; word-break:break-all;">' . esc_html($button_url) . '</p>';
    }

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($title); ?></title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6;">
    <?php if ($preheader !== '') : ?>
        <div style="display:none; max-height:0; overflow:hidden; opacity:0; mso-hide:all; font-size:1px; line-height:1px; color:#ffffff;">
            <?php echo esc_html($preheader); ?>
        </div>
    <?php endif; ?>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse; width:100%; background-color:#f3f4f6; margin:0; padding:0;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse; width:100%; max-width:600px;">
                    <tr>
                        <td style="height:4px; line-height:4px; font-size:0; background-color:#2271b1;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="padding:32px 28px; background-color:#ffffff; border:1px solid #e5e7eb;">
                            <p style="margin:0 0 8px 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#2271b1; font-weight:bold; text-transform:uppercase; letter-spacing:0.5px;"><?php echo esc_html($site_name); ?></p>
                            <?php if ($title !== '') : ?>
                                <h1 style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:24px; line-height:30px; color:#111827; font-weight:bold;"><?php echo esc_html($title); ?></h1>
                            <?php endif; ?>
                            <?php if ($greeting !== '') : ?>
                                <p style="margin:0 0 16px 0; font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:24px; color:#111827;"><?php echo esc_html($greeting); ?></p>
                            <?php endif; ?>
                            <?php echo $custom_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php echo $paragraphs_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php echo $details_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php echo $button_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php echo $note_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php echo $secondary_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:22px; color:#374151;"><?php echo esc_html($footer); ?><br><?php echo esc_html($site_name); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 8px 0 8px; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#6b7280; text-align:center;">
                            Esta mensagem foi enviada automaticamente por <?php echo esc_html($site_name); ?>.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
    <?php

    return ob_get_clean();
}

function cadastrar_usuarios_em_lote_enviar_email_boas_vindas($user_id) {
    $user = get_user_by('id', $user_id);
    if (!($user instanceof WP_User)) {
        return false;
    }

    $site_name = get_bloginfo('name');
    $nome = cadastrar_usuarios_em_lote_obter_nome_destinatario($user->first_name ?: $user->display_name ?: $user->user_login);
    $subject = 'Bem-vindo(a) - ' . $site_name;
    $reset_url = cadastrar_usuarios_em_lote_obter_reset_url_usuario($user);
    if ($reset_url === '') {
        return false;
    }
    $login_url = wp_login_url();
    $message = cadastrar_usuarios_em_lote_renderizar_email_html([
        'preheader' => 'Sua conta foi criada e já está pronta para o primeiro acesso.',
        'greeting' => $nome !== '' ? 'Olá ' . $nome . ',' : 'Olá,',
        'title' => 'Sua conta foi criada',
        'paragraphs' => [
            'Sua conta foi criada com sucesso no site ' . $site_name . '.',
            'Para definir sua senha e concluir o primeiro acesso, use o botão abaixo.'
        ],
        'button_text' => 'Definir senha',
        'button_url' => $reset_url,
        'secondary_text' => 'Se o botão não funcionar, copie e cole este link no navegador:',
        'note' => 'Depois de definir a senha, acesse sua conta em ' . $login_url . '.',
        'footer' => 'Atenciosamente,'
    ]);

    return wp_mail(
        $user->user_email,
        $subject,
        $message,
        ['Content-Type: text/html; charset=UTF-8']
    );
}

function cadastrar_usuarios_em_lote_enviar_email_personalizado($user_id, $template, $args = []) {
    $user = get_user_by('id', $user_id);
    if (!($user instanceof WP_User)) {
        return false;
    }

    if (!is_array($template) || empty($template['subject']) || empty($template['body_html'])) {
        return false;
    }

    $placeholders = cadastrar_usuarios_em_lote_montar_placeholders_email($user, $args);
    $subject = cadastrar_usuarios_em_lote_replace_placeholders($template['subject'], $placeholders);
    $body_html = cadastrar_usuarios_em_lote_replace_placeholders($template['body_html'], $placeholders);
    $message = cadastrar_usuarios_em_lote_montar_mensagem_email_personalizado($subject, $body_html);

    return wp_mail(
        $user->user_email,
        $subject,
        $message,
        ['Content-Type: text/html; charset=UTF-8']
    );
}

/**
 * Classe responsável pelo processamento de usuários em lote
 * Seguindo princípio SRP (Single Responsibility Principle) do SOLID
 */
class UserBatchProcessor {
    private $resultados = [];
    private $usuarios_para_email = [];
    private $usuarios_com_senha = [];
    private $usuarios_para_email_personalizado = [];
    private $template_email_personalizado = null;
    private $escopo_email_personalizado = 'none';
    private $total_usuarios = 0;
    private $processados = 0;

    /**
     * Processa uma lista de usuários em lote
     *
     * @param string $usuarios Lista de usuários no formato email,nome
     * @param string $senha Senha padrão ou vazia para gerar senha aleatória
     * @param bool $enviar_email Se deve enviar email para os usuários
     * @param bool $enviar_senha Se deve enviar a senha por email
     * @param array $roles Funções do usuário no WordPress
     * @param int $curso_id ID do curso para matricular os usuários
     * @param int $grupo_id ID do grupo para adicionar os usuários
     * @param array $email_personalizado_config Configuração do template personalizado
     * @return array Resultados do processamento
     */
    public function processar($usuarios, $senha, $enviar_email, $enviar_senha, $roles, $curso_id, $grupo_id, $email_personalizado_config = []) {
        $this->resetar_estado();
        $this->configurar_email_personalizado($email_personalizado_config);

        $linhas = explode("\n", $usuarios);
        $this->total_usuarios = count(array_filter($linhas, 'trim'));

        $emails = [];
        foreach ($linhas as $linha) {
            $linha = trim($linha);
            if ($linha === '') {
                continue;
            }

            $dados_usuario = $this->extrair_dados_usuario_da_linha($linha);
            if (!empty($dados_usuario['email'])) {
                $emails[] = $dados_usuario['email'];
            }
        }

        $existing_users = [];
        if (!empty($emails)) {
            global $wpdb;
            $placeholders = implode(',', array_fill(0, count($emails), '%s'));
            $query = $wpdb->prepare(
                "SELECT user_email, ID FROM {$wpdb->users} WHERE user_email IN ($placeholders)",
                $emails
            );
            $results = $wpdb->get_results($query);

            foreach ($results as $user) {
                $existing_users[strtolower($user->user_email)] = (int) $user->ID;
            }
        }

        foreach ($linhas as $linha) {
            $linha = trim($linha);
            if ($linha === '') {
                continue;
            }

            $this->processados++;
            $dados_usuario = $this->extrair_dados_usuario_da_linha($linha);
            $email = $dados_usuario['email'];
            $primeiro_nome = $dados_usuario['primeiro_nome'];

            if (!$email || !is_email($email)) {
                $this->resultados[] = "Erro: email inválido na linha - $linha";
                continue;
            }

            $senha_usuario = $senha ? $senha : wp_generate_password();

            if (isset($existing_users[$email])) {
                $user_id = $existing_users[$email];
                $user = new WP_User($user_id);
                $acoes = [];

                if (!empty($roles)) {
                    $roles_atuais = $user->roles;
                    $roles_novas_adicionadas = [];

                    foreach ($roles as $role) {
                        if (!in_array($role, $roles_atuais, true)) {
                            $user->add_role($role);
                            $roles_novas_adicionadas[] = $role;
                        }
                    }

                    if (!empty($roles_novas_adicionadas)) {
                        $acoes[] = 'roles adicionadas: ' . implode(', ', $roles_novas_adicionadas);
                    }
                }

                if ($curso_id > 0 && function_exists('ld_update_course_access')) {
                    ld_update_course_access($user_id, $curso_id);
                    $acoes[] = 'matriculado no curso';
                }

                if ($grupo_id > 0 && function_exists('ld_update_group_access')) {
                    ld_update_group_access($user_id, $grupo_id);
                    $acoes[] = 'adicionado ao grupo';
                }

                if ($acoes) {
                    $this->resultados[] = "O usuário $email já existe no sistema e foi atualizado: " . implode(', ', $acoes);
                } else {
                    $this->resultados[] = "O usuário $email já existe no sistema e já possui as roles selecionadas";
                }

                $this->enfileirar_email_personalizado($user_id, 'existing', [
                    'curso_id' => $curso_id,
                    'grupo_id' => $grupo_id,
                    'senha' => '',
                ]);

                continue;
            }

            $user_id = wp_create_user($email, $senha_usuario, $email);
            if (is_wp_error($user_id)) {
                $this->resultados[] = "Erro ao processar $email: " . $user_id->get_error_message();
                continue;
            }

            $nome_partes = explode(' ', $primeiro_nome, 2);
            $first_name = $nome_partes[0];
            $last_name = isset($nome_partes[1]) ? $nome_partes[1] : '';

            wp_update_user([
                'ID' => $user_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
            ]);

            $user = new WP_User($user_id);
            if (!empty($roles)) {
                $user->set_role('');
                foreach ($roles as $role) {
                    $user->add_role($role);
                }
            }

            $mensagem = "Sucesso: $email";
            $acoes_novas = [];

            if ($curso_id > 0 && function_exists('ld_update_course_access')) {
                ld_update_course_access($user_id, $curso_id);
                $acoes_novas[] = 'matriculado no curso';
            }

            if ($grupo_id > 0 && function_exists('ld_update_group_access')) {
                ld_update_group_access($user_id, $grupo_id);
                $acoes_novas[] = 'adicionado ao grupo';
            }

            if (!empty($acoes_novas)) {
                $mensagem .= ' (' . implode(' e ', $acoes_novas) . ')';
            }

            $this->resultados[] = $mensagem;

            if ($enviar_email) {
                $this->usuarios_para_email[] = $user_id;
            }

            if ($enviar_senha) {
                $this->usuarios_com_senha[] = [
                    'user_id' => $user_id,
                    'email' => $email,
                    'nome' => $primeiro_nome,
                    'senha' => $senha_usuario,
                ];
            }

            $this->enfileirar_email_personalizado($user_id, 'new', [
                'curso_id' => $curso_id,
                'grupo_id' => $grupo_id,
                'senha' => $senha_usuario,
            ]);
        }

        $this->processar_emails_em_lote();
        $this->processar_emails_personalizados();
        $this->enviar_senhas_por_email();

        return $this->resultados;
    }

    private function resetar_estado() {
        $this->resultados = [];
        $this->usuarios_para_email = [];
        $this->usuarios_com_senha = [];
        $this->usuarios_para_email_personalizado = [];
        $this->template_email_personalizado = null;
        $this->escopo_email_personalizado = 'none';
        $this->total_usuarios = 0;
        $this->processados = 0;
    }

    private function configurar_email_personalizado($config) {
        $this->template_email_personalizado = isset($config['template']) && is_array($config['template'])
            ? $config['template']
            : null;

        $escopo = isset($config['scope']) ? sanitize_text_field((string) $config['scope']) : 'none';
        $escopos_validos = ['none', 'all', 'new'];

        if (!in_array($escopo, $escopos_validos, true) || $this->template_email_personalizado === null) {
            $escopo = 'none';
        }

        $this->escopo_email_personalizado = $escopo;
    }

    private function extrair_dados_usuario_da_linha($linha) {
        $partes = explode(',', (string) $linha);
        $email = null;
        $primeiro_nome = '';

        if (count($partes) === 1) {
            $email = strtolower(sanitize_text_field(trim($partes[0])));

            if (is_email($email)) {
                $primeiro_nome = ucfirst(explode('@', $email)[0]);
            }
        } else {
            $parte0 = sanitize_text_field(trim($partes[0]));
            $parte1 = sanitize_text_field(trim($partes[1]));

            if (is_email($parte0)) {
                $email = strtolower($parte0);
                $primeiro_nome = $parte1 ?: ucfirst(explode('@', $email)[0]);
            } elseif (is_email($parte1)) {
                $email = strtolower($parte1);
                $primeiro_nome = $parte0 ?: ucfirst(explode('@', $email)[0]);
            }
        }

        return [
            'email' => $email && is_email($email) ? $email : null,
            'primeiro_nome' => $primeiro_nome,
        ];
    }

    private function deve_enviar_email_personalizado($tipo_usuario) {
        if ($this->template_email_personalizado === null || $this->escopo_email_personalizado === 'none') {
            return false;
        }

        if ($this->escopo_email_personalizado === 'all') {
            return true;
        }

        return $this->escopo_email_personalizado === 'new' && $tipo_usuario === 'new';
    }

    private function enfileirar_email_personalizado($user_id, $tipo_usuario, $args = []) {
        if (!$this->deve_enviar_email_personalizado($tipo_usuario)) {
            return;
        }

        $this->usuarios_para_email_personalizado[] = [
            'user_id' => (int) $user_id,
            'curso_id' => isset($args['curso_id']) ? (int) $args['curso_id'] : 0,
            'grupo_id' => isset($args['grupo_id']) ? (int) $args['grupo_id'] : 0,
            'senha' => isset($args['senha']) ? (string) $args['senha'] : '',
        ];
    }
    
    /**
     * Processa os e-mails em lote de forma mais eficiente
     */
    private function processar_emails_em_lote() {
        if (empty($this->usuarios_para_email)) {
            return;
        }
        
        // Aumentar o tempo limite de execução para processar mais usuários
        $max_execution = ini_get('max_execution_time');
        if ($max_execution < 180 && $max_execution != 0) {
            @set_time_limit(180);
        }
        
        // Processa os e-mails em lotes menores para evitar sobrecarga
        $batch_size = 5;
        $batches = array_chunk($this->usuarios_para_email, $batch_size);
        $total_batches = count($batches);

        foreach ($batches as $batch_index => $batch) {
            foreach ($batch as $user_id) {
                wp_new_user_notification($user_id, null, 'admin');
                cadastrar_usuarios_em_lote_enviar_email_boas_vindas($user_id);
            }

            if ($batch_index < ($total_batches - 1)) {
                usleep(250000);
            }
        }
    }

    private function processar_emails_personalizados() {
        if (empty($this->usuarios_para_email_personalizado) || $this->template_email_personalizado === null) {
            return;
        }

        $batch_size = 5;
        $batches = array_chunk($this->usuarios_para_email_personalizado, $batch_size);
        $total_batches = count($batches);

        foreach ($batches as $batch_index => $batch) {
            foreach ($batch as $usuario_email) {
                cadastrar_usuarios_em_lote_enviar_email_personalizado(
                    $usuario_email['user_id'],
                    $this->template_email_personalizado,
                    [
                        'curso_id' => $usuario_email['curso_id'],
                        'grupo_id' => $usuario_email['grupo_id'],
                        'senha' => $usuario_email['senha'],
                    ]
                );
            }

            if ($batch_index < ($total_batches - 1)) {
                usleep(250000);
            }
        }
    }
    
    /**
     * Envia as senhas por email para os usuários
     */
    private function enviar_senhas_por_email() {
        if (empty($this->usuarios_com_senha)) {
            return;
        }
        
        foreach ($this->usuarios_com_senha as $usuario_info) {
            $to = $usuario_info['email'];
            $subject = 'Seus dados de acesso - ' . get_bloginfo('name');

            $nome = cadastrar_usuarios_em_lote_obter_nome_destinatario($usuario_info['nome']);
            $message = cadastrar_usuarios_em_lote_renderizar_email_html([
                'preheader' => 'Seus dados de acesso ao site já estão disponíveis.',
                'greeting' => $nome !== '' ? 'Olá ' . $nome . ',' : 'Olá,',
                'title' => 'Seus dados de acesso',
                'paragraphs' => [
                    'Sua conta foi criada com sucesso.',
                    'Abaixo estão os dados para acessar o site.'
                ],
                'details' => [
                    'E-mail' => $usuario_info['email'],
                    'Senha' => $usuario_info['senha']
                ],
                'button_text' => 'Acessar o site',
                'button_url' => wp_login_url(),
                'secondary_text' => 'Se o botão não funcionar, copie e cole este link no navegador:',
                'note' => 'Recomendamos que você altere sua senha após o primeiro acesso.',
                'footer' => 'Atenciosamente,'
            ]);

            $headers = ['Content-Type: text/html; charset=UTF-8'];
            
            wp_mail($to, $subject, $message, $headers);
            
            usleep(250000);
        }
    }
    
    /**
     * Retorna o progresso atual do processamento
     */
    public function get_progresso() {
        if ($this->total_usuarios == 0) return 0;
        return round(($this->processados / $this->total_usuarios) * 100);
    }
}

function cadastrar_usuarios_em_lote_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Você não tem permissão para acessar esta página.', 'cadastrar-usuarios-em-lote'));
    }

    $form_values = [
        'usuarios' => '',
        'senha' => '',
        'enviar_email' => false,
        'enviar_senha' => false,
        'roles' => ['subscriber'],
        'curso_id' => 0,
        'grupo_id' => 0,
        'email_template_id' => '',
        'email_template_scope' => 'none',
    ];

    $email_templates = cadastrar_usuarios_em_lote_get_email_templates();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_admin_referer('cadastrar_usuarios_em_lote');

        $form_values['usuarios'] = isset($_POST['usuarios']) ? trim(sanitize_textarea_field($_POST['usuarios'])) : '';
        $form_values['senha'] = isset($_POST['senha']) ? sanitize_text_field((string) $_POST['senha']) : '';
        $form_values['enviar_email'] = isset($_POST['enviar_email']);
        $form_values['enviar_senha'] = isset($_POST['enviar_senha']);
        $form_values['roles'] = isset($_POST['roles']) && is_array($_POST['roles'])
            ? array_map('sanitize_text_field', $_POST['roles'])
            : ['subscriber'];
        $form_values['curso_id'] = isset($_POST['curso_id']) ? (int) $_POST['curso_id'] : 0;
        $form_values['grupo_id'] = isset($_POST['grupo_id']) ? (int) $_POST['grupo_id'] : 0;
        $form_values['email_template_id'] = isset($_POST['email_template_id']) ? sanitize_text_field((string) $_POST['email_template_id']) : '';
        $form_values['email_template_scope'] = isset($_POST['email_template_scope']) ? sanitize_text_field((string) $_POST['email_template_scope']) : 'none';

        $email_personalizado_config = [
            'template' => null,
            'scope' => 'none',
        ];
        $resultados_iniciais = [];
        $escopos_validos = ['none', 'all', 'new'];

        if (!in_array($form_values['email_template_scope'], $escopos_validos, true)) {
            $form_values['email_template_scope'] = 'none';
        }

        if ($form_values['email_template_scope'] !== 'none') {
            if ($form_values['email_template_id'] === '') {
                $resultados_iniciais[] = 'Aviso: selecione uma mensagem de e-mail ativa para usar o envio personalizado.';
            } else {
                $email_template = cadastrar_usuarios_em_lote_get_email_template($form_values['email_template_id']);

                if (!is_array($email_template) || $email_template['status'] !== 'active') {
                    $resultados_iniciais[] = 'Aviso: a mensagem personalizada selecionada não está disponível para envio.';
                } else {
                    $email_personalizado_config = [
                        'template' => $email_template,
                        'scope' => $form_values['email_template_scope'],
                    ];
                }
            }
        }

        $processador = new UserBatchProcessor();
        $resultados = array_merge(
            $resultados_iniciais,
            $processador->processar(
                $form_values['usuarios'],
                $form_values['senha'],
                $form_values['enviar_email'],
                $form_values['enviar_senha'],
                $form_values['roles'],
                $form_values['curso_id'],
                $form_values['grupo_id'],
                $email_personalizado_config
            )
        );

        echo '<div id="message" class="updated notice is-dismissible"><p>';
        echo implode('<br>', array_map('esc_html', $resultados));
        echo '</p></div>';
    }

    echo '<form id="cadastrar-usuarios-form" method="post">';
    wp_nonce_field('cadastrar_usuarios_em_lote');

    echo '<textarea name="usuarios" placeholder="email,nome ou nome,email ou apenas email">' . esc_textarea($form_values['usuarios']) . '</textarea>';
    echo '<div class="formato-instrucao" style="margin-top: 2px; margin-bottom: 5px; color: #666;">';
    echo '<strong>Formato:</strong> Insira um usuário por linha. Aceita:<br>';
    echo '&bull; <code>email,nome</code> ou <code>nome,email</code> (detecta automaticamente)<br>';
    echo '&bull; <code>email</code> (nome será extraído do email)<br>';
    echo '<em>Exemplo:</em><br>';
    echo '<code>usuario1@exemplo.com,João<br>Maria,usuario2@exemplo.com<br>usuario3@exemplo.com</code>';
    echo '</div>';

    echo '<p>Senha padrão, se ficar em branco uma senha única e aleatória será gerada.</p>';
    echo '<input type="password" name="senha" placeholder="Senha padrão (opcional)" value="' . esc_attr($form_values['senha']) . '">';

    echo '<p>Selecione a(s) função(ões) no site:</p>';
    echo '<div id="userRolesCheckboxes" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">';
    global $wp_roles;
    foreach ($wp_roles->roles as $key => $value) {
        echo '<label style="display: block; margin-bottom: 5px; cursor: pointer;">';
        echo '<input type="checkbox" name="roles[]" value="' . esc_attr($key) . '"' . checked(in_array($key, $form_values['roles'], true), true, false) . '> ';
        echo esc_html($value['name']);
        echo '</label>';
    }
    echo '</div>';
    echo '<small style="color: #666;">Selecione uma ou mais funções. O usuário terá todas as permissões das funções selecionadas.</small>';

    if (function_exists('ld_course_list')) {
        $args = [
            'post_type' => 'sfwd-courses',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ];
        $cursos = get_posts($args);

        if (!empty($cursos)) {
            echo '<p>Selecione o curso para matricular os usuários:</p>';
            echo '<select name="curso_id" id="courseDropdown">';
            echo '<option value="0">Nenhum curso</option>';
            foreach ($cursos as $curso) {
                echo '<option value="' . esc_attr($curso->ID) . '"' . selected($form_values['curso_id'], $curso->ID, false) . '>' . esc_html($curso->post_title) . '</option>';
            }
            echo '</select>';
        }
    }

    if (post_type_exists('groups')) {
        $args = [
            'post_type' => 'groups',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ];
        $grupos = get_posts($args);

        if (!empty($grupos)) {
            echo '<p>Selecione o grupo para adicionar os usuários:</p>';
            echo '<select name="grupo_id" id="groupDropdown">';
            echo '<option value="0">Nenhum grupo</option>';
            foreach ($grupos as $grupo) {
                echo '<option value="' . esc_attr($grupo->ID) . '"' . selected($form_values['grupo_id'], $grupo->ID, false) . '>' . esc_html($grupo->post_title) . '</option>';
            }
            echo '</select>';
        }
    }

    echo '<div class="cul-email-selection-box">';
    echo '<h3>Mensagem personalizada</h3>';

    if (empty($email_templates)) {
        echo '<p>Nenhuma mensagem cadastrada. <a href="' . esc_url(admin_url('admin.php?page=cadastrar-usuarios-em-lote-mensagens')) . '">Cadastre uma mensagem aqui</a>.</p>';
    } else {
        echo '<p>Selecione uma mensagem para envio opcional durante o processamento do lote. Mensagens inativas aparecem na lista, mas não podem ser usadas.</p>';
        echo '<select name="email_template_id" id="cul-email-template-select">';
        echo '<option value="">Nenhuma mensagem selecionada</option>';

        foreach ($email_templates as $template) {
            $label = $template['name'];
            if ($template['status'] !== 'active') {
                $label .= ' (Inativa)';
            }

            echo '<option value="' . esc_attr($template['id']) . '"' . selected($form_values['email_template_id'], $template['id'], false) . disabled($template['status'] !== 'active', true, false) . '>' . esc_html($label) . '</option>';
        }

        echo '</select>';
        echo '<p class="description">Gerencie as mensagens em <a href="' . esc_url(admin_url('admin.php?page=cadastrar-usuarios-em-lote-mensagens')) . '">Mensagens de E-mail</a>.</p>';
    }

    echo '<select name="email_template_scope" id="cul-email-template-scope">';
    echo '<option value="none"' . selected($form_values['email_template_scope'], 'none', false) . '>Não enviar mensagem personalizada</option>';
    echo '<option value="all"' . selected($form_values['email_template_scope'], 'all', false) . '>Enviar mensagem personalizada para todos os usuários processados</option>';
    echo '<option value="new"' . selected($form_values['email_template_scope'], 'new', false) . '>Enviar mensagem personalizada apenas para novos cadastros</option>';
    echo '</select>';
    echo '<p class="description">A mensagem personalizada é independente dos e-mails padrão do plugin.</p>';
    echo '</div>';

    echo '<br>';
    echo '<div style="margin-top: 20px;">';
    echo '<label style="display: block; margin-bottom: 10px;"><input type="checkbox" name="enviar_email"' . checked($form_values['enviar_email'], true, false) . '> Enviar e-mail padrão de boas-vindas do plugin (com link para redefinição de senha)</label>';
    echo '<label style="display: block; margin-bottom: 10px;"><input type="checkbox" name="enviar_senha"' . checked($form_values['enviar_senha'], true, false) . '> Enviar senha por e-mail com o modelo padrão do plugin</label>';
    echo '</div>';

    echo '<input type="submit" value="Cadastrar Usuários" id="submit-button">';
    echo '<div id="progress-container" style="display:none; margin-top: 20px;">';
    echo '<p>Processando usuários... Por favor, não feche esta página.</p>';
    echo '<progress id="progress-bar" max="100" value="0" style="width: 100%;"></progress>';
    echo '<p id="progress-status">0%</p>';
    echo '</div>';
    echo '</form>';

    echo '<div style="text-align: center; margin-top: 30px; color: #666; font-size: 12px;">';
    echo 'Versão: ' . esc_html(get_plugin_version());
    echo '</div>';

    echo '<script>
    jQuery(document).ready(function($) {
        $("#cadastrar-usuarios-form").on("submit", function() {
            $("#submit-button").prop("disabled", true);
            $("#progress-container").show();

            var lines = $("textarea[name=\'usuarios\']").val().split("\\n").filter(function(line) {
                return line.trim().length > 0;
            }).length;

            if (lines > 0) {
                var progress = 0;
                var interval = setInterval(function() {
                    if (progress < 95) {
                        progress += (95 - progress) / 10;
                        $("#progress-bar").val(progress);
                        $("#progress-status").text(Math.round(progress) + "%");
                    }
                }, 1000);

                $(this).data("progressInterval", interval);
            }

            return true;
        });
    });
    </script>';
}
