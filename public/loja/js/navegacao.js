/* ---------------- Actualização automática da app (GitHub Releases) ---------------- */

// Actualizado automaticamente pelo workflow de build a cada publicação (não editar à mão).
const VERSAO_APP = '0';
const REPO_GITHUB = 'muniroamad-commits/al-muzawad';

async function verificarActualizacaoApp() {
  const nativo = window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform();
  if (!nativo) return;

  try {
    const resposta = await fetch(`https://api.github.com/repos/${REPO_GITHUB}/releases/latest`);
    if (!resposta.ok) return;
    const dados = await resposta.json();

    const versaoRemota = parseInt(String(dados.tag_name || '').replace(/^v/i, ''), 10);
    const versaoLocal = parseInt(VERSAO_APP, 10);
    if (isNaN(versaoRemota) || isNaN(versaoLocal) || versaoRemota <= versaoLocal) return;

    const apk = (dados.assets || []).find(a => (a.name || '').toLowerCase().endsWith('.apk'));
    const urlDescarregar = apk ? apk.browser_download_url : dados.html_url;
    mostrarAvisoActualizacao(dados.tag_name, urlDescarregar);
  } catch (err) {
    // Sem rede ou API indisponível — falha em silêncio, não incomoda o utilizador.
  }
}

function mostrarAvisoActualizacao(versao, urlDescarregar) {
  if (document.getElementById('aviso-actualizacao-app')) return;

  const aviso = document.createElement('div');
  aviso.id = 'aviso-actualizacao-app';
  aviso.style.cssText = `
    position: fixed; left: 12px; right: 12px; bottom: 76px; z-index: 9999;
    background: #233A5E; color: #EDE4CF; border-radius: 12px; padding: 12px 14px;
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    font-family: 'Archivo', sans-serif; box-shadow: 0 6px 18px rgba(0,0,0,.35);
  `;
  aviso.innerHTML = `
    <span style="font-size:.82rem; line-height:1.3;">Nova versão da app disponível (${versao.replace(/^v/i, '')})</span>
    <div style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
      <a href="${urlDescarregar}" target="_blank" rel="noopener"
         style="background:#B35C38; color:#fff; padding:7px 14px; border-radius:8px; text-decoration:none; font-size:.78rem; font-weight:600; white-space:nowrap;">
        Actualizar
      </a>
      <button onclick="document.getElementById('aviso-actualizacao-app').remove()"
              style="background:transparent; border:none; color:#EDE4CF; font-size:1.2rem; line-height:1; cursor:pointer; padding:0 4px;">
        &times;
      </button>
    </div>
  `;
  document.body.appendChild(aviso);
}

document.addEventListener('DOMContentLoaded', verificarActualizacaoApp);

function montarBarraInferior(paginaActiva) {
  const itens = [
    { href: 'index.html', icone: '🏠', label: 'Loja', id: 'loja' },
    { href: 'carrinho.html', icone: '🧺', label: 'Cesto', id: 'carrinho' },
    { href: 'pedidos.html', icone: '📦', label: 'Pedidos', id: 'pedidos' },
    { href: 'conta.html', icone: '👤', label: 'Conta', id: 'conta' },
  ];
  return `
    <nav class="barra-inferior">
      ${itens.map(i => `
        <a href="${i.href}" class="${i.id === paginaActiva ? 'activo' : ''}">
          <span class="icone">${i.icone}</span>${i.label}
        </a>`).join('')}
    </nav>
  `;
}

function montarTopo(titulo) {
  return `
    <header class="topo-app">
      <span class="marca">
        <img src="assets/icone.png" alt="" style="height:28px; width:28px; object-fit:contain; vertical-align:middle; margin-right:8px;">${titulo}
      </span>
      <div class="acoes-topo">
        <a href="carrinho.html" style="position:relative;">
          🧺 <span id="contagem-cesto" class="contagem-cesto" style="display:none;">0</span>
        </a>
      </div>
    </header>
  `;
}
