/*
 * admin.js
 * --------------------------------------------------------------------------
 * Controla a conversão em massa na tela de administração.
 *
 * Fluxo:
 *   1. Usuário clica em "Iniciar conversão em massa".
 *   2. Buscamos no servidor a lista de IDs de imagens (AJAX).
 *   3. Dividimos os IDs em lotes e enviamos um lote por vez.
 *   4. A cada lote concluído, atualizamos a barra de progresso.
 *
 * Usamos jQuery porque ele já vem com o WordPress no admin.
 */

(function ($) {
	'use strict';

	// Quantas imagens enviamos por requisição (deve combinar com o servidor).
	var TAMANHO_LOTE = 5;

	// Espera o documento estar pronto.
	$(function () {

		var $botao    = $('#wgc-iniciar-bulk');     // Botão de iniciar.
		var $wrap     = $('#wgc-progresso-wrap');   // Container da barra.
		var $barra    = $('#wgc-barra-interna');    // Barra interna (preenchimento).
		var $status   = $('#wgc-status');           // Texto de status.

		// Quando o botão for clicado, inicia o processo.
		$botao.on('click', function () {
			iniciarConversao();
		});

		/**
		 * Passo 1: pede ao servidor a lista de imagens a converter.
		 */
		function iniciarConversao() {

			// Desabilita o botão e mostra a área de progresso.
			$botao.prop('disabled', true);
			$wrap.show();
			$status.text('Contando imagens…');

			$.post(WGC_DADOS.ajax_url, {
				action: 'wgc_contar_imagens',   // Endpoint AJAX no PHP.
				nonce: WGC_DADOS.nonce           // Token de segurança.
			})
			.done(function (resposta) {
				if (resposta.success && resposta.data.total > 0) {
					// Inicia o processamento em lotes com a lista recebida.
					processarLotes(resposta.data.ids);
				} else {
					// Não há imagens para converter.
					$status.text('Nenhuma imagem JPG/PNG encontrada.');
					$botao.prop('disabled', false);
				}
			})
			.fail(function () {
				$status.text('Erro ao consultar o servidor.');
				$botao.prop('disabled', false);
			});
		}

		/**
		 * Passo 2: processa a lista de IDs em lotes, recursivamente.
		 *
		 * @param {Array} ids Lista completa de IDs a converter.
		 */
		function processarLotes(ids) {

			var total      = ids.length; // Total de imagens.
			var processadas = 0;          // Quantas já foram convertidas.

			// Função interna que envia o próximo lote.
			function enviarProximoLote() {

				// Se não restam IDs, terminamos.
				if (ids.length === 0) {
					$status.text('Concluído! ' + total + ' imagens convertidas.');
					$botao.prop('disabled', false);
					return;
				}

				// Retira do início da lista os IDs deste lote.
				var lote = ids.splice(0, TAMANHO_LOTE);

				$.post(WGC_DADOS.ajax_url, {
					action: 'wgc_converter_lote',
					nonce: WGC_DADOS.nonce,
					ids: lote
				})
				.done(function (resposta) {
					if (resposta.success) {
						// Atualiza o contador e a barra de progresso.
						processadas += resposta.data.convertidas;
						atualizarProgresso(processadas, total);
					}
					// Continua com o próximo lote (mesmo se um falhar, segue).
					enviarProximoLote();
				})
				.fail(function () {
					// Em caso de falha de rede, tenta continuar mesmo assim.
					enviarProximoLote();
				});
			}

			// Dispara o primeiro lote.
			enviarProximoLote();
		}

		/**
		 * Passo 3: atualiza visualmente a barra e o texto de progresso.
		 *
		 * @param {number} feitas Quantidade já convertida.
		 * @param {number} total  Quantidade total.
		 */
		function atualizarProgresso(feitas, total) {
			var porcentagem = Math.round((feitas / total) * 100);
			$barra.css('width', porcentagem + '%');
			$status.text('Convertendo… ' + feitas + ' de ' + total + ' (' + porcentagem + '%)');
		}
	});

})(jQuery);
