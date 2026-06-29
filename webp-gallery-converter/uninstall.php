<?php
/**
 * uninstall.php
 * --------------------------------------------------------------------------
 * Este arquivo roda automaticamente quando o usuário REMOVE (apaga) o plugin
 * pelo painel do WordPress — não apenas desativa.
 *
 * Aqui limpamos os dados que o plugin gravou no banco de dados (as opções).
 * NÃO apagamos os arquivos .webp gerados, pois eles podem estar em uso no
 * site mesmo após a remoção do plugin.
 */

// Segurança: este arquivo só pode ser executado pelo próprio WordPress
// durante a desinstalação. Se a constante não existir, encerra.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove a opção de configurações criada pelo plugin.
delete_option( 'wgc_opcoes' );

// Em instalações multisite, removemos a opção de toda a rede também.
if ( is_multisite() ) {
	delete_site_option( 'wgc_opcoes' );
}
