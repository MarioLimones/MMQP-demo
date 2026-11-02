<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('cuadre-form');
    const inputs = form.querySelectorAll('.calc-input');
    const shift = @json($turno);
    const format = cents => `${cents < 0 ? '-' : ''}${Math.floor(Math.abs(cents) / 100)}.${String(Math.abs(cents) % 100).padStart(2, '0')} €`;
    const show = (id, cents) => { const el = document.getElementById(id); if (el) el.textContent = format(cents); };
    function money(id) {
        const value = document.getElementById(id)?.value ?? '0';
        if (!/^\d{1,8}(\.\d{1,2})?$/.test(value)) throw new Error('Revisa los importes: usa como máximo dos decimales.');
        const [whole, decimal = ''] = value.split('.');
        return Number(whole) * 100 + Number(decimal.padEnd(2, '0'));
    }
    function calculate() {
        try {
            let cash = 0, deposit = 0, countsError = '';
            for (const [kind, prefix] of [['billetes', 'billete'], ['monedas', 'moneda']]) {
                let counted = 0, income = 0, invalid = false;
                form.querySelectorAll(`.${prefix}-total`).forEach(input => {
                    const validCount = !input.validity.badInput && (input.value === '' || /^\d{1,6}$/.test(input.value));
                    const countError = validCount ? '' : 'Las unidades deben ser enteros entre 0 y 999.999.';
                    input.setCustomValidity(countError);
                    input.setAttribute('aria-invalid', String(!validCount));
                    const count = Number(input.value);
                    const incomeInput = input.closest('tr').querySelector(`.${prefix}-ingreso`);
                    const incomeCount = incomeInput ? Number(incomeInput.value) : 0;
                    let incomeError = '';
                    if (incomeInput) {
                        incomeInput.max = validCount ? String(count) : '999999';
                        if (incomeInput.validity.badInput || (incomeInput.value !== '' && !/^\d{1,6}$/.test(incomeInput.value))) {
                            incomeError = 'Al ingreso: introduce unidades enteras entre 0 y 999.999.';
                        } else if (validCount && incomeCount > count) {
                            incomeError = `Al ingreso (${format(Number(input.dataset.cents))}): máximo ${count} unidades, según Total.`;
                        }
                        incomeInput.setCustomValidity(incomeError);
                        incomeInput.setAttribute('aria-invalid', String(Boolean(incomeError)));
                        const error = document.getElementById(`error-${incomeInput.id}`);
                        error.textContent = incomeError;
                        error.closest('[data-income-error-row]').hidden = !incomeError;
                    }
                    const rowInvalid = !validCount || Boolean(incomeError);
                    const remaining = input.closest('tr').querySelector(`.${prefix}-fondo`);
                    if (remaining) remaining.textContent = rowInvalid ? 'Pendiente' : count - incomeCount;
                    if (rowInvalid) {
                        invalid = true;
                        countsError ||= countError || incomeError;
                    } else {
                        counted += count * Number(input.dataset.cents);
                        income += incomeCount * Number(input.dataset.cents);
                    }
                });
                if (invalid) {
                    for (const suffix of ['total', 'ingreso', 'fondo']) {
                        const el = document.getElementById(`suma-${kind}-${suffix}`);
                        if (el) el.textContent = 'Pendiente';
                    }
                } else {
                    cash += counted; deposit += income;
                    show(`suma-${kind}-total`, counted); show(`suma-${kind}-ingreso`, income); show(`suma-${kind}-fondo`, counted - income);
                }
            }
            if (countsError) throw new Error(countsError);
            const additional = shift === 'intermedio' ? ['gastos', 'cambios', 'repartos', 'pedidos_hora_exacta', 'otros'].reduce((sum, id) => sum + money(id), 0) : 0;
            const total = cash + money('blisters-total') + money('nota_blanca') + additional;
            const fund = money('fondo_fijo'), sales = shift === 'intermedio' ? money('venta_parcial') : 0;
            const difference = total - deposit - fund - sales;
            if (Math.abs(total) > 9999999999 || Math.abs(difference) > 9999999999) throw new Error('El resultado supera el límite permitido.');
            show('total-caja', total); show('total-ingreso-text', deposit); show('total-fondo-text', total - deposit);
            show('subtotal-intermedio', total - fund); show('venta-parcial-text', sales); show('descuadre-text', difference);
            document.getElementById('preview-state').textContent = difference === 0 ? 'Cuadra en la previsualización.' : difference > 0 ? 'Sobrante en la previsualización.' : 'Faltante en la previsualización.';
        } catch (error) {
            for (const id of ['total-caja', 'total-ingreso-text', 'total-fondo-text', 'subtotal-intermedio', 'venta-parcial-text', 'descuadre-text']) {
                const el = document.getElementById(id); if (el) el.textContent = 'Pendiente';
            }
            document.getElementById('preview-state').textContent = error.message;
        }
    }
    inputs.forEach(input => input.addEventListener('input', calculate));
    const unitInputs = form.querySelectorAll('input[data-cents]');
    const restoreEmptyUnits = input => {
        if (input.value === '' && !input.validity.badInput) input.value = '0';
    };
    unitInputs.forEach(input => {
        const clearZero = () => {
            if (input.value === '0') {
                input.value = '';
                calculate();
            }
        };
        input.addEventListener('focus', clearZero);
        input.addEventListener('click', clearZero);
        input.addEventListener('blur', () => {
            restoreEmptyUnits(input);
            calculate();
        });
        input.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                restoreEmptyUnits(input);
                calculate();
            }
        });
    });
    document.getElementById('save-cuadre').addEventListener('click', () => {
        unitInputs.forEach(restoreEmptyUnits);
        calculate();
    });
    let submitting = false;
    const saveFeedback = document.getElementById('cuadre-save-feedback');
    form.addEventListener('submit', event => {
        if (submitting) { event.preventDefault(); return; }
        const button = document.getElementById('save-cuadre');
        if (navigator.onLine === false) {
            event.preventDefault();
            saveFeedback.hidden = false;
            saveFeedback.textContent = 'Sin conexión. El cuadre no se ha enviado y tus datos siguen aquí. Recupera la conexión y pulsa «Reintentar guardado».';
            button.textContent = 'Reintentar guardado';
            return;
        }
        submitting = true;
        saveFeedback.hidden = true;
        saveFeedback.textContent = '';
        button.disabled = true; button.textContent = 'Guardando…';
    });
    window.addEventListener('online', () => {
        if (!saveFeedback.hidden && !submitting) {
            saveFeedback.textContent = 'Conexión recuperada. Reintenta el guardado; tus datos se conservan.';
        }
    });
    window.addEventListener('pageshow', () => {
        submitting = false;
        const button = document.getElementById('save-cuadre');
        if (!document.getElementById('cuadre-conflict')) { button.disabled = false; button.textContent = @json($cuadre ? 'Guardar rectificación' : 'Guardar cuadre'); }
        calculate();
    });
    document.getElementById('error-summary')?.focus();
    calculate();
});
</script>
