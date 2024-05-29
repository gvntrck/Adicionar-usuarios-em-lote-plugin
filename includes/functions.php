<?php
function cadastrar_usuarios_em_lote_page() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_admin_referer('cadastrar_usuarios_em_lote');

        $usuarios = sanitize_textarea_field($_POST['usuarios']);
        $usuarios = trim($usuarios);  // Remover espaços em branco e linhas extras
        $senha = sanitize_text_field($_POST['senha']);
        $enviar_email = isset($_POST['enviar_email']);
        $role = sanitize_text_field($_POST['role']);  // Função do usuário

        $linhas = explode("\n", $usuarios);
        $resultados = [];

        foreach ($linhas as $linha) {
            $linha = trim($linha);  // Remover espaços em branco
            if (empty($linha)) {
                continue; // Ignora linhas vazias
            }
            $partes = explode(',', $linha);
            if (count($partes) < 2) {
                $resultados[] = "Erro: linha inválida - $linha";
                continue;
            }

            $email = sanitize_email($partes[0]);
            $primeiro_nome = sanitize_text_field($partes[1]);

            if (!is_email($email)) {
                $resultados[] = "Erro: email inválido - $email";
                continue;
            }

            $senha_usuario = $senha ? $senha : wp_generate_password();

            $user_id = wp_create_user($email, $senha_usuario, $email);
            if (is_wp_error($user_id)) {
                $resultados[] = "Erro: $email - " . $user_id->get_error_message();
                continue;
            }

            // Atualizar usuário com a função e o primeiro nome
            wp_update_user([
                'ID' => $user_id,
                'first_name' => $primeiro_nome,
                'role' => $role
            ]);

            if ($enviar_email) {
                wp_new_user_notification($user_id, null, 'both');
            }

            $resultados[] = "Sucesso: $email";
        }

        echo '<div id="message" class="updated notice is-dismissible"><p>';
        echo implode('<br>', array_map('esc_html', $resultados));
        echo '</p></div>';
    }

    // Campos para adicionar email e inserir a senha padrão, se houver
    echo '<form id="cadastrar-usuarios-form" method="post">';
    wp_nonce_field('cadastrar_usuarios_em_lote');
    echo '<textarea name="usuarios" placeholder="email,nome"></textarea>';
    echo '<p>Senha padrão, se ficar em branco uma senha única e aleatória será gerada.</p>';
    echo '<input type="password" name="senha" placeholder="Senha padrão (opcional)">';

    // Dropdown para função do usuário
    echo '<p>Selecione a função no site:</p>';
    echo '<select name="role" id="userRoleDropdown">';
    global $wp_roles;
    foreach ($wp_roles->roles as $key => $value) {
        echo '<option value="' . esc_attr($key) . '"' . selected($key, 'subscriber', false) . '>' . esc_html($value['name']) . '</option>';
    }
    echo '</select>';

    echo '<br>';
    echo '<label><input type="checkbox" name="enviar_email"> Enviar e-mail para novos usuários (com link para redefinição)</label>';
    echo '<input type="submit" value="Cadastrar Usuários">';
    echo '<progress id="progress-bar" max="100" value="0"></progress>';
    echo '</form>';
}
