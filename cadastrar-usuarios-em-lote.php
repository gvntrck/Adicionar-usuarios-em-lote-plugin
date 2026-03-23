<?php
/**
 * Plugin Name: Cadastrar Usuários em Lote
 * Author: Giovani Tureck
 * Author Email: gvntrck@gmail.com
 * Author URI: http://projetoalfa.org/
 * Description: Plugin para cadastrar usuários em lote no WordPress. Integração com LearnDash para matricular em cursos e grupos
 * Version: 4.8.1
 * License: GPLv2 or later
 */

// Evitar acesso direto ao arquivo
if (!defined('ABSPATH')) {
    exit;
}

require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/gvntrck/Adicionar-usuarios-em-lote-plugin',
    __FILE__,
    'Adicionar-usuarios-em-lote-plugin'
);

//Set the branch that contains the stable release.
$myUpdateChecker->setBranch('main');

//Optional: If you're using a private repository, specify the access token like this:
$myUpdateChecker->setAuthentication('your-token-here');






// Carregar scripts e estilos
function enqueue_cadastrar_usuarios_em_lote_scripts($hook)
{
    $current_page = isset($_GET['page']) ? sanitize_text_field((string) $_GET['page']) : '';
    $allowed_pages = ['cadastrar-usuarios-em-lote', 'cadastrar-usuarios-em-lote-mensagens'];

    if (!in_array($current_page, $allowed_pages, true)) {
        return;
    }

    $version = function_exists('get_plugin_version') ? get_plugin_version() : '4.8.1';
    wp_enqueue_script('cadastrar-usuarios-js', plugin_dir_url(__FILE__) . 'assets/js/main.js', ['jquery'], $version, true);
    wp_enqueue_style('cadastrar-usuarios-css', plugin_dir_url(__FILE__) . 'assets/css/style.css', [], $version);

    $editor_settings = null;
    if ($current_page === 'cadastrar-usuarios-em-lote-mensagens') {
        $editor_settings = wp_enqueue_code_editor(['type' => 'text/html']);

        if ($editor_settings) {
            wp_enqueue_script('code-editor');
            wp_enqueue_style('code-editor');
        }
    }

    wp_localize_script('cadastrar-usuarios-js', 'culAdminData', [
        'codeEditorSettings' => $editor_settings,
    ]);

}
add_action('admin_enqueue_scripts', 'enqueue_cadastrar_usuarios_em_lote_scripts');

// Adicionar menu no painel admin
function cadastrar_usuarios_em_lote_menu()
{
    add_menu_page('Cadastrar Usuários em Lote', 'Cadastrar Usuários', 'manage_options', 'cadastrar-usuarios-em-lote', 'cadastrar_usuarios_em_lote_page', 'dashicons-groups', 6);
    add_submenu_page('cadastrar-usuarios-em-lote', 'Cadastro em Massa', 'Cadastro em Massa', 'manage_options', 'cadastrar-usuarios-em-lote', 'cadastrar_usuarios_em_lote_page');
    add_submenu_page('cadastrar-usuarios-em-lote', 'Mensagens de E-mail', 'Mensagens de E-mail', 'manage_options', 'cadastrar-usuarios-em-lote-mensagens', 'cadastrar_usuarios_em_lote_email_templates_page');
}
add_action('admin_menu', 'cadastrar_usuarios_em_lote_menu');

// Inclui o arquivo de funções
require_once(plugin_dir_path(__FILE__) . 'includes/email-templates.php');
require_once(plugin_dir_path(__FILE__) . 'includes/functions.php');

