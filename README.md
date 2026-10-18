# Al Muzawad — Plataforma de E-commerce

Loja online de cosméticos artesanais (sabonetes, velas, cosmética natural),
com app para clientes, carrinho, checkout, login social e painel de administrador.

**Arquitectura actual: Firebase** (Firestore + Authentication + Hosting + Storage).
A versão anterior em PHP/MySQL fica guardada em `legacy-php/` só como referência —
não é usada nem precisa de ser instalada.

## Estrutura do projecto

```
loja-artesanato/
├── public/
│   ├── firebase-config.js   # Config do projecto Firebase (preencher)
│   ├── firebase-init.js     # Inicializa o Firebase (não precisas de tocar)
│   ├── admin/                # Painel do administrador
│   └── loja/                 # Loja do cliente
├── scripts/
│   └── seed.js               # Cria o 1º admin + categorias de exemplo
├── firebase.json             # Configuração do Hosting/Firestore/Storage
├── firestore.rules           # Regras de segurança da base de dados
├── storage.rules             # Regras de segurança das fotos
└── legacy-php/               # Versão antiga em PHP/MySQL (não usada, só referência)
```

---

## Passo a passo — do zero até à demonstração

### 1. Criar o projecto Firebase (5 minutos, feito por ti na consola)

1. Vai a **https://console.firebase.google.com** e clica em "Adicionar projecto".
2. Nome do projecto: `Al Muzawad` (o Firebase gera um ID único, ex: `al-muzawad-xxxxx`).
3. Podes desactivar o Google Analytics (não é preciso para isto).
4. Depois do projecto criado, no menu lateral:
   - **Authentication** → separador "Sign-in method" → activa **Email/Password**, **Google** e **Facebook**.
     - Para o Facebook vais precisar do App ID/Secret (ver secção de pagamentos/login social abaixo).
   - **Firestore Database** → "Criar base de dados" → modo **produção** → escolhe a região mais próxima (ex: `europe-west1`).
   - **Storage** → "Começar" → modo produção.
   - **Definições do projecto** (ícone de engrenagem) → aba "Geral" → em "As tuas apps" clica no ícone **Web `</>`** → regista a app como "Al Muzawad Web" → copia o objecto `firebaseConfig` que aparece.

5. Cola esse objecto em `public/firebase-config.js` (substitui os valores "COLOCAR_AQUI").

6. Edita `.firebaserc` e substitui `COLOCAR_AQUI_O_ID_DO_PROJECTO_FIREBASE` pelo ID real do teu projecto (aparece nas Definições do projecto).

### 2. Criar a tua conta de administrador

Isto cria o teu login do painel admin directamente no Firebase.

```bash
cd scripts
npm install
```

Depois, na consola Firebase: **Definições do projecto → Contas de serviço → Gerar nova chave privada**.
Guarda o ficheiro descarregado como `scripts/service-account.json` (este ficheiro é secreto — já está no `.gitignore`, nunca vai para o GitHub).

```bash
node seed.js o-teu-email@gmail.com "umaSenhaForte123" "O Teu Nome"
```

Isto cria a tua conta de admin e 3 categorias de exemplo (Sabonetes, Velas, Cosmética Natural).

### 3. Publicar (Firebase Hosting)

```bash
npm install -g firebase-tools   # só da primeira vez
firebase login                  # abre o browser, entra com a tua conta Google
cd /caminho/para/loja-artesanato
firebase deploy
```

No final, o terminal mostra 2 URLs — algo como:
- `https://al-muzawad-xxxxx.web.app/loja/` → a loja para os clientes
- `https://al-muzawad-xxxxx.web.app/admin/` → o painel do administrador

**É essa a ligação que mostras ao cliente.** Fica sempre online, não precisas de ter o
computador aberto nem nada a correr localmente.

### 4. Antes da apresentação

- Entra no painel admin com o email/senha que usaste no `seed.js`.
- Cria pelo menos 3–4 produtos com fotos, para o catálogo não aparecer vazio.
- Testa o fluxo completo tu mesmo primeiro: ver produto → adicionar ao cesto →
  criar conta → checkout → ver em "Meus pedidos" → confirmar no admin que a
  encomenda aparece lá.

---

## Login social (Google/Facebook)

Com Firebase Authentication isto é mais simples do que com a versão PHP antiga:
- **Google**: já funciona assim que activares o provider em Authentication → Sign-in method. Não precisas de nenhum Client ID no código.
- **Facebook**: precisas de criar uma app em developers.facebook.com, e colar o App ID + App Secret no ecrã de configuração do provider Facebook dentro do Firebase Console (Authentication → Sign-in method → Facebook).

Nota importante para a versão Android (abaixo): o login Google deixou de funcionar
dentro de WebViews simples desde 2021. A boa notícia é que, por estarmos a usar
Firebase Authentication, a correcção para a app Android é instalar o plugin
`@capacitor-firebase/authentication`, que faz o login nativo — não precisas de
reescrever a lógica de novo.

## Pagamentos (M-Pesa, e-Mola, mKesh, cartão)

A função `processarPagamento()` em `public/loja/js/api.js` simula o pagamento
(tal como a versão PHP fazia), para já poderes demonstrar o fluxo completo.
Quando tiveres os acordos comerciais com as operadoras/gateway, troca o miolo
dessa função pela chamada real à API escolhida — a estrutura (criar encomenda →
processar pagamento → actualizar estado) já está pronta para isso.

- **M-Pesa**: contacto directo com a Vodacom M-Pesa Business (API OpenAPI).
- **e-Mola / mKesh**: contacto directo com a Movitel / Tmcel.
- **Cartão**: considera um agregador como PaySuite ou DPO Group, que normalmente já
  cobre M-Pesa/e-Mola também — evita negociar com as 3 operadoras em separado.

## Empacotar como app Android (Capacitor)

```bash
npm install @capacitor/core @capacitor/cli @capacitor/android
npx cap init "Al Muzawad" "mz.almuzawad.app"
# webDir no capacitor.config.json deve apontar para "public/loja" (app do cliente)
npx cap add android
npm install @capacitor-firebase/authentication   # login nativo (resolve o bloqueio do Google)
npx cap sync
npx cap open android
```

Precisas de ter o Android Studio instalado. O painel admin (`public/admin`) normalmente
fica só como site web (não precisa de ser empacotado em app).

## Repositório no GitHub

O código está versionado em git. Para ligar a um repositório remoto:

```bash
git remote add origin <URL-do-teu-repositório-no-GitHub>
git push -u origin main
```

(Se já tiveres criado o repositório vazio no GitHub, troca `<URL-do-teu-repositório-no-GitHub>`
pelo link que o GitHub te dá, ex: `git@github.com:o-teu-utilizador/al-muzawad-app.git`.)
