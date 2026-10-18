// Inicializa o Firebase usando a configuração de firebase-config.js.
// Precisa de ser carregado DEPOIS dos scripts firebase-*-compat.js e firebase-config.js.
firebase.initializeApp(FIREBASE_CONFIG);

const db = firebase.firestore();
const auth = firebase.auth();
const storage = firebase.storage();
