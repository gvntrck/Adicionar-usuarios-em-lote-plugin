<?php
function cadastrar_usuarios_em_lote_page() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_admin_referer('cadastrar_usuarios_em_lote');

        $usuarios = sanitize_textarea_field($_POST['usuarios']);
        $usuarios = trim($usuarios);  // Remover espaços em branco e linhas extras
        $senha = sanitize_text_field($_POST['senha']);
        $enviar_email = isset($_POST['enviar_email']);

        $linhas = explode("\n", $usuarios);
        $resultados = [];

        foreach ($linhas as $linha) {
            $linha = trim($linha);  // Remover espaços em branco
            list($email, $primeiro_nome) = explode(',', $linha);
            $senha_usuario = $senha ? $senha : wp_generate_password();

            $user_id = wp_create_user($email, $senha_usuario, $email);
            if (is_wp_error($user_id)) {
                $resultados[] = "Erro: $email - " . $user_id->get_error_message();
                continue;
            }

            wp_update_user([
                'ID' => $user_id,
                'first_name' => $primeiro_nome
            ]);

            if ($enviar_email) {
                wp_new_user_notification($user_id, null, 'both');
            }

            $resultados[] = "Sucesso: $email";
        }

        echo '<div id="message" class="updated notice is-dismissible"><p>';
        echo implode('<br>', $resultados);
        echo '</p></div>';
    }


// Formulário HTML
echo '<form id="cadastrar-usuarios-form" method="post">';
wp_nonce_field('cadastrar_usuarios_em_lote');
echo '<textarea name="usuarios" placeholder="email,nome"></textarea>';
echo '<input type="password" name="senha" placeholder="Senha padrão (opcional)">';
echo '<label><input type="checkbox" name="enviar_email"> Enviar e-mail para novos usuários</label>';
echo '<input type="submit" value="Cadastrar Usuários">';
echo '<progress id="progress-bar" max="100" value="0"></progress>';
echo '</form>';
}
