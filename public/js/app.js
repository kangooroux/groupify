function reindexPods() {
    document.querySelectorAll('#pods-list .pod-entry__title').forEach((el, i) => {
        el.textContent = 'Custom pod ' + (i + 1);
    });
}

document.getElementById('add-pod').addEventListener('click', () => {
    const tpl = document.getElementById('pod-template').content.cloneNode(true);
    document.getElementById('pods-list').appendChild(tpl);
    reindexPods();
});

document.getElementById('pods-list').addEventListener('click', (e) => {
    if (e.target.classList.contains('pod-entry__remove')) {
        e.target.closest('.pod-entry').remove();
        reindexPods();
    }
});

const MAX_PLAYERS_PER_POD = parseInt(document.getElementById('pods-list').dataset.maxPlayers, 10);

document.getElementById('pods-list').addEventListener('input', (e) => {
    if (!e.target.classList.contains('pod-entry__players')) return;
    const count = e.target.value.split('\n').filter(l => l.trim() !== '').length;
    e.target.nextElementSibling.hidden = count <= MAX_PLAYERS_PER_POD;
});

document.querySelector('.form').addEventListener('submit', (e) => {
    const invalid = [...document.querySelectorAll('.pod-entry__players')]
        .some(ta => ta.value.split('\n').filter(l => l.trim() !== '').length > MAX_PLAYERS_PER_POD);
    if (invalid) e.preventDefault();
});

const clearHistoryForm = document.getElementById('clear-history-form');
if (clearHistoryForm) {
    clearHistoryForm.addEventListener('submit', (e) => {
        if (!confirm('Are you sure you want to reset all rounds? This cannot be undone.')) {
            e.preventDefault();
        }
    });
}
