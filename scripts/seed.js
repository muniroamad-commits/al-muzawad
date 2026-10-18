/**
 * Script de arranque do projecto Firebase "Al Muzawad".
 *
 * Cria a primeira conta de administrador (Auth + Firestore) e algumas
 * categorias de exemplo, para teres logo algo para mostrar.
 *
 * Como usar:
 *   1. npm install firebase-admin
 *   2. Na consola Firebase: Definições do projecto → Contas de serviço →
 *      "Gerar nova chave privada" → guarda o ficheiro como
 *      scripts/service-account.json (NUNCA subir isto ao GitHub — já está no .gitignore)
 *   3. node scripts/seed.js admin@teuemail.com "umaSenhaForte123" "Nome do Admin"
 */

const admin = require('firebase-admin');
const path = require('path');

const serviceAccountPath = path.join(__dirname, 'service-account.json');
const serviceAccount = require(serviceAccountPath);

admin.initializeApp({
  credential: admin.credential.cert(serviceAccount),
});

const db = admin.firestore();
const auth = admin.auth();

async function main() {
  const [, , email, senha, nome] = process.argv;
  if (!email || !senha) {
    console.error('Uso: node scripts/seed.js <email> <senha> ["Nome do Admin"]');
    process.exit(1);
  }

  // 1. Criar (ou reutilizar) o utilizador de autenticação
  let user;
  try {
    user = await auth.createUser({ email, password: senha, displayName: nome || 'Admin' });
    console.log('✓ Utilizador criado:', user.uid);
  } catch (err) {
    if (err.code === 'auth/email-already-exists') {
      user = await auth.getUserByEmail(email);
      console.log('→ Utilizador já existia, a reutilizar:', user.uid);
    } else {
      throw err;
    }
  }

  // 2. Marcar como admin no Firestore (isto é o que dá acesso ao painel)
  await db.collection('admins').doc(user.uid).set({
    nome: nome || 'Admin',
    email,
  });
  console.log('✓ Documento admins/' + user.uid + ' criado.');

  // 3. Categorias de exemplo (só cria se a colecção estiver vazia)
  const existentes = await db.collection('categorias').limit(1).get();
  if (existentes.empty) {
    const categorias = [
      { nome: 'Sabonetes', slug: 'sabonetes' },
      { nome: 'Velas', slug: 'velas' },
      { nome: 'Cosmética Natural', slug: 'cosmetica-natural' },
    ];
    for (const c of categorias) {
      await db.collection('categorias').add(c);
    }
    console.log('✓ Categorias de exemplo criadas:', categorias.map(c => c.nome).join(', '));
  } else {
    console.log('→ Já existem categorias, não foram criadas novas.');
  }

  console.log('\nTudo pronto. Entra no painel admin com:');
  console.log('  Email:', email);
  console.log('  Senha: (a que escolheste)');
  process.exit(0);
}

main().catch(err => { console.error(err); process.exit(1); });
