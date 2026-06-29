<?php
/**
 * Plugin Name:       WebP Gallery Converter
 * Plugin URI:        https://github.com/devmendes23/devmendes23
 * Description:       Converte automaticamente as imagens JPG/PNG da galeria do WordPress para o formato WebP, reduzindo o peso das páginas e melhorando a performance. Permite conversão no upload e conversão em massa das imagens já existentes.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            devMendes23
 * Author URI:        https://github.com/devmendes23
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       webp-gallery-converter
 *
 * --------------------------------------------------------------------------
 * COMO FUNCIONA (visão geral)
 * --------------------------------------------------------------------------
 * 1. Quando você envia uma imagem para a Biblioteca de Mídia, o plugin gera
 *    automaticamente uma versão .webp ao lado do arquivo original.
 * 2. No front-end, o plugin substitui as URLs .jpg/.png por .webp quando o
 *    navegador do visitante suporta WebP.
 * 3. Existe uma tela no admin (Mídia > WebP Converter) para converter em massa
 *    as imagens que já estavam na biblioteca antes da instalação.
 * --------------------------------------------------------------------------
 */

// Bloqueia acesso direto ao arquivo. Se "ABSPATH" não existir, significa que
// o arquivo foi acessado fora do WordPress, então encerramos a execução.
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Sai silenciosamente por segurança.
}

/**
 * ==========================================================================
 * CONSTANTES DO PLUGIN
 * ==========================================================================
 * Definimos constantes para reaproveitar caminhos e versão em todo o código.
 */

// Versão atual do plugin (usada para versionar CSS/JS no cache do navegador).
define( 'WGC_VERSION', '1.0.0' );

// Caminho absoluto até a pasta do plugin no servidor (termina com "/").
define( 'WGC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// URL pública até a pasta do plugin (usada para carregar CSS/JS no admin).
define( 'WGC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Caminho até este arquivo principal (usado nos hooks de ativação).
define( 'WGC_PLUGIN_FILE', __FILE__ );

/**
 * ==========================================================================
 * CARREGAMENTO DAS CLASSES (arquivos da pasta /includes)
 * ==========================================================================
 * Cada responsabilidade fica em uma classe separada para o código ficar
 * organizado e fácil de manter.
 */

// Classe responsável pela conversão de imagens em WebP (o "motor" do plugin).
require_once WGC_PLUGIN_DIR . 'includes/class-wgc-converter.php';

// Classe responsável pela tela de administração e configurações.
require_once WGC_PLUGIN_DIR . 'includes/class-wgc-admin.php';

// Classe responsável pela conversão em massa (AJAX) das imagens existentes.
require_once WGC_PLUGIN_DIR . 'includes/class-wgc-bulk.php';

/**
 * ==========================================================================
 * INICIALIZAÇÃO DO PLUGIN
 * ==========================================================================
 * Instancia as classes principais quando o WordPress termina de carregar
 * os plugins. Usamos o hook "plugins_loaded" para garantir que o WP esteja
 * pronto.
 */
function wgc_inicializar_plugin() {

	// Cria o conversor (registra os hooks de upload e substituição no front).
	$conversor = new WGC_Converter();
	$conversor->registrar_hooks();

	// Cria a tela de administração (menu, configurações, etc.).
	$admin = new WGC_Admin();
	$admin->registrar_hooks();

	// Cria o handler de conversão em massa (endpoints AJAX).
	$bulk = new WGC_Bulk( $conversor );
	$bulk->registrar_hooks();
}
add_action( 'plugins_loaded', 'wgc_inicializar_plugin' );

/**
 * ==========================================================================
 * HOOK DE ATIVAÇÃO
 * ==========================================================================
 * Roda uma única vez quando o plugin é ativado. Aqui definimos as opções
 * padrão e verificamos se o servidor tem suporte a WebP.
 */
function wgc_ao_ativar() {

	// Opções padrão do plugin caso ainda não existam no banco de dados.
	$opcoes_padrao = array(
		'qualidade'        => 80,    // Qualidade do WebP (0 a 100).
		'manter_original'  => 1,     // 1 = mantém o JPG/PNG original; 0 = apaga.
		'converter_upload' => 1,     // 1 = converte automaticamente no upload.
		'substituir_front' => 1,     // 1 = troca as imagens no front-end.
	);

	// add_option só grava se a opção ainda não existir (não sobrescreve).
	add_option( 'wgc_opcoes', $opcoes_padrao );
}
register_activation_hook( __FILE__, 'wgc_ao_ativar' );

/**
 * ==========================================================================
 * HOOK DE DESATIVAÇÃO
 * ==========================================================================
 * Roda quando o plugin é desativado. Mantemos os arquivos WebP gerados,
 * apenas limpamos tarefas agendadas (se houver). A remoção completa fica
 * a cargo do uninstall.php.
 */
function wgc_ao_desativar() {
	// Espaço reservado para limpeza de agendamentos (wp_cron), se forem
	// adicionados em versões futuras.
}
register_deactivation_hook( __FILE__, 'wgc_ao_desativar' );
