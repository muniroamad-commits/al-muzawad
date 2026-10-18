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
