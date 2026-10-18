<?php
/**
 * Endpoint de produtos.
 * GET    /produtos.php              -> lista produtos (público)
 * GET    /produtos.php?id=5         -> detalhe de um produto (público)
 * POST   /produtos.php              -> criar produto (admin)
 * POST   /produtos.php?id=5&_method=PUT    -> editar produto (admin)
 * POST   /produtos.php?id=5&_method=DELETE -> apagar produto (admin)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$db = getDB();
$metodo = $_SERVER['REQUEST_METHOD'];
$metodoReal = $_GET['_method'] ?? $metodo;
$id = $_GET['id'] ?? null;

switch ($metodoReal) {

    case 'GET':
        if ($id) {
            $stmt = $db->prepare('SELECT p.*, c.nome AS categoria_nome FROM produtos p
                                   LEFT JOIN categorias c ON c.id = p.categoria_id
                                   WHERE p.id = ?');
            $stmt->execute([$id]);
            $produto = $stmt->fetch();
            if (!$produto) jsonError('Produto não encontrado.', 404);

            $imgs = $db->prepare('SELECT * FROM produto_imagens WHERE produto_id = ? ORDER BY principal DESC, ordem ASC');
            $imgs->execute([$id]);
            $produto['imagens'] = $imgs->fetchAll();

            $vars = $db->prepare('SELECT * FROM produto_variantes WHERE produto_id = ?');
            $vars->execute([$id]);
            $produto['variantes'] = $vars->fetchAll();

            jsonResponse($produto);
        } else {
            $categoriaSlug = $_GET['categoria'] ?? null;
            $busca = $_GET['busca'] ?? null;
            $apenasAtivos = !isset($_GET['todos']); // admin pode pedir ?todos=1

            $sql = 'SELECT p.*, c.nome AS categoria_nome,
                           (SELECT caminho FROM produto_imagens WHERE produto_id = p.id ORDER BY principal DESC, ordem ASC LIMIT 1) AS imagem
                    FROM produtos p
                    LEFT JOIN categorias c ON c.id = p.categoria_id
                    WHERE 1=1';
            $params = [];

            if ($apenasAtivos) {
                $sql .= ' AND p.activo = 1';
            }
            if ($categoriaSlug) {
                $sql .= ' AND c.slug = ?';
                $params[] = $categoriaSlug;
            }
            if ($busca) {
                $sql .= ' AND (p.nome LIKE ? OR p.descricao_curta LIKE ?)';
                $params[] = "%$busca%";
                $params[] = "%$busca%";
            }
            $sql .= ' ORDER BY p.criado_em DESC';

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            jsonResponse($stmt->fetchAll());
        }
        break;

    case 'POST':
        exigirAdmin();
        $d = json_decode(file_get_contents('php://input'), true);

        $obrigatorios = ['nome', 'preco'];
        foreach ($obrigatorios as $campo) {
            if (!isset($d[$campo]) || $d[$campo] === '') jsonError("Campo obrigatório: $campo");
        }

        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $d['nome']), '-'));
        $sku = $d['sku'] ?? ('SKU-' . strtoupper(substr(uniqid(), -8)));

        $stmt = $db->prepare('INSERT INTO produtos
            (categoria_id, nome, slug, descricao_curta, descricao_longa, preco, preco_promocional, stock, stock_minimo, sku, peso_g, activo, destaque)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $d['categoria_id'] ?? null,
            $d['nome'],
            $slug . '-' . substr(uniqid(), -5),
            $d['descricao_curta'] ?? null,
            $d['descricao_longa'] ?? null,
            $d['preco'],
            $d['preco_promocional'] ?? null,
            $d['stock'] ?? 0,
            $d['stock_minimo'] ?? 3,
            $sku,
            $d['peso_g'] ?? null,
            $d['activo'] ?? 1,
            $d['destaque'] ?? 0,
        ]);

        jsonResponse(['id' => $db->lastInsertId(), 'mensagem' => 'Produto criado com sucesso.'], 201);
        break;

    case 'PUT':
        exigirAdmin();
        if (!$id) jsonError('ID do produto em falta.');
        $d = json_decode(file_get_contents('php://input'), true);

        $campos = ['categoria_id','nome','descricao_curta','descricao_longa','preco','preco_promocional',
                   'stock','stock_minimo','peso_g','activo','destaque'];
        $sets = [];
        $valores = [];
        foreach ($campos as $c) {
            if (array_key_exists($c, $d)) {
                $sets[] = "$c = ?";
                $valores[] = $d[$c];
            }
        }
        if (!$sets) jsonError('Nada para actualizar.');
        $valores[] = $id;

        $stmt = $db->prepare('UPDATE produtos SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->execute($valores);

        jsonResponse(['mensagem' => 'Produto actualizado com sucesso.']);
        break;

    case 'DELETE':
        exigirAdmin();
        if (!$id) jsonError('ID do produto em falta.');
        $stmt = $db->prepare('DELETE FROM produtos WHERE id = ?');
        $stmt->execute([$id]);
        jsonResponse(['mensagem' => 'Produto removido.']);
        break;

    default:
        jsonError('Método não suportado.', 405);
}
