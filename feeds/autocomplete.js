/**
 * feeds/autocomplete.js
 */

const autocompleteState = {
    menu: null,
    type: '', 
    query: '',
    active: false,
    currentInput: null
};

async function handleAutocomplete(e) {
    const input = e.target;
    const val = input.value;
    const cursorPos = input.selectionStart;
    const textBeforeCursor = val.slice(0, cursorPos);
    const words = textBeforeCursor.split(/[\s\n]/);
    const lastWord = words[words.length - 1];

    if (lastWord.startsWith('@') || lastWord.startsWith('#')) {
        autocompleteState.type = lastWord.startsWith('@') ? 'user' : 'tag';
        autocompleteState.query = lastWord.slice(1);
        autocompleteState.currentInput = input;

        if (autocompleteState.query.length >= 1) {
            try {
                // Fetch from the local feeds directory
                const response = await fetch(`autocomplete.php?type=${autocompleteState.type}&q=${autocompleteState.query}`);
                const data = await response.json();
                
                if (data && data.length > 0) {
                    renderAutocompleteMenu(input, data);
                } else {
                    hideAutocompleteMenu();
                }
            } catch (err) { console.error("Autocomplete fetch failed:", err); }
        } else {
            hideAutocompleteMenu();
        }
    } else {
        hideAutocompleteMenu();
    }
}

function renderAutocompleteMenu(input, items) {
    if (!autocompleteState.menu) {
        autocompleteState.menu = document.createElement('div');
        // Standardized styles matching your dark theme
        autocompleteState.menu.className = 'absolute z-[99999] bg-[#1A1A1B] border border-[#343435] rounded-xl shadow-2xl overflow-hidden min-w-[200px] block';
        document.body.appendChild(autocompleteState.menu);
    }

    const rect = input.getBoundingClientRect();
    const scrollY = window.pageYOffset || document.documentElement.scrollTop;
    
    autocompleteState.menu.style.left = `${rect.left}px`;
    autocompleteState.menu.style.top = `${rect.bottom + scrollY + 5}px`;

    autocompleteState.menu.innerHTML = items.map(item => `
        <div class="px-4 py-3 text-sm text-[#D7DADC] cursor-pointer hover:bg-blue-600 transition flex items-center gap-2 border-b border-[#2D2D2E] last:border-0" 
             onmousedown="selectSuggestion('${item.label}')">
            <span class="${autocompleteState.type === 'user' ? 'text-blue-400' : 'text-orange-400'} font-bold">
                ${autocompleteState.type === 'user' ? '@' : '#'}
            </span>
            <span>${item.label}</span>
        </div>
    `).join('');
    
    autocompleteState.menu.classList.remove('hidden');
    autocompleteState.active = true;
}

function selectSuggestion(value) {
    const input = autocompleteState.currentInput;
    if (!input) return;

    const val = input.value;
    const cursorPos = input.selectionStart;
    const textBefore = val.slice(0, cursorPos);
    const textAfter = val.slice(cursorPos);
    
    const words = textBefore.split(/[\s\n]/);
    words[words.length - 1] = (autocompleteState.type === 'user' ? '@' : '#') + value + ' ';
    
    input.value = words.join(' ') + textAfter;
    hideAutocompleteMenu();
    input.focus();
}

function hideAutocompleteMenu() {
    if (autocompleteState.menu) autocompleteState.menu.classList.add('hidden');
    autocompleteState.active = false;
}

// Global initialization function
window.initAutocomplete = function() {
    // Selects the search bar, comment inputs, and post textareas
    const selectors = 'input[type="text"], textarea, input[name="search"]';
    const inputs = document.querySelectorAll(selectors);
    inputs.forEach(input => {
        input.removeEventListener('input', handleAutocomplete);
        input.addEventListener('input', handleAutocomplete);
    });
};

// Close menu when clicking outside
document.addEventListener('mousedown', (e) => {
    if (autocompleteState.active && !autocompleteState.menu.contains(e.target)) {
        hideAutocompleteMenu();
    }
});
