(async function () {
  const resultsEl = document.getElementById('results');
  const pages = ['../index.html', '../hizmetler.html'];
  const results = [];

  async function fetchText(url) {
    const res = await fetch(url, { cache: 'reload' });
    if (!res.ok) throw new Error(`HTTP ${res.status} for ${url}`);
    return await res.text();
  }

  async function checkImage(url) {
    const res = await fetch(url, { cache: 'reload' });
    return res.ok;
  }

  function extractImages(html, baseUrl) {
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    const imgs = Array.from(doc.querySelectorAll('img[src]')).map(img => {
      const src = img.getAttribute('src');
      const a = document.createElement('a');
      a.href = src;
      let abs = a.href;
      if (!/^(https?:)?\/\//.test(src)) {
        const base = new URL(baseUrl, window.location.href);
        abs = new URL(src, base).href;
      }
      return { src, abs };
    });
    return imgs;
  }

  for (const page of pages) {
    const pageResult = { page, passes: [], failures: [] };
    try {
      const html = await fetchText(page);
      const imgs = extractImages(html, page);
      for (const img of imgs) {
        try {
          const ok = await checkImage(img.abs);
          if (ok) pageResult.passes.push(`OK ${img.src}`);
          else pageResult.failures.push(`FAIL ${img.src}`);
        } catch (e) {
          pageResult.failures.push(`ERR ${img.src}: ${e.message}`);
        }
      }
    } catch (e) {
      pageResult.failures.push(`Page fetch failed: ${e.message}`);
    }
    results.push(pageResult);
  }

  const totalFails = results.reduce((n, r) => n + r.failures.length, 0);
  const lines = [];
  for (const r of results) {
    lines.push(`Page: ${r.page}`);
    r.passes.forEach(p => lines.push(`  ✓ ${p}`));
    r.failures.forEach(f => lines.push(`  ✗ ${f}`));
  }
  resultsEl.innerHTML = '';
  const pre = document.createElement('pre');
  pre.textContent = lines.join('\n') + `\n\nSummary: ${totalFails === 0 ? 'PASS' : 'FAIL ('+totalFails+' errors)'}`;
  pre.className = totalFails === 0 ? 'pass' : 'fail';
  resultsEl.appendChild(pre);
})();
