const liveRegion = document.getElementById('sr-live');
const flashes = document.querySelectorAll('[role="alert"]');
const resultsHeading = document.querySelector('.results__header h2');

if (flashes.length) {
    const text = Array.from(flashes).map(f => f.textContent.trim()).join('. ');
    setTimeout(() => { liveRegion.textContent = text; }, 100);
} else if (resultsHeading) {
    setTimeout(() => { liveRegion.textContent = resultsHeading.textContent; }, 100);
}

// --- Chip-based player entry ---
// Players are entered as removable chips. On submit the chips are serialized
// into the hidden fields the backend expects: `players` (newline string) and
// one `custom_pod[]` per custom table.
const form = document.getElementById('pod-form');
const remainingChips = document.getElementById('remaining-chips');
const customTables = document.getElementById('custom-tables');
const addTableBtn = document.getElementById('add-table');
const submitBtn = document.getElementById('form-submit');
const remainingCount = document.getElementById('remaining-count');

const announce = msg => { setTimeout(() => { liveRegion.textContent = msg; }, 50); };
const chipNames = container => Array.from(container.querySelectorAll('.chip')).map(c => c.dataset.name);

// Add one or more chips to a `.chips` container. Splits on newline/comma so a
// pasted list becomes many chips. Dedupes (case-insensitive) and honours a cap.
function addChips(container, rawValue, cap) {
    const input = container.querySelector('.chips__input');
    const names = rawValue.split(/[\n,]+/).map(s => s.trim()).filter(Boolean);
    let added = 0;
    for (const name of names) {
        if (cap && container.querySelectorAll('.chip').length >= cap) { announce(`Table full — max ${cap} players.`); break; }
        if (chipNames(container).some(n => n.toLowerCase() === name.toLowerCase())) continue;
        const chip = document.createElement('span');
        chip.className = 'chip';
        chip.dataset.name = name;
        chip.append(name + ' ');
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.setAttribute('aria-label', `Remove ${name}`);
        btn.textContent = '×';
        chip.append(btn);
        container.insertBefore(chip, input);
        added += 1;
    }
    if (added) announce(`${added} player${added > 1 ? 's' : ''} added.`);
    updateCount(container);
    return added;
}

// Keep a container's live count in sync: a custom table's "n/4" badge, or the
// remaining-players box's "n waiting" badge.
function updateCount(container) {
    const table = container.closest('.custom-table');
    if (table) {
        const n = container.querySelectorAll('.chip').length;
        const badge = table.querySelector('.custom-table__count');
        badge.textContent = `${n}/4`;
        badge.classList.toggle('is-full', n >= 4);
        refreshAddButton();
    } else if (container === remainingChips && remainingCount) {
        remainingCount.textContent = `${container.querySelectorAll('.chip').length} in pool`;
    }
}

// Wire an input + remove-delegation onto a `.chips` container.
function wireChips(container, cap) {
    const input = container.querySelector('.chips__input');
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); addChips(container, input.value, cap); input.value = ''; }
        else if (e.key === 'Backspace' && input.value === '') {
            const chips = container.querySelectorAll('.chip');
            if (chips.length) { announce(`${chips[chips.length - 1].dataset.name} removed.`); chips[chips.length - 1].remove(); updateCount(container); }
        }
    });
    input.addEventListener('paste', e => {
        const text = (e.clipboardData || window.clipboardData).getData('text');
        if (/[\n,]/.test(text)) { e.preventDefault(); addChips(container, text, cap); input.value = ''; }
    });
    container.addEventListener('click', e => {
        const btn = e.target.closest('.chip button');
        if (!btn) return;
        const chip = btn.closest('.chip');
        announce(`${chip.dataset.name} removed.`);
        chip.remove();
        updateCount(container);
    });
}

function renumberTables() {
    customTables.querySelectorAll('.custom-table').forEach((t, i) => {
        t.querySelector('.custom-table__name').lastChild.textContent = `Custom table ${i + 1}`;
    });
}

function addCustomTable() {
    const index = customTables.children.length + 1;

    const dot = document.createElement('span');
    dot.className = 'custom-table__dot';
    const name = document.createElement('span');
    name.className = 'custom-table__name';
    name.append(dot, `Custom table ${index}`);

    const count = document.createElement('span');
    count.className = 'custom-table__count';
    count.textContent = '0/4';

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'custom-table__remove';
    remove.textContent = 'Remove';

    const meta = document.createElement('div');
    meta.className = 'custom-table__meta';
    meta.append(count, remove);

    const head = document.createElement('div');
    head.className = 'custom-table__head';
    head.append(name, meta);

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'chips__input';
    input.placeholder = 'Add player…';
    input.setAttribute('aria-label', `Add player to custom table ${index}`);

    const chips = document.createElement('div');
    chips.className = 'chips';
    chips.append(input);

    const table = document.createElement('div');
    table.className = 'custom-table';
    table.append(head, chips);

    remove.addEventListener('click', () => { table.remove(); announce('Custom table removed.'); renumberTables(); refreshAddButton(); });
    customTables.append(table);
    wireChips(chips, 4);
    refreshAddButton();
    input.focus();
}

// Guard against empties: disable "Add custom table" while any table has no players yet.
function refreshAddButton() {
    const hasEmpty = Array.from(customTables.querySelectorAll('.custom-table'))
        .some(t => t.querySelectorAll('.chip').length === 0);
    addTableBtn.disabled = hasEmpty;
    addTableBtn.title = hasEmpty ? 'Add at least one player to the current table first' : '';
}

if (form) {
    wireChips(remainingChips);
    addTableBtn.addEventListener('click', addCustomTable);

    form.addEventListener('submit', e => {
        const triggered = e.submitter;
        if (triggered?.value === 'reset') {
            if (!confirm('Reset all rounds and start over?')) { e.preventDefault(); return; }
        }
        // Flush any text still sitting in a chip input into chips.
        form.querySelectorAll('.chips__input').forEach(inp => {
            if (inp.value.trim()) {
                addChips(inp.closest('.chips'), inp.value, inp.closest('.custom-table') ? 4 : null);
                inp.value = '';
            }
        });
        // Rebuild the hidden fields the backend reads.
        form.querySelectorAll('.serialized').forEach(n => n.remove());
        const players = document.createElement('input');
        players.type = 'hidden';
        players.name = 'players';
        players.className = 'serialized';
        players.value = chipNames(remainingChips).join('\n');
        form.append(players);
        customTables.querySelectorAll('.custom-table').forEach(t => {
            const names = chipNames(t);
            if (!names.length) return;
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'custom_pod[]';
            hidden.className = 'serialized';
            hidden.value = names.join('\n');
            form.append(hidden);
        });
        if (triggered === submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Loading…'; }
    });
}

// Theme toggle: default follows the OS (CSS); an explicit choice is stored
// and reapplied here. No stored value means "follow OS".
const root = document.documentElement;
const themeToggle = document.getElementById('theme-toggle');
const osPrefersDark = window.matchMedia('(prefers-color-scheme: dark)');

const stored = localStorage.getItem('theme');
if (stored === 'light' || stored === 'dark') root.dataset.theme = stored;

const activeTheme = () => root.dataset.theme || (osPrefersDark.matches ? 'dark' : 'light');

const updateToggle = () => {
    const dark = activeTheme() === 'dark';
    themeToggle.textContent = dark ? '☀' : '☾';
    themeToggle.setAttribute('aria-label', `Switch to ${dark ? 'light' : 'dark'} theme`);
};

themeToggle.addEventListener('click', () => {
    const next = activeTheme() === 'dark' ? 'light' : 'dark';
    root.dataset.theme = next;
    localStorage.setItem('theme', next);
    updateToggle();
});

osPrefersDark.addEventListener('change', () => { if (!root.dataset.theme) updateToggle(); });
updateToggle();
