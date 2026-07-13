/**
 * Calculadora — lógica de interface e cálculo.
 *
 * Encapsulada numa IIFE para não poluir o escopo global.
 * A interação acontece por eventos (delegação de clique + teclado),
 * usando os atributos data-* definidos no HTML.
 */
(() => {
    'use strict';

    const display = document.getElementById('display');
    const expressionEl = document.getElementById('expression');

    // Estado da calculadora.
    const state = {
        current: '0',      // número sendo digitado (string)
        previous: null,    // operando anterior (number) ou null
        operator: null,    // '+', '-', '*', '/' ou null
        justEvaluated: false, // true logo após pressionar '='
    };

    const OPERATOR_SYMBOLS = { '+': '+', '-': '−', '*': '×', '/': '÷' };

    /**
     * Arredonda para eliminar ruído de ponto flutuante (ex.: 0.1 + 0.2)
     * mantendo até 12 casas significativas, e formata para exibição.
     */
    function formatNumber(value) {
        if (!isFinite(value)) return 'Erro';
        // parseFloat(toPrecision) remove zeros à direita desnecessários.
        const rounded = parseFloat(value.toPrecision(12));
        return rounded.toString();
    }

    function render() {
        display.value = state.current;

        if (state.operator && state.previous !== null) {
            expressionEl.textContent =
                `${formatNumber(state.previous)} ${OPERATOR_SYMBOLS[state.operator]}`;
        } else {
            expressionEl.textContent = '';
        }
    }

    function inputNumber(digit) {
        if (state.justEvaluated) {
            // Começar um novo cálculo do zero após um resultado.
            state.current = digit;
            state.previous = null;
            state.operator = null;
            state.justEvaluated = false;
            return render();
        }

        // Evita zeros à esquerda ("007" -> "7").
        if (state.current === '0') {
            state.current = digit;
        } else {
            state.current += digit;
        }
        render();
    }

    function inputDecimal() {
        if (state.justEvaluated) {
            state.current = '0.';
            state.previous = null;
            state.operator = null;
            state.justEvaluated = false;
            return render();
        }
        if (!state.current.includes('.')) {
            state.current += '.';
        }
        render();
    }

    function chooseOperator(op) {
        // Encadeia operações: se já há uma pendente, calcula primeiro.
        // compute() move o resultado para `current`, então relemos abaixo.
        if (state.operator !== null && !state.justEvaluated) {
            compute();
        }
        if (state.current === 'Erro') return;
        state.previous = parseFloat(state.current);
        state.operator = op;
        state.current = '0';
        state.justEvaluated = false;
        render();
    }

    function compute() {
        if (state.operator === null || state.previous === null) return;

        const prev = state.previous;
        const curr = parseFloat(state.current);
        let result;

        switch (state.operator) {
            case '+': result = prev + curr; break;
            case '-': result = prev - curr; break;
            case '*': result = prev * curr; break;
            case '/':
                if (curr === 0) {
                    resetToError();
                    return;
                }
                result = prev / curr;
                break;
            default: return;
        }

        state.current = formatNumber(result);
        state.previous = null;
        state.operator = null;
    }

    function equals() {
        if (state.operator === null) return;
        compute();
        state.justEvaluated = true;
        render();
    }

    function percent() {
        const value = parseFloat(state.current);
        // Se há operação pendente, o percentual é relativo ao operando anterior.
        if (state.operator !== null && state.previous !== null) {
            state.current = formatNumber((state.previous * value) / 100);
        } else {
            state.current = formatNumber(value / 100);
        }
        render();
    }

    function toggleSign() {
        if (state.current === '0' || state.current === 'Erro') return;
        state.current = state.current.startsWith('-')
            ? state.current.slice(1)
            : '-' + state.current;
        render();
    }

    function backspace() {
        if (state.justEvaluated || state.current === 'Erro') {
            return clearAll();
        }
        if (state.current.length <= 1 ||
            (state.current.length === 2 && state.current.startsWith('-'))) {
            state.current = '0';
        } else {
            state.current = state.current.slice(0, -1);
        }
        render();
    }

    function clearAll() {
        state.current = '0';
        state.previous = null;
        state.operator = null;
        state.justEvaluated = false;
        render();
    }

    function resetToError() {
        state.current = 'Erro';
        state.previous = null;
        state.operator = null;
        state.justEvaluated = true;
        render();
    }

    // Se estiver em estado de erro, qualquer nova entrada limpa antes.
    function guardError() {
        if (state.current === 'Erro') clearAll();
    }

    // --- Ligações de eventos --------------------------------------------

    document.querySelector('.buttons').addEventListener('click', (event) => {
        const btn = event.target.closest('button');
        if (!btn) return;

        if (btn.dataset.number !== undefined) {
            guardError();
            return inputNumber(btn.dataset.number);
        }
        if (btn.dataset.operator !== undefined) {
            guardError();
            return chooseOperator(btn.dataset.operator);
        }

        switch (btn.dataset.action) {
            case 'decimal':   guardError(); return inputDecimal();
            case 'equals':    return equals();
            case 'clear':     return clearAll();
            case 'sign':      return toggleSign();
            case 'percent':   return percent();
            case 'backspace': return backspace();
        }
    });

    // Suporte a teclado.
    document.addEventListener('keydown', (event) => {
        const { key } = event;

        if (key >= '0' && key <= '9') { guardError(); return inputNumber(key); }
        if (key === '.' || key === ',') { guardError(); return inputDecimal(); }
        if (['+', '-', '*', '/'].includes(key)) { guardError(); return chooseOperator(key); }
        if (key === 'Enter' || key === '=') { event.preventDefault(); return equals(); }
        if (key === 'Backspace') { return backspace(); }
        if (key === 'Escape') { return clearAll(); }
        if (key === '%') { return percent(); }
    });

    render();
})();
