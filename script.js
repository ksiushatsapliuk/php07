document.addEventListener('DOMContentLoaded', () => {
    const body = document.getElementById('plantTableBody');
    const search = document.getElementById('search');
    const dueOnly = document.getElementById('onlyNeedsWater');
    const form = document.getElementById('addPlantForm');
    const csrfToken = form.elements.csrf.value;
    let plants = [];
    let requestNumber = 0;
    let searchTimer;

    // Дані користувача вставляються лише через textContent, без innerHTML.
    function cell(row, text) {
        const td = document.createElement('td');
        td.textContent = text;
        row.append(td);
        return td;
    }
    function message(text, error = false) {
        const row = document.createElement('tr');
        const td = cell(row, text);
        td.colSpan = 8;
        td.style.textAlign = 'center';
        if (error) row.setAttribute('role', 'alert');
        body.replaceChildren(row);
    }
    async function request(url, options = {}) {
        let response;
        if (options.method === 'POST') {
            options.headers = {...options.headers, 'X-CSRF-Token': csrfToken};
        }
        try { response = await fetch(url, options); }
        catch { throw new Error('Немає зв’язку із сервером. Перевірте підключення та повторіть дію.'); }
        let data;
        try { data = await response.json(); }
        catch { throw new Error('Сервер повернув некоректну відповідь. Перевірте PHP і підключення до бази даних.'); }
        if (!response.ok || data.success !== true) throw new Error(data.error || 'Не вдалося виконати запит.');
        return data;
    }
    function render() {
        body.replaceChildren();
        if (!plants.length) {
            message(search.value.trim() ? 'Нічого не знайдено.' : dueOnly.checked ? 'Усі рослини политі. Полив поки не потрібен.' : 'Рослин поки немає. Додайте першу!');
            return;
        }
        plants.forEach((p, index) => {
            const row = document.createElement('tr');
            row.dataset.index = index;
            row.dataset.name = p.name;
            row.dataset.days = p.days_since_watered;
            cell(row, p.id);
            const name = cell(row, '');
            const strong = document.createElement('strong'); strong.textContent = p.name;
            const light = document.createElement('span'); light.className = 'plant-light';
            light.textContent = 'Світло: ' + (p.is_succulent ? p.sunlight_needs : 'Розсіяне світло');
            name.append(strong, light);
            cell(row, p.watering_interval_days);
            cell(row, p.last_watered_at);
            cell(row, p.days_since_watered);
            cell(row, p.next_watering);
            const badge = document.createElement('span');
            badge.className = 'badge ' + (p.needs_water ? 'badge-alert' : 'badge-ok');
            badge.textContent = p.needs_water ? 'Потребує поливу' : 'Зволожена';
            cell(row, '').append(badge);
            const actions = cell(row, ''); actions.className = 'actions';
            function link(text, href) {
                const a = document.createElement('a'); a.textContent = text; a.href = href;
                actions.append(a); return a;
            }
            if (p.needs_water) {
                const water = link('Полити', '#');
                water.addEventListener('click', async event => {
                    event.preventDefault();
                    if (water.dataset.busy) return;
                    water.dataset.busy = '1'; water.textContent = 'Поливаємо…';
                    try {
                        const values = new FormData(); values.set('id', p.id);
                        await request('api.php?resource=plants&action=water', {method: 'POST', body: values});
                        await load();
                    } catch (error) { message(error.message, true); }
                });
            }
            link('Редагувати', 'edit.php?id=' + p.id);
            const remove = link('Видалити', '#'); remove.className = 'danger';
            remove.addEventListener('click', event => {
                event.preventDefault();
                if (!confirm('Видалити цю рослину?')) return;
                const deletion = document.createElement('form');
                deletion.method = 'POST'; deletion.action = 'delete.php';
                for (const [name, value] of Object.entries({id: p.id, csrf: csrfToken})) {
                    const input = document.createElement('input');
                    input.type = 'hidden'; input.name = name; input.value = value; deletion.append(input);
                }
                document.body.append(deletion); deletion.submit();
            });
            body.append(row);
        });
        body.dispatchEvent(new Event('plants:updated'));
    }
    async function load() {
        const current = ++requestNumber;
        body.setAttribute('aria-busy', 'true');
        try {
            const url = 'api.php?resource=plants&q=' + encodeURIComponent(search.value.trim())
                + '&due=' + (dueOnly.checked ? '1' : '0');

            const {data, meta} = await request(url);
            if (current !== requestNumber) return;
            if (!Array.isArray(data) || !meta || !Number.isInteger(meta.total) || !Number.isInteger(meta.needs_water)) {
                throw new Error('Сервер повернув некоректний список рослин або лічильники.');
            }
            plants = data;
            document.getElementById('needsWaterCount').textContent = meta.needs_water;
            document.getElementById('plantCount').textContent = 'із ' + meta.total + ' рослин';
            const icon = document.getElementById('summaryIcon');
            icon.replaceChildren();
            if (meta.needs_water > 0) {
                const image = document.createElement('img'); image.src = 'watering-can.png'; image.alt = 'Полив'; image.className = 'summary-icon-img'; icon.append(image);
            } else icon.textContent = '✨';
            render();
        } catch (error) { if (current === requestNumber) message(error.message, true); }
        finally { if (current === requestNumber) body.setAttribute('aria-busy', 'false'); }
    }
    search.addEventListener('input', () => {
        ++requestNumber; // Попередня відповідь не повинна підмінити новий пошук.
        clearTimeout(searchTimer); searchTimer = setTimeout(load, 200);
    });
    document.getElementById('searchForm').addEventListener('submit', event => {
        event.preventDefault(); clearTimeout(searchTimer); load();
    });
    dueOnly.addEventListener('change', () => { clearTimeout(searchTimer); load(); });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        if (button.disabled) return;
        button.disabled = true;
        try {
            await request('api.php?resource=plants', {method: 'POST', body: new FormData(form)});
            form.reset();
            // Новий запис одразу видно, навіть якщо до додавання діяв фільтр.
            search.value = ''; dueOnly.checked = false; clearTimeout(searchTimer);
            await load();
        } catch (error) { message(error.message, true); }
        finally { button.disabled = false; }
    });
    search.value = new URLSearchParams(location.search).get('q') || '';
    message('Завантаження рослин…');
    load();
});
