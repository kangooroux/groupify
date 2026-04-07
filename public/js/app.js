const list = document.getElementById('custom-pods');
const addBtn = document.getElementById('add-pod');

function checkPodSize(textarea) {
    const count = textarea.value.split('\n').filter(l => l.trim() !== '').length;
    const pod = textarea.closest('.custom-pod');
    let warning = pod.querySelector('.custom-pod__warning');
    if (count > 4) {
        if (!warning) {
            warning = document.createElement('p');
            warning.className = 'custom-pod__warning';
            warning.textContent = 'Max 4 players per table — extras will be ignored.';
            pod.appendChild(warning);
        }
    } else if (warning) {
        warning.remove();
    }
}

addBtn.addEventListener('click', () => {
    const textarea = document.createElement('textarea');
    textarea.name = 'custom_pod[]';
    textarea.className = 'form__textarea custom-pod__textarea';
    textarea.rows = 4;
    textarea.maxLength = 500;
    textarea.setAttribute('aria-label', 'Custom table players');
    textarea.placeholder = 'Alice\nBob\nCharlie\nDave';

    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'custom-pod__remove';
    removeBtn.setAttribute('aria-label', 'Remove table');
    removeBtn.textContent = '✕';

    const row = document.createElement('div');
    row.className = 'custom-pod';
    row.appendChild(textarea);
    row.appendChild(removeBtn);
    list.appendChild(row);
});

list.addEventListener('click', e => {
    const btn = e.target.closest('.custom-pod__remove');
    if (btn) {
        const rows = list.querySelectorAll('.custom-pod');
        if (rows.length > 1) btn.closest('.custom-pod').remove();
        else {
            const ta = btn.closest('.custom-pod').querySelector('textarea');
            ta.value = '';
            checkPodSize(ta);
        }
    }
});

list.addEventListener('input', e => {
    if (e.target.matches('.custom-pod__textarea')) {
        checkPodSize(e.target);
    }
});
