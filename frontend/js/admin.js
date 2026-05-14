
const BASE = '../../backend';

async function apiFetch(url, options = {}) {
    const res = await fetch(url, { credentials: 'same-origin', ...options });
    if (res.status === 401) { location.href = 'admin-login.html'; return null; }
    if (res.status === 403) { showAlert('Acesso negado.', 'err'); return null; }
    return res.json();
}

function getCsrf() {
    return document.getElementById('csrf-token')?.value ?? '';
}

function showAlert(msg, type = 'ok') {
    const id = type === 'ok' ? 'alert-ok' : 'alert-err';
    let el = document.getElementById(id);
    if (!el) {
        el = document.createElement('div');
        el.id = id;
        el.className = 'admin-alert ' + (type === 'ok' ? 'admin-alert-ok' : 'admin-alert-err');
        document.querySelector('main').prepend(el);
    }
    el.textContent = msg;
    el.style.display = 'block';
    setTimeout(() => (el.style.display = 'none'), 4000);
}

function parseDbDate(s) {
    if (!s) return null;
    if (s instanceof Date) return s;
    const str = String(s);
    if (str.includes('T') || str.endsWith('Z')) return new Date(str);
    return new Date(str.replace(' ', 'T') + 'Z');
}

function fmt(dateStr) {
    const d = parseDbDate(dateStr);
    return d ? d.toLocaleDateString('pt-BR') : '—';
}

function fmtDateTime(dateStr) {
    const d = parseDbDate(dateStr);
    return d ? d.toLocaleString('pt-BR') : '—';
}

function esc(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

let _confirmResolve = null;
function adminConfirm(title, text) {
    return new Promise(resolve => {
        _confirmResolve = resolve;
        document.getElementById('modal-title').textContent = title;
        document.getElementById('modal-text').textContent  = text;
        document.getElementById('modal-backdrop').classList.add('open');
    });
}
document.getElementById('modal-cancel').addEventListener('click', () => {
    document.getElementById('modal-backdrop').classList.remove('open');
    _confirmResolve?.(false);
});
document.getElementById('modal-confirm').addEventListener('click', () => {
    document.getElementById('modal-backdrop').classList.remove('open');
    _confirmResolve?.(true);
});

document.querySelectorAll('.admin-tab').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.admin-tab').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
        window.scrollTo({ top: 0, behavior: 'instant' });
        if (btn.dataset.tab === 'overview')  loadStats();
        if (btn.dataset.tab === 'usuarios')  loadUsuarios(1);
        if (btn.dataset.tab === 'objetos')   loadObjetos(1);
        if (btn.dataset.tab === 'logs')      loadLogs(1);
        if (btn.dataset.tab === 'lgpd')      loadLgpd();
    });
});

document.getElementById('logo-link').addEventListener('click', e => {
    e.preventDefault();
    document.querySelector('[data-tab="overview"]').click();
});

document.getElementById('btn-logout').addEventListener('click', async () => {
    const csrf = getCsrf();
    await fetch(`${BASE}/auth/logout.php?csrf=${encodeURIComponent(csrf)}`, { credentials: 'same-origin' });
    location.href = 'admin-login.html';
});

async function loadStats() {
    const data = await apiFetch(`${BASE}/admin/stats.php`);
    if (!data?.success) return;
    const t = data.data.totais;
    document.getElementById('stats-grid').innerHTML = `
        <div class="stat-card green"><div class="label">Usuários ativos</div><div class="value">${t.usuarios_ativos}</div></div>
        <div class="stat-card"><div class="label">Total de usuários</div><div class="value">${t.total_usuarios}</div></div>
        <div class="stat-card"><div class="label">Total de objetos</div><div class="value">${t.total_objetos}</div></div>
        <div class="stat-card danger"><div class="label">Objetos roubados</div><div class="value">${t.objetos_roubados}</div></div>
        <div class="stat-card warn"><div class="label">Objetos perdidos</div><div class="value">${t.objetos_perdidos}</div></div>
        <div class="stat-card warn"><div class="label">Mensagens não lidas</div><div class="value">${t.mensagens_nao_lidas}</div></div>
        <div class="stat-card"><div class="label">Exclusões pendentes (LGPD)</div><div class="value">${t.exclusoes_pendentes}</div></div>
        <div class="stat-card"><div class="label">Administradores</div><div class="value">${t.total_admins}</div></div>
    `;
}

let uPage = 1;
async function loadUsuarios(page = 1) {
    uPage = page;
    const busca  = document.getElementById('u-busca').value;
    const status = document.getElementById('u-status').value;
    const params = new URLSearchParams({ page, per_page: 20, busca, status });
    const data = await apiFetch(`${BASE}/admin/usuarios.php?${params}`);
    if (!data?.success) return;
    const { usuarios, total, last_page } = data.data;
    const tbody = document.getElementById('u-tbody');
    if (!usuarios.length) {
        tbody.innerHTML = '<tr><td colspan="12" class="td-empty">Nenhum usuário encontrado.</td></tr>';
        document.getElementById('u-pagination').innerHTML = '';
        return;
    }
    tbody.innerHTML = usuarios.map(u => `
        <tr>
            <td>${u.id}</td>
            <td>${esc(u.name)}</td>
            <td>${esc(u.email)}</td>
            <td>${esc(u.cpf)}</td>
            <td><span class="badge ${u.is_active ? 'badge-green' : 'badge-red'}">${u.is_active ? 'Ativo' : 'Inativo'}</span></td>
            <td>${u.is_admin ? '<span class="badge badge-blue">Admin</span>' : '<span class="badge badge-gray">Usuário</span>'}</td>
            <td>${u.total_objetos}</td>
            <td>${u.objetos_normais}</td>
            <td>${u.objetos_roubados}</td>
            <td>${u.objetos_perdidos}</td>
            <td>${fmt(u.created_at)}</td>
            <td>
                ${u.is_admin ? '<span class="text-muted">—</span>' : `
                <select class="select-acoes js-acao-usuario" data-id="${u.id}">
                    <option value="">Ações…</option>
                    ${u.is_active
                        ? '<option value="desativar">Desativar</option>'
                        : '<option value="ativar">Ativar</option>'}
                    <option value="excluir">Excluir</option>
                </select>`}
            </td>
        </tr>
    `).join('');
    renderPagination('u-pagination', page, last_page, loadUsuarios);
}

async function acoesUsuario(id, sel) {
    const acao = sel.value;
    if (!acao) return;
    sel.value = '';
    const labels = { ativar:'Ativar usuário', desativar:'Desativar usuário', excluir:'Excluir usuário' };
    const ok = await adminConfirm(labels[acao], `Confirma "${labels[acao]}" para o usuário #${id}?`);
    if (!ok) return;
    const data = await apiFetch(`${BASE}/admin/usuarios.php`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, acao, csrf: getCsrf() }),
    });
    if (data?.success) { showAlert(data.data.mensagem, 'ok'); loadUsuarios(uPage); }
    else showAlert(data?.error ?? 'Erro ao executar ação.', 'err');
}

let uDebounce;
document.getElementById('u-busca').addEventListener('input', () => { clearTimeout(uDebounce); uDebounce = setTimeout(() => loadUsuarios(1), 400); });
document.getElementById('u-busca').addEventListener('search', () => { clearTimeout(uDebounce); loadUsuarios(1); });
document.getElementById('u-busca').addEventListener('keydown', e => { if (e.key === 'Enter') { clearTimeout(uDebounce); loadUsuarios(1); } });
document.getElementById('u-status').addEventListener('change', () => loadUsuarios(1));

document.getElementById('u-tbody').addEventListener('change', (e) => {
    const sel = e.target.closest('.js-acao-usuario');
    if (!sel) return;
    acoesUsuario(Number(sel.dataset.id), sel);
});

let oPage = 1;
const statusColors = { normal: 'badge-green', roubado: 'badge-red', perdido: 'badge-yellow' };

async function loadObjetos(page = 1) {
    oPage = page;
    const busca  = document.getElementById('o-busca').value;
    const status = document.getElementById('o-status').value;
    const params = new URLSearchParams({ page, per_page: 20, busca, status });
    const data = await apiFetch(`${BASE}/admin/objetos.php?${params}`);
    if (!data?.success) return;
    const { objetos, total, last_page } = data.data;
    const tbody = document.getElementById('o-tbody');
    if (!objetos.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="td-empty">Nenhum objeto encontrado.</td></tr>';
        document.getElementById('o-pagination').innerHTML = '';
        return;
    }
    tbody.innerHTML = objetos.map(o => `
        <tr>
            <td>${o.id}</td>
            <td title="${esc(o.descricao)}">${esc(o.descricao.slice(0,40))}${o.descricao.length>40?'…':''}</td>
            <td><code>${esc(o.serial_number)}</code></td>
            <td><span class="badge ${statusColors[o.status] ?? 'badge-gray'}">${o.status}</span></td>
            <td title="${esc(o.user_email)}">${esc(o.user_name)}</td>
            <td>${o.nfe_validada ? '<span class="badge badge-green">Validada</span>' : (o.nfe_chave ? '<span class="badge badge-yellow">Pendente</span>' : '—')}</td>
            <td>${o.score}</td>
            <td>${fmt(o.created_at)}</td>
            <td>
                <select class="btn-sm js-mudar-status" data-id="${o.id}"
                    style="padding:.28rem .5rem;border:1.5px solid var(--border);border-radius:6px;font-size:.75rem;font-family:inherit;background:var(--surface);color:var(--navy);">
                    <option value="">Mudar status…</option>
                    <option value="normal"  ${o.status==='normal'  ?'disabled':''}>Normal</option>
                    <option value="roubado" ${o.status==='roubado' ?'disabled':''}>Roubado</option>
                    <option value="perdido" ${o.status==='perdido' ?'disabled':''}>Perdido</option>
                </select>
                <button class="btn-sm btn-danger js-excluir-objeto" data-id="${o.id}">Excluir</button>
            </td>
        </tr>
    `).join('');
    renderPagination('o-pagination', page, last_page, loadObjetos);
}

async function alterarStatusObjeto(id, sel) {
    const novoStatus = sel.value;
    if (!novoStatus) return;
    sel.value = '';
    const ok = await adminConfirm('Alterar status', `Mudar objeto #${id} para "${novoStatus}"?`);
    if (!ok) return;
    const data = await apiFetch(`${BASE}/admin/objetos.php`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, acao: 'alterar_status', status: novoStatus, csrf: getCsrf() }),
    });
    if (data?.success) { showAlert(data.data.mensagem, 'ok'); loadObjetos(oPage); }
    else showAlert(data?.error ?? 'Erro.', 'err');
}

async function acoesObjeto(id, acao) {
    const ok = await adminConfirm('Excluir objeto', `Confirma exclusão do objeto #${id}?`);
    if (!ok) return;
    const data = await apiFetch(`${BASE}/admin/objetos.php`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, acao, csrf: getCsrf() }),
    });
    if (data?.success) { showAlert(data.data.mensagem, 'ok'); loadObjetos(oPage); }
    else showAlert(data?.error ?? 'Erro.', 'err');
}

let oDebounce;
document.getElementById('o-busca').addEventListener('input', () => { clearTimeout(oDebounce); oDebounce = setTimeout(() => loadObjetos(1), 400); });
document.getElementById('o-busca').addEventListener('search', () => { clearTimeout(oDebounce); loadObjetos(1); });
document.getElementById('o-busca').addEventListener('keydown', e => { if (e.key === 'Enter') { clearTimeout(oDebounce); loadObjetos(1); } });
document.getElementById('o-status').addEventListener('change', () => loadObjetos(1));

document.getElementById('o-tbody').addEventListener('change', (e) => {
    const sel = e.target.closest('.js-mudar-status');
    if (!sel) return;
    alterarStatusObjeto(Number(sel.dataset.id), sel);
});
document.getElementById('o-tbody').addEventListener('click', (e) => {
    const btn = e.target.closest('.js-excluir-objeto');
    if (!btn) return;
    acoesObjeto(Number(btn.dataset.id), 'excluir');
});

let lPage = 1;
async function loadLogs(page = 1) {
    lPage = page;
    const busca = document.getElementById('l-busca').value;
    const role  = document.getElementById('l-role').value;
    const params = new URLSearchParams({ page, per_page: 50, busca, role });
    const data = await apiFetch(`${BASE}/admin/logs.php?${params}`);
    if (!data?.success) return;
    const { logs, last_page } = data.data;
    const tbody = document.getElementById('l-tbody');
    if (!logs.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="td-empty">Nenhum log encontrado.</td></tr>';
        document.getElementById('l-pagination').innerHTML = '';
        return;
    }
    tbody.innerHTML = logs.map(l => `
        <tr>
            <td style="white-space:nowrap;">${fmtDateTime(l.created_at)}</td>
            <td><span class="badge ${l.role==='admin'?'badge-blue':'badge-gray'}">${l.role}</span></td>
            <td>${esc(l.user_email)}</td>
            <td><code style="font-size:.78rem;">${esc(l.action)}</code></td>
            <td>${l.entity ? esc(l.entity) + (l.entity_id ? ' #'+l.entity_id : '') : '—'}</td>
            <td>${esc(l.ip)}</td>
            <td style="font-size:.75rem;color:var(--muted);">${l.details ? esc(JSON.stringify(JSON.parse(l.details))).slice(0,80) : '—'}</td>
        </tr>
    `).join('');
    renderPagination('l-pagination', page, last_page, loadLogs);
}

let lDebounce;
document.getElementById('l-busca')?.addEventListener('input', () => { clearTimeout(lDebounce); lDebounce = setTimeout(() => loadLogs(1), 400); });
document.getElementById('l-busca')?.addEventListener('search', () => { clearTimeout(lDebounce); loadLogs(1); });
document.getElementById('l-busca')?.addEventListener('keydown', e => { if (e.key === 'Enter') { clearTimeout(lDebounce); loadLogs(1); } });
document.getElementById('l-role')?.addEventListener('change', () => loadLogs(1));

async function loadLgpd() {
    const data = await apiFetch(`${BASE}/admin/lgpd.php`);
    if (!data?.success) return;
    const { solicitacoes } = data.data;
    const tbody = document.getElementById('lgpd-tbody');
    if (!solicitacoes.length) {
        tbody.innerHTML = '<tr><td colspan="10" class="td-empty">Nenhuma solicitação de exclusão registrada.</td></tr>';
        return;
    }
    const typeLabels = { partial: 'Parcial', total: 'Total' };
    const typeBadge  = { partial: 'badge-yellow', total: 'badge-red' };
    tbody.innerHTML = solicitacoes.map(r => `
        <tr>
            <td>${r.id}</td>
            <td><span class="badge ${typeBadge[r.type] ?? 'badge-gray'}">${typeLabels[r.type] ?? r.type}</span></td>
            <td>${r.user_id}</td>
            <td>${esc(r.user_name)}</td>
            <td>${esc(r.user_email)}</td>
            <td>${esc(r.user_cpf)}</td>
            <td>${esc(r.ip)}</td>
            <td style="white-space:nowrap;">${fmtDateTime(r.requested_at)}</td>
            <td style="white-space:nowrap;">${fmt(r.purge_after)}</td>
            <td>${r.purged_at ? fmtDateTime(r.purged_at) : '<span class="badge badge-yellow">Pendente</span>'}</td>
        </tr>
    `).join('');
}

const _pageCallbacks = {};
function renderPagination(containerId, current, last, cb) {
    const el = document.getElementById(containerId);
    if (last <= 1) { el.innerHTML = ''; return; }
    _pageCallbacks[containerId] = cb;
    let html = `<span>Página ${current} de ${last}</span>`;
    html += `<button ${current===1?'disabled':''} data-page="${current-1}">‹ Anterior</button>`;
    for (let p = Math.max(1, current-2); p <= Math.min(last, current+2); p++) {
        html += `<button class="${p===current?'current':''}" data-page="${p}">${p}</button>`;
    }
    html += `<button ${current===last?'disabled':''} data-page="${current+1}">Próxima ›</button>`;
    el.innerHTML = html;
}

['u-pagination', 'o-pagination', 'l-pagination'].forEach(id => {
    document.getElementById(id)?.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-page]');
        if (!btn || btn.disabled) return;
        const cb = _pageCallbacks[id];
        if (cb) cb(Number(btn.dataset.page));
    });
});

(async () => {
    const csrf = await fetch(`${BASE}/auth/get_csrf.php`, { credentials: 'same-origin' })
        .then(r => r.json()).then(d => d.csrf ?? '').catch(() => '');
    const csrfEl = document.getElementById('csrf-token');
    if (csrfEl) csrfEl.value = csrf;

    const me = await apiFetch(`${BASE}/auth/me.php`);
    if (!me?.success) return;
    if (!me.data.is_admin) { location.href = 'dashboard.html'; return; }
    document.getElementById('admin-name').textContent = me.data.name;
    loadStats();
})();
