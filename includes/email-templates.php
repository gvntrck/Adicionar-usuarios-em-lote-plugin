<?php
if (!defined('ABSPATH')) {
    exit;
}

function cadastrar_usuarios_em_lote_get_email_templates_option_name() {
    return 'cadastrar_usuarios_em_lote_email_templates';
}

function cadastrar_usuarios_em_lote_get_email_templates() {
    $templates = get_option(cadastrar_usuarios_em_lote_get_email_templates_option_name(), []);

    if (!is_array($templates)) {
        return [];
    }

    uasort($templates, static function ($template_a, $template_b) {
        $updated_a = isset($template_a['updated_at']) ? strtotime((string) $template_a['updated_at']) : 0;
        $updated_b = isset($template_b['updated_at']) ? strtotime((string) $template_b['updated_at']) : 0;

        return $updated_b <=> $updated_a;
    });

    return $templates;
}

function cadastrar_usuarios_em_lote_get_email_template($template_id) {
    $template_id = sanitize_text_field((string) $template_id);
    if ($template_id === '') {
        return null;
    }

    $templates = cadastrar_usuarios_em_lote_get_email_templates();

    return isset($templates[$template_id]) ? $templates[$template_id] : null;
}

function cadastrar_usuarios_em_lote_get_active_email_templates() {
    $templates = cadastrar_usuarios_em_lote_get_email_templates();

    return array_filter($templates, static function ($template) {
        return isset($template['status']) && $template['status'] === 'active';
    });
}

function cadastrar_usuarios_em_lote_save_email_templates($templates) {
    update_option(cadastrar_usuarios_em_lote_get_email_templates_option_name(), $templates, false);
}

function cadastrar_usuarios_em_lote_get_email_template_status_options() {
    return [
        'active' => 'Ativa',
        'inactive' => 'Inativa',
    ];
}

function cadastrar_usuarios_em_lote_get_email_template_status_label($status) {
    $options = cadastrar_usuarios_em_lote_get_email_template_status_options();

    return isset($options[$status]) ? $options[$status] : 'Desconhecido';
}

function cadastrar_usuarios_em_lote_get_email_placeholders_help() {
    return [
        '{nome}' => 'Nome do usuário.',
        '{email}' => 'E-mail do usuário.',
        '{site_nome}' => 'Nome do site.',
        '{login_url}' => 'URL da tela de login.',
        '{definir_senha_url}' => 'URL para criação/redefinição de senha.',
        '{senha}' => 'Senha gerada no lote. Fica vazia para usuários já existentes.',
        '{curso_nome}' => 'Nome do curso selecionado no lote.',
        '{grupo_nome}' => 'Nome do grupo selecionado no lote.',
    ];
}

function cadastrar_usuarios_em_lote_sanitize_email_template_body($body_html) {
    $body_html = (string) $body_html;

    if (current_user_can('unfiltered_html')) {
        return $body_html;
    }

    $allowed_html = wp_kses_allowed_html('post');
    $table_tags = [
        'table',
        'thead',
        'tbody',
        'tfoot',
        'tr',
        'td',
        'th',
        'colgroup',
        'col',
    ];

    foreach ($table_tags as $tag) {
        if (!isset($allowed_html[$tag])) {
            $allowed_html[$tag] = [];
        }

        $allowed_html[$tag]['style'] = true;
        $allowed_html[$tag]['class'] = true;
        $allowed_html[$tag]['align'] = true;
        $allowed_html[$tag]['width'] = true;
        $allowed_html[$tag]['height'] = true;
        $allowed_html[$tag]['border'] = true;
        $allowed_html[$tag]['cellpadding'] = true;
        $allowed_html[$tag]['cellspacing'] = true;
        $allowed_html[$tag]['valign'] = true;
    }

    $generic_tags = ['div', 'span', 'p', 'a', 'img', 'h1', 'h2', 'h3', 'h4'];
    foreach ($generic_tags as $tag) {
        if (!isset($allowed_html[$tag])) {
            $allowed_html[$tag] = [];
        }

        $allowed_html[$tag]['style'] = true;
        $allowed_html[$tag]['class'] = true;
    }

    if (!isset($allowed_html['a'])) {
        $allowed_html['a'] = [];
    }
    $allowed_html['a']['target'] = true;
    $allowed_html['a']['rel'] = true;
    $allowed_html['a']['href'] = true;

    if (!isset($allowed_html['img'])) {
        $allowed_html['img'] = [];
    }
    $allowed_html['img']['src'] = true;
    $allowed_html['img']['alt'] = true;
    $allowed_html['img']['width'] = true;
    $allowed_html['img']['height'] = true;

    return wp_kses($body_html, $allowed_html);
}

function cadastrar_usuarios_em_lote_normalize_email_template_input($input) {
    $name = isset($input['name']) ? sanitize_text_field((string) $input['name']) : '';
    $subject = isset($input['subject']) ? sanitize_text_field((string) $input['subject']) : '';
    $body_html = isset($input['body_html']) ? wp_unslash((string) $input['body_html']) : '';
    $status = isset($input['status']) ? sanitize_text_field((string) $input['status']) : 'active';

    $status_options = cadastrar_usuarios_em_lote_get_email_template_status_options();
    if (!isset($status_options[$status])) {
        $status = 'active';
    }

    return [
        'name' => $name,
        'subject' => $subject,
        'body_html' => $body_html,
        'status' => $status,
    ];
}

function cadastrar_usuarios_em_lote_validate_email_template_input($input) {
    if ($input['name'] === '') {
        return new WP_Error('cul_email_template_name_required', 'Informe um nome interno para a mensagem.');
    }

    if ($input['subject'] === '') {
        return new WP_Error('cul_email_template_subject_required', 'Informe o assunto do e-mail.');
    }

    if (trim($input['body_html']) === '') {
        return new WP_Error('cul_email_template_body_required', 'Informe o HTML da mensagem.');
    }

    return true;
}

function cadastrar_usuarios_em_lote_build_email_template_record($template_id, $input, $existing_template = null) {
    $now = current_time('mysql');

    return [
        'id' => $template_id,
        'name' => $input['name'],
        'subject' => $input['subject'],
        'body_html' => cadastrar_usuarios_em_lote_sanitize_email_template_body($input['body_html']),
        'status' => $input['status'],
        'created_at' => isset($existing_template['created_at']) ? $existing_template['created_at'] : $now,
        'updated_at' => $now,
    ];
}

function cadastrar_usuarios_em_lote_get_default_email_template_form_data() {
    return [
        'id' => '',
        'name' => '',
        'subject' => '',
        'body_html' => '',
        'status' => 'active',
        'created_at' => '',
        'updated_at' => '',
    ];
}

function cadastrar_usuarios_em_lote_send_email_template_test($template_input, $test_email) {
    $test_email = sanitize_email((string) $test_email);
    if (!is_email($test_email)) {
        return new WP_Error('cul_invalid_test_email', 'Informe um e-mail de teste válido.');
    }

    $validation = cadastrar_usuarios_em_lote_validate_email_template_input($template_input);
    if (is_wp_error($validation)) {
        return $validation;
    }

    $subject = cadastrar_usuarios_em_lote_replace_placeholders($template_input['subject'], [
        '{nome}' => 'Aluno Exemplo',
        '{email}' => $test_email,
        '{site_nome}' => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
        '{login_url}' => wp_login_url(),
        '{definir_senha_url}' => wp_lostpassword_url(),
        '{senha}' => 'SenhaExemplo123',
        '{curso_nome}' => 'Curso de Exemplo',
        '{grupo_nome}' => 'Grupo de Exemplo',
    ]);

    $body_html = cadastrar_usuarios_em_lote_replace_placeholders($template_input['body_html'], [
        '{nome}' => 'Aluno Exemplo',
        '{email}' => $test_email,
        '{site_nome}' => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
        '{login_url}' => wp_login_url(),
        '{definir_senha_url}' => wp_lostpassword_url(),
        '{senha}' => 'SenhaExemplo123',
        '{curso_nome}' => 'Curso de Exemplo',
        '{grupo_nome}' => 'Grupo de Exemplo',
    ]);

    $message = cadastrar_usuarios_em_lote_montar_mensagem_email_personalizado($subject, $body_html);

    $sent = wp_mail(
        $test_email,
        $subject,
        $message,
        ['Content-Type: text/html; charset=UTF-8']
    );

    if (!$sent) {
        return new WP_Error('cul_email_test_failed', 'Não foi possível enviar o e-mail de teste.');
    }

    return true;
}

function cadastrar_usuarios_em_lote_email_templates_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Você não tem permissão para acessar esta página.', 'cadastrar-usuarios-em-lote'));
    }

    $notices = [];
    $form_data = cadastrar_usuarios_em_lote_get_default_email_template_form_data();
    $editing_template_id = isset($_GET['template_id']) ? sanitize_text_field((string) $_GET['template_id']) : '';

    if ($editing_template_id !== '') {
        $editing_template = cadastrar_usuarios_em_lote_get_email_template($editing_template_id);
        if (is_array($editing_template)) {
            $form_data = array_merge($form_data, $editing_template);
        } else {
            $notices[] = [
                'type' => 'error',
                'message' => 'A mensagem selecionada para edição não foi encontrada.',
            ];
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cul_email_template_action'])) {
        $action = sanitize_text_field((string) $_POST['cul_email_template_action']);

        if ($action === 'save') {
            check_admin_referer('cul_save_email_template', 'cul_save_email_template_nonce');

            $template_input = cadastrar_usuarios_em_lote_normalize_email_template_input([
                'name' => isset($_POST['template_name']) ? $_POST['template_name'] : '',
                'subject' => isset($_POST['template_subject']) ? $_POST['template_subject'] : '',
                'body_html' => isset($_POST['template_body']) ? $_POST['template_body'] : '',
                'status' => isset($_POST['template_status']) ? $_POST['template_status'] : 'active',
            ]);

            $form_data = array_merge($form_data, $template_input);
            $template_id = isset($_POST['template_id']) ? sanitize_text_field((string) $_POST['template_id']) : '';
            $existing_template = $template_id !== '' ? cadastrar_usuarios_em_lote_get_email_template($template_id) : null;

            $validation = cadastrar_usuarios_em_lote_validate_email_template_input($template_input);
            if (is_wp_error($validation)) {
                $notices[] = [
                    'type' => 'error',
                    'message' => $validation->get_error_message(),
                ];
            } else {
                if ($template_id === '') {
                    $template_id = wp_generate_uuid4();
                }

                $templates = cadastrar_usuarios_em_lote_get_email_templates();
                $templates[$template_id] = cadastrar_usuarios_em_lote_build_email_template_record($template_id, $template_input, $existing_template);
                cadastrar_usuarios_em_lote_save_email_templates($templates);

                $form_data = array_merge($form_data, $templates[$template_id]);
                $editing_template_id = $template_id;
                $notices[] = [
                    'type' => 'success',
                    'message' => $existing_template ? 'Mensagem de e-mail atualizada com sucesso.' : 'Mensagem de e-mail criada com sucesso.',
                ];
            }
        }

        if ($action === 'send_test') {
            check_admin_referer('cul_send_test_email', 'cul_send_test_email_nonce');

            $template_input = cadastrar_usuarios_em_lote_normalize_email_template_input([
                'name' => isset($_POST['template_name']) ? $_POST['template_name'] : '',
                'subject' => isset($_POST['template_subject']) ? $_POST['template_subject'] : '',
                'body_html' => isset($_POST['template_body']) ? $_POST['template_body'] : '',
                'status' => isset($_POST['template_status']) ? $_POST['template_status'] : 'active',
            ]);

            $form_data = array_merge($form_data, $template_input);
            $test_email = isset($_POST['test_email']) ? $_POST['test_email'] : '';
            $send_test = cadastrar_usuarios_em_lote_send_email_template_test($template_input, $test_email);

            $notices[] = [
                'type' => is_wp_error($send_test) ? 'error' : 'success',
                'message' => is_wp_error($send_test)
                    ? $send_test->get_error_message()
                    : 'E-mail de teste enviado com sucesso.',
            ];
        }

        if ($action === 'delete') {
            $template_id = isset($_POST['template_id']) ? sanitize_text_field((string) $_POST['template_id']) : '';
            check_admin_referer('cul_delete_email_template_' . $template_id);

            $templates = cadastrar_usuarios_em_lote_get_email_templates();
            if (isset($templates[$template_id])) {
                unset($templates[$template_id]);
                cadastrar_usuarios_em_lote_save_email_templates($templates);

                if ($editing_template_id === $template_id) {
                    $editing_template_id = '';
                    $form_data = cadastrar_usuarios_em_lote_get_default_email_template_form_data();
                }

                $notices[] = [
                    'type' => 'success',
                    'message' => 'Mensagem de e-mail excluída com sucesso.',
                ];
            } else {
                $notices[] = [
                    'type' => 'error',
                    'message' => 'A mensagem de e-mail informada não foi encontrada.',
                ];
            }
        }
    }

    $templates = cadastrar_usuarios_em_lote_get_email_templates();
    $status_options = cadastrar_usuarios_em_lote_get_email_template_status_options();
    $placeholders = cadastrar_usuarios_em_lote_get_email_placeholders_help();
    $form_title = $editing_template_id !== '' ? 'Editar mensagem de e-mail' : 'Nova mensagem de e-mail';

    echo '<div class="wrap cul-email-templates-page">';
    echo '<h1>Mensagens de E-mail</h1>';
    echo '<p>Cadastre modelos reutilizáveis para usar no cadastro em massa. Se você colar um HTML completo com <code>&lt;!DOCTYPE html&gt;</code> ou <code>&lt;html&gt;</code>, ele será enviado como está. Se informar apenas um trecho HTML, ele será inserido no layout padrão do plugin.</p>';

    foreach ($notices as $notice) {
        $notice_class = $notice['type'] === 'success' ? 'notice notice-success' : 'notice notice-error';
        echo '<div class="' . esc_attr($notice_class) . ' is-dismissible"><p>' . esc_html($notice['message']) . '</p></div>';
    }

    echo '<div class="cul-admin-grid">';
    echo '<div class="cul-admin-card cul-admin-card-form">';
    echo '<h2>' . esc_html($form_title) . '</h2>';
    echo '<form method="post">';
    echo '<input type="hidden" name="template_id" value="' . esc_attr($form_data['id']) . '">';
    wp_nonce_field('cul_save_email_template', 'cul_save_email_template_nonce');
    wp_nonce_field('cul_send_test_email', 'cul_send_test_email_nonce');
    echo '<table class="form-table" role="presentation">';
    echo '<tbody>';
    echo '<tr>';
    echo '<th scope="row"><label for="cul-template-name">Nome interno</label></th>';
    echo '<td><input type="text" id="cul-template-name" name="template_name" class="regular-text" value="' . esc_attr($form_data['name']) . '" placeholder="Ex.: Boas-vindas Curso de Excel"></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="cul-template-subject">Assunto</label></th>';
    echo '<td><input type="text" id="cul-template-subject" name="template_subject" class="regular-text" value="' . esc_attr($form_data['subject']) . '" placeholder="Ex.: Seu acesso ao curso foi liberado"></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="cul-template-status">Status</label></th>';
    echo '<td><select id="cul-template-status" name="template_status">';

    foreach ($status_options as $status_key => $status_label) {
        echo '<option value="' . esc_attr($status_key) . '"' . selected($form_data['status'], $status_key, false) . '>' . esc_html($status_label) . '</option>';
    }

    echo '</select></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="cul-email-template-body">Corpo HTML</label></th>';
    echo '<td>';
    echo '<textarea id="cul-email-template-body" name="template_body" rows="18" class="large-text code">' . esc_textarea($form_data['body_html']) . '</textarea>';
    echo '<p class="description">Use HTML válido. HTML completo será enviado diretamente; HTML parcial será inserido dentro do layout padrão do plugin.</p>';
    echo '</td>';
    echo '</tr>';
    echo '</tbody>';
    echo '</table>';

    echo '<h3>Enviar teste</h3>';
    echo '<p><input type="email" name="test_email" class="regular-text" placeholder="seuemail@dominio.com"></p>';
    echo '<p class="description">Os placeholders serão preenchidos com valores de exemplo.</p>';
    echo '<p class="submit">';
    echo '<button type="submit" name="cul_email_template_action" value="save" class="button button-primary">Salvar mensagem</button> ';
    echo '<button type="button" id="cul-open-template-preview" class="button">Ver prévia</button> ';
    echo '<button type="submit" name="cul_email_template_action" value="send_test" class="button">Enviar e-mail de teste</button> ';
    if ($editing_template_id !== '') {
        echo '<a href="' . esc_url(admin_url('admin.php?page=cadastrar-usuarios-em-lote-mensagens')) . '" class="button">Nova mensagem</a>';
    }
    echo '</p>';
    echo '<p class="description">A prévia é estática e mostra exatamente o HTML digitado no editor, sem substituir placeholders.</p>';
    echo '</form>';
    echo '<div id="cul-template-preview-modal" class="cul-preview-modal" hidden>';
    echo '<div class="cul-preview-modal__backdrop" data-cul-modal-close="true"></div>';
    echo '<div class="cul-preview-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="cul-template-preview-title">';
    echo '<div class="cul-preview-modal__header">';
    echo '<h3 id="cul-template-preview-title">Prévia da mensagem</h3>';
    echo '<button type="button" class="button-link cul-preview-modal__close" data-cul-modal-close="true" aria-label="Fechar prévia">&times;</button>';
    echo '</div>';
    echo '<p class="cul-preview-modal__subject-label">Assunto</p>';
    echo '<p id="cul-template-preview-subject" class="cul-preview-modal__subject">(Sem assunto)</p>';
    echo '<iframe id="cul-template-preview-frame" class="cul-preview-modal__frame" title="Prévia estática da mensagem"></iframe>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="cul-admin-card cul-admin-card-list">';
    echo '<h2>Mensagens cadastradas</h2>';

    if (empty($templates)) {
        echo '<p>Nenhuma mensagem cadastrada até o momento.</p>';
    } else {
        echo '<table class="widefat striped cul-email-template-table">';
        echo '<thead><tr><th>Nome</th><th>Assunto</th><th>Status</th><th>Atualizada em</th><th>Ações</th></tr></thead>';
        echo '<tbody>';

        foreach ($templates as $template) {
            $edit_url = admin_url('admin.php?page=cadastrar-usuarios-em-lote-mensagens&template_id=' . rawurlencode($template['id']));

            echo '<tr>';
            echo '<td>' . esc_html($template['name']) . '</td>';
            echo '<td>' . esc_html($template['subject']) . '</td>';
            echo '<td>' . esc_html(cadastrar_usuarios_em_lote_get_email_template_status_label($template['status'])) . '</td>';
            echo '<td>' . esc_html(mysql2date('d/m/Y H:i', $template['updated_at'])) . '</td>';
            echo '<td class="cul-email-template-actions">';
            echo '<a href="' . esc_url($edit_url) . '" class="button button-small">Editar</a>';
            echo '<form method="post">';
            echo '<input type="hidden" name="cul_email_template_action" value="delete">';
            echo '<input type="hidden" name="template_id" value="' . esc_attr($template['id']) . '">';
            wp_nonce_field('cul_delete_email_template_' . $template['id']);
            echo '<button type="submit" class="button button-small cul-button-danger" onclick="return confirm(\'Tem certeza que deseja excluir esta mensagem?\');">Excluir</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    }

    echo '<hr>';
    echo '<h3>Placeholders disponíveis</h3>';
    echo '<table class="widefat striped cul-email-placeholders-table">';
    echo '<thead><tr><th>Placeholder</th><th>Descrição</th></tr></thead>';
    echo '<tbody>';

    foreach ($placeholders as $placeholder => $description) {
        echo '<tr>';
        echo '<td><code>' . esc_html($placeholder) . '</code></td>';
        echo '<td>' . esc_html($description) . '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}
