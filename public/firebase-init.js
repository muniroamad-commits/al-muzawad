// Inicializa o Firebase usando a configuração de firebase-config.js.
// Precisa de ser carregado DEPOIS dos scripts firebase-*-compat.js e firebase-config.js.

let db, auth, storage;

try {
  firebase.initializeApp(FIREBASE_CONFIG);
  db = firebase.firestore();
  auth = firebase.auth();
  storage = firebase.storage();
} catch (erro) {
  console.error('Falha ao iniciar o Firebase:', erro);
  window.ERRO_FIREBASE_INIT = true;
}

// Mostra um aviso visível caso o Firebase não tenha conseguido iniciar
// (por exemplo, por falta de ligação à internet num dispositivo móvel).
document.addEventListener('DOMContentLoaded', () => {
  if (!window.ERRO_FIREBASE_INIT) return;
  const aviso = document.createElement('div');
  aviso.style.cssText = `
    position: fixed; top: 0; left: 0; right: 0; z-index: 99999;
    background: #B35C38; color: #fff; padding: 10px 16px; text-align: center;
    font-family: 'Archivo', sans-serif; font-size: .85rem;
  `;
  aviso.textContent = 'Sem ligação à internet. Verifica a tua rede e tenta novamente.';
  document.body.prepend(aviso);
});
