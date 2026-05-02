/* DRN.EKİN OTO — #panel-dk Hesaplama Kayıtları Paneli
 * Bağımlılıklar: DKStorage, DKWidget, DKOutput, DKCore.
 * Public: DKPanel.mount(containerEl), DKPanel.refresh()
 */
(function (root) {
  'use strict';

  const S = root.DKStorage;
  const W = root.DKWidget;
  const O = root.DKOutput;
  const C = root.DKCore;

  if (!S || !W || !O || !C) {
    console.error('[DKPanel] Bağımlılık eksik:', { S: !!S, W: !!W, O: !!O, C: !!C });
    return;
  }

  let containerEl = null;

  const TRY = (n) => (typeof n === 'number' ? n.toLocaleString('tr-TR', {maximumFractionDigits: 0}) : n);
  const CURR = (n) => (typeof n === 'number' ? TRY(n) + ' ₺' : n);

  function buildHtml() {
    const records = S.list();
    const summaries = records.map(S.summarize);
    const empty = summaries.length === 0;

    return `
      <div id="panel-dk" class="dk-panel">
        <div class="dk-panel-head">
          <div>
            <h2 class="dk-panel-title">📁 Hesaplama Kayıtları</h2>
            <p class="dk-panel-sub">${empty ? 'Henüz kayıtlı dosya yok.' : `Toplam ${summaries.length} kayıt`}</p>
          </div>
          <div class="dk-panel-actions">
            <button class="dk-add-btn" data-action="new-record" type="button">
              <span class="dk-add-icon">+</span> Yeni Dosya Ekle
            </button>
          </div>
        </div>

        ${empty ? renderEmpty() : renderTable(summaries)}
      </div>
    `;
  }

  function renderEmpty() {
    return `
      <div class="dk-empty-state">
        <div class="dk-empty-icon">📋</div>
        <h3>Henüz Hesaplama Kaydı Yok</h3>
        <p>Yukarıdaki "Yeni Dosya Ekle" butonu ile ilk değer kaybı dosyanızı oluşturun.</p>
      </div>
    `;
  }

  function renderTable(rows) {
    return `
      <div class="table-wrap" data-table="dk-records">
        <div class="table-scroll-hint">← Sağa kaydır →</div>
        <table class="dk-records-table">
          <thead>
            <tr>
              <th class="col-sticky col-plate">Plaka</th>
              <th class="col-date">Tarih</th>
              <th class="col-brand">Marka / Model</th>
              <th class="col-year">Yıl</th>
              <th class="col-group">Grup</th>
              <th class="col-num">Rayiç</th>
              <th class="col-num">Hasar</th>
              <th class="col-num">KM</th>
              <th class="col-parts">Parça</th>
              <th class="col-num col-total">Değer Kaybı</th>
              <th class="col-actions col-sticky-right">İşlemler</th>
            </tr>
          </thead>
          <tbody>
            ${rows.map(r => `
              <tr data-record-id="${r.id}">
                <td class="col-sticky col-plate"><strong>${escape(r.plate)}</strong></td>
                <td class="col-date">${r.date}<br><small>${r.time}</small></td>
                <td class="col-brand">${escape(r.brand)}</td>
                <td class="col-year">${escape(r.modelYear)}</td>
                <td class="col-group"><span class="dk-badge">${escape(r.group)}</span></td>
                <td class="col-num">${CURR(r.price)}</td>
                <td class="col-num">${CURR(r.damage)}</td>
                <td class="col-num">${TRY(r.km)} km</td>
                <td class="col-parts">${r.partsCount}</td>
                <td class="col-num col-total"><strong>${CURR(r.total)}</strong></td>
                <td class="col-actions col-sticky-right">
                  <button class="dk-icon-btn" data-action="view" data-id="${r.id}" title="Detay görüntüle" type="button">👁</button>
                  <button class="dk-icon-btn" data-action="edit" data-id="${r.id}" title="Düzenle" type="button">✏️</button>
                  <button class="dk-icon-btn dk-icon-danger" data-action="delete" data-id="${r.id}" title="Sil" type="button">🗑</button>
                </td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    `;
  }

  function escape(s) {
    if (s == null) return '—';
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  function bind() {
    if (!containerEl) return;

    containerEl.querySelectorAll('[data-action]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const action = btn.dataset.action;
        const id = btn.dataset.id;
        switch (action) {
          case 'new-record':
            W.launchNew();
            break;
          case 'edit':
            W.launchEdit(id);
            break;
          case 'view':
            showDetail(id);
            break;
          case 'delete':
            if (confirm('Bu kaydı silmek istediğinize emin misiniz?')) {
              S.remove(id);
              refresh();
            }
            break;
        }
      });
    });
  }

  function showDetail(id) {
    const rec = S.get(id);
    if (!rec) return;
    const result = C.calculate(rec);
    const html = O.renderReport(rec, result);

    // Detail modal
    const modal = document.createElement('div');
    modal.className = 'dk-modal-backdrop';
    modal.innerHTML = `
      <div class="dk-modal">
        <div class="dk-modal-head">
          <h3>Dosya Detayı</h3>
          <div class="dk-modal-tools">
            <button data-modal-action="print" class="dk-btn">🖨 Yazdır</button>
            <button data-modal-action="pdf" class="dk-btn">📄 PDF</button>
            <button data-modal-action="edit" class="dk-btn">✏️ Düzenle</button>
            <button data-modal-action="close" class="dk-btn dk-btn-ghost">Kapat</button>
          </div>
        </div>
        <div class="dk-modal-body">${html}</div>
      </div>
    `;
    document.body.appendChild(modal);
    requestAnimationFrame(() => modal.classList.add('open'));

    const close = () => {
      modal.classList.remove('open');
      setTimeout(() => modal.remove(), 200);
    };
    modal.addEventListener('click', (e) => {
      if (e.target === modal) close();
      const a = e.target.closest('[data-modal-action]');
      if (!a) return;
      const action = a.dataset.modalAction;
      if (action === 'close') close();
      else if (action === 'print') O.printReport(rec, result);
      else if (action === 'pdf') O.exportPdf(rec, result);
      else if (action === 'edit') { close(); W.launchEdit(id); }
    });
    document.addEventListener('keydown', function onEsc(e) {
      if (e.key === 'Escape') { close(); document.removeEventListener('keydown', onEsc); }
    });
  }

  function mount(target) {
    containerEl = (typeof target === 'string') ? document.querySelector(target) : target;
    if (!containerEl) {
      console.error('[DKPanel] mount target bulunamadı');
      return;
    }
    refresh();
  }

  function refresh() {
    if (!containerEl) return;
    containerEl.innerHTML = buildHtml();
    bind();
  }

  root.DKPanel = { mount, refresh };
})(window);
