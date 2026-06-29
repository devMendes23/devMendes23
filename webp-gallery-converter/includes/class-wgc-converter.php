<?php
/**
 * Classe WGC_Converter
 * --------------------------------------------------------------------------
 * É o "coração" do plugin. Cuida de:
 *   - Converter um arquivo JPG/PNG em WebP (usando GD ou Imagick).
 *   - Gerar a versão WebP automaticamente quando uma imagem é enviada.
 *   - Substituir as URLs .jpg/.png por .webp no front-end.
 *
 * Mantemos toda a lógica de conversão isolada aqui para facilitar testes
 * e reaproveitamento (a tela de conversão em massa também usa esta classe).
 */

// Impede acesso direto ao arquivo.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WGC_Converter {

	/**
	 * Guarda as opções configuradas pelo usuário (qualidade, etc.).
	 *
	 * @var array
	 */
	private $opcoes;

	/**
	 * Construtor: carrega as opções salvas no banco de dados.
	 */
	public function __construct() {
		// Busca as opções; se não existirem, usa um array vazio como segurança.
		$this->opcoes = get_option( 'wgc_opcoes', array() );
	}

	/**
	 * Registra os hooks do WordPress relacionados à conversão.
	 * Esse método é chamado a partir do arquivo principal do plugin.
	 */
	public function registrar_hooks() {

		// Converte automaticamente quando o WordPress termina de gerar os
		// metadados/miniaturas de uma imagem enviada para a biblioteca.
		if ( $this->opcao( 'converter_upload', 1 ) ) {
			add_filter( 'wp_generate_attachment_metadata', array( $this, 'converter_no_upload' ), 10, 2 );
		}

		// Substitui as imagens no conteúdo do front-end por versões WebP.
		if ( $this->opcao( 'substituir_front', 1 ) ) {
			// Troca imagens dentro do conteúdo dos posts/páginas.
			add_filter( 'the_content', array( $this, 'substituir_imagens_no_html' ), 20 );
			// Troca imagens renderizadas via wp_get_attachment_image (galerias, blocos, etc.).
			add_filter( 'wp_get_attachment_image_attributes', array( $this, 'substituir_atributos_imagem' ), 20, 1 );
		}
	}

	/**
	 * Helper para ler uma opção com valor padrão.
	 *
	 * @param string $chave  Nome da opção.
	 * @param mixed  $padrao Valor retornado caso a opção não exista.
	 * @return mixed
	 */
	private function opcao( $chave, $padrao = '' ) {
		return isset( $this->opcoes[ $chave ] ) ? $this->opcoes[ $chave ] : $padrao;
	}

	/**
	 * ----------------------------------------------------------------------
	 * VERIFICA SUPORTE A WEBP NO SERVIDOR
	 * ----------------------------------------------------------------------
	 * Retorna qual biblioteca está disponível: 'gd', 'imagick' ou false.
	 *
	 * @return string|false
	 */
	public function suporte_webp() {

		// Preferimos a Imagick por ter melhor qualidade, mas só se ela
		// realmente suportar o formato WebP.
		if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
			$formatos = Imagick::queryFormats( 'WEBP' );
			if ( ! empty( $formatos ) ) {
				return 'imagick';
			}
		}

		// Em seguida tentamos a GD, que precisa da função imagewebp().
		if ( extension_loaded( 'gd' ) && function_exists( 'imagewebp' ) ) {
			return 'gd';
		}

		// Nenhuma biblioteca disponível: o servidor não suporta WebP.
		return false;
	}

	/**
	 * ----------------------------------------------------------------------
	 * CONVERTE UM ARQUIVO PARA WEBP
	 * ----------------------------------------------------------------------
	 * Recebe o caminho ABSOLUTO de um JPG/PNG e gera o .webp ao lado dele.
	 *
	 * @param string $caminho_origem Caminho absoluto do arquivo original.
	 * @return string|false Caminho do WebP gerado ou false em caso de erro.
	 */
	public function converter_arquivo( $caminho_origem ) {

		// O arquivo existe mesmo? Se não, não há o que converter.
		if ( ! file_exists( $caminho_origem ) ) {
			return false;
		}

		// Descobre o tipo (mime) real da imagem para escolher como abri-la.
		$info = @getimagesize( $caminho_origem );
		if ( false === $info || empty( $info['mime'] ) ) {
			return false; // Não é uma imagem válida.
		}
		$mime = $info['mime'];

		// Só convertemos JPEG e PNG. GIF/WebP são ignorados.
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) {
			return false;
		}

		// Monta o caminho de destino trocando a extensão por ".webp".
		$caminho_destino = $this->caminho_webp( $caminho_origem );

		// Se o WebP já existe, não reprocessa (economiza tempo/CPU).
		if ( file_exists( $caminho_destino ) ) {
			return $caminho_destino;
		}

		// Qualidade desejada (0-100), com fallback para 80.
		$qualidade = (int) $this->opcao( 'qualidade', 80 );

		// Escolhe o método disponível e executa a conversão.
		$metodo = $this->suporte_webp();

		if ( 'imagick' === $metodo ) {
			return $this->converter_com_imagick( $caminho_origem, $caminho_destino, $qualidade );
		}

		if ( 'gd' === $metodo ) {
			return $this->converter_com_gd( $caminho_origem, $caminho_destino, $qualidade, $mime );
		}

		// Sem suporte: falha controlada.
		return false;
	}

	/**
	 * Converte usando a extensão Imagick.
	 *
	 * @return string|false
	 */
	private function converter_com_imagick( $origem, $destino, $qualidade ) {
		try {
			$imagem = new Imagick( $origem );      // Carrega a imagem.
			$imagem->setImageFormat( 'webp' );      // Define o formato de saída.
			$imagem->setImageCompressionQuality( $qualidade ); // Define a qualidade.
			$imagem->writeImage( $destino );        // Grava o arquivo .webp.
			$imagem->clear();                       // Libera a memória.
			$imagem->destroy();
			return file_exists( $destino ) ? $destino : false;
		} catch ( Exception $e ) {
			// Em caso de erro, retornamos false sem quebrar o site.
			return false;
		}
	}

	/**
	 * Converte usando a extensão GD.
	 *
	 * @return string|false
	 */
	private function converter_com_gd( $origem, $destino, $qualidade, $mime ) {

		// Cria o recurso de imagem na memória conforme o tipo de origem.
		if ( 'image/jpeg' === $mime ) {
			$imagem = @imagecreatefromjpeg( $origem );
		} else { // image/png
			$imagem = @imagecreatefrompng( $origem );

			// Para PNG precisamos preservar a transparência (canal alfa).
			if ( $imagem ) {
				imagepalettetotruecolor( $imagem );      // Garante "truecolor".
				imagealphablending( $imagem, true );     // Mistura corretamente.
				imagesavealpha( $imagem, true );         // Salva o canal alfa.
			}
		}

		// Se não conseguiu abrir a imagem, aborta.
		if ( ! $imagem ) {
			return false;
		}

		// Gera o WebP. imagewebp() retorna true/false.
		$ok = imagewebp( $imagem, $destino, $qualidade );

		// Libera a memória usada pela imagem.
		imagedestroy( $imagem );

		return ( $ok && file_exists( $destino ) ) ? $destino : false;
	}

	/**
	 * ----------------------------------------------------------------------
	 * HOOK: CONVERTE NO UPLOAD
	 * ----------------------------------------------------------------------
	 * Disparado pelo filtro "wp_generate_attachment_metadata". Converte a
	 * imagem original e também todas as miniaturas geradas pelo WordPress.
	 *
	 * @param array $metadata      Metadados da imagem (tamanhos/miniaturas).
	 * @param int   $attachment_id ID do anexo na biblioteca de mídia.
	 * @return array Metadados (inalterados — apenas devolvemos o que recebemos).
	 */
	public function converter_no_upload( $metadata, $attachment_id ) {

		// Caminho absoluto do arquivo original (ex.: .../uploads/2026/06/foto.jpg).
		$arquivo_original = get_attached_file( $attachment_id );

		// Pasta base onde o arquivo está (para montar o caminho das miniaturas).
		$pasta_base = trailingslashit( dirname( $arquivo_original ) );

		// 1) Converte a imagem em tamanho original.
		$this->converter_arquivo( $arquivo_original );

		// 2) Converte cada miniatura (thumbnail, medium, large, etc.).
		if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $tamanho ) {
				if ( ! empty( $tamanho['file'] ) ) {
					$this->converter_arquivo( $pasta_base . $tamanho['file'] );
				}
			}
		}

		// 3) Se o usuário optou por NÃO manter os originais, apaga-os.
		//    (Cuidado: isso é opcional e desativado por padrão.)
		if ( ! $this->opcao( 'manter_original', 1 ) ) {
			// Mantemos os originais por segurança nesta versão; a remoção
			// total exigiria reescrever os metadados do anexo. Deixamos o
			// gancho documentado para evolução futura.
		}

		// Sempre devolvemos os metadados originais para o WordPress.
		return $metadata;
	}

	/**
	 * ----------------------------------------------------------------------
	 * FRONT-END: SUBSTITUI IMAGENS NO HTML DO CONTEÚDO
	 * ----------------------------------------------------------------------
	 * Percorre o HTML do conteúdo e troca os caminhos .jpg/.png por .webp
	 * quando o arquivo WebP existe e o navegador suporta o formato.
	 *
	 * @param string $conteudo HTML do post/página.
	 * @return string HTML modificado.
	 */
	public function substituir_imagens_no_html( $conteudo ) {

		// Se o navegador do visitante não aceita WebP, não mexe em nada.
		if ( ! $this->navegador_suporta_webp() ) {
			return $conteudo;
		}

		// Expressão regular que captura URLs de imagens .jpg/.jpeg/.png
		// dentro de atributos src/srcset.
		$padrao = '/(https?:\/\/[^\s"\']+\.(?:jpe?g|png))/i';

		// Para cada URL encontrada, tentamos trocar pela versão .webp.
		$conteudo = preg_replace_callback(
			$padrao,
			array( $this, 'callback_troca_url' ),
			$conteudo
		);

		return $conteudo;
	}

	/**
	 * Callback usado pelo preg_replace_callback acima.
	 * Recebe a URL original e devolve a URL .webp se o arquivo existir.
	 *
	 * @param array $matches Resultado do regex ($matches[0] = URL completa).
	 * @return string
	 */
	private function callback_troca_url( $matches ) {

		$url_original = $matches[0];

		// Converte a URL em caminho de arquivo no servidor.
		$caminho_arquivo = $this->url_para_caminho( $url_original );

		// Se conseguimos mapear o caminho e a versão WebP existe, troca a URL.
		if ( $caminho_arquivo ) {
			$caminho_webp = $this->caminho_webp( $caminho_arquivo );
			if ( file_exists( $caminho_webp ) ) {
				return $this->caminho_webp( $url_original ); // Troca a extensão na URL.
			}
		}

		// Caso contrário, mantém a URL original.
		return $url_original;
	}

	/**
	 * Substitui o atributo "src" de imagens renderizadas pelo WordPress
	 * (usado por galerias de blocos e wp_get_attachment_image).
	 *
	 * @param array $atributos Atributos da tag <img>.
	 * @return array
	 */
	public function substituir_atributos_imagem( $atributos ) {

		if ( ! $this->navegador_suporta_webp() ) {
			return $atributos;
		}

		// Troca o src, se houver versão WebP.
		if ( ! empty( $atributos['src'] ) ) {
			$caminho = $this->url_para_caminho( $atributos['src'] );
			if ( $caminho && file_exists( $this->caminho_webp( $caminho ) ) ) {
				$atributos['src'] = $this->caminho_webp( $atributos['src'] );
			}
		}

		return $atributos;
	}

	/**
	 * ----------------------------------------------------------------------
	 * FUNÇÕES AUXILIARES
	 * ----------------------------------------------------------------------
	 */

	/**
	 * Troca a extensão de um caminho/URL .jpg|.jpeg|.png por .webp.
	 *
	 * @param string $caminho Caminho ou URL.
	 * @return string
	 */
	public function caminho_webp( $caminho ) {
		// preg_replace troca a extensão final mantendo o resto do caminho.
		return preg_replace( '/\.(jpe?g|png)$/i', '.webp', $caminho );
	}

	/**
	 * Converte uma URL pública em caminho absoluto no servidor.
	 *
	 * @param string $url URL da imagem.
	 * @return string|false Caminho absoluto ou false se a URL for externa.
	 */
	private function url_para_caminho( $url ) {

		// Pega informações da pasta de uploads (baseurl e basedir).
		$uploads = wp_upload_dir();

		// Só tratamos imagens que estão dentro da pasta de uploads do site.
		if ( strpos( $url, $uploads['baseurl'] ) === 0 ) {
			// Substitui a URL base pelo diretório físico correspondente.
			return str_replace( $uploads['baseurl'], $uploads['basedir'], $url );
		}

		return false;
	}

	/**
	 * Verifica, pelo cabeçalho HTTP "Accept", se o navegador aceita WebP.
	 *
	 * @return bool
	 */
	private function navegador_suporta_webp() {
		// O navegador informa os formatos aceitos no header "Accept".
		return isset( $_SERVER['HTTP_ACCEPT'] )
			&& strpos( $_SERVER['HTTP_ACCEPT'], 'image/webp' ) !== false;
	}
}
