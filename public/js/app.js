const liveRegion = document.getElementById('sr-live');
const flashes = document.querySelectorAll('[role="alert"]');
const resultsHeading = document.querySelector('.results__header h2');

if (flashes.length) {
    const text = Array.from(flashes).map(f => f.textContent.trim()).join('. ');
    setTimeout(() => { liveRegion.textContent = text; }, 100);
} else if (resultsHeading) {
    setTimeout(() => { liveRegion.textContent = resultsHeading.textContent; }, 100);
}

const validatedList = document.getElementById('validated-pods');
const addBtn = document.getElementById('add-pod');
const podInput = document.getElementById('custom-pod-input');
const podWarning = document.getElementById('pod-warning');
const submitBtn = document.getElementById('form-submit');

podInput.addEventListener('input', () => {
    const count = podInput.value.split('\n').filter(l => l.trim() !== '').length;
    podWarning.hidden = count <= 4;
});

addBtn.addEventListener('click', () => {
    const players = podInput.value.split('\n').map(l => l.trim()).filter(Boolean);
    if (players.length === 0) return;

    const index = validatedList.children.length + 1;

    const title = document.createElement('h3');
    title.className = 'validated-pod__title';
    title.textContent = `Custom Table ${index}`;

    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'validated-pod__remove';
    removeBtn.setAttribute('aria-label', `Remove Custom Table ${index}`);
    removeBtn.textContent = '✕';

    const header = document.createElement('div');
    header.className = 'validated-pod__header';
    header.appendChild(title);
    header.appendChild(removeBtn);

    const ul = document.createElement('ul');
    ul.className = 'validated-pod__list';
    players.forEach(name => {
        const li = document.createElement('li');
        li.className = 'validated-pod__player';
        li.textContent = name;
        ul.appendChild(li);
    });

    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'custom_pod[]';
    hidden.value = players.join('\n');

    const card = document.createElement('div');
    card.className = 'validated-pod';
    card.appendChild(header);
    card.appendChild(ul);
    card.appendChild(hidden);

    validatedList.appendChild(card);

    podInput.value = '';
    podWarning.hidden = true;
    podInput.focus();
});

validatedList.addEventListener('click', e => {
    const btn = e.target.closest('.validated-pod__remove');
    if (!btn) return;
    if (!confirm('Remove this custom table?')) return;
    btn.closest('.validated-pod').remove();
    validatedList.querySelectorAll('.validated-pod').forEach((pod, i) => {
        pod.querySelector('.validated-pod__title').textContent = `Custom Table ${i + 1}`;
        pod.querySelector('.validated-pod__remove').setAttribute('aria-label', `Remove Custom Table ${i + 1}`);
    });
});

submitBtn.closest('form').addEventListener('submit', e => {
    const triggered = e.submitter;
    if (triggered?.value === 'reset') {
        if (!confirm('Reset all rounds and start over?')) {
            e.preventDefault();
            return;
        }
    }
    if (triggered === submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Loading…';
    }
});
