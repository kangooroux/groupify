const list = document.getElementById('custom-pods');
const addBtn = document.getElementById('add-pod');

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
        else btn.closest('.custom-pod').querySelector('textarea').value = '';
    }
});
