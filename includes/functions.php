<?php
function get_plugin_version() {
    $plugin_data = get_file_data(plugin_dir_path(dirname(__FILE__)) . 'cadastrar-usuarios-em-lote.php', array('Version' => 'Version'), 'plugin');
    return $plugin_data['Version'];
}

/**
 * Classe responsável pelo processamento de usuários em lote
 * Seguindo princípio SRP (Single Responsibility Principle) do SOLID
 */
class UserBatchProcessor {
    private $resultados = [];
    private $usuarios_para_email = [];
    private $usuarios_com_senha = [];
    private $total_usuarios = 0;
    private $processados = 0;

    /**
     * Processa uma lista de usuários em lote
     * 
     * @param string $usuarios Lista de usuários no formato email,nome
     * @param string $senha Senha padrão ou vazia para gerar senha aleatória
     * @param bool $enviar_email Se deve enviar email para os usuários
     * @param bool $enviar_senha Se deve enviar a senha por email
     * @param string $role Função do usuário no WordPress
     * @param int $curso_id ID do curso para matricular os usuários
     * @param int $grupo_id ID do grupo para adicionar os usuários
     * @return array Resultados do processamento
     */
    public function processar($usuarios, $senha, $enviar_email, $enviar_senha, $role, $curso_id, $grupo_id) {
        $linhas = explode("\n", $usuarios);
        $this->total_usuarios = count(array_filter($linhas, 'trim'));
        
        // Verificar emails existentes em lote (reduz consultas ao banco)
        $emails = [];
        foreach ($linhas as $linha) {
            $linha = trim($linha);
            if (empty($linha)) continue;
            
            $partes = explode(',', $linha);
            
            // Detectar formato automaticamente (email,nome ou nome,email ou apenas email)
            $email = null;
            if (count($partes) === 1) {
                $email = strtolower(sanitize_text_field(trim($partes[0])));
            } else {
                $parte0 = strtolower(sanitize_text_field(trim($partes[0])));
                $parte1 = strtolower(sanitize_text_field(trim($partes[1])));
                $email = is_email($parte0) ? $parte0 : (is_email($parte1) ? $parte1 : null);
            }
            if ($email && is_email($email)) {
                $emails[] = $email;
            }
        }
        
        // Buscar usuários existentes em uma única consulta
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
                $existing_users[strtolower($user->user_email)] = $user->ID;
            }
        }
        
        // Processar cada linha
        foreach ($linhas as $linha) {
            $linha = trim($linha);
            if (empty($linha)) {
                continue; // Ignora linhas vazias
            }
            
            $this->processados++;
            
            $partes = explode(',', $linha);
            
            // Detectar formato automaticamente (email,nome ou nome,email ou apenas email)
            $email = null;
            $primeiro_nome = '';
            
            if (count($partes) === 1) {
                // Apenas email fornecido
                $email = strtolower(sanitize_text_field(trim($partes[0])));
                if (is_email($email)) {
                    // Extrair nome do email (parte antes do @)
                    $primeiro_nome = ucfirst(explode('@', $email)[0]);
                }
            } else {
                $parte0 = sanitize_text_field(trim($partes[0]));
                $parte1 = sanitize_text_field(trim($partes[1]));
                
                if (is_email($parte0)) {
                    // Formato: email,nome
                    $email = strtolower($parte0);
                    $primeiro_nome = $parte1 ?: ucfirst(explode('@', $email)[0]);
                } elseif (is_email($parte1)) {
                    // Formato: nome,email
                    $email = strtolower($parte1);
                    $primeiro_nome = $parte0 ?: ucfirst(explode('@', $email)[0]);
                }
            }

            if (!$email || !is_email($email)) {
                $this->resultados[] = "Erro: email inválido na linha - $linha";
                continue;
            }

            $senha_usuario = $senha ? $senha : wp_generate_password();

            // Verifica se o usuário já existe usando o array pré-carregado
            if (isset($existing_users[$email])) {
                $user_id = $existing_users[$email];

                $acoes = [];
                if ($curso_id > 0 && function_exists('ld_update_course_access')) {
                    ld_update_course_access($user_id, $curso_id);
                    $acoes[] = 'matriculado no curso';
                }
                if ($grupo_id > 0 && function_exists('ld_update_group_access')) {
                    ld_update_group_access($user_id, $grupo_id);
                    $acoes[] = 'adicionado ao grupo';
                }

                if ($acoes) {
                    $this->resultados[] = "O usuário $email já existe no sistema e foi " . implode(' e ', $acoes) . ' com sucesso';
                } else {
                    $this->resultados[] = "O usuário $email já existe no sistema. Selecione um curso ou grupo para associá-lo";
                }
                continue;
            }

            // Se não existe, cria o novo usuário
            $user_id = wp_create_user($email, $senha_usuario, $email);
            if (is_wp_error($user_id)) {
                $this->resultados[] = "Erro ao processar $email: " . $user_id->get_error_message();
                continue;
            }

            // Atualizar usuário com a função e o primeiro nome
            wp_update_user([
                'ID' => $user_id,
                'first_name' => $primeiro_nome,
                'role' => $role
            ]);

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
            if ($acoes_novas) {
                $mensagem .= ' (' . implode(' e ', $acoes_novas) . ')';
            }
            $this->resultados[] = $mensagem;

            // Armazenar para envio em lote
            if ($enviar_email) {
                $this->usuarios_para_email[] = $user_id;
            }
            
            // Armazenar senha para envio por email
            if ($enviar_senha) {
                $this->usuarios_com_senha[] = [
                    'user_id' => $user_id,
                    'email' => $email,
                    'nome' => $primeiro_nome,
                    'senha' => $senha_usuario
                ];
            }
        }
        
        // Processar e-mails em lote após todos os usuários terem sido criados
        $this->processar_emails_em_lote();
        $this->enviar_senhas_por_email();
        
        return $this->resultados;
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
        
        foreach ($batches as $batch) {
            foreach ($batch as $user_id) {
                wp_new_user_notification($user_id, null, 'both');
            }
            // Pequena pausa entre lotes para evitar sobrecarga do servidor SMTP
            if (count($batches) > 1) {
                usleep(250000); // 0.25 segundos
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
            
            $message = "Olá {$usuario_info['nome']},\n\n";
            $message .= "Sua conta foi criada com sucesso!\n\n";
            $message .= "Seus dados de acesso são:\n";
            $message .= "Email: {$usuario_info['email']}\n";
            $message .= "Senha: {$usuario_info['senha']}\n\n";
            $message .= "Acesse o site em: " . wp_login_url() . "\n\n";
            $message .= "Recomendamos que você altere sua senha após o primeiro acesso.\n\n";
            $message .= "Atenciosamente,\n";
            $message .= get_bloginfo('name');
            
            $headers = ['Content-Type: text/plain; charset=UTF-8'];
            
            wp_mail($to, $subject, $message, $headers);
            
            // Pequena pausa para evitar sobrecarga do servidor SMTP
            usleep(250000); // 0.25 segundos
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
    // Verificar se o usuário tem permissão para acessar esta página
    if (!current_user_can('manage_options')) {
        wp_die(__('Você não tem permissão para acessar esta página.', 'cadastrar-usuarios-lote'));
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_admin_referer('cadastrar_usuarios_em_lote');

        $usuarios = sanitize_textarea_field($_POST['usuarios']);
        $usuarios = trim($usuarios);  // Remover espaços em branco e linhas extras
        $senha = sanitize_text_field($_POST['senha']);
        $enviar_email = isset($_POST['enviar_email']);
        $enviar_senha = isset($_POST['enviar_senha']);
        $role = sanitize_text_field($_POST['role']);  // Função do usuário
        $curso_id  = isset($_POST['curso_id'])  ? intval($_POST['curso_id'])  : 0;
        $grupo_id = isset($_POST['grupo_id']) ? intval($_POST['grupo_id']) : 0;

        // Usar o processador de lote para cadastrar os usuários
        $processador = new UserBatchProcessor();
        $resultados = $processador->processar($usuarios, $senha, $enviar_email, $enviar_senha, $role, $curso_id, $grupo_id);

        echo '<div id="message" class="updated notice is-dismissible"><p>';
        echo implode('<br>', array_map('esc_html', $resultados));
        echo '</p></div>';
    }

    // Campos para adicionar email e inserir a senha padrão, se houver
    echo '<form id="cadastrar-usuarios-form" method="post">';
    wp_nonce_field('cadastrar_usuarios_em_lote');
    echo '<textarea name="usuarios" placeholder="email,nome ou nome,email ou apenas email"></textarea>';
    echo '<div class="formato-instrucao" style="margin-top: 2px; margin-bottom: 5px; color: #666;">';
    echo '<strong>Formato:</strong> Insira um usuário por linha. Aceita:<br>';
    echo '&bull; <code>email,nome</code> ou <code>nome,email</code> (detecta automaticamente)<br>';
    echo '&bull; <code>email</code> (nome será extraído do email)<br>';
    echo '<em>Exemplo:</em><br>';
    echo '<code>usuario1@exemplo.com,João<br>Maria,usuario2@exemplo.com<br>usuario3@exemplo.com</code>';
    echo '</div>';
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

    // Adicionar dropdown de cursos do LearnDash
    if (function_exists('ld_course_list')) {
        $args = array(
            'post_type'      => 'sfwd-courses',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC'
        );
        $cursos = get_posts($args);

        if (!empty($cursos)) {
            echo '<p>Selecione o curso para matricular os usuários:</p>';
            echo '<select name="curso_id" id="courseDropdown">';
            echo '<option value="0">Nenhum curso</option>';
            foreach ($cursos as $curso) {
                echo '<option value="' . esc_attr($curso->ID) . '">' . esc_html($curso->post_title) . '</option>';
            }
            echo '</select>';
        }
    }

    // Adicionar dropdown de grupos do LearnDash
    if (post_type_exists('groups')) {
        $args = [
            'post_type'      => 'groups',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];
        $grupos = get_posts($args);

        if (!empty($grupos)) {
            echo '<p>Selecione o grupo para adicionar os usuários:</p>';
            echo '<select name="grupo_id" id="groupDropdown">';
            echo '<option value="0">Nenhum grupo</option>';
            foreach ($grupos as $grupo) {
                echo '<option value="' . esc_attr($grupo->ID) . '">' . esc_html($grupo->post_title) . '</option>';
            }
            echo '</select>';
        }
    }

    echo '<br>';
    echo '<div style="margin-top: 20px;">';
    echo '<label style="display: block; margin-bottom: 10px;"><input type="checkbox" name="enviar_email"> Enviar e-mail de boas-vindas (com link para redefinição de senha)</label>';
    echo '<label style="display: block; margin-bottom: 10px;"><input type="checkbox" name="enviar_senha"> Enviar senha por e-mail (recomendado para facilitar o acesso)</label>';
    echo '</div>';
    echo '<input type="submit" value="Cadastrar Usuários" id="submit-button">';
    echo '<div id="progress-container" style="display:none; margin-top: 20px;">';
    echo '<p>Processando usuários... Por favor, não feche esta página.</p>';
    echo '<progress id="progress-bar" max="100" value="0" style="width: 100%;"></progress>';
    echo '<p id="progress-status">0%</p>';
    echo '</div>';
    echo '</form>';
    
    // Adicionar rodapé com a versão do plugin
    echo '<div style="text-align: center; margin-top: 30px; color: #666; font-size: 12px;">';
    echo 'Versão: ' . esc_html(get_plugin_version());
    echo '</div>';
    
    // Adicionar script para mostrar progresso
    echo '<script>
    jQuery(document).ready(function($) {
        $("#cadastrar-usuarios-form").on("submit", function() {
            $("#submit-button").prop("disabled", true);
            $("#progress-container").show();
            
            // Contar número de linhas não vazias
            var lines = $("textarea[name=\'usuarios\']").val().split("\\n").filter(function(line) {
                return line.trim().length > 0;
            }).length;
            
            if (lines > 0) {
                var progress = 0;
                var interval = setInterval(function() {
                    // Simular progresso gradualmente até 95%
                    if (progress < 95) {
                        progress += (95 - progress) / 10;
                        $("#progress-bar").val(progress);
                        $("#progress-status").text(Math.round(progress) + "%");
                    }
                }, 1000);
                
                // Salvar o intervalo para limpar depois
                $(this).data("progressInterval", interval);
            }
            
            return true;
        });
    });
    </script>';
}
