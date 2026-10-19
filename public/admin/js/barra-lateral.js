function montarBarraLateral(paginaActiva) {
const nome = localStorage.getItem('admin_nome') || 'Admin';
const papel = localStorage.getItem('admin_papel') || 'admin';
const itensCompletos = [
{ href: 'index.html', label: 'Dashboard', id: 'dashboard' },
{ href: 'produtos.html', label: 'Produtos', id: 'produtos' },
{ href: 'encomendas.html', label: 'Encomendas', id: 'encomendas' },
{ href: 'categorias.html', label: 'Categorias', id: 'categorias' },
{ href: 'relatorios.html', label: 'Relatórios', id: 'relatorios' },
{ href: 'utilizadores.html', label: 'Utilizadores', id: 'utilizadores' },
];

// Operador só trata de atendimento: só vê "Encomendas".
const itens = papel === 'operador'
? itensCompletos.filter(i => i.id === 'encomendas')
: itensCompletos;

const nav = itens.map(i =>
`<a href="${i.href}" class="${i.id === paginaActiva ? 'activo' : ''}">${i.label}</a>`
).join('');

const rotuloPapel = papel === 'operador' ? ' · Operador' : '';

return `
<aside class="barra-lateral">
<div class="logo"><img src="assets/icone.png" alt="Al Muzawad" style="height:34px; width:34px; object-fit:contain; vertical-align:middle; margin-right:8px;">Al Muzawad</div>
<nav>${nav}</nav>
<div style="font-size:.8rem; color:#B9B09A; margin-bottom:6px;">Sessão: ${nome}${rotuloPapel}</div>
<button class="sair" onclick="sessaoSair()">Sair</button>
</aside>
`;
}

function sessaoSair() {
limparSessao();
window.location.href = 'login.html';
}
