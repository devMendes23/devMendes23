let display = document.getElementById('display');
let currentInput = '';
let previousInput = '';
let operator = null;

function appendNumber(number) {
    currentInput += number;
    display.value = currentInput;
}

function appendOperator(op) {
    if (currentInput === '') return;  // Não faz nada se não houver número para operar
    if (previousInput !== '') {
        calculateResult();
    }
    operator = op;
    previousInput = currentInput;
    currentInput = '';
}

function appendDecimal() {
    if (currentInput.includes('.')) return;  // Evita múltiplos pontos decimais
    currentInput += '.';
    display.value = currentInput;
}

function clearDisplay() {
    currentInput = '';
    previousInput = '';
    operator = null;
    display.value = '';
}

function calculateResult() {
    if (previousInput === '' || currentInput === '') return;

    let result;
    const prev = parseFloat(previousInput);
    const current = parseFloat(currentInput);

    switch (operator) {
        case '+':
            result = prev + current;
            break;
        case '-':
            result = prev - current;
            break;
        case '*':
            result = prev * current;
            break;
        case '/':
            if (current === 0) {
                alert('Erro: Divisão por zero');
                return;
            }
            result = prev / current;
            break;
        default:
            return;
    }

    display.value = result;
    currentInput = result.toString();
    previousInput = '';
    operator = null;
}