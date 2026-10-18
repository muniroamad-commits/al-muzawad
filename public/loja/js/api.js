/**
 * Camada de dados da loja (lado do cliente/comprador), usando Firebase
 * (Auth + Firestore). Os nomes das funções mantêm-se iguais aos da versão
 * PHP para que nenhuma página precise de ser reescrita.
 */

/* ---------------- Sessão do cliente (Firebase Auth) ---------------- */

function estaAutenticado() {
  return !!auth.currentUser;
}

/** O Firebase Auth restaura a sessão de forma assíncrona. Páginas que
 * precisam de saber "já sei se há sessão ou não" antes de decidir o que
 * mostrar devem `await aguardarAuth()` em vez de confiar logo em
 * estaAutenticado() no carregamento da página. */
let _authProntoPromise = null;
function aguardarAuth() {
  if (!_authProntoPromise) {
    _authProntoPromise = new Promise((resolve) => {
      const cancelar = auth.onAuthStateChanged((user) => { cancelar(); resolve(user); });
    });
  }
  return _authProntoPromise;
}

function getNomeCliente() {
  return auth.currentUser?.displayName || localStorage.getItem('cliente_nome') || 'Cliente';
}

function limparSessaoCliente() {
  auth.signOut();
}

/** Garante que existe um documento clientes/{uid} com os dados básicos do perfil. */
async function sincronizarPerfilCliente(user, extra = {}) {
  const ref = db.collection('clientes').doc(user.uid);
  const doc = await ref.get();
  const dados = {
    nome: user.displayName || extra.nome || 'Cliente',
    email: user.email || '',
    telefone: extra.telefone || (doc.exists ? doc.data().telefone : '') || '',
    avatar_url: user.photoURL || '',
    provider: extra.provider || (doc.exists ? doc.data().provider : 'email'),
    actualizado_em: firebase.firestore.FieldValue.serverTimestamp(),
  };
  if (!doc.exists) dados.criado_em = firebase.firestore.FieldValue.serverTimestamp();
  await ref.set(dados, { merge: true });
  localStorage.setItem('cliente_nome', dados.nome);
}

/** Mantido por compatibilidade — já não é preciso chamar manualmente
 * (o listener onAuthStateChanged trata disto), mas pode ser chamado
 * logo após um login para garantir que o perfil fica gravado de imediato. */
async function guardarSessaoCliente() {
  if (auth.currentUser) await sincronizarPerfilCliente(auth.currentUser);
}

firebase.auth().onAuthStateChanged((user) => {
  if (user) sincronizarPerfilCliente(user).catch(() => {});
  actualizarContagemCesto();
});

/* ---------------- Autenticação por email/senha ---------------- */

async function entrarComEmail(email, senha) {
  try {
    const cred = await auth.signInWithEmailAndPassword(email, senha);
    await sincronizarPerfilCliente(cred.user, { provider: 'email' });
    return cred.user;
  } catch (err) {
    throw new Error(traduzirErroAuth(err));
  }
}

async function registarComEmail(nome, email, senha, telefone = '') {
  try {
    const cred = await auth.createUserWithEmailAndPassword(email, senha);
    await cred.user.updateProfile({ displayName: nome });
    await sincronizarPerfilCliente(cred.user, { nome, telefone, provider: 'email' });
    return cred.user;
  } catch (err) {
    throw new Error(traduzirErroAuth(err));
  }
}

async function entrarComGoogle() {
  try {
    const provider = new firebase.auth.GoogleAuthProvider();
    const cred = await auth.signInWithPopup(provider);
    await sincronizarPerfilCliente(cred.user, { provider: 'google' });
    return cred.user;
  } catch (err) {
    throw new Error(traduzirErroAuth(err));
  }
}

async function entrarComFacebook() {
  try {
    const provider = new firebase.auth.FacebookAuthProvider();
    const cred = await auth.signInWithPopup(provider);
    await sincronizarPerfilCliente(cred.user, { provider: 'facebook' });
    return cred.user;
  } catch (err) {
    throw new Error(traduzirErroAuth(err));
  }
}

function traduzirErroAuth(err) {
  const mapa = {
    'auth/invalid-email': 'Email inválido.',
    'auth/user-not-found': 'Não existe nenhuma conta com este email.',
    'auth/wrong-password': 'Senha incorrecta.',
    'auth/invalid-credential': 'Email ou senha incorrectos.',
    'auth/email-already-in-use': 'Já existe uma conta com este email.',
    'auth/weak-password': 'A senha deve ter pelo menos 6 caracteres.',
    'auth/popup-closed-by-user': 'Janela de login fechada antes de terminar.',
  };
  return mapa[err.code] || err.message || 'Ocorreu um erro inesperado.';
}

/* ---------------- Catálogo (Firestore) ---------------- */

async function listarCategorias() {
  const snap = await db.collection('categorias').orderBy('nome').get();
  return snap.docs.map(d => ({ id: d.id, ...d.data() }));
}

async function listarProdutos({ categoriaSlug = '', busca = '' } = {}) {
  let query = db.collection('produtos').where('activo', '==', true);
  const snap = await query.get();
  let produtos = snap.docs.map(d => ({ id: d.id, ...d.data() }));

  if (categoriaSlug) produtos = produtos.filter(p => p.categoria_slug === categoriaSlug);
  if (busca) {
    const termo = busca.toLowerCase();
    produtos = produtos.filter(p => (p.nome || '').toLowerCase().includes(termo));
  }
  return produtos.map(normalizarProduto);
}

async function obterProduto(id) {
  const doc = await db.collection('produtos').doc(id).get();
  if (!doc.exists) throw new Error('Produto não encontrado.');
  return normalizarProduto({ id: doc.id, ...doc.data() });
}

function normalizarProduto(p) {
  p.imagem = (p.imagens && p.imagens[0]) || null;
  return p;
}

/* ---------------- Encomendas e pagamento ---------------- */

function gerarCodigoEncomenda() {
  return 'AM-' + Date.now().toString(36).toUpperCase();
}

/** Cria a encomenda no Firestore. Calcula subtotal a partir do cesto local. */
async function criarEncomenda(dadosEntrega) {
  const user = auth.currentUser;
  if (!user) throw new Error('Precisas de iniciar sessão.');

  const cesto = getCesto();
  if (!cesto.length) throw new Error('O cesto está vazio.');

  const itens = cesto.map(i => ({
    produto_id: i.produto_id,
    nome_produto: i.nome,
    quantidade: i.quantidade,
    preco_unitario: i.preco,
    subtotal: i.preco * i.quantidade,
  }));
  const subtotal = itens.reduce((s, i) => s + i.subtotal, 0);
  const taxa_entrega = dadosEntrega.taxa_entrega || 0;
  const total = subtotal + taxa_entrega;
  const codigo = gerarCodigoEncomenda();

  const ref = await db.collection('encomendas').add({
    codigo,
    cliente_id: user.uid,
    cliente_nome: getNomeCliente(),
    cliente_telefone: dadosEntrega.telefone_contacto || '',
    endereco_entrega: dadosEntrega.endereco_entrega || '',
    cidade_entrega: dadosEntrega.cidade_entrega || '',
    telefone_contacto: dadosEntrega.telefone_contacto || '',
    notas: dadosEntrega.notas || '',
    itens,
    subtotal,
    taxa_entrega,
    total,
    metodo_pagamento: dadosEntrega.metodo_pagamento,
    estado_pagamento: 'pendente',
    referencia_pagamento: '',
    status: 'pendente',
    criado_em: firebase.firestore.FieldValue.serverTimestamp(),
  });

  return { id: ref.id, codigo, total };
}

/** Simula o pagamento (tal como a versão PHP fazia) e actualiza a encomenda.
 * Quando tiveres a integração real com M-Pesa/e-Mola/mKesh/cartão, troca o
 * miolo desta função pela chamada à API do gateway escolhido. */
async function processarPagamento({ encomenda_id, metodo, numero_telefone }) {
  const referencia = metodo.toUpperCase() + '-' + Math.random().toString(36).slice(2, 10).toUpperCase();
  const novoEstado = metodo === 'entrega' ? 'pendente' : 'pago';

  await db.collection('encomendas').doc(encomenda_id).update({
    estado_pagamento: novoEstado,
    referencia_pagamento: referencia,
    status: novoEstado === 'pago' ? 'pago' : 'pendente',
  });

  return { sucesso: true, referencia };
}

async function listarMinhasEncomendas() {
  const user = auth.currentUser;
  if (!user) throw new Error('Precisas de iniciar sessão.');
  const snap = await db.collection('encomendas')
    .where('cliente_id', '==', user.uid)
    .orderBy('criado_em', 'desc')
    .get();
  return snap.docs.map(d => ({ id: d.id, ...d.data() }));
}

/* ---------------- Utilitários ---------------- */

function formatarMT(valor) {
  return new Intl.NumberFormat('pt-MZ', { minimumFractionDigits: 2 }).format(valor || 0) + ' MT';
}

function formatarData(valor) {
  if (!valor) return '';
  const d = valor.toDate ? valor.toDate() : new Date(valor);
  return d.toLocaleDateString('pt-PT', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

const ROTULOS_STATUS = {
  pendente: 'Pendente', pago: 'Pago', confirmado: 'Confirmado',
  em_preparacao: 'Em preparação', enviado: 'Enviado', entregue: 'Entregue', cancelado: 'Cancelado',
};

/* ---------------- Carrinho (guardado localmente no dispositivo) ---------------- */

function getCesto() {
  return JSON.parse(localStorage.getItem('cesto') || '[]');
}

function guardarCesto(cesto) {
  localStorage.setItem('cesto', JSON.stringify(cesto));
  actualizarContagemCesto();
}

function adicionarAoCesto(produto, quantidade = 1) {
  const cesto = getCesto();
  const existente = cesto.find(i => i.produto_id === produto.id);
  if (existente) {
    existente.quantidade += quantidade;
  } else {
    cesto.push({
      produto_id: produto.id,
      nome: produto.nome,
      preco: produto.preco_promocional || produto.preco,
      imagem: produto.imagem || (produto.imagens && produto.imagens[0]),
      quantidade,
    });
  }
  guardarCesto(cesto);
}

function removerDoCesto(produtoId) {
  guardarCesto(getCesto().filter(i => i.produto_id !== produtoId));
}

function actualizarQuantidadeCesto(produtoId, quantidade) {
  const cesto = getCesto();
  const item = cesto.find(i => i.produto_id === produtoId);
  if (item) {
    item.quantidade = quantidade;
    if (item.quantidade <= 0) return removerDoCesto(produtoId);
    guardarCesto(cesto);
  }
}

function totalCesto() {
  return getCesto().reduce((soma, i) => soma + i.preco * i.quantidade, 0);
}

function contagemCesto() {
  return getCesto().reduce((n, i) => n + i.quantidade, 0);
}

function actualizarContagemCesto() {
  const el = document.getElementById('contagem-cesto');
  if (el) {
    const n = contagemCesto();
    el.textContent = n;
    el.style.display = n > 0 ? 'flex' : 'none';
  }
}

function imgOuPlaceholder(caminho) {
  return caminho || 'https://placehold.co/300x300?text=Sem+Foto';
}

document.addEventListener('DOMContentLoaded', actualizarContagemCesto);
