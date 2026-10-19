<?php
/**
 * Classe WGC_Admin
 * --------------------------------------------------------------------------
 * Responsável por toda a parte visual no painel administrativo:
 *   - Cria o item de menu em "Mídia > WebP Converter".
 *   - Registra e exibe as configurações (qualidade, manter original, etc.).
 *   - Renderiza o botão de conversão em massa.
 *   - Carrega o CSS e JS da tela de administração.
 */

// Bloqueia acesso direto.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WGC_Admin {

	/**
	 * Registra os hooks de administração.
	 */
	public function registrar_hooks() {

		// Adiciona o menu no painel.
		add_action( 'admin_menu', array( $this, 'adicionar_menu' ) );

		// Registra as configurações (Settings API do WordPress).
		add_action( 'admin_init', array( $this, 'registrar_configuracoes' ) );

		// Carrega CSS/JS apenas na nossa tela.
		add_action( 'admin_enqueue_scripts', array( $this, 'carregar_assets' ) );

		// Adiciona um link de "Configurações" na lista de plugins.
		add_filter( 'plugin_action_links_' . plugin_basename( WGC_PLUGIN_FILE ), array( $this, 'link_configuracoes' ) );
	}

	/**
	 * Cria a página dentro do menu "Mídia".
	 */
	public function adicionar_menu() {
		add_submenu_page(
			'upload.php',                       // Slug do menu pai (Mídia).
			'WebP Gallery Converter',           // Título da página (<title>).
			'WebP Converter',                   // Texto exibido no menu.
			'manage_options',                   // Capacidade exigida (admin).
			'wgc-converter',                    // Slug único desta página.
			array( $this, 'renderizar_pagina' ) // Função que desenha a tela.
		);
	}

	/**
	 * Registra o grupo de opções e os campos usando a Settings API.
	 * Isso garante que o WordPress salve os dados com segurança (nonce, etc.).
	 */
	public function registrar_configuracoes() {

		// Registra a opção "wgc_opcoes" com uma função de sanitização.
		register_setting(
			'wgc_grupo_opcoes',                 // Nome do grupo de opções.
			'wgc_opcoes',                       // Nome da opção no banco.
			array( $this, 'sanitizar_opcoes' )  // Função que valida a entrada.
		);

		// Cria uma "seção" para agrupar os campos visualmente.
		add_settings_section(
			'wgc_secao_principal',                       // ID da seção.
			'Configurações de Conversão',                // Título exibido.
			array( $this, 'descricao_secao' ),           // Texto descritivo.
			'wgc-converter'                              // Página onde aparece.
		);

		// Campo: Qualidade do WebP.
		add_settings_field(
			'wgc_qualidade',
			'Qualidade do WebP (0-100)',
			array( $this, 'campo_qualidade' ),
			'wgc-converter',
			'wgc_secao_principal'
		);

		// Campo: Converter automaticamente no upload.
		add_settings_field(
			'wgc_converter_upload',
			'Converter no upload',
			array( $this, 'campo_converter_upload' ),
			'wgc-converter',
			'wgc_secao_principal'
		);

		// Campo: Substituir imagens no front-end.
		add_settings_field(
			'wgc_substituir_front',
			'Substituir imagens no site',
			array( $this, 'campo_substituir_front' ),
			'wgc-converter',
			'wgc_secao_principal'
		);

		// Campo: Manter arquivo original.
		add_settings_field(
			'wgc_manter_original',
			'Manter arquivos originais',
			array( $this, 'campo_manter_original' ),
			'wgc-converter',
			'wgc_secao_principal'
		);
	}

	/**
	 * Sanitiza/valida as opções antes de salvar no banco.
	 *
	 * @param array $entrada Dados enviados pelo formulário.
	 * @return array Dados limpos e seguros.
	 */
	public function sanitizar_opcoes( $entrada ) {

		$limpo = array();

		// Qualidade: força um número inteiro entre 1 e 100.
		$qualidade            = isset( $entrada['qualidade'] ) ? (int) $entrada['qualidade'] : 80;
		$limpo['qualidade']   = max( 1, min( 100, $qualidade ) );

		// Checkboxes: 1 se marcado, 0 se não.
		$limpo['converter_upload'] = ! empty( $entrada['converter_upload'] ) ? 1 : 0;
		$limpo['substituir_front'] = ! empty( $entrada['substituir_front'] ) ? 1 : 0;
		$limpo['manter_original']  = ! empty( $entrada['manter_original'] ) ? 1 : 0;

		return $limpo;
	}

	/**
	 * Texto descritivo da seção de configurações.
	 */
	public function descricao_secao() {
		echo '<p>Ajuste como o plugin converte e entrega as imagens em WebP.</p>';
	}

	/**
	 * Lê uma opção específica com valor padrão.
	 */
	private function opcao( $chave, $padrao = '' ) {
		$opcoes = get_option( 'wgc_opcoes', array() );
		return isset( $opcoes[ $chave ] ) ? $opcoes[ $chave ] : $padrao;
	}

	/**
	 * Renderiza o campo "Qualidade".
	 */
	public function campo_qualidade() {
		$valor = (int) $this->opcao( 'qualidade', 80 );
		printf(
			'<input type="number" min="1" max="100" name="wgc_opcoes[qualidade]" value="%d" class="small-text" /> '
			. '<span class="description">Quanto maior, melhor a imagem e maior o arquivo. Recomendado: 80.</span>',
			$valor
		);
	}

	/**
	 * Renderiza o checkbox "Converter no upload".
	 */
	public function campo_converter_upload() {
		$marcado = checked( 1, $this->opcao( 'converter_upload', 1 ), false );
		echo '<label><input type="checkbox" name="wgc_opcoes[converter_upload]" value="1" ' . $marcado . ' /> '
			. 'Gerar WebP automaticamente ao enviar novas imagens.</label>';
	}

	/**
	 * Renderiza o checkbox "Substituir no front".
	 */
	public function campo_substituir_front() {
		$marcado = checked( 1, $this->opcao( 'substituir_front', 1 ), false );
		echo '<label><input type="checkbox" name="wgc_opcoes[substituir_front]" value="1" ' . $marcado . ' /> '
			. 'Entregar WebP para navegadores compatíveis.</label>';
	}

	/**
	 * Renderiza o checkbox "Manter original".
	 */
	public function campo_manter_original() {
		$marcado = checked( 1, $this->opcao( 'manter_original', 1 ), false );
		echo '<label><input type="checkbox" name="wgc_opcoes[manter_original]" value="1" ' . $marcado . ' /> '
			. 'Não apagar os arquivos JPG/PNG originais (recomendado).</label>';
	}

	/**
	 * Carrega o CSS e o JS, mas SOMENTE na página do plugin.
	 *
	 * @param string $hook Identificador da página atual no admin.
	 */
	public function carregar_assets( $hook ) {

		// "media_page_wgc-converter" é o hook da nossa subpágina em Mídia.
		if ( 'media_page_wgc-converter' !== $hook ) {
			return; // Em outras telas, não carregamos nada (boa prática).
		}

		// CSS da tela de administração.
		wp_enqueue_style(
			'wgc-admin',
			WGC_PLUGIN_URL . 'assets/admin.css',
			array(),
			WGC_VERSION
		);

		// JS responsável pela conversão em massa via AJAX.
		wp_enqueue_script(
			'wgc-admin',
			WGC_PLUGIN_URL . 'assets/admin.js',
			array( 'jquery' ),
			WGC_VERSION,
			true // Carrega no rodapé.
		);

		// Passa dados do PHP para o JS (URL do AJAX e nonce de segurança).
		wp_localize_script(
			'wgc-admin',
			'WGC_DADOS',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wgc_bulk_nonce' ),
			)
		);
	}

	/**
	 * Adiciona um link "Configurações" na lista de plugins instalados.
	 *
	 * @param array $links Links existentes.
	 * @return array
	 */
	public function link_configuracoes( $links ) {
		$url        = admin_url( 'upload.php?page=wgc-converter' );
		$novo_link  = '<a href="' . esc_url( $url ) . '">Configurações</a>';
		array_unshift( $links, $novo_link ); // Coloca no início da lista.
		return $links;
	}

	/**
	 * Desenha a página principal do plugin no admin.
	 */
	public function renderizar_pagina() {

		// Garante que apenas administradores acessem.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Cria um conversor temporário só para checar suporte do servidor.
		$conversor = new WGC_Converter();
		$suporte   = $conversor->suporte_webp();
		?>
		<div class="wrap wgc-wrap">
			<h1>🖼️ WebP Gallery Converter</h1>

			<?php // Aviso sobre o suporte do servidor. ?>
			<?php if ( false === $suporte ) : ?>
				<div class="notice notice-error">
					<p><strong>Atenção:</strong> seu servidor não suporta a geração de WebP.
					Instale a extensão <code>GD</code> (com WebP) ou <code>Imagick</code> no PHP.</p>
				</div>
			<?php else : ?>
				<div class="notice notice-success">
					<p>Servidor compatível! Biblioteca em uso: <strong><?php echo esc_html( strtoupper( $suporte ) ); ?></strong>.</p>
				</div>
			<?php endif; ?>

			<?php // ---- Formulário de configurações ---- ?>
			<form method="post" action="options.php" class="wgc-form">
				<?php
				// Imprime campos ocultos de segurança (nonce) do grupo.
				settings_fields( 'wgc_grupo_opcoes' );
				// Renderiza todas as seções/campos registrados.
				do_settings_sections( 'wgc-converter' );
				// Botão "Salvar alterações".
				submit_button( 'Salvar configurações' );
				?>
			</form>

			<hr />

			<?php // ---- Seção de conversão em massa ---- ?>
			<h2>Converter imagens existentes</h2>
			<p>Clique no botão abaixo para converter todas as imagens JPG/PNG já presentes
			na Biblioteca de Mídia para WebP.</p>

			<button id="wgc-iniciar-bulk" class="button button-primary" <?php disabled( false === $suporte ); ?>>
				Iniciar conversão em massa
			</button>

			<?php // Barra de progresso (preenchida pelo JS). ?>
			<div id="wgc-progresso-wrap" style="display:none;">
				<div class="wgc-barra"><div id="wgc-barra-interna"></div></div>
				<p id="wgc-status">Preparando…</p>
			</div>
		</div>
		<?php
	}
}
