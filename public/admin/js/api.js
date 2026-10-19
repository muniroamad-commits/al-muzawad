/**
 * Camada de dados do painel admin, usando Firebase (Auth + Firestore + Storage).
 */

/* ---------------- Sessão do admin ---------------- */

let _authProntoPromiseAdmin = null;
function aguardarAuthAdmin() {
  if (!_authProntoPromiseAdmin) {
    _authProntoPromiseAdmin = new Promise((resolve) => {
      const cancelar = auth.onAuthStateChanged((user) => { cancelar(); resolve(user); });
    });
  }
  return _authProntoPromiseAdmin;
}

function limparSessao() {
  auth.signOut();
}

/** Protege uma página: redirecciona para login se não houver sessão de admin válida. */
async function exigirSessaoAdmin() {
  const user = await aguardarAuthAdmin();
  if (!user) { window.location.href = 'login.html'; return; }

  const doc = await db.collection('admins').doc(user.uid).get();
  if (!doc.exists) {
    await auth.signOut();
    window.location.href = 'login.html';
    return;
  }
  localStorage.setItem('admin_nome', doc.data().nome || user.email || 'Admin');
  localStorage.setItem('admin_papel', doc.data().papel || 'admin');
}

async function entrarComoAdmin(email, senha) {
  try {
    const cred = await auth.signInWithEmailAndPassword(email, senha);
    const doc = await db.collection('admins').doc(cred.user.uid).get();
    if (!doc.exists) {
      await auth.signOut();
      throw new Error('Esta conta não tem acesso ao painel admin.');
    }
    localStorage.setItem('admin_nome', doc.data().nome || cred.user.email);
    localStorage.setItem('admin_papel', doc.data().papel || 'admin');
    return cred.user;
  } catch (err) {
    throw new Error(traduzirErroAuthAdmin(err));
  }
}

/** Devolve o papel da sessão actual: 'admin' (acesso total) ou 'operador' (só atendimento). */
function obterPapelAdmin() {
  return localStorage.getItem('admin_papel') || 'admin';
}

/** Protege uma página de gestão (tudo excepto Encomendas) contra operadores.
 * Chamar logo a seguir a exigirSessaoAdmin(); se devolver false, a página
 * já foi redireccionada e o resto do script não deve continuar a correr. */
function exigirPapelAdmin() {
  if (obterPapelAdmin() !== 'admin') {
    window.location.href = 'encomendas.html';
    return false;
  }
  return true;
}

function traduzirErroAuthAdmin(err) {
  const mapa = {
    'auth/invalid-email': 'Email inválido.',
    'auth/user-not-found': 'Credenciais inválidas.',
    'auth/wrong-password': 'Credenciais inválidas.',
    'auth/invalid-credential': 'Credenciais inválidas.',
    'auth/email-already-in-use': 'Já existe uma conta com este email.',
    'auth/weak-password': 'A senha deve ter pelo menos 6 caracteres.',
  };
  return mapa[err.code] || err.message || 'Ocorreu um erro inesperado.';
}

/* ---------------- Administradores ---------------- */

/** Lista todas as contas com acesso ao painel admin. */
async function listarAdmins() {
  const snap = await db.collection('admins').orderBy('nome').get();
  return snap.docs.map(d => ({ uid: d.id, ...d.data() }));
}

/** Regista um novo administrador/colaborador sem terminar a sessão actual.
 * O SDK do Firebase não tem um "Admin SDK" no browser, por isso criar uma conta
 * com auth.createUserWithEmailAndPassword() na app principal iniciaria sessão
 * automaticamente como essa nova conta. Para evitar isso, usa-se uma app
 * Firebase secundária só para criar a conta Auth; a sessão do admin actual
 * (na app principal) nunca é tocada. */
async function criarAdmin(nome, email, senha, papel = 'admin') {
  const nomeAppSecundaria = 'secundaria_' + Date.now();
  const appSecundaria = firebase.initializeApp(FIREBASE_CONFIG, nomeAppSecundaria);
  try {
    const cred = await appSecundaria.auth().createUserWithEmailAndPassword(email, senha);
    await db.collection('admins').doc(cred.user.uid).set({
      nome: nome,
      email: email,
      papel: papel === 'operador' ? 'operador' : 'admin',
      criado_em: firebase.firestore.FieldValue.serverTimestamp(),
    });
    await appSecundaria.auth().signOut();
  } catch (err) {
    throw new Error(traduzirErroAuthAdmin(err));
  } finally {
    await appSecundaria.delete();
  }
}

/** Remove o acesso de administrador. Só apaga o documento em "admins";
 * a conta de autenticação em si continua a existir (sem backend/Admin SDK
 * não é possível apagar a conta Auth a partir do browser). */
async function apagarAdmin(uid) {
  if (auth.currentUser && auth.currentUser.uid === uid) {
    throw new Error('Não podes remover o teu próprio acesso.');
  }
  await db.collection('admins').doc(uid).delete();
}

/* ---------------- Categorias ---------------- */

function gerarSlug(nome) {
  return nome.toLowerCase()
    .normalize('NFD').replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
}

async function listarCategoriasAdmin() {
  const snap = await db.collection('categorias').orderBy('nome').get();
  return snap.docs.map(d => ({ id: d.id, ...d.data() }));
}

async function criarCategoria(nome) {
  const slug = gerarSlug(nome);
  const ref = await db.collection('categorias').add({ nome, slug });
  return { id: ref.id };
}

/* ---------------- Produtos ---------------- */

async function listarProdutosAdmin() {
  const [produtosSnap, categorias] = await Promise.all([
    db.collection('produtos').orderBy('nome').get(),
    listarCategoriasAdmin(),
  ]);
  const mapaCategorias = {};
  categorias.forEach(c => { mapaCategorias[c.id] = c.nome; });

  return produtosSnap.docs.map(d => {
    const p = { id: d.id, ...d.data() };
    p.categoria_nome = mapaCategorias[p.categoria_id] || null;
    p.imagem = (p.imagens && p.imagens[0]) || null;
    return p;
  });
}

async function obterProdutoAdmin(id) {
  const doc = await db.collection('produtos').doc(id).get();
  if (!doc.exists) throw new Error('Produto não encontrado.');
  return { id: doc.id, ...doc.data() };
}

async function criarProduto(dados) {
  dados.imagens = [];
  dados.criado_em = firebase.firestore.FieldValue.serverTimestamp();
  const ref = await db.collection('produtos').add(dados);
  return { id: ref.id };
}

async function actualizarProduto(id, dados) {
  await db.collection('produtos').doc(id).update(dados);
  return { id };
}

async function apagarProduto(id) {
  await db.collection('produtos').doc(id).delete();
}

/** Faz upload da foto para o Firebase Storage e adiciona o URL ao produto. */
async function enviarImagemProduto(produtoId, ficheiro) {
  const caminho = `produtos/${produtoId}/${Date.now()}_${ficheiro.name}`;
  const ref = storage.ref(caminho);
  await ref.put(ficheiro);
  const url = await ref.getDownloadURL();

  await db.collection('produtos').doc(produtoId).update({
    imagens: firebase.firestore.FieldValue.arrayUnion(url),
  });
  return { url };
}

/* ---------------- Encomendas ---------------- */

async function listarEncomendasAdmin(status = '') {
  let query = db.collection('encomendas').orderBy('criado_em', 'desc');
  if (status) query = db.collection('encomendas').where('status', '==', status).orderBy('criado_em', 'desc');
  const snap = await query.get();
  return snap.docs.map(d => ({ id: d.id, ...d.data() }));
}

async function obterEncomendaAdmin(id) {
  const doc = await db.collection('encomendas').doc(id).get();
  if (!doc.exists) throw new Error('Encomenda não encontrada.');
  return { id: doc.id, ...doc.data() };
}

async function actualizarEstadoEncomenda(id, status) {
  await db.collection('encomendas').doc(id).update({ status });
}

/* ---------------- Dashboard ---------------- */

async function obterDashboard() {
  const [encomendasSnap, clientesSnap] = await Promise.all([
    db.collection('encomendas').orderBy('criado_em', 'desc').get(),
    db.collection('clientes').get(),
  ]);
  const encomendas = encomendasSnap.docs.map(d => ({ id: d.id, ...d.data() }));

  const hoje = new Date(); hoje.setHours(0, 0, 0, 0);
  const inicioMes = new Date(hoje.getFullYear(), hoje.getMonth(), 1);

  let vendas_hoje = 0, vendas_mes = 0, encomendas_pendentes = 0;
  encomendas.forEach(e => {
    const data = e.criado_em?.toDate ? e.criado_em.toDate() : null;
    if (data && e.estado_pagamento === 'pago') {
      if (data >= hoje) vendas_hoje += e.total || 0;
      if (data >= inicioMes) vendas_mes += e.total || 0;
    }
    if (['pendente', 'pago', 'confirmado'].includes(e.status)) encomendas_pendentes++;
  });

  const produtosSnap = await db.collection('produtos').get();
  const produtos_stock_baixo = produtosSnap.docs
    .map(d => ({ id: d.id, ...d.data() }))
    .filter(p => (p.stock ?? 0) <= (p.stock_minimo ?? 3));

  return {
    vendas_hoje, vendas_mes, encomendas_pendentes,
    total_clientes: clientesSnap.size,
    ultimas_encomendas: encomendas.slice(0, 5),
    produtos_stock_baixo,
  };
}

/* ---------------- Relatórios de vendas ---------------- */

/** Recolhe e agrega os dados de vendas para o relatório.
 * Considera-se "venda" qualquer encomenda que não tenha sido cancelada.
 * dataInicioStr / dataFimStr: strings 'YYYY-MM-DD' (de um <input type="date">) ou '' para sem limite. */
async function obterDadosRelatorioVendas(dataInicioStr = '', dataFimStr = '') {
  const snap = await db.collection('encomendas').orderBy('criado_em', 'desc').get();
  let encomendas = snap.docs.map(d => ({ id: d.id, ...d.data() }));

  encomendas = encomendas.filter(e => e.status !== 'cancelado');

  if (dataInicioStr) {
    const inicio = new Date(dataInicioStr + 'T00:00:00');
    encomendas = encomendas.filter(e => {
      const data = e.criado_em?.toDate ? e.criado_em.toDate() : null;
      return data && data >= inicio;
    });
  }
  if (dataFimStr) {
    const fim = new Date(dataFimStr + 'T23:59:59');
    encomendas = encomendas.filter(e => {
      const data = e.criado_em?.toDate ? e.criado_em.toDate() : null;
      return data && data <= fim;
    });
  }

  const totalVendas = encomendas.reduce((s, e) => s + (e.total || 0), 0);
  const totalEntrega = encomendas.reduce((s, e) => s + (e.taxa_entrega || 0), 0);
  const totalProdutos = totalVendas - totalEntrega;
  const numEncomendas = encomendas.length;
  const ticketMedio = numEncomendas ? totalVendas / numEncomendas : 0;

  const mapaCidade = {};
  const mapaProduto = {};
  const mapaPagamento = {};
  const mapaStatus = {};

  encomendas.forEach(e => {
    const cidade = (e.cidade_entrega || '').trim() || 'Não especificada';
    if (!mapaCidade[cidade]) mapaCidade[cidade] = { cidade, encomendas: 0, total: 0 };
    mapaCidade[cidade].encomendas++;
    mapaCidade[cidade].total += e.total || 0;

    const metodo = (e.metodo_pagamento || 'desconhecido').toUpperCase();
    if (!mapaPagamento[metodo]) mapaPagamento[metodo] = { metodo, encomendas: 0, total: 0 };
    mapaPagamento[metodo].encomendas++;
    mapaPagamento[metodo].total += e.total || 0;

    const status = e.status || 'desconhecido';
    mapaStatus[status] = (mapaStatus[status] || 0) + 1;

    (e.itens || []).forEach(it => {
      const chave = it.nome_produto || 'Produto';
      if (!mapaProduto[chave]) mapaProduto[chave] = { nome: chave, quantidade: 0, total: 0 };
      mapaProduto[chave].quantidade += it.quantidade || 0;
      mapaProduto[chave].total += it.subtotal || 0;
    });
  });

  const porCidade = Object.values(mapaCidade).sort((a, b) => b.total - a.total);
  const porProduto = Object.values(mapaProduto).sort((a, b) => b.quantidade - a.quantidade);
  const porPagamento = Object.values(mapaPagamento).sort((a, b) => b.total - a.total);
  const porStatus = Object.keys(mapaStatus).map(s => ({ status: s, encomendas: mapaStatus[s] }));

  // Stock actual: é uma fotografia do momento, não depende do período filtrado.
  const produtosSnap = await db.collection('produtos').orderBy('nome').get();
  const produtosStock = produtosSnap.docs.map(d => {
    const p = d.data();
    const stock = p.stock ?? 0;
    const stock_minimo = p.stock_minimo ?? 3;
    return {
      nome: p.nome || 'Produto',
      stock,
      stock_minimo,
      baixo: stock <= stock_minimo,
    };
  });

  return {
    periodo: { inicio: dataInicioStr || null, fim: dataFimStr || null },
    resumo: { totalVendas, totalEntrega, totalProdutos, numEncomendas, ticketMedio },
    porCidade, porProduto, porPagamento, porStatus,
    encomendas,
    produtosStock,
  };
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
  pendente: 'Pendente',
  pago: 'Pago',
  confirmado: 'Confirmado',
  em_preparacao: 'Em preparação',
  enviado: 'Enviado',
  entregue: 'Entregue',
  cancelado: 'Cancelado',
};
