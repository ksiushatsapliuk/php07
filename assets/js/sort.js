document.addEventListener('DOMContentLoaded', () => {
    const sortSelect = document.getElementById('sortSelect');
    const tableBody = document.getElementById('plantTableBody');
    if (!sortSelect || !tableBody) return;

    const SORT_KEY = 'floracare_sort_preference';
    const validSorts = ['default', 'name', 'days'];

    function applySorting(sortType) {
        const rows = Array.from(tableBody.querySelectorAll('tr[data-index]'));
        rows.sort((a, b) => {
            const originalOrder = Number(a.dataset.index) - Number(b.dataset.index);
            if (sortType === 'name') {
                return a.dataset.name.trim().localeCompare(b.dataset.name.trim(), 'uk', {
                    sensitivity: 'base'
                }) || originalOrder;
            }
            if (sortType === 'days') {
                return Number(b.dataset.days) - Number(a.dataset.days) || originalOrder;
            }
            return originalOrder;
        });
        rows.forEach(row => tableBody.appendChild(row));
    }

    // Сортування працює і тоді, коли браузер забороняє localStorage.
    let savedSort = 'default';
    try {
        const storedSort = localStorage.getItem(SORT_KEY);
        if (validSorts.includes(storedSort)) savedSort = storedSort;
    } catch {
        // Залишаємо початковий порядок, якщо сховище недоступне.
    }
    sortSelect.value = savedSort;
    applySorting(savedSort);

    tableBody.addEventListener('plants:updated', () => applySorting(sortSelect.value));

    sortSelect.addEventListener('change', () => {
        const selectedSort = sortSelect.value;
        applySorting(selectedSort);
        try {
            localStorage.setItem(SORT_KEY, selectedSort);
        } catch {
            // Вибраний порядок діє до наступного завантаження сторінки.
        }
    });
});
