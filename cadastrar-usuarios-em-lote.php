<?php
/**
 * Plugin Name: Cadastrar Usuários em Lote
 * Author: Giovani Tureck
 * Author Email: gvntrck@gmail.com
 * Author URI: http://projetoalfa.org/
 * Description: Plugin para cadastrar usuários em lote no WordPress.
 * Version: 4.1.0
 * License: GPLv2 or later
 */

// Evitar acesso direto ao arquivo
if (!defined('ABSPATH')) {
    exit;
}

// Carregar scripts e estilos
function enqueue_cadastrar_usuarios_em_lote_scripts() {
    wp_enqueue_script('cadastrar-usuarios-js', plugin_dir_url(__FILE__) . 'assets/js/main.js', ['jquery'], '1.0.0', true);
    wp_enqueue_style('cadastrar-usuarios-css', plugin_dir_url(__FILE__) . 'assets/css/style.css', [], '1.0.0');

}
add_action('admin_enqueue_scripts', 'enqueue_cadastrar_usuarios_em_lote_scripts');

// Adicionar menu no painel admin
function cadastrar_usuarios_em_lote_menu() {
    add_menu_page('Cadastrar Usuários em Lote', 'Cadastrar Usuários', 'manage_options', 'cadastrar-usuarios-em-lote', 'cadastrar_usuarios_em_lote_page', 'dashicons-groups', 6);
}
add_action('admin_menu', 'cadastrar_usuarios_em_lote_menu');

// Inclui o arquivo de funções
require_once(plugin_dir_path(__FILE__) . 'includes/functions.php');

