<?php
/**
 * Classe WGC_Bulk
 * --------------------------------------------------------------------------
 * Cuida da conversão em massa das imagens que JÁ existiam na Biblioteca de
 * Mídia antes da instalação do plugin.
 *
 * Para não estourar o tempo limite do PHP, o processo é feito em "lotes":
 * o JavaScript chama o servidor várias vezes, convertendo poucas imagens
 * por requisição e atualizando a barra de progresso.
 */

// Bloqueia acesso direto.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WGC_Bulk {

	/**
	 * Instância do conversor (reaproveitamos a lógica de conversão).
	 *
	 * @var WGC_Converter
	 */
	private $conversor;

	/**
	 * Quantas imagens converter por requisição (lote).
	 *
	 * @var int
	 */
	private $tamanho_lote = 5;

	/**
	 * Construtor: recebe o conversor já instanciado.
	 *
	 * @param WGC_Converter $conversor
	 */
	public function __construct( WGC_Converter $conversor ) {
		$this->conversor = $conversor;
	}

	/**
	 * Registra os endpoints AJAX usados pela tela de administração.
	 */
	public function registrar_hooks() {

		// Endpoint para descobrir o total de imagens a converter.
		add_action( 'wp_ajax_wgc_contar_imagens', array( $this, 'ajax_contar_imagens' ) );

		// Endpoint para converter um lote de imagens.
		add_action( 'wp_ajax_wgc_converter_lote', array( $this, 'ajax_converter_lote' ) );
	}

	/**
	 * Valida a requisição AJAX (nonce + permissão).
	 * Encerra a execução com erro se algo estiver errado.
	 */
	private function validar_requisicao() {

		// Confere o nonce enviado pelo JS para evitar requisições forjadas (CSRF).
		check_ajax_referer( 'wgc_bulk_nonce', 'nonce' );

		// Apenas administradores podem rodar a conversão.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'mensagem' => 'Permissão negada.' ) );
		}
	}

	/**
	 * ----------------------------------------------------------------------
	 * AJAX: CONTA QUANTAS IMAGENS EXISTEM
	 * ----------------------------------------------------------------------
	 * Retorna o total de anexos JPG/PNG, usado para calcular o progresso.
	 */
	public function ajax_contar_imagens() {

		$this->validar_requisicao();

		// Busca os IDs de todos os anexos JPEG/PNG.
		$ids = $this->buscar_ids_imagens();

		// Devolve o total em JSON para o JavaScript.
		wp_send_json_success(
			array(
				'total' => count( $ids ),
				'ids'   => $ids, // A lista completa para o JS processar em lotes.
			)
		);
	}

	/**
	 * ----------------------------------------------------------------------
	 * AJAX: CONVERTE UM LOTE
	 * ----------------------------------------------------------------------
	 * Recebe uma lista de IDs (um lote) e converte cada imagem + miniaturas.
	 */
	public function ajax_converter_lote() {

		$this->validar_requisicao();

		// Lê os IDs enviados pelo JS e garante que são números inteiros.
		$ids_brutos = isset( $_POST['ids'] ) ? (array) $_POST['ids'] : array();
		$ids        = array_map( 'absint', $ids_brutos );

		$convertidas = 0; // Contador de imagens processadas neste lote.

		foreach ( $ids as $id ) {

			// Caminho do arquivo original deste anexo.
			$arquivo = get_attached_file( $id );
			if ( ! $arquivo ) {
				continue; // Pula se não encontrar o arquivo.
			}

			// Converte a imagem em tamanho original.
			$this->conversor->converter_arquivo( $arquivo );

			// Converte também todas as miniaturas geradas pelo WordPress.
			$metadata   = wp_get_attachment_metadata( $id );
			$pasta_base = trailingslashit( dirname( $arquivo ) );

			if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $tamanho ) {
					if ( ! empty( $tamanho['file'] ) ) {
						$this->conversor->converter_arquivo( $pasta_base . $tamanho['file'] );
					}
				}
			}

			$convertidas++;
		}

		// Informa ao JS quantas imagens foram convertidas neste lote.
		wp_send_json_success( array( 'convertidas' => $convertidas ) );
	}

	/**
	 * Busca os IDs de todos os anexos do tipo JPEG e PNG.
	 *
	 * @return int[] Lista de IDs.
	 */
	private function buscar_ids_imagens() {

		$consulta = new WP_Query(
			array(
				'post_type'      => 'attachment',                 // Apenas anexos.
				'post_status'    => 'inherit',                    // Status padrão de anexos.
				'post_mime_type' => array( 'image/jpeg', 'image/png' ), // Só JPG/PNG.
				'posts_per_page' => -1,                            // Todos.
				'fields'         => 'ids',                         // Só os IDs (mais leve).
				'no_found_rows'  => true,                          // Otimização.
			)
		);

		return $consulta->posts;
	}
}
